import { QueryClient } from "@tanstack/react-query";
import { normalizeApiError } from "@/lib/api/errors";

export function createQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                retry: (count, error) =>
                    ![401, 403, 419, 404].includes(
                        normalizeApiError(error).status,
                    ) && count < 1,
                refetchOnWindowFocus: false,
            },
            mutations: { retry: false },
        },
    });
}
