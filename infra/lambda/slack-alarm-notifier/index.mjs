/**
 * SNS 経由で届いた CloudWatch アラームの状態変化を Slack Incoming Webhook へ転送する。
 *
 * 送信は Node.js 22 のグローバル fetch だけで行う。
 * Webhook URL は環境変数ではなく Secrets Manager から読む（関数の設定画面に URL を出さないため）。
 * AWS SDK v3 は Lambda の Node.js 22 ランタイムに同梱されているので、パッケージには含めない。
 * 送信に失敗しても例外は投げない。Lambda を再試行させても Slack 側は復旧しないため、
 * 失敗は CloudWatch Logs へ記録して握りつぶす。
 */

// 送信のタイムアウト。AbortSignal としてリクエストに渡す（テストは init.signal を見る）。
const SEND_TIMEOUT_MS = 5000;

// ALARM は赤、OK は緑、INSUFFICIENT_DATA などは灰色で表示する。
const STATE_COLORS = {
  ALARM: '#d13212',
  OK: '#2d7d46',
};

function buildAttachment(rawMessage) {
  let alarm;
  try {
    alarm = JSON.parse(rawMessage);
  } catch {
    alarm = undefined;
  }

  // CloudWatch アラーム以外の通知も同じ Topic に流れてくる可能性があるため、
  // JSON として解釈できない本文はそのまま転送する。
  if (alarm === null || typeof alarm !== 'object' || !alarm.AlarmName) {
    return {
      color: '#8c8c8c',
      fallback: String(rawMessage),
      text: String(rawMessage),
    };
  }

  const state = String(alarm.NewStateValue ?? 'UNKNOWN');
  const title = `[${state}] ${alarm.AlarmName}`;
  const fields = [
    { title: '状態', value: state, short: true },
    { title: 'リージョン', value: String(alarm.Region ?? '-'), short: true },
    { title: '理由', value: String(alarm.NewStateReason ?? '-'), short: false },
  ];
  if (alarm.AlarmDescription) {
    fields.push({ title: '説明', value: String(alarm.AlarmDescription), short: false });
  }
  if (alarm.StateChangeTime) {
    fields.push({ title: '発生時刻', value: String(alarm.StateChangeTime), short: false });
  }

  return {
    color: STATE_COLORS[state] ?? '#8c8c8c',
    fallback: title,
    title,
    // OK は障害ではなく復旧なので、文面でも区別できるようにする。
    text: state === 'OK' ? 'アラームが復旧しました。' : 'アラームが発生しました。',
    fields,
  };
}

async function postToSlack(webhookUrl, attachment) {
  try {
    const response = await fetch(webhookUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ attachments: [attachment] }),
      signal: AbortSignal.timeout(SEND_TIMEOUT_MS),
    });
    if (!response.ok) {
      console.error(
        `Slack webhook returned ${response.status}: ${await response.text()}`,
      );
    }
  } catch (error) {
    console.error('Slack webhook request failed', error);
  }
}

// Secret を読み直すまでの時間。URL を入れ替えたあと、この時間内に新しい値へ切り替わる。
const SECRET_CACHE_MS = 5 * 60 * 1000;

/**
 * Secrets Manager から Secret の文字列を読む。
 * SDK は実行時にだけ読み込む。指定子を変数にしてあるのは、SDK を入れていない
 * テスト環境でバンドラがこの import を解決しようとして失敗するのを避けるため。
 */
async function loadSecretFromSecretsManager(secretArn) {
  const sdkModule = '@aws-sdk/client-secrets-manager';
  const { SecretsManagerClient, GetSecretValueCommand } = await import(sdkModule);
  const response = await new SecretsManagerClient({}).send(
    new GetSecretValueCommand({ SecretId: secretArn }),
  );
  return response.SecretString;
}

/**
 * Secret（{"url": "https://hooks.slack.com/services/..."}）から Webhook URL を取り出す。
 * デプロイ直後の仮の値（"unset"）や壊れた値は未設定として扱う。
 */
function parseWebhookUrl(secretString) {
  let url;
  try {
    url = JSON.parse(secretString ?? '')?.url;
  } catch {
    url = undefined;
  }
  return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : undefined;
}

/**
 * ハンドラを作る。Secret の読み込み方を差し替えられるようにしてあるのはテストのため。
 */
export function createHandler(loadSecret = loadSecretFromSecretsManager) {
  let cached;

  async function resolveWebhookUrl() {
    const secretArn = process.env.SLACK_WEBHOOK_SECRET_ARN;
    if (!secretArn) {
      console.error('SLACK_WEBHOOK_SECRET_ARN is not set; skipping Slack notification');
      return undefined;
    }
    if (cached && cached.secretArn === secretArn && Date.now() - cached.loadedAt < SECRET_CACHE_MS) {
      return cached.url;
    }
    let url;
    try {
      url = parseWebhookUrl(await loadSecret(secretArn));
    } catch (error) {
      // 読めなかったことは覚えない。次の通知でもう一度読みに行く。
      console.error('Failed to read the Slack webhook secret', error);
      return undefined;
    }
    if (!url) {
      console.error('Slack webhook secret has no URL yet; skipping Slack notification');
      return undefined;
    }
    cached = { secretArn, url, loadedAt: Date.now() };
    return url;
  }

  return async function handler(event) {
    const webhookUrl = await resolveWebhookUrl();
    if (!webhookUrl) {
      return;
    }

    // Records が配列でない（= 想定外の）イベントでも TypeError を投げないようにする。
    // ここで投げると Lambda の非同期呼び出しが再試行され、Slack へ同じ通知が重複する。
    const records = Array.isArray(event?.Records) ? event.Records : [];
    if (records.length === 0) {
      console.error('No SNS records in event; skipping Slack notification');
      return;
    }

    for (const record of records) {
      // 1 件の異常で残りの通知を落とさない。
      try {
        await postToSlack(webhookUrl, buildAttachment(record?.Sns?.Message ?? ''));
      } catch (error) {
        console.error('Failed to handle SNS record', error);
      }
    }
  };
}

export const handler = createHandler();
