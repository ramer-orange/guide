"use client";
import { useFieldArray, useFormContext, useFormState } from "react-hook-form";
import { Plus } from "lucide-react";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import { SortableFieldList } from "@/features/itineraries/shared/sortable-field-list";
import { PlanRow } from "./plan-row";
import { emptyPlan } from "@/features/itineraries/utils/itinerary-form";
import { EmptyState } from "@/components/ui/empty-state";

export function PlansSection({ disabled = false }: { disabled?: boolean }) {
    const { control } = useFormContext<ItineraryFormValues>();
    const { errors } = useFormState({ control, name: "plans" });
    const { fields, append, remove, move } = useFieldArray({
        control,
        name: "plans",
        keyName: "fieldKey",
    });
    return (
        <ItinerarySection
            eyebrow="DAY BY DAY"
            title="旅のプラン"
            description="日付や時間を入れて、当日の流れを並べましょう。"
            action={
                <button
                    type="button"
                    onClick={() => append(emptyPlan())}
                    className="inline-flex items-center gap-1 rounded-full bg-[#e8f5ef] px-4 py-2 text-xs font-bold text-[#357d73] hover:bg-[#d9efe5]"
                >
                    <Plus size={16} />
                    予定を追加
                </button>
            }
        >
            <SortableFieldList onMove={move} disabled={disabled}>
                <div className="grid gap-3">
                    {fields.map((field, index) => (
                        <PlanRow
                            key={field.fieldKey}
                            clientId={field.client_id}
                            index={index}
                            disabled={disabled}
                            onRemove={() => remove(index)}
                        />
                    ))}
                </div>
            </SortableFieldList>
            {fields.length === 0 && (
                <EmptyState
                    title="予定はまだありません"
                    description="「予定を追加」から旅の予定を作成できます。"
                />
            )}
            {errors.plans?.root?.message && (
                <p role="alert" className="mt-3 text-xs text-rose-700">
                    {errors.plans.root.message}
                </p>
            )}
        </ItinerarySection>
    );
}
