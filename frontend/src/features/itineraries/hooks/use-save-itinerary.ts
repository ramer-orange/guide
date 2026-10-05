"use client";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { flushSync } from "react-dom";
import type { UseFormReturn, Path } from "react-hook-form";
import { saveItinerary } from "@/features/itineraries/api/itineraries";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import { normalizeApiError } from "@/lib/api/errors";
import {
    apiErrorsToFormFields,
    toItineraryFormValues,
} from "@/features/itineraries/utils/itinerary-form";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";

export function useSaveItinerary(
    id: string | undefined,
    form: UseFormReturn<ItineraryFormValues>,
) {
    const client = useQueryClient();
    return useMutation({
        mutationFn: (values: ItineraryFormValues) => saveItinerary(values, id),
        onSuccess: async (saved) => {
            await client.invalidateQueries({ queryKey: itineraryKeys.all });
            await client.setQueriesData(
                { queryKey: itineraryKeys.byId(saved.id) },
                saved,
            );
            flushSync(() => form.reset(toItineraryFormValues(saved)));
        },
        onError: (error) => {
            const normalized = normalizeApiError(error);
            for (const [field, message] of Object.entries(
                apiErrorsToFormFields(normalized.errors),
            )) {
                form.setError(field as Path<ItineraryFormValues>, {
                    type: "server",
                    message: String(message),
                });
            }
        },
    });
}
