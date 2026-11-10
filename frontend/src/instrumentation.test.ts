import type { Instrumentation } from "next";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { onRequestError } from "./instrumentation";
import { notifySlack } from "@/lib/alerts/slack";

vi.mock("@/lib/alerts/slack", () => ({
    notifySlack: vi.fn(async () => {}),
}));

type Params = Parameters<Instrumentation.onRequestError>;

const request: Params[1] = {
    path: "/itineraries",
    method: "GET",
    headers: { cookie: "secret" },
};

const context: Params[2] = {
    routerKind: "App Router",
    routePath: "/itineraries",
    routeType: "render",
    revalidateReason: undefined,
};

describe("onRequestError", () => {
    beforeEach(() => {
        vi.mocked(notifySlack).mockImplementation(async () => {});
    });
    afterEach(() => {
        vi.clearAllMocks();
        // console.error のスパイを後続のテストへ持ち越さない。
        vi.restoreAllMocks();
    });

    test("エラー名・メッセージとリクエスト情報を通知する", () => {
        onRequestError(new TypeError("boom"), request, context);

        expect(notifySlack).toHaveBeenCalledWith({
            title: "Next.js サーバーエラー",
            detail: "TypeError: boom",
            context: {
                path: "/itineraries",
                method: "GET",
                routeType: "render",
            },
        });
    });

    test("message が空でもエラー名が残る", () => {
        onRequestError(new Error(""), request, context);

        expect(vi.mocked(notifySlack).mock.calls[0][0].detail).toBe("Error: ");
    });

    test("文字列化できない値でも例外を投げない", () => {
        // プロトタイプを持たないので String() が TypeError を投げる
        expect(() =>
            onRequestError(Object.create(null), request, context),
        ).not.toThrow();

        // toString 自体が投げる値
        expect(() =>
            onRequestError(
                {
                    toString() {
                        throw new Error("toString failed");
                    },
                },
                request,
                context,
            ),
        ).not.toThrow();
    });

    test("await しない Promise を .catch で閉じる", () => {
        const pending = Promise.resolve();
        const catchSpy = vi.spyOn(pending, "catch");
        vi.mocked(notifySlack).mockReturnValue(pending);

        onRequestError(new Error("boom"), request, context);

        // 閉じていなければ将来 notifySlack が reject したとき未処理 rejection になる
        expect(catchSpy).toHaveBeenCalled();
    });

    test("通知が reject したら記録する", async () => {
        const reason = new Error("unexpected");
        vi.mocked(notifySlack).mockRejectedValue(reason);
        const consoleError = vi
            .spyOn(console, "error")
            .mockImplementation(() => {});

        onRequestError(new Error("boom"), request, context);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(consoleError).toHaveBeenCalledWith(
            "Slack 通知の送信に失敗しました",
            reason,
        );
    });

    // 補助的な確認。.catch を外しても（測定したところ）この書き方では
    // 未処理 rejection が観測されずパスしてしまうため、回帰はこのテストでは捕まらない。
    // 上の「通知が reject したら記録する」が .catch の有無を見ている。
    test("通知が reject しても未処理の rejection にしない", async () => {
        vi.mocked(notifySlack).mockRejectedValue(new Error("unexpected"));
        vi.spyOn(console, "error").mockImplementation(() => {});
        const unhandled: unknown[] = [];
        const onUnhandled = (reason: unknown) => unhandled.push(reason);
        process.on("unhandledRejection", onUnhandled);

        try {
            expect(() =>
                onRequestError(new Error("boom"), request, context),
            ).not.toThrow();

            // 未処理 rejection は tick の終わりに通知されるので待つ
            await new Promise((resolve) => setTimeout(resolve, 0));
            expect(unhandled).toEqual([]);
        } finally {
            process.off("unhandledRejection", onUnhandled);
        }
    });
});
