import type { Metadata } from "next";
import { dehydrate, HydrationBoundary } from "@tanstack/react-query";
import { createQueryClient } from "@/lib/query-client";
import { getServerSession } from "@/lib/api/server";
import { sessionKey } from "@/features/auth/api/query-keys";
import { SiteShell } from "@/app/_components/site-shell";

export const metadata: Metadata = {
    robots: { index: false, follow: false },
};

export default async function ItinerariesLayout({
    children,
}: Readonly<{ children: React.ReactNode }>) {
    const client = createQueryClient();
    client.setQueryData(sessionKey, await getServerSession());
    return (
        <HydrationBoundary state={dehydrate(client)}>
            <SiteShell>{children}</SiteShell>
        </HydrationBoundary>
    );
}
