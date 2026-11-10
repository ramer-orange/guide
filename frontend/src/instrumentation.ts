import type { Instrumentation } from "next";
import { notifySlack } from "@/lib/alerts/slack";

/**
 * 投げられた値を 1 行で説明する。
 * Error なら名前を付ける（message が空でも何が壊れたか分かるように）。
 * String() は toString が無い／例外を投げる値で失敗しうるので、
 * 呼び出し側の try/catch 内から使う前提。
 */
function describeError(error: unknown): string {
    if (error instanceof Error) {
        return `${error.name}: ${error.message}`;
    }
    return String(error);
}

/**
 * Next.js のサーバー側で処理されなかったエラーを Slack へ通知する。
 * レスポンスを遅らせないため notifySlack は await しない。
 * この関数は何があっても Next.js のランタイムへ例外を伝えてはいけないので、
 * 本体を try/catch で囲み、await しない Promise も .catch で閉じる。
 */
export const onRequestError: Instrumentation.onRequestError = (
    error,
    request,
    context,
) => {
    try {
        void notifySlack({
            title: "Next.js サーバーエラー",
            detail: describeError(error),
            context: {
                path: request.path,
                method: request.method,
                routeType: context.routeType,
            },
        }).catch((reason: unknown) => {
            // notifySlack は自身の送信失敗を記録するので、ここへ来るのは
            // その try/catch の外側で起きた想定外の例外だけ。黙って捨てると痕跡が残らない。
            console.error("Slack 通知の送信に失敗しました", reason);
        });
    } catch {
        // 通知の組み立て自体が失敗しても握りつぶす。
        // エラー処理中のランタイムへ二次的な例外を投げないことを優先する。
    }
};
