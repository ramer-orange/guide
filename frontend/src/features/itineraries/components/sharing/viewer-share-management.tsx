"use client";

import { useState, useSyncExternalStore } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Copy, Link2, LockKeyhole, Share2 } from "lucide-react";
import {
    revokeViewerShare,
    updateViewerShare,
} from "@/features/itineraries/api/itineraries";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import { FieldError } from "@/components/ui/field-error";
import { TextInput } from "@/components/ui/text-input";
import { Button } from "@/components/ui/button";
import {
    toIsoDateTime,
    toLocalDateTime,
} from "@/features/itineraries/utils/itinerary-form";
import { normalizeApiError } from "@/lib/api/errors";
import type { Itinerary } from "@/types/api";

export function ViewerShareManagement({ itinerary }: { itinerary: Itinerary }) {
    const client = useQueryClient();
    const [password, setPassword] = useState("");
    const [confirmation, setConfirmation] = useState("");
    const [editedExpiry, setEditedExpiry] = useState<string | null>(null);
    const initialExpiry = useSyncExternalStore(
        subscribeToNothing,
        () =>
            itinerary.viewer_share?.expires_at
                ? toLocalDateTime(itinerary.viewer_share.expires_at)
                : defaultExpiry(),
        () => "",
    );
    const expires = editedExpiry ?? initialExpiry;
    const [notice, setNotice] = useState("");
    const [localError, setLocalError] = useState("");

    const save = useMutation({
        mutationFn: () =>
            updateViewerShare(itinerary.id, {
                shared_password: password || undefined,
                shared_password_confirmation: confirmation || undefined,
                expires_at: toIsoDateTime(expires),
            }),
        onSuccess: async (updated) => {
            await client.setQueriesData(
                { queryKey: ["itinerary", itinerary.id] },
                updated,
            );
            setPassword("");
            setConfirmation("");
            setEditedExpiry(null);
            setNotice("閲覧共有を更新しました。");
            setLocalError("");
        },
    });
    const revoke = useMutation({
        mutationFn: () => revokeViewerShare(itinerary.id),
        onSuccess: async (updated) => {
            await client.setQueriesData(
                { queryKey: ["itinerary", itinerary.id] },
                updated,
            );
            setEditedExpiry(null);
            setNotice("閲覧共有を停止しました。");
        },
    });
    if (!itinerary.permissions.can_manage_viewer_share) return null;

    function getShareUrl() {
        return `${window.location.origin}/itineraries/${encodeURIComponent(itinerary.id)}/shared-access`;
    }
    async function copyUrl() {
        try {
            await navigator.clipboard.writeText(getShareUrl());
            setNotice("共有URLをコピーしました。");
        } catch {
            setNotice(
                "URLをコピーできませんでした。ブラウザの共有機能をお試しください。",
            );
        }
    }
    async function shareUrlNative() {
        if (!navigator.share) return copyUrl();
        try {
            await navigator.share({
                title: itinerary.title,
                url: getShareUrl(),
            });
        } catch {
            /* User dismissed the native share sheet. */
        }
    }
    const serverErrors = save.error ? normalizeApiError(save.error).errors : {};
    return (
        <ItinerarySection
            eyebrow="READ ONLY LINK"
            title="閲覧用リンク"
            description="パスワードを知っている人だけが旅行のしおりを閲覧できます。"
        >
            {itinerary.viewer_share?.active && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-[#edf7f1] p-3">
                    <div className="flex items-center gap-2 text-sm font-semibold text-[#36776d]">
                        <Link2 size={17} />
                        共有中
                        {itinerary.viewer_share.expires_at && (
                            <span className="text-xs font-normal">
                                ・期限{" "}
                                {new Date(
                                    itinerary.viewer_share.expires_at,
                                ).toLocaleString("ja-JP")}
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={shareUrlNative}
                            className="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#36776d]"
                        >
                            <Share2 size={14} />
                            共有
                        </button>
                        <button
                            type="button"
                            onClick={copyUrl}
                            className="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#36776d]"
                        >
                            <Copy size={14} />
                            URLコピー
                        </button>
                    </div>
                </div>
            )}
            <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    setLocalError("");
                    if (
                        Boolean(password) !== Boolean(confirmation) ||
                        (password && password !== confirmation)
                    ) {
                        setLocalError(
                            "パスワードと確認用の入力が一致しません。",
                        );
                        return;
                    }
                    if (!password && !itinerary.viewer_share?.active) {
                        setLocalError("共有パスワードを入力してください。");
                        return;
                    }
                    save.mutate();
                }}
            >
                <label className="grid gap-1.5 text-xs font-semibold text-[#61736f]">
                    閲覧パスワード
                    <TextInput
                        type="password"
                        minLength={8}
                        maxLength={32}
                        autoComplete="new-password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        placeholder={
                            itinerary.viewer_share?.active
                                ? "変更する場合のみ入力"
                                : "8〜32文字"
                        }
                        required={!itinerary.viewer_share?.active}
                    />
                    {serverErrors.shared_password && (
                        <FieldError>
                            {serverErrors.shared_password[0]}
                        </FieldError>
                    )}
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#61736f]">
                    パスワード確認
                    <TextInput
                        type="password"
                        autoComplete="new-password"
                        value={confirmation}
                        onChange={(event) =>
                            setConfirmation(event.target.value)
                        }
                        required={
                            !itinerary.viewer_share?.active || Boolean(password)
                        }
                    />
                    {serverErrors.shared_password_confirmation && (
                        <FieldError>
                            {serverErrors.shared_password_confirmation[0]}
                        </FieldError>
                    )}
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#61736f] sm:col-span-2">
                    共有期限
                    <TextInput
                        type="datetime-local"
                        className="max-w-sm"
                        value={expires}
                        onChange={(event) =>
                            setEditedExpiry(event.target.value)
                        }
                        required
                    />
                    {serverErrors.expires_at && (
                        <FieldError>{serverErrors.expires_at[0]}</FieldError>
                    )}
                </label>
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <Button type="submit" disabled={save.isPending}>
                        <LockKeyhole size={16} />
                        {itinerary.viewer_share?.active
                            ? "設定を更新"
                            : "閲覧共有を開始"}
                    </Button>
                    {itinerary.viewer_share?.active && (
                        <button
                            type="button"
                            disabled={revoke.isPending}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        "閲覧用リンクを停止しますか？",
                                    )
                                )
                                    revoke.mutate();
                            }}
                            className="rounded-full border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700"
                        >
                            共有を停止
                        </button>
                    )}
                </div>
            </form>
            {(localError ||
                (save.isError && Object.keys(serverErrors).length === 0) ||
                revoke.isError) && (
                <p role="alert" className="mt-3 text-sm text-rose-700">
                    {localError ||
                        (save.isError
                            ? normalizeApiError(save.error).message
                            : normalizeApiError(revoke.error).message)}
                </p>
            )}
            {notice && (
                <p role="status" className="mt-3 text-sm text-emerald-800">
                    {notice}
                </p>
            )}
        </ItinerarySection>
    );
}
function subscribeToNothing() {
    return () => undefined;
}
function defaultExpiry() {
    const date = new Date(Date.now() + 1000 * 60 * 60 * 24 * 30);
    date.setMinutes(0, 0, 0);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}T${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}
