"use client";
import { useQuery } from "@tanstack/react-query";
import { getSession } from "@/features/auth/api/session";
import { sessionKey } from "@/features/auth/api/query-keys";
export { sessionKey };
export function useSession() {
    return useQuery({
        queryKey: sessionKey,
        queryFn: getSession,
        staleTime: 60_000,
    });
}
