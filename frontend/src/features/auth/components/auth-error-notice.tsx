"use client";
import { useQueryClient } from "@tanstack/react-query";
import { useSession, sessionKey } from "@/features/auth/hooks/use-session";
export function AuthErrorNotice() {
    const { data } = useSession();
    const client = useQueryClient();
    if (!data?.auth_error) return null;
    return (
        <div
            role="status"
            className="fixed top-[82px] left-1/2 z-50 w-[min(92vw,620px)] -translate-x-1/2 rounded-xl border border-amber-200 bg-[#fff9e9] px-4 py-3 text-sm text-amber-900 shadow-lg"
        >
            <div className="flex items-start justify-between gap-4">
                <p>{data.auth_error}</p>
                <button
                    type="button"
                    onClick={() =>
                        client.setQueryData(sessionKey, {
                            ...data,
                            auth_error: null,
                        })
                    }
                    aria-label="通知を閉じる"
                    className="shrink-0 text-lg leading-none"
                >
                    ×
                </button>
            </div>
        </div>
    );
}
