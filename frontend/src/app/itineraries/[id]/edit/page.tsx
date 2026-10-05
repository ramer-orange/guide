import type { Metadata } from "next";
import { ItineraryEditScreen } from "@/features/itineraries/components/itinerary-edit-screen";
export const metadata: Metadata = {
    title: "しおり",
    robots: { index: false, follow: false },
};
export default async function Page({
    params,
}: {
    params: Promise<{ id: string }>;
}) {
    const { id } = await params;
    return <ItineraryEditScreen id={id} />;
}
