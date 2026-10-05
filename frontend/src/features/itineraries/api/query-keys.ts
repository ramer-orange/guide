export const itineraryKeys = {
    all: ["itineraries"] as const,
    list: (principal: string | number) =>
        ["itineraries", principal, "list"] as const,
    detail: (id: string, principal: string | number) =>
        ["itinerary", id, principal] as const,
    byId: (id: string) => ["itinerary", id] as const,
    templates: ["packing-templates"] as const,
    sharedAccess: (id: string) => ["shared-access", id] as const,
};
