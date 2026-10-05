"use client";
import { useFieldArray, useFormContext } from "react-hook-form";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { getPackingTemplates } from "@/features/itineraries/api/itineraries";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import {
    SortableFieldList,
    SortableFieldRow,
} from "@/features/itineraries/shared/sortable-field-list";
import { EmptyState } from "@/components/ui/empty-state";
export function PackingSection() {
    const { control, register, setValue } =
        useFormContext<ItineraryFormValues>();
    const { fields, append, remove, move } = useFieldArray({
        control,
        name: "packing_items",
        keyName: "fieldKey",
    });
    const templates = useQuery({
        queryKey: ["packing-templates"],
        queryFn: getPackingTemplates,
    });
    return (
        <ItinerarySection
            eyebrow="GET READY"
            title="持ち物リスト"
            description="自分用のチェックリストです。メンバーごとにリストが分かれます。"
            action={
                <div className="flex items-center gap-2">
                    <label className="sr-only" htmlFor="packing-template">
                        テンプレート
                    </label>
                    <select
                        id="packing-template"
                        className="rounded-full border border-[#d8e5de] bg-white px-3 py-2 text-xs font-semibold text-[#48655f]"
                        defaultValue=""
                        disabled={templates.isLoading}
                        onChange={(event) => {
                            const template = templates.data?.find(
                                (entry) => entry.type === event.target.value,
                            );
                            if (template) {
                                setValue(
                                    "packing_items",
                                    template.items.map((name, order) => ({
                                        name,
                                        is_checked: false,
                                        order,
                                    })),
                                    { shouldDirty: true },
                                );
                                setValue("template_type", template.type, {
                                    shouldDirty: true,
                                });
                            }
                        }}
                    >
                        <option value="">テンプレート</option>
                        {templates.data?.map((template) => (
                            <option key={template.type} value={template.type}>
                                {template.label}
                            </option>
                        ))}
                    </select>
                    <button
                        type="button"
                        onClick={() =>
                            append({
                                name: "",
                                is_checked: false,
                                order: fields.length,
                            })
                        }
                        className="inline-flex items-center gap-1 rounded-full bg-[#e8f5ef] px-3 py-2 text-xs font-bold text-[#357d73]"
                    >
                        <Plus size={16} />
                        追加
                    </button>
                </div>
            }
        >
            {templates.isError && (
                <p role="status" className="mb-3 text-xs text-amber-800">
                    テンプレートを読み込めませんでした。手入力で追加できます。
                </p>
            )}
            {fields.length > 0 ? (
                <SortableFieldList onMove={move}>
                    <div className="grid gap-2">
                        {fields.map((field, index) => (
                            <SortableFieldRow
                                key={field.fieldKey}
                                id={`packing-${field.fieldKey}`}
                                index={index}
                                onRemove={() => remove(index)}
                            >
                                <div className="flex items-center gap-3 pr-20">
                                    <input
                                        type="checkbox"
                                        aria-label={`${index + 1}番目の持ち物を準備済みにする`}
                                        className="size-5 accent-[#198e82]"
                                        {...register(
                                            `packing_items.${index}.is_checked`,
                                        )}
                                    />
                                    <input
                                        className="editor-input"
                                        placeholder="持ち物"
                                        {...register(
                                            `packing_items.${index}.name`,
                                        )}
                                    />
                                </div>
                            </SortableFieldRow>
                        ))}
                    </div>
                </SortableFieldList>
            ) : (
                <EmptyState
                    title="持ち物はまだありません"
                    description="テンプレートや手入力で追加できます。"
                />
            )}
        </ItinerarySection>
    );
}
