"use client";

import { QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";
import { NavigationGuardProvider } from "nextjs-nav-guard";
import { createQueryClient } from "@/lib/query-client";
import { SessionExpiryHandler } from "@/features/auth/components/session-expiry-handler";

export function Providers({
    children,
}: Readonly<{ children: React.ReactNode }>) {
    const [client] = useState(createQueryClient);
    return (
        <NavigationGuardProvider>
            <QueryClientProvider client={client}>
                <SessionExpiryHandler />
                {children}
            </QueryClientProvider>
        </NavigationGuardProvider>
    );
}
