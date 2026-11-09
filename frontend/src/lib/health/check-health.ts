export type HealthResult = {
    status: 200 | 503;
    body: { status: "ok" | "unhealthy"; backend: number | "unreachable" };
};

// Next.js が応答できること（このコードが動くこと）に加え、
// nginx 経由で Laravel の /up まで届くことを確認する。
export async function checkHealth(
    origin: string,
    fetchImpl: typeof fetch = fetch,
    timeoutMs = 3_000,
): Promise<HealthResult> {
    try {
        const response = await fetchImpl(new URL("/up", origin), {
            cache: "no-store",
            redirect: "manual",
            signal: AbortSignal.timeout(timeoutMs),
        });
        if (response.ok) {
            return { status: 200, body: { status: "ok", backend: response.status } };
        }
        return { status: 503, body: { status: "unhealthy", backend: response.status } };
    } catch {
        return { status: 503, body: { status: "unhealthy", backend: "unreachable" } };
    }
}
