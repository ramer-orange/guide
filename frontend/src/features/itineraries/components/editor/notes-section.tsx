"use client";
import { useId } from "react";
import { useFieldArray, useFormContext } from "react-hook-form";
import { Plus } from "lucide-react";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import {
    SortableFieldList,
    SortableFieldRow,
} from "@/features/itineraries/shared/sortable-field-list";
export function NotesSection({ disabled = false }: { disabled?: boolean }) {
    const labelId = useId();
    const { control, register, getValues } =
        useFormContext<ItineraryFormValues>();
    const { fields, append, remove, move } = useFieldArray({
        control,
        name: "notes",
        keyName: "fieldKey",
    });
    return (
        <ItinerarySection
            eyebrow="KEEP IN MIND"
            title="メモ"
            description="予約番号や、旅先で忘れたくないことを残しておきましょう。"
            action={
                <button
                    type="button"
                    onClick={() =>
                        append({ title: "", text: "", order: fields.length })
                    }
                    className="inline-flex items-center gap-1 rounded-full bg-[#e8f5ef] px-4 py-2 text-xs font-bold text-[#357d73]"
                >
                    <Plus size={16} />
                    メモを追加
                </button>
            }
        >
            {fields.length > 0 ? (
                <SortableFieldList onMove={move} disabled={disabled}>
                    <div className="grid gap-2">
                        {fields.map((field, index) => (
                            <SortableFieldRow
                                key={field.fieldKey}
                                id={`note-${field.fieldKey}`}
                                index={index}
                                disabled={disabled}
                                onRemove={() => remove(index)}
                            >
                                <div className="grid gap-3 pr-20">
                                    <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                                        見出し
                                        <input
                                            className="editor-input"
                                            placeholder="ホテルの予約"
                                            defaultValue={getValues(
                                                `notes.${index}.title`,
                                            )}
                                            {...register(
                                                `notes.${index}.title`,
                                            )}
                                        />
                                    </label>
                                    <div className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                                        <label htmlFor={`${labelId}-${index}`}>
                                            内容
                                        </label>
                                        <textarea
                                            id={`${labelId}-${index}`}
                                            rows={2}
                                            className="editor-input resize-y"
                                            defaultValue={getValues(
                                                `notes.${index}.text`,
                                            )}
                                            {...register(`notes.${index}.text`)}
                                        />
                                    </div>
                                </div>
                            </SortableFieldRow>
                        ))}
                    </div>
                </SortableFieldList>
            ) : (
                <p className="text-sm text-[#758582]">メモはまだありません。</p>
            )}
        </ItinerarySection>
    );
}
