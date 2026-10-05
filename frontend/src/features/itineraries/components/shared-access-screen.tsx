"use client";
import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useParams } from "next/navigation";
import { LockKeyhole } from "lucide-react";
import {
    confirmSharedAccess,
    getSharedAccess,
} from "@/features/itineraries/api/itineraries";
import { ItineraryView } from "@/features/itineraries/components/itinerary-view";
import { normalizeApiError } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import { TextInput } from "@/components/ui/text-input";
export function SharedAccessScreen() {
    const params = useParams<{ id: string }>();
    const id = params.id;
    const [password, setPassword] = useState("");
    const status = useQuery({
        queryKey: ["shared-access", id],
        queryFn: () => getSharedAccess(id),
        retry: false,
        gcTime: 0,
    });
    const verify = useMutation({
        mutationFn: () => confirmSharedAccess(id, password),
        retry: false,
    });
    if (verify.data)
        return (
            <div className="min-h-[75vh] px-5 pt-28 pb-16">
                <ItineraryView itinerary={verify.data} />
            </div>
        );
    if (status.isPending)
        return (
            <div
                role="status"
                className="mx-auto mt-32 h-48 max-w-xl animate-pulse rounded-3xl bg-[#e8efea]"
            >
                共有情報を確認しています…
            </div>
        );
    if (status.isError || !status.data.available)
        return (
            <section className="mx-auto max-w-xl px-6 pt-36 pb-24 text-center">
                <div className="mx-auto grid size-14 place-items-center rounded-full bg-[#fff0ea] text-[#c67c68]">
                    <LockKeyhole />
                </div>
                <h1 className="mt-5 text-2xl font-bold text-[#3d5554]">
                    この共有リンクは利用できません
                </h1>
                <p className="mt-3 text-sm leading-7 text-[#798682]">
                    共有が停止されたか、閲覧期限が過ぎています。リンクを作成した方にご確認ください。
                </p>
            </section>
        );
    return (
        <section className="mx-auto max-w-xl px-6 pt-32 pb-24">
            <div className="rounded-[1.7rem] border border-[#e1eae4] bg-white p-7 shadow-lg sm:p-9">
                <p className="text-xs font-bold tracking-[.2em] text-[#55a6a0]">
                    PLAGINE · SHARED NOTE
                </p>
                <h1 className="mt-3 text-2xl font-extrabold text-[#25474d]">
                    {status.data.title}
                </h1>
                <p className="mt-2 text-sm leading-7 text-[#71817e]">
                    このしおりを閲覧するには、共有パスワードを入力してください。
                </p>
                <form
                    className="mt-6 grid gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        verify.mutate();
                    }}
                >
                    <label
                        htmlFor="shared-password"
                        className="text-sm font-semibold text-[#506563]"
                    >
                        共有パスワード
                    </label>
                    <TextInput
                        id="shared-password"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        aria-invalid={verify.isError}
                    />
                    <Button
                        type="submit"
                        disabled={verify.isPending}
                        className="mt-1 w-full"
                    >
                        <LockKeyhole size={16} />
                        {verify.isPending ? "確認中…" : "しおりを開く"}
                    </Button>
                </form>
                {verify.isError && (
                    <p role="alert" className="mt-3 text-sm text-rose-700">
                        {normalizeApiError(verify.error).status === 429
                            ? "試行回数が上限に達しました。しばらくしてからお試しください。"
                            : "パスワードを確認できませんでした。入力内容をご確認ください。"}
                    </p>
                )}
            </div>
        </section>
    );
}
