import type { Itinerary, ItineraryChanges, ItinerarySave } from "@/types/api";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";

export function emptyPlan(): ItineraryFormValues["plans"][number] {
    return {
        client_id: crypto.randomUUID(),
        date: "",
        time: "",
        title: "",
        content: "",
        order: 0,
        files: [],
        existing_files: [],
    };
}
export function toLocalDateTime(isoDate: string | null | undefined) {
    if (!isoDate) return "";
    const date = new Date(isoDate);
    return Number.isNaN(date.getTime())
        ? ""
        : `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}T${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}
export function toIsoDateTime(localDate: string) {
    if (!localDate) return undefined;
    const date = new Date(localDate);
    return Number.isNaN(date.getTime()) ? undefined : date.toISOString();
}
export function defaultItineraryValues(): ItineraryFormValues {
    return {
        title: "",
        overview_text: "",
        template_type: null,
        plans: [emptyPlan()],
        packing_items: [{ name: "", is_checked: false, order: 0 }],
        souvenirs: [{ name: "", is_checked: false, order: 0 }],
        notes: [{ title: "", text: "", order: 0 }],
    };
}
export function toItineraryFormValues(trip: Itinerary): ItineraryFormValues {
    const values = defaultItineraryValues();
    return {
        ...values,
        title: trip.title,
        overview_text: trip.overview_text ?? "",
        template_type: trip.template_type,
        plans: trip.plans.map((plan) => ({
            client_id: crypto.randomUUID(),
            id: plan.id,
            date: plan.date ?? "",
            time: plan.time ?? "",
            title: plan.title ?? "",
            content: plan.content ?? "",
            order: plan.order,
            files: [],
            existing_files: plan.files,
        })),
        packing_items: trip.packing_items.map((item) => ({
            id: item.id,
            name: item.name ?? "",
            is_checked: item.is_checked,
            order: item.order,
        })),
        souvenirs: trip.souvenirs.map((item) => ({
            id: item.id,
            name: item.name ?? "",
            is_checked: item.is_checked,
            order: item.order,
        })),
        notes: trip.notes.map((item) => ({
            id: item.id,
            title: item.title ?? "",
            text: item.text ?? "",
            order: item.order,
        })),
    };
}
export function toItineraryPayload(values: ItineraryFormValues): ItinerarySave {
    return {
        title: values.title.trim(),
        overview_text: values.overview_text,
        template_type: values.template_type,
        plans: values.plans.map((plan, order) => ({
            ...(plan.id === undefined ? {} : { id: plan.id }),
            client_id: plan.client_id,
            date: plan.date || "",
            time: plan.time || "",
            title: plan.title.trim(),
            content: plan.content,
            order,
            existing_file_ids: plan.existing_files.map((file) => file.id),
        })),
        packing_items: values.packing_items.map(({ name, ...item }, order) => ({
            ...item,
            name: name.trim(),
            order,
        })),
        souvenirs: values.souvenirs.map(({ name, ...item }, order) => ({
            ...item,
            name: name.trim(),
            order,
        })),
        notes: values.notes.map((item, order) => ({
            ...item,
            title: item.title.trim(),
            order,
        })),
    };
}
/** Diff against the last successful load/save, never against background refetches. */
export function toItineraryChanges(
    values: ItineraryFormValues,
    baseline: ItineraryFormValues,
): ItineraryChanges {
    const current = toItineraryPayload(values);
    const previous = toItineraryPayload(baseline);
    const changes: ItineraryChanges = {};
    for (const field of ["title", "overview_text", "template_type"] as const) {
        if (current[field] !== previous[field]) {
            Object.assign(changes, { [field]: current[field] });
        }
    }

    for (const [collection, deletedKey] of [
        ["plans", "deleted_plan_ids"],
        ["packing_items", "deleted_packing_item_ids"],
        ["souvenirs", "deleted_souvenir_ids"],
        ["notes", "deleted_note_ids"],
    ] as const) {
        const oldRows = new Map(
            previous[collection].map((row) => [row.id, row]),
        );
        const currentIds = new Set(current[collection].map((row) => row.id));
        const deleted = previous[collection].flatMap((row) =>
            row.id !== undefined && !currentIds.has(row.id) ? [row.id] : [],
        );
        if (deleted.length) Object.assign(changes, { [deletedKey]: deleted });

        const rows: Record<string, unknown>[] = [];
        current[collection].forEach((row, index) => {
            const oldRow =
                row.id === undefined ? undefined : oldRows.get(row.id);
            const update: Record<string, unknown> = {};
            if (row.id !== undefined) update.id = row.id;
            for (const [key, value] of Object.entries(row)) {
                if (["id", "client_id", "existing_file_ids"].includes(key))
                    continue;
                if (!oldRow || value !== oldRow[key as keyof typeof oldRow]) {
                    update[key] = value;
                }
            }
            if (collection === "plans") {
                const plan = current.plans[index];
                const before = previous.plans.find(
                    (item) => item.id === plan.id,
                );
                const kept = new Set(plan.existing_file_ids);
                const removed =
                    before?.existing_file_ids.filter(
                        (fileId) => !kept.has(fileId),
                    ) ?? [];
                if (removed.length) update.removed_file_ids = removed;
                const hasUploads = values.plans[index].files.length > 0;
                if (oldRow && Object.keys(update).length === 1 && !hasUploads)
                    return;
                update.client_id = plan.client_id;
            } else if (oldRow && Object.keys(update).length === 1) {
                return;
            }
            rows.push(update);
        });
        if (rows.length) Object.assign(changes, { [collection]: rows });
    }
    return changes;
}

export function apiErrorsToFormFields(errorMap: Record<string, string[]>) {
    return Object.fromEntries(
        Object.entries(errorMap).map(([path, messages]) => [
            path.replace(/\.(\d+)\./g, ".$1."),
            messages[0] ?? "入力内容を確認してください。",
        ]),
    );
}
