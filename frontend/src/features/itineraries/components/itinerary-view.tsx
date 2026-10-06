import Link from "next/link";
import {
    CalendarDays,
    Clock3,
    Download,
    MapPin,
    NotebookText,
} from "lucide-react";
import { MemberManagement } from "@/features/itineraries/components/sharing/member-management";
import { ViewerShareManagement } from "@/features/itineraries/components/sharing/viewer-share-management";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import type { Itinerary } from "@/types/api";

export function ItineraryView({ itinerary }: { itinerary: Itinerary }) {
    return (
        <div className="mx-auto grid max-w-4xl gap-5">
            <div className="overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-[#dff0e7] via-[#e9f4ef] to-[#e3f2f7] p-6 sm:p-10">
                <p className="text-xs font-bold tracking-[.2em] text-[#438c83]">
                    PLAGINE · TRAVEL NOTE
                </p>
                <h1 className="mt-3 text-3xl leading-tight font-extrabold text-[#22434a] sm:text-4xl">
                    {itinerary.title}
                </h1>
                {itinerary.overview_text && (
                    <p className="mt-4 max-w-2xl leading-7 whitespace-pre-wrap text-[#59706d]">
                        {itinerary.overview_text}
                    </p>
                )}
                <div className="mt-6 flex flex-wrap gap-2">
                    {itinerary.permissions.can_edit && (
                        <Link
                            href={`/itineraries/${encodeURIComponent(itinerary.id)}/edit?mode=edit`}
                            className="rounded-full bg-[#238b82] px-5 py-2.5 text-sm font-bold text-white"
                        >
                            編集する
                        </Link>
                    )}
                    <Link
                        href="/itineraries/index"
                        className="rounded-full border border-[#bdd4cb] bg-white/70 px-5 py-2.5 text-sm font-bold text-[#4d7069]"
                    >
                        しおり一覧
                    </Link>
                </div>
            </div>
            <ItinerarySection eyebrow="THE ROUTE" title="旅の予定">
                {itinerary.plans.length ? (
                    <ol className="space-y-3">
                        {[...itinerary.plans]
                            .sort((a, b) => a.order - b.order)
                            .map((plan, index) => (
                                <li
                                    key={plan.id}
                                    className="grid gap-3 rounded-2xl bg-[#fcfdfb] p-4 sm:grid-cols-[90px_1fr]"
                                >
                                    <div className="text-xs font-bold text-[#55978d]">
                                        <span className="block text-lg">
                                            {String(index + 1).padStart(2, "0")}
                                        </span>
                                        {plan.date && (
                                            <span className="mt-1 flex items-center gap-1">
                                                <CalendarDays size={13} />
                                                {plan.date}
                                            </span>
                                        )}
                                        {plan.time && (
                                            <span className="mt-1 flex items-center gap-1">
                                                <Clock3 size={13} />
                                                {plan.time}
                                            </span>
                                        )}
                                    </div>
                                    <div>
                                        <h3 className="flex items-center gap-2 font-bold text-[#405957]">
                                            <MapPin
                                                size={15}
                                                className="text-[#de947c]"
                                            />
                                            {plan.title || "予定"}
                                        </h3>
                                        {plan.content && (
                                            <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-[#71817e]">
                                                {plan.content}
                                            </p>
                                        )}
                                        {plan.files.length > 0 && (
                                            <ul className="mt-3 flex flex-wrap gap-2">
                                                {plan.files.map((file) => (
                                                    <li key={file.id} className="flex items-center gap-3 rounded-full border border-[#d8e5de] bg-white px-3 py-1.5 text-xs font-medium text-[#46766e]">
                                                        <span className="max-w-48 truncate">{file.file_name}</span>
                                                        {file.preview_url ? (
                                                            <a
                                                                href={file.preview_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                aria-label={`${file.file_name}を開く`}
                                                                className="underline"
                                                            >
                                                                開く
                                                            </a>
                                                        ) : (
                                                            <span className="text-[#899693]">プレビューできません</span>
                                                        )}
                                                        <a
                                                            href={file.url}
                                                            aria-label={`${file.file_name}をダウンロード`}
                                                            className="inline-flex items-center gap-1 underline"
                                                        >
                                                            <Download size={13} />
                                                            ダウンロード
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </li>
                            ))}
                    </ol>
                ) : (
                    <p className="text-sm text-[#87938f]">
                        予定はまだありません。
                    </p>
                )}
            </ItinerarySection>
            <ItinerarySection eyebrow="CHECKLIST" title="持ち物リスト">
                <Checklist
                    items={itinerary.packing_items}
                    empty="持ち物はまだありません。"
                />
            </ItinerarySection>
            <ItinerarySection eyebrow="SOUVENIRS" title="お土産">
                <Checklist
                    items={itinerary.souvenirs}
                    empty="お土産はまだありません。"
                />
            </ItinerarySection>
            <ItinerarySection eyebrow="NOTES" title="メモ">
                {itinerary.notes.length ? (
                    <div className="grid gap-3">
                        {itinerary.notes.map((note) => (
                            <article
                                key={note.id}
                                className="rounded-xl bg-[#fcfdfb] p-4"
                            >
                                <h3 className="flex items-center gap-2 font-semibold text-[#4d6863]">
                                    <NotebookText size={16} />
                                    {note.title || "メモ"}
                                </h3>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-[#71817e]">
                                    {note.text}
                                </p>
                            </article>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-[#87938f]">
                        メモはまだありません。
                    </p>
                )}
            </ItinerarySection>
            <MemberManagement itinerary={itinerary} />
            <ViewerShareManagement itinerary={itinerary} />
        </div>
    );
}
function Checklist({
    items,
    empty,
}: {
    items: Itinerary["packing_items"];
    empty: string;
}) {
    return items.length ? (
        <ul className="grid gap-2 sm:grid-cols-2">
            {[...items]
                .sort((a, b) => a.order - b.order)
                .map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center gap-3 rounded-xl bg-[#fcfdfb] p-3"
                    >
                        <span
                            className={`grid size-5 place-items-center rounded-md border ${item.is_checked ? "border-[#319186] bg-[#319186] text-white" : "border-[#ccd9d3]"}`}
                        >
                            {item.is_checked ? "✓" : ""}
                        </span>
                        <span
                            className={`text-sm ${item.is_checked ? "text-[#99a4a0] line-through" : "text-[#506562]"}`}
                        >
                            {item.name ?? ""}
                        </span>
                    </li>
                ))}
        </ul>
    ) : (
        <p className="text-sm text-[#87938f]">{empty}</p>
    );
}
