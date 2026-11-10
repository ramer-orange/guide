import type { Instrumentation } from "next";
import { notifySlack } from "@/lib/alerts/slack";

/**
 * Next.js のサーバー側で処理されなかったエラーを Slack へ通知する。
 * レスポンスを遅らせないため notifySlack は await しない
 * （notifySlack は例外を投げないので未処理の reject にもならない）。
 */
export const onRequestError: Instrumentation.onRequestError = (
    error,
    request,
    context,
) => {
    void notifySlack({
        title: "Next.js サーバーエラー",
        detail: error instanceof Error ? error.message : String(error),
        context: {
            path: request.path,
            method: request.method,
            routeType: context.routeType,
        },
    });
};
