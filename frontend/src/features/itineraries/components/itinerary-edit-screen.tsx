"use client";
import { useQuery } from "@tanstack/react-query";
import { ItineraryEditor } from "@/features/itineraries/components/editor/itinerary-editor";
import { ItineraryView } from "@/features/itineraries/components/itinerary-view";
import { getItinerary } from "@/features/itineraries/api/itineraries";
import { normalizeApiError } from "@/lib/api/errors";
import Link from "next/link";
import { useSession } from "@/features/auth/hooks/use-session";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
export function ItineraryEditScreen({ id }: { id: string }) {
    const session = useSession();
    const principal = session.data?.user?.id ?? "guest";
    const query = useQuery({
        queryKey: itineraryKeys.detail(id, principal),
        queryFn: () => getItinerary(id),
        staleTime: 0,
        gcTime: 0,
        enabled: session.isSuccess,
    });
    if (session.isPending)
        return (
            <div
                role="status"
                className="mx-auto h-72 max-w-4xl animate-pulse rounded-3xl bg-[#e8efea] px-5 pt-28"
            >
                しおりを読み込み中…
            </div>
        );
    if (session.isError)
        return (
            <div className="mx-auto min-h-[60vh] max-w-4xl px-5 pt-36 text-center">
                <p role="alert">ログイン状態を確認できませんでした。</p>
                <button
                    type="button"
                    onClick={() => session.refetch()}
                    className="mt-3 rounded-full border px-4 py-2"
                >
                    再読み込み
                </button>
            </div>
        );
    return (
        <div className="min-h-[70vh] bg-[#fffdfa] px-5 pt-28 pb-20 sm:px-8">
            {query.isPending ? (
                <div
                    role="status"
                    className="mx-auto h-72 max-w-4xl animate-pulse rounded-3xl bg-[#e8efea]"
                >
                    しおりを読み込み中…
                </div>
            ) : query.isError ? (
                <div className="mx-auto max-w-3xl rounded-2xl bg-white p-8 text-center">
                    <p role="alert" className="text-sm text-rose-800">
                        {normalizeApiError(query.error).status === 404
                            ? "しおりが見つからないか、閲覧権限がありません。"
                            : normalizeApiError(query.error).message}
                    </p>
                    {normalizeApiError(query.error).status === 403 && (
                        <Link
                            href={`/itineraries/${encodeURIComponent(id)}/shared-access`}
                            className="mt-4 inline-flex rounded-full bg-[#208b82] px-5 py-2.5 text-sm font-bold text-white"
                        >
                            共有パスワードを入力
                        </Link>
                    )}
                    <button
                        type="button"
                        onClick={() => query.refetch()}
                        className="mt-4 rounded-full border px-4 py-2 text-sm font-semibold"
                    >
                        再読み込み
                    </button>
                </div>
            ) : query.data.permissions.can_edit ? (
                <ItineraryEditor itinerary={query.data} id={id} />
            ) : (
                <div className="mx-auto max-w-4xl">
                    <p className="mb-4 rounded-full bg-[#edf6f1] px-4 py-2 text-xs font-semibold text-[#568179]">
                        閲覧モード
                    </p>
                    <ItineraryView itinerary={query.data} />
                </div>
            )}
        </div>
    );
}
