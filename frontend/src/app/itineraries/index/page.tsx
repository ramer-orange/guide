import type { Metadata } from "next";
import { ItineraryIndex } from "@/features/itineraries/components/itinerary-index";
export const metadata: Metadata = {
    title: "しおり一覧",
    robots: { index: false, follow: false },
};
export default function Page() {
    return <ItineraryIndex />;
}
