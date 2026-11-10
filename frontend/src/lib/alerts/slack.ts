/**
 * Slack Incoming Webhook への障害通知。
 * 失敗しても呼び出し側に例外を伝えない（通知はアプリの動作を妨げない）。
 */
export type SlackAlert = {
    title: string;
    detail: string;
    context?: Record<string, string>;
};

/** 詳細の最大文字数。スタックトレース全文や巨大な出力を送らないために切る。 */
const DETAIL_MAX_LENGTH = 2000;

/**
 * context の値 1 件あたりの最大文字数。
 * この関数は公開ライブラリなので、呼び出し側が長い値を渡しても
 * 本文が無制限に膨らまないようにここで切る。
 */
const CONTEXT_VALUE_MAX_LENGTH = 200;

/** Webhook への送信を打ち切るまでの時間（ミリ秒）。 */
const TIMEOUT_MS = 5000;

/**
 * 同じ通知を抑制する時間（ミリ秒）。Laravel 経路の重複抑制（300秒）と同じ幅にする。
 * onRequestError はリクエストごとに呼ばれるため、これが無いと壊れたルート1つと
 * クローラーだけでチャンネルが埋まる。
 */
const DUPLICATE_WINDOW_MS = 300_000;

/** 記憶しておく通知の最大件数。長く動くプロセスで Map が無制限に増えないようにする。 */
const RECENT_ALERTS_MAX = 200;

/** 「タイトル + 詳細」ごとの最終送信時刻。プロセスの寿命いっぱい保持する。 */
const recentAlerts = new Map<string, number>();

/**
 * 直近に同じ通知を送っていれば true。送ると決めた場合は時刻を記録する。
 * 送信の成否は見ない（失敗した通知も次の窓までは再送しない）。
 */
function isSuppressed(alert: SlackAlert, now: number): boolean {
    const key = `${alert.title}\n${alert.detail}`;
    const lastSentAt = recentAlerts.get(key);

    if (lastSentAt !== undefined && now - lastSentAt < DUPLICATE_WINDOW_MS) {
        return true;
    }

    // 窓を過ぎたものを落としてから上限を見る。
    for (const [recordedKey, recordedAt] of recentAlerts) {
        if (now - recordedAt >= DUPLICATE_WINDOW_MS) {
            recentAlerts.delete(recordedKey);
        }
    }
    // それでも上限なら、古い順に捨てる（Map は挿入順を保つ）。
    while (recentAlerts.size >= RECENT_ALERTS_MAX) {
        const oldestKey = recentAlerts.keys().next().value;
        if (oldestKey === undefined) {
            break;
        }
        recentAlerts.delete(oldestKey);
    }

    recentAlerts.set(key, now);
    return false;
}

/** 抑制の記憶を捨てる。テストから使う。 */
export function resetSlackAlertSuppression(): void {
    recentAlerts.clear();
}

function buildText(alert: SlackAlert): string {
    const lines = [alert.title, alert.detail.slice(0, DETAIL_MAX_LENGTH)];
    for (const [key, value] of Object.entries(alert.context ?? {})) {
        lines.push(`${key}: ${value.slice(0, CONTEXT_VALUE_MAX_LENGTH)}`);
    }
    return lines.join("\n");
}

export async function notifySlack(alert: SlackAlert): Promise<void> {
    const webhookUrl = process.env.SLACK_WEBHOOK_URL;
    // 未設定なら通知機能は無効。警告も出さない。
    if (!webhookUrl) {
        return;
    }

    // 同じエラーの連打を抑える。URL 未設定のときは記録もしない。
    if (isSuppressed(alert, Date.now())) {
        return;
    }

    try {
        const response = await fetch(webhookUrl, {
            method: "POST",
            headers: { "content-type": "application/json" },
            body: JSON.stringify({ text: buildText(alert) }),
            signal: AbortSignal.timeout(TIMEOUT_MS),
        });
        if (!response.ok) {
            console.error(
                `Slack への通知が失敗しました: status=${response.status}`,
            );
        }
    } catch (error) {
        console.error(
            `Slack への通知が失敗しました: ${error instanceof Error ? error.message : String(error)}`,
        );
    }
}
