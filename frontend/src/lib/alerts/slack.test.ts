import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { notifySlack, resetSlackAlertSuppression } from "./slack";

describe("notifySlack", () => {
    beforeEach(() => {
        // 抑制はモジュール全体で共有される状態なので、テストごとに捨てる。
        resetSlackAlertSuppression();
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => new Response("ok", { status: 200 })),
        );
        vi.spyOn(console, "error").mockImplementation(() => {});
    });
    afterEach(() => {
        delete process.env.SLACK_WEBHOOK_URL;
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    test("Webhook URL が無いときは送信しない", async () => {
        await notifySlack({ title: "エラー", detail: "boom" });

        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(console.error).not.toHaveBeenCalled();
    });

    // AWS では Secret を仮の値で先に作るため、URL を入れるまでは未設定と同じに扱う。
    test.each(["unset", "hooks.slack.com/services/T/B/x"])(
        "http(s):// で始まらない値（%s）は未設定として扱い、送信しない",
        async (value) => {
            process.env.SLACK_WEBHOOK_URL = value;

            await notifySlack({ title: "エラー", detail: "boom" });

            expect(globalThis.fetch).not.toHaveBeenCalled();
            expect(console.error).not.toHaveBeenCalled();
        },
    );

    test("タイトルと詳細を送る", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";

        await notifySlack({ title: "Next.js サーバーエラー", detail: "boom" });

        const [url, init] = vi.mocked(globalThis.fetch).mock.calls[0];
        expect(url).toBe("https://hooks.slack.com/services/T/B/x");
        expect(init?.method).toBe("POST");
        expect(String(init?.body)).toContain("Next.js サーバーエラー");
        expect(String(init?.body)).toContain("boom");
    });

    test("context のキーと値を本文に並べる", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";

        await notifySlack({
            title: "エラー",
            detail: "boom",
            context: { path: "/itineraries", method: "GET" },
        });

        const [, init] = vi.mocked(globalThis.fetch).mock.calls[0];
        expect(String(init?.body)).toContain("path");
        expect(String(init?.body)).toContain("/itineraries");
        expect(String(init?.body)).toContain("method");
        expect(String(init?.body)).toContain("GET");
    });

    test("context の値は 200 文字で切る", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";

        await notifySlack({
            title: "エラー",
            detail: "boom",
            context: { path: "b".repeat(500) },
        });

        const [, init] = vi.mocked(globalThis.fetch).mock.calls[0];
        const body = String(init?.body);
        expect(body).toContain("b".repeat(200));
        expect(body).not.toContain("b".repeat(201));
    });

    test("タイトルは 200 文字で切る", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";

        await notifySlack({ title: "t".repeat(500), detail: "boom" });

        const [, init] = vi.mocked(globalThis.fetch).mock.calls[0];
        const body = String(init?.body);
        expect(body).toContain("t".repeat(200));
        expect(body).not.toContain("t".repeat(201));
    });

    test("詳細は 2000 文字で切る", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";

        await notifySlack({ title: "エラー", detail: "a".repeat(2500) });

        const [, init] = vi.mocked(globalThis.fetch).mock.calls[0];
        const body = String(init?.body);
        expect(body).toContain("a".repeat(2000));
        expect(body).not.toContain("a".repeat(2001));
    });

    test("送信が失敗しても例外を投げない", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => {
                throw new Error("network down");
            }),
        );

        await expect(
            notifySlack({ title: "エラー", detail: "boom" }),
        ).resolves.toBeUndefined();
        expect(console.error).toHaveBeenCalled();
    });

    test("非 2xx でも例外を投げない", async () => {
        process.env.SLACK_WEBHOOK_URL = "https://hooks.slack.com/services/T/B/x";
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => new Response("err", { status: 429 })),
        );

        await expect(
            notifySlack({ title: "エラー", detail: "boom" }),
        ).resolves.toBeUndefined();
        expect(console.error).toHaveBeenCalled();
    });
});

describe("notifySlack の重複抑制", () => {
    const webhookUrl = "https://hooks.slack.com/services/T/B/x";
    const alert = { title: "Next.js サーバーエラー", detail: "TypeError: boom" };

    beforeEach(() => {
        resetSlackAlertSuppression();
        vi.useFakeTimers();
        vi.setSystemTime(new Date("2026-11-11T00:00:00.000Z"));
        process.env.SLACK_WEBHOOK_URL = webhookUrl;
        vi.stubGlobal(
            "fetch",
            vi.fn(async () => new Response("ok", { status: 200 })),
        );
        vi.spyOn(console, "error").mockImplementation(() => {});
    });

    afterEach(() => {
        vi.useRealTimers();
        delete process.env.SLACK_WEBHOOK_URL;
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    test("同じ通知は1回しか送らない", async () => {
        await notifySlack(alert);
        await notifySlack(alert);
        await notifySlack(alert);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });

    test("別の通知は抑制しない", async () => {
        await notifySlack(alert);
        await notifySlack({ ...alert, detail: "RangeError: other" });
        await notifySlack({ ...alert, title: "別のタイトル" });

        expect(globalThis.fetch).toHaveBeenCalledTimes(3);
    });

    test("300秒を過ぎれば同じ通知でも送る", async () => {
        await notifySlack(alert);

        vi.setSystemTime(new Date("2026-11-11T00:04:59.000Z"));
        await notifySlack(alert);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);

        vi.setSystemTime(new Date("2026-11-11T00:05:00.000Z"));
        await notifySlack(alert);
        expect(globalThis.fetch).toHaveBeenCalledTimes(2);
    });

    test("記憶件数には上限があり、古いものから捨てる", async () => {
        await notifySlack(alert);

        // 上限（200件）を超えるまで別のメッセージを送る。Map が無制限に増えるなら
        // 最初の通知はいつまでも抑制されたままになる。
        for (let i = 0; i < 250; i++) {
            await notifySlack({ ...alert, detail: `別のエラー ${i}` });
        }

        vi.mocked(globalThis.fetch).mockClear();
        await notifySlack(alert);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });

    test("Webhook URL が無いときは抑制の記録もしない", async () => {
        delete process.env.SLACK_WEBHOOK_URL;
        await notifySlack(alert);

        process.env.SLACK_WEBHOOK_URL = webhookUrl;
        await notifySlack(alert);

        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });
});
