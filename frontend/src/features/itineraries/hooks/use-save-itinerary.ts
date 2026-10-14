"use client";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRef } from "react";
import { flushSync } from "react-dom";
import type { UseFormReturn, Path } from "react-hook-form";
import { saveItinerary } from "@/features/itineraries/api/itineraries";
import { itineraryKeys } from "@/features/itineraries/api/query-keys";
import { normalizeApiError } from "@/lib/api/errors";
import {
    apiErrorsToFormFields,
    toItineraryFormValues,
    toItineraryChanges,
} from "@/features/itineraries/utils/itinerary-form";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";

export function useSaveItinerary(
    id: string | undefined,
    form: UseFormReturn<ItineraryFormValues>,
    initialValues: ItineraryFormValues,
) {
    const client = useQueryClient();
    const baseline = useRef(initialValues);
    return useMutation({
        mutationFn: (values: ItineraryFormValues) =>
            saveItinerary(values, id, baseline.current),
        onSuccess: async (saved) => {
            await client.invalidateQueries({ queryKey: itineraryKeys.all });
            await client.setQueriesData(
                { queryKey: itineraryKeys.byId(saved.id) },
                saved,
            );
            baseline.current = toItineraryFormValues(saved);
            flushSync(() => form.reset(baseline.current));
        },
        onError: (error, values) => {
            const changes = id
                ? toItineraryChanges(values, baseline.current)
                : undefined;
            const normalized = normalizeApiError(error);
            for (const [field, message] of Object.entries(
                apiErrorsToFormFields(normalized.errors),
            )) {
                // The request contains a subset of rows, so map server indexes back to the form.
                const formField = field.replace(
                    /^(plans|packing_items|souvenirs|notes)\.(\d+)(?=\.|$)/,
                    (
                        match,
                        collection:
                            "plans" | "packing_items" | "souvenirs" | "notes",
                        index: string,
                    ) => {
                        const row = changes?.[collection]?.[Number(index)];
                        if (!row) return match;
                        const formIndex =
                            row.id === undefined
                                ? row.order
                                : values[collection].findIndex(
                                      (item) => item.id === row.id,
                                  );
                        return formIndex === undefined || formIndex < 0
                            ? match
                            : `${collection}.${formIndex}`;
                    },
                );
                form.setError(formField as Path<ItineraryFormValues>, {
                    type: "server",
                    message: String(message),
                });
            }
        },
    });
}
