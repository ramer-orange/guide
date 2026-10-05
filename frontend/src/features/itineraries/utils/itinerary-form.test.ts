import { describe, expect, it } from "vitest";
import {
    defaultItineraryValues,
    emptyPlan,
    toItineraryPayload,
} from "./itinerary-form";

describe("itinerary form conversion", () => {
    it("preserves explicit empty collections so full replacement can clear them", () => {
        const values = defaultItineraryValues();
        values.plans = [];
        values.packing_items = [];
        values.souvenirs = [];
        values.notes = [];

        expect(toItineraryPayload(values)).toMatchObject({
            plans: [],
            packing_items: [],
            souvenirs: [],
            notes: [],
        });
    });

    it("keeps stable plan correlation, existing attachment IDs, and false checklist values", () => {
        const values = defaultItineraryValues();
        values.plans = [
            {
                ...emptyPlan(),
                id: 45,
                client_id: "row_client_1",
                existing_files: [
                    { id: 9, file_name: "ticket.pdf", url: "/files/9" },
                ],
            },
        ];
        values.packing_items = [
            { id: 12, name: "Passport", is_checked: false, order: 99 },
        ];

        const payload = toItineraryPayload(values);
        expect(payload.plans[0]).toMatchObject({
            id: 45,
            client_id: "row_client_1",
            existing_file_ids: [9],
            order: 0,
        });
        expect(payload.plans[0]).not.toHaveProperty("files");
        expect(payload.packing_items[0]).toMatchObject({
            id: 12,
            is_checked: false,
            order: 0,
        });
        expect(payload).not.toHaveProperty("viewer_share_expires_at");
        expect(payload).not.toHaveProperty("shared_password");
        expect(payload).not.toHaveProperty("shared_password_confirmation");
    });
});
