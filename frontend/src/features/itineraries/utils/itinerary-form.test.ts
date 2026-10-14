import { describe, expect, it } from "vitest";
import {
    defaultItineraryValues,
    emptyPlan,
    toItineraryPayload,
    toItineraryChanges,
} from "./itinerary-form";

describe("itinerary form conversion", () => {
    it("preserves empty collections in the creation payload", () => {
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

function savedValues() {
    const values = defaultItineraryValues();
    values.title = "Original";
    values.plans = [
        {
            ...emptyPlan(),
            id: 1,
            client_id: "plan-1",
            title: "First",
            order: 0,
        },
        {
            ...emptyPlan(),
            id: 2,
            client_id: "plan-2",
            title: "Second",
            content: "Original content",
            order: 1,
            existing_files: [
                { id: 10, file_name: "old.pdf", url: "/files/10" },
                { id: 11, file_name: "keep.pdf", url: "/files/11" },
            ],
        },
    ];
    values.packing_items = [{ id: 3, name: "Bag", is_checked: true, order: 0 }];
    values.souvenirs = [{ id: 4, name: "Tea", is_checked: true, order: 0 }];
    values.notes = [{ id: 5, title: "Hotel", text: "Old", order: 0 }];
    return values;
}

describe("itinerary change payload", () => {
    it("omits unchanged fields and sends only a changed field on an existing row", () => {
        const baseline = savedValues();
        const values = structuredClone(baseline);
        expect(toItineraryChanges(values, baseline)).toEqual({});
        values.plans[1].content = "New content";
        values.packing_items[0].is_checked = false;
        values.notes[0].text = "";
        expect(toItineraryChanges(values, baseline)).toEqual({
            plans: [{ id: 2, client_id: "plan-2", content: "New content" }],
            packing_items: [{ id: 3, is_checked: false }],
            notes: [{ id: 5, text: "" }],
        });
    });

    it("sends explicit row and attachment deletions without a retain list", () => {
        const baseline = savedValues();
        const values = structuredClone(baseline);
        values.plans.shift();
        values.plans[0].existing_files.shift();
        values.packing_items = [];
        values.souvenirs = [];
        values.notes = [];
        expect(toItineraryChanges(values, baseline)).toEqual({
            plans: [
                {
                    id: 2,
                    client_id: "plan-2",
                    order: 0,
                    removed_file_ids: [10],
                },
            ],
            deleted_plan_ids: [1],
            deleted_packing_item_ids: [3],
            deleted_souvenir_ids: [4],
            deleted_note_ids: [5],
        });
    });

    it("includes uploads even when existing plan fields are unchanged and includes new rows", () => {
        const baseline = savedValues();
        const values = structuredClone(baseline);
        values.plans[0].files = [
            new File(["pdf"], "new.pdf", { type: "application/pdf" }),
        ];
        values.plans.push({
            ...emptyPlan(),
            client_id: "new-plan",
            title: "Added",
        });
        values.souvenirs.push({ name: "New", is_checked: false, order: 0 });
        const changes = toItineraryChanges(values, baseline);
        expect(changes.plans).toEqual([
            { id: 1, client_id: "plan-1" },
            {
                client_id: "new-plan",
                title: "Added",
                content: "",
                date: "",
                time: "",
                order: 2,
            },
        ]);
        expect(changes.souvenirs).toEqual([
            { name: "New", is_checked: false, order: 1 },
        ]);
    });

    it("sends reordered IDs while leaving content and attachments untouched", () => {
        const baseline = savedValues();
        const values = structuredClone(baseline);
        values.plans.reverse();
        expect(toItineraryChanges(values, baseline)).toEqual({
            plans: [
                { id: 2, client_id: "plan-2", order: 0 },
                { id: 1, client_id: "plan-1", order: 1 },
            ],
        });
    });
});
