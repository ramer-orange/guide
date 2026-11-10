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

/** Webhook への送信を打ち切るまでの時間（ミリ秒）。 */
const TIMEOUT_MS = 5000;

function buildText(alert: SlackAlert): string {
    const lines = [alert.title, alert.detail.slice(0, DETAIL_MAX_LENGTH)];
    for (const [key, value] of Object.entries(alert.context ?? {})) {
        lines.push(`${key}: ${value}`);
    }
    return lines.join("\n");
}

export async function notifySlack(alert: SlackAlert): Promise<void> {
    const webhookUrl = process.env.SLACK_WEBHOOK_URL;
    // 未設定なら通知機能は無効。警告も出さない。
    if (!webhookUrl) {
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
