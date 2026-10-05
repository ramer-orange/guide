"use client";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { logout } from "@/features/auth/api/logout";
import { sessionKey } from "@/features/auth/hooks/use-session";
import type { Session } from "@/types/api";
export function useLogout() {
    const client = useQueryClient();
    const router = useRouter();
    return useMutation({
        mutationFn: logout,
        onSuccess: () => {
            client.clear();
            client.setQueryData<Session>(sessionKey, {
                authenticated: false,
                user: null,
                auth_error: null,
            });
            router.push("/");
            router.refresh();
        },
    });
}
