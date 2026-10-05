"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    CalendarDays,
    Copy,
    FilePlus2,
    Pencil,
    Share2,
    Trash2,
} from "lucide-react";
import {
    deleteItinerary,
    listItineraries,
} from "@/features/itineraries/api/itineraries";
import { useSession } from "@/features/auth/hooks/use-session";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import { normalizeApiError } from "@/lib/api/errors";

export function ItineraryIndex() {
    const client = useQueryClient();
    const router = useRouter();
    const session = useSession();
    const [notice, setNotice] = useState("");
    const principal = session.data?.user?.id ?? "guest";
    const query = useQuery({
        queryKey: itineraryKeys.list(principal),
        queryFn: listItineraries,
        enabled: Boolean(session.data?.authenticated),
    });
    const remove = useMutation({
        mutationFn: deleteItinerary,
        onSuccess: () =>
            client.invalidateQueries({ queryKey: itineraryKeys.all }),
    });
    useEffect(() => {
        if (session.data && !session.data.authenticated)
            router.replace("/login");
    }, [session.data, router]);
    if (session.isError)
        return (
            <section className="mx-auto min-h-[60vh] max-w-6xl px-5 pt-36 text-center">
                <p role="alert">ログイン状態を確認できませんでした。</p>
                <button
                    type="button"
                    onClick={() => session.refetch()}
                    className="mt-3 rounded-full border px-4 py-2"
                >
                    再読み込み
                </button>
            </section>
        );
    if (session.isPending)
        return (
            <section
                role="status"
                className="mx-auto min-h-[60vh] max-w-6xl px-5 pt-36 text-center text-sm text-[#73827e]"
            >
                ログイン状態を確認しています…
            </section>
        );
    if (!session.data?.authenticated)
        return (
            <section
                role="status"
                className="mx-auto min-h-[60vh] max-w-6xl px-5 pt-36 text-center text-sm text-[#73827e]"
            >
                ログイン画面へ移動しています…
            </section>
        );
    async function share(id: string, title: string) {
        const url = `${window.location.origin}/itineraries/${encodeURIComponent(id)}/edit`;
        try {
            if (navigator.share) await navigator.share({ title, url });
            else {
                await navigator.clipboard.writeText(url);
                setNotice("編集ページのURLをコピーしました。");
            }
        } catch {
            setNotice(
                "共有できませんでした。URLをコピーできる設定を確認してください。",
            );
        }
    }
    return (
        <section className="mx-auto max-w-6xl px-5 pt-28 pb-20 sm:px-8">
            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-extrabold tracking-[.22em] text-[#55a6a0]">
                        MY TRAVEL NOTES
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold text-[#203f45]">
                        しおり一覧
                    </h1>
                    <p className="mt-2 text-sm text-[#778681]">
                        あなたの旅の計画をまとめて管理できます。
                    </p>
                </div>
                <Link
                    href="/itineraries/create"
                    className="inline-flex items-center gap-2 rounded-full bg-[#208b82] px-5 py-3 text-sm font-bold text-white shadow-md shadow-[#208b82]/20"
                >
                    <FilePlus2 size={17} />
                    新しいしおり
                </Link>
            </div>
            {notice && (
                <p
                    role="status"
                    className="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"
                >
                    {notice}
                </p>
            )}
            {remove.isError && (
                <p
                    role="alert"
                    className="mb-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800"
                >
                    {normalizeApiError(remove.error).message}
                </p>
            )}
            {query.isPending ? (
                <div
                    role="status"
                    className="grid gap-3"
                    aria-label="しおりを読み込み中"
                >
                    {[1, 2, 3].map((item) => (
                        <div
                            key={item}
                            className="h-32 animate-pulse rounded-2xl bg-[#e8efea]"
                        />
                    ))}
                </div>
            ) : query.isError ? (
                <div className="rounded-2xl border border-rose-100 bg-white p-8 text-center">
                    <p role="alert" className="text-sm text-rose-800">
                        {normalizeApiError(query.error).message}
                    </p>
                    <button
                        type="button"
                        onClick={() => query.refetch()}
                        className="mt-4 rounded-full border px-4 py-2 text-sm font-semibold"
                    >
                        再読み込み
                    </button>
                </div>
            ) : query.data.length === 0 ? (
                <div className="paper-grid rounded-[1.8rem] border border-[#e2ebe5] p-10 text-center sm:p-16">
                    <div className="mx-auto grid size-16 place-items-center rounded-2xl bg-white text-[#4b9b91] shadow-sm">
                        <FilePlus2 size={26} />
                    </div>
                    <h2 className="mt-5 text-xl font-bold text-[#385655]">
                        まだしおりがありません
                    </h2>
                    <p className="mt-2 text-sm text-[#7b8985]">
                        次の旅の予定を作ってみましょう。
                    </p>
                    <Link
                        href="/itineraries/create"
                        className="mt-6 inline-flex rounded-full bg-[#208b82] px-5 py-3 text-sm font-bold text-white"
                    >
                        しおりを作成する
                    </Link>
                </div>
            ) : (
                <div className="grid gap-3">
                    {query.data.map((trip) => (
                        <article
                            key={trip.id}
                            className="rounded-2xl border border-[#e2eae5] bg-white p-5 shadow-[0_10px_35px_-28px_rgba(32,74,69,.4)] transition hover:border-[#c9dfd5] sm:p-6"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="min-w-0 flex-1">
                                    <h2 className="truncate text-lg font-bold text-[#304e50]">
                                        {trip.title || "タイトルなし"}
                                    </h2>
                                    {trip.overview_text && (
                                        <p className="mt-2 line-clamp-2 text-sm leading-6 whitespace-pre-wrap text-[#75827e]">
                                            {trip.overview_text}
                                        </p>
                                    )}
                                    <p className="mt-3 flex items-center gap-1.5 text-xs text-[#96a19c]">
                                        <CalendarDays size={14} />
                                        作成日{" "}
                                        {trip.created_at
                                            ? new Intl.DateTimeFormat(
                                                  "ja-JP",
                                              ).format(
                                                  new Date(trip.created_at),
                                              )
                                            : "—"}
                                        {!trip.permissions.can_delete && (
                                            <span className="ml-2 rounded-full bg-[#eef6f2] px-2.5 py-1 text-[#5e8278]">
                                                共有されたしおり
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            share(trip.id, trip.title)
                                        }
                                        aria-label="共有URLをコピー"
                                        className="rounded-xl p-2.5 text-[#528e87] hover:bg-[#edf7f2]"
                                    >
                                        <Share2 size={18} />
                                    </button>
                                    <Link
                                        href={`/itineraries/${encodeURIComponent(trip.id)}/edit`}
                                        aria-label={
                                            trip.permissions.can_edit
                                                ? "編集"
                                                : "閲覧"
                                        }
                                        className="rounded-xl p-2.5 text-[#528e87] hover:bg-[#edf7f2]"
                                    >
                                        {trip.permissions.can_edit ? (
                                            <Pencil size={18} />
                                        ) : (
                                            <Copy size={18} />
                                        )}
                                    </Link>
                                    {trip.permissions.can_delete && (
                                        <button
                                            type="button"
                                            disabled={remove.isPending}
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `「${trip.title}」を削除しますか？この操作は取り消せません。`,
                                                    )
                                                )
                                                    remove.mutate(trip.id);
                                            }}
                                            aria-label="削除"
                                            className="rounded-xl p-2.5 text-[#c77c6a] hover:bg-rose-50"
                                        >
                                            <Trash2 size={18} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
