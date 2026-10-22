import { dehydrate, HydrationBoundary } from "@tanstack/react-query";
import { requireServerSession, serverApi } from "@/lib/api/server";
import { createQueryClient } from "@/lib/query-client";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import type { PackingTemplate } from "@/types/api";
import type { Metadata } from "next";
import { ItineraryCreateScreen } from "@/features/itineraries/components/itinerary-create-screen";
export const metadata: Metadata = {
    title: "しおり作成",
    robots: { index: false, follow: false },
};
export default async function Page() {
    await requireServerSession();
    const client = createQueryClient();
    const response = await serverApi<{ templates: PackingTemplate[] }>(
        "/packing-templates",
    );
    client.setQueryData(itineraryKeys.templates, response.templates);
    return (
        <HydrationBoundary state={dehydrate(client)}>
            <ItineraryCreateScreen />
        </HydrationBoundary>
    );
}
