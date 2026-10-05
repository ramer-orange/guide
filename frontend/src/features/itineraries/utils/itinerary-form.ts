import type { Itinerary, ItinerarySave } from "@/types/api";
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
export function apiErrorsToFormFields(errorMap: Record<string, string[]>) {
    return Object.fromEntries(
        Object.entries(errorMap).map(([path, messages]) => [
            path.replace(/\.(\d+)\./g, ".$1."),
            messages[0] ?? "入力内容を確認してください。",
        ]),
    );
}
