import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { notifySlack } from "./slack";

describe("notifySlack", () => {
    beforeEach(() => {
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
