import { dehydrate, HydrationBoundary } from "@tanstack/react-query";
import { serverApi, requireServerSession } from "@/lib/api/server";
import { createQueryClient } from "@/lib/query-client";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import type { ItineraryListResponse } from "@/types/api";
import type { Metadata } from "next";
import { ItineraryIndex } from "@/features/itineraries/components/itinerary-index";
export const metadata: Metadata = {
    title: "しおり一覧",
    robots: { index: false, follow: false },
};
export default async function Page() {
    const session = await requireServerSession();
    const response = await serverApi<ItineraryListResponse>("/itineraries");
    const client = createQueryClient();
    client.setQueryData(
        itineraryKeys.list(session.user!.id),
        response.itineraries,
    );
    return (
        <HydrationBoundary state={dehydrate(client)}>
            <ItineraryIndex />
        </HydrationBoundary>
    );
}
