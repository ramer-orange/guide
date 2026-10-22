import { dehydrate, HydrationBoundary } from "@tanstack/react-query";
import { serverApi, ServerApiError } from "@/lib/api/server";
import { createQueryClient } from "@/lib/query-client";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import type { SharedAccessStatus } from "@/types/api";
import type { Metadata } from "next";
import { SharedAccessScreen } from "@/features/itineraries/components/shared-access-screen";
export const metadata: Metadata = {
    title: "共有されたしおり",
    robots: { index: false, follow: false },
};
export default async function Page({
    params,
}: {
    params: Promise<{ id: string }>;
}) {
    const { id } = await params;
    const client = createQueryClient();
    try {
        const status = await serverApi<SharedAccessStatus>(
            `/itineraries/${encodeURIComponent(id)}/shared-access`,
        );
        client.setQueryData(itineraryKeys.sharedAccess(id), status);
    } catch (error) {
        if (error instanceof ServerApiError && error.status === 404) {
            return <SharedAccessScreen key={id} unavailable />;
        }
        throw error;
    }
    return (
        <HydrationBoundary state={dehydrate(client)}>
            <SharedAccessScreen key={id} />
        </HydrationBoundary>
    );
}
