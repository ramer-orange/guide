import { api, ensureCsrfCookie } from "@/lib/api/client";
import type {
    AddMemberInput,
    AddMemberResponse,
    Itinerary,
    ItineraryListResponse,
    PackingTemplate,
    SharedAccessStatus,
    ViewerShareInput,
} from "@/types/api";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { toItineraryPayload } from "@/features/itineraries/utils/itinerary-form";

export async function listItineraries() {
    return (await api.get<ItineraryListResponse>("/itineraries")).data
        .itineraries;
}
export async function getItinerary(id: string) {
    return (await api.get<Itinerary>(`/itineraries/${encodeURIComponent(id)}`))
        .data;
}
export async function getPackingTemplates() {
    return (
        await api.get<{ templates: PackingTemplate[] }>("/packing-templates")
    ).data.templates;
}
export async function saveItinerary(
    values: ItineraryFormValues,
    updateId?: string,
) {
    await ensureCsrfCookie();
    const body = new FormData();
    const payload = toItineraryPayload(values);
    body.set("payload", JSON.stringify(payload));
    values.plans.forEach((plan) =>
        plan.files.forEach((file) =>
            body.append(`files[${plan.client_id}][]`, file),
        ),
    );
    const route = updateId
        ? `/itineraries/${encodeURIComponent(updateId)}`
        : "/itineraries";
    if (updateId) body.set("_method", "PUT");
    return (await api.post<Itinerary>(route, body)).data;
}
export async function deleteItinerary(id: string) {
    await ensureCsrfCookie();
    await api.delete(`/itineraries/${encodeURIComponent(id)}`);
}
export async function addMember(id: string, email: string) {
    await ensureCsrfCookie();
    return (
        await api.post<
            AddMemberResponse,
            import("axios").AxiosResponse<AddMemberResponse>,
            AddMemberInput
        >(`/itineraries/${encodeURIComponent(id)}/members`, { email })
    ).data.member;
}
export async function removeMember(id: string, memberId: number) {
    await ensureCsrfCookie();
    await api.delete(
        `/itineraries/${encodeURIComponent(id)}/members/${memberId}`,
    );
}
export async function updateViewerShare(id: string, input: ViewerShareInput) {
    await ensureCsrfCookie();
    return (
        await api.put<Itinerary>(
            `/itineraries/${encodeURIComponent(id)}/viewer-share`,
            input,
        )
    ).data;
}
export async function revokeViewerShare(id: string) {
    await ensureCsrfCookie();
    return (
        await api.delete<Itinerary>(
            `/itineraries/${encodeURIComponent(id)}/viewer-share`,
        )
    ).data;
}
export async function getSharedAccess(id: string) {
    return (
        await api.get<SharedAccessStatus>(
            `/itineraries/${encodeURIComponent(id)}/shared-access`,
        )
    ).data;
}
export async function confirmSharedAccess(id: string, shared_password: string) {
    await ensureCsrfCookie();
    return (
        await api.post<Itinerary>(
            `/itineraries/${encodeURIComponent(id)}/shared-access`,
            { shared_password },
        )
    ).data;
}
export function fileUrl(id: string, fileId: number) {
    return `${api.defaults.baseURL}/itineraries/${encodeURIComponent(id)}/files/${fileId}`;
}
