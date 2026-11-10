/**
 * SNS 経由で届いた CloudWatch アラームの状態変化を Slack Incoming Webhook へ転送する。
 *
 * 依存パッケージは使わず、Node.js 22 のグローバル fetch だけで送る。
 * 送信に失敗しても例外は投げない。Lambda を再試行させても Slack 側は復旧しないため、
 * 失敗は CloudWatch Logs へ記録して握りつぶす。
 */

// 送信のタイムアウト。テストから値を確認できるように公開する。
export const SEND_TIMEOUT_MS = 5000;

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

export async function handler(event) {
  const webhookUrl = process.env.SLACK_WEBHOOK_URL;
  if (!webhookUrl) {
    console.error('SLACK_WEBHOOK_URL is not set; skipping Slack notification');
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
}
