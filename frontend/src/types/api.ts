import type { components, operations } from "./api.generated";

export type Session = components["schemas"]["Session"];
export type User = NonNullable<Session["user"]>;
export type Itinerary = components["schemas"]["Itinerary"];
export type ItinerarySave = components["schemas"]["ItinerarySave"];
export type ItinerarySummary = components["schemas"]["ItinerarySummary"];
export type ChecklistItem = components["schemas"]["ChecklistItem"];
export type Plan = Itinerary["plans"][number];
export type Attachment = Plan["files"][number];
export type Member = components["schemas"]["Member"];
export type Permissions = Itinerary["permissions"];
export type PackingTemplate =
    operations["listPackingTemplates"]["responses"][200]["content"]["application/json"]["templates"][number];
export type SharedAccessStatus =
    operations["getSharedAccessStatus"]["responses"][200]["content"]["application/json"];
export type ViewerShareInput =
    operations["setViewerShare"]["requestBody"]["content"]["application/json"];
export type AddMemberInput =
    operations["addMember"]["requestBody"]["content"]["application/json"];
export type AddMemberResponse =
    operations["addMember"]["responses"][201]["content"]["application/json"];
export type ItineraryListResponse =
    operations["listItineraries"]["responses"][200]["content"]["application/json"];
