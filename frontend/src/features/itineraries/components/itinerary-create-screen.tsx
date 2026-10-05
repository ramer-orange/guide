"use client";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useSession } from "@/features/auth/hooks/use-session";
import { ItineraryEditor } from "@/features/itineraries/components/editor/itinerary-editor";
export function ItineraryCreateScreen() {
    const { data, isPending, isError, refetch } = useSession();
    const router = useRouter();
    useEffect(() => {
        if (data && !data.authenticated) router.replace("/login");
    }, [data, router]);
    if (isError)
        return (
            <div className="mx-auto max-w-3xl px-6 pt-32 text-center">
                <p role="alert">ログイン状態を確認できませんでした。</p>
                <button
                    onClick={() => refetch()}
                    className="mt-3 rounded-full border px-4 py-2"
                >
                    再読み込み
                </button>
            </div>
        );
    return (
        <div className="min-h-[70vh] bg-[#fffdfa] px-5 pt-28 pb-20 sm:px-8">
            {isPending || !data?.authenticated ? (
                <div
                    role="status"
                    className="mx-auto h-60 max-w-4xl animate-pulse rounded-3xl bg-[#e8efea]"
                >
                    ログイン状態を確認中…
                </div>
            ) : (
                <ItineraryEditor />
            )}
        </div>
    );
}
