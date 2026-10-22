import Link from "next/link";
import { redirect } from "next/navigation";
import { dehydrate, HydrationBoundary } from "@tanstack/react-query";
import { getServerSession, serverApi, ServerApiError } from "@/lib/api/server";
import { createQueryClient } from "@/lib/query-client";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import type { Itinerary, PackingTemplate } from "@/types/api";
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
    const [session, result] = await Promise.all([
        getServerSession(),
        serverApi<Itinerary>(`/itineraries/${encodeURIComponent(id)}`).then(
            (data) => ({ data, error: null }),
            (error: unknown) => ({ data: null, error }),
        ),
    ]);
    if (result.error) {
        if (result.error instanceof ServerApiError) {
            if ([401, 419].includes(result.error.status)) redirect("/login");
            if ([403, 404].includes(result.error.status)) {
                return (
                    <div className="mx-auto min-h-[70vh] max-w-3xl px-5 pt-32 text-center">
                        <p role="alert">
                            {result.error.status === 404
                                ? "しおりが見つからないか、閲覧権限がありません。"
                                : "このしおりの閲覧権限がありません。"}
                        </p>
                        {result.error.status === 403 && (
                            <Link
                                href={`/itineraries/${encodeURIComponent(id)}/shared-access`}
                                className="mt-4 inline-flex rounded-full bg-[#208b82] px-5 py-2.5 text-sm font-bold text-white"
                            >
                                共有パスワードを入力
                            </Link>
                        )}
                    </div>
                );
            }
        }
        throw result.error;
    }
    const itinerary = result.data!;
    const client = createQueryClient();
    client.setQueryData(
        itineraryKeys.detail(id, session.user?.id ?? "guest"),
        itinerary,
    );
    if (itinerary.permissions.can_edit) {
        const response = await serverApi<{ templates: PackingTemplate[] }>(
            "/packing-templates",
        );
        client.setQueryData(itineraryKeys.templates, response.templates);
    }
    return (
        <HydrationBoundary state={dehydrate(client)}>
            <ItineraryEditScreen key={id} id={id} />
        </HydrationBoundary>
    );
}
