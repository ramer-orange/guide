"use client";
import { useFieldArray, useFormContext } from "react-hook-form";
import { Plus } from "lucide-react";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import {
    SortableFieldList,
    SortableFieldRow,
} from "@/features/itineraries/shared/sortable-field-list";
export function SouvenirsSection({ disabled = false }: { disabled?: boolean }) {
    const { control, register } = useFormContext<ItineraryFormValues>();
    const { fields, append, remove, move } = useFieldArray({
        control,
        name: "souvenirs",
        keyName: "fieldKey",
    });
    return (
        <ItinerarySection
            eyebrow="LITTLE MEMORIES"
            title="お土産リスト"
            description="買いたいものや渡したい人をメモしておけます。"
            action={
                <button
                    type="button"
                    onClick={() =>
                        append({
                            name: "",
                            is_checked: false,
                            order: fields.length,
                        })
                    }
                    className="inline-flex items-center gap-1 rounded-full bg-[#fff1ec] px-4 py-2 text-xs font-bold text-[#bf7460]"
                >
                    <Plus size={16} />
                    追加
                </button>
            }
        >
            {fields.length > 0 ? (
                <SortableFieldList onMove={move} disabled={disabled}>
                    <div className="grid gap-2">
                        {fields.map((field, index) => (
                            <SortableFieldRow
                                key={field.fieldKey}
                                id={`souvenir-${field.fieldKey}`}
                                index={index}
                                disabled={disabled}
                                onRemove={() => remove(index)}
                            >
                                <div className="flex items-center gap-3 pr-20">
                                    <input
                                        type="checkbox"
                                        aria-label={`${index + 1}番目のお土産を購入済みにする`}
                                        className="size-5 accent-[#db907b]"
                                        {...register(
                                            `souvenirs.${index}.is_checked`,
                                        )}
                                    />
                                    <input
                                        className="editor-input"
                                        placeholder="八つ橋（祖母へ）"
                                        {...register(`souvenirs.${index}.name`)}
                                    />
                                </div>
                            </SortableFieldRow>
                        ))}
                    </div>
                </SortableFieldList>
            ) : (
                <p className="text-sm text-[#758582]">
                    お土産はまだありません。
                </p>
            )}
        </ItinerarySection>
    );
}
