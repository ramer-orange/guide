"use client";
import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Mail, UserRound, UserPlus, Trash2 } from "lucide-react";
import {
    addMember,
    removeMember,
} from "@/features/itineraries/api/itineraries";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import { normalizeApiError } from "@/lib/api/errors";
import type { Itinerary } from "@/types/api";

export function MemberManagement({ itinerary }: { itinerary: Itinerary }) {
    const [email, setEmail] = useState("");
    const [message, setMessage] = useState("");
    const client = useQueryClient();
    const invalidate = () =>
        client.invalidateQueries({ queryKey: ["itinerary", itinerary.id] });
    const add = useMutation({
        mutationFn: () => addMember(itinerary.id, email),
        onSuccess: async () => {
            setEmail("");
            setMessage("メンバーを追加しました。");
            await invalidate();
        },
    });
    const remove = useMutation({
        mutationFn: (memberId: number) => removeMember(itinerary.id, memberId),
        onSuccess: invalidate,
    });
    if (!itinerary.permissions.can_manage_members) return null;
    return (
        <ItinerarySection
            eyebrow="TRAVEL TOGETHER"
            title="メンバー"
            description="登録済みのメールアドレスを追加すると、一緒に編集できます。持ち物は各自のリストに分かれています。"
        >
            <form
                className="flex flex-col gap-2 sm:flex-row"
                onSubmit={(event) => {
                    event.preventDefault();
                    add.mutate();
                }}
            >
                <label className="sr-only" htmlFor="member-email">
                    メンバーのメールアドレス
                </label>
                <div className="relative flex-1">
                    <Mail
                        className="absolute top-1/2 left-3 -translate-y-1/2 text-[#90a09b]"
                        size={17}
                    />
                    <input
                        id="member-email"
                        required
                        type="email"
                        autoComplete="email"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        className="editor-input editor-input-with-leading-icon"
                        placeholder="member@example.com"
                    />
                </div>
                <button
                    disabled={add.isPending}
                    className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-[#278d83] px-5 text-sm font-bold text-white disabled:opacity-60"
                >
                    <UserPlus size={16} />
                    追加
                </button>
            </form>
            {(add.isError || remove.isError) && (
                <p role="alert" className="mt-3 text-sm text-rose-700">
                    {normalizeApiError(add.error ?? remove.error).message}
                </p>
            )}
            {message && (
                <p role="status" className="mt-3 text-sm text-emerald-800">
                    {message}
                </p>
            )}
            <ul className="mt-4 divide-y divide-[#edf0ed]">
                {itinerary.members.map((member) => (
                    <li
                        key={member.id}
                        className="flex items-center justify-between gap-3 py-3"
                    >
                        <div className="flex items-center gap-3">
                            <span className="grid size-9 place-items-center rounded-full bg-[#eff6f1] text-[#458078]">
                                <UserRound size={17} />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-[#405957]">
                                    {member.user.name}
                                </p>
                                <p className="text-xs text-[#86938f]">
                                    {member.user.email}
                                </p>
                            </div>
                        </div>
                        {member.role === "owner" ? (
                            <span className="rounded-full bg-[#eaf4f0] px-3 py-1 text-xs font-semibold text-[#57837b]">
                                作成者
                            </span>
                        ) : (
                            <button
                                type="button"
                                aria-label={`${member.user.name}をメンバーから削除`}
                                disabled={remove.isPending}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `${member.user.name}さんをメンバーから削除しますか？`,
                                        )
                                    )
                                        remove.mutate(member.id);
                                }}
                                className="rounded-lg p-2 text-[#bb7465] hover:bg-rose-50"
                            >
                                <Trash2 size={17} />
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </ItinerarySection>
    );
}
