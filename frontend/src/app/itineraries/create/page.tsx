import type { Metadata } from "next";
import { ItineraryCreateScreen } from "@/features/itineraries/components/itinerary-create-screen";
export const metadata: Metadata = {
    title: "しおり作成",
    robots: { index: false, follow: false },
};
export default function Page() {
    return <ItineraryCreateScreen />;
}
