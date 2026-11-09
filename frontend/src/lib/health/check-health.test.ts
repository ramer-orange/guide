import { describe, expect, it, vi } from "vitest";
import { checkHealth } from "./check-health";

describe("checkHealth", () => {
    it("Laravel の /up が 200 なら正常を返す", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response("", { status: 200 }));
        const result = await checkHealth("http://127.0.0.1:80", fetchMock);
        expect(result).toEqual({ status: 200, body: { status: "ok", backend: 200 } });
        expect(String(fetchMock.mock.calls[0][0])).toBe("http://127.0.0.1/up");
    });

    it("Laravel がエラーを返したら 503 を返す", async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response("", { status: 500 }));
        const result = await checkHealth("http://127.0.0.1:80", fetchMock);
        expect(result).toEqual({ status: 503, body: { status: "unhealthy", backend: 500 } });
    });

    it("Laravel に届かなければ 503 を返す", async () => {
        const fetchMock = vi.fn().mockRejectedValue(new TypeError("fetch failed"));
        const result = await checkHealth("http://127.0.0.1:80", fetchMock);
        expect(result).toEqual({ status: 503, body: { status: "unhealthy", backend: "unreachable" } });
    });
});
