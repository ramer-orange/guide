import { checkHealth } from "@/lib/health/check-health";

export const dynamic = "force-dynamic";

// ALB とコンテナのヘルスチェック用。Next.js と Laravel の両方が動いているときだけ 200 を返す。
export async function GET() {
    const origin = process.env.API_INTERNAL_ORIGIN ?? "http://127.0.0.1:80";
    const result = await checkHealth(origin);
    return Response.json(result.body, {
        status: result.status,
        headers: { "Cache-Control": "no-store" },
    });
}
