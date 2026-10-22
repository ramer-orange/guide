"use client";
import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { sessionKey } from "@/features/auth/hooks/use-session";
export function SessionExpiryHandler() {
    const client = useQueryClient();
    useEffect(() => {
        const clear = () => {
            void client.cancelQueries();
            client.removeQueries({
                predicate: (query) => query.queryKey[0] !== sessionKey[0],
            });
            void client.invalidateQueries({ queryKey: sessionKey });
        };
        window.addEventListener("plagine:session-expired", clear);
        return () =>
            window.removeEventListener("plagine:session-expired", clear);
    }, [client]);
    return null;
}
