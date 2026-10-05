import type { Metadata } from "next";
import { SharedAccessScreen } from "@/features/itineraries/components/shared-access-screen";
export const metadata: Metadata = {
    title: "共有されたしおり",
    robots: { index: false, follow: false },
};
export default function Page() {
    return <SharedAccessScreen />;
}
