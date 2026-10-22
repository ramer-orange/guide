import "server-only";

import { cache } from "react";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import type { Session } from "@/types/api";

export class ServerApiError extends Error {
    constructor(public readonly status: number) {
        super(`API request failed (${status})`);
    }
}

// Only a configured origin is used; never send session cookies to a request Host.
export async function serverApi<T>(path: string): Promise<T> {
    const incoming = await headers();
    const origin =
        process.env.API_INTERNAL_ORIGIN ??
        process.env.NEXT_PUBLIC_SITE_URL ??
        "http://localhost:8081";
    const response = await fetch(new URL(`/api/v1${path}`, origin), {
        headers: {
            Accept: "application/json",
            Cookie: incoming.get("cookie") ?? "",
        },
        cache: "no-store",
        redirect: "manual",
        signal: AbortSignal.timeout(15_000),
    });
    if (!response.ok) throw new ServerApiError(response.status);
    return response.json() as Promise<T>;
}

// React cache deduplicates only within this server request.
export const getServerSession = cache(() => serverApi<Session>("/session"));

export async function requireServerSession() {
    const session = await getServerSession();
    if (!session.authenticated) redirect("/login");
    return session;
}
