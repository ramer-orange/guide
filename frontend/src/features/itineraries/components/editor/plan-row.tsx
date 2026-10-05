"use client";
import { useFormContext, useFormState } from "react-hook-form";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { SortableFieldRow } from "@/features/itineraries/shared/sortable-field-list";
import { PlanAttachments } from "./plan-attachments";
import { TextInput } from "@/components/ui/text-input";
import { TextArea } from "@/components/ui/text-area";
export function PlanRow({
    index,
    clientId,
    onRemove,
}: {
    index: number;
    clientId: string;
    onRemove: () => void;
}) {
    const { control, register } = useFormContext<ItineraryFormValues>();
    const { errors } = useFormState<ItineraryFormValues>({
        control,
        name: `plans.${index}`,
        exact: false,
    });
    const error = errors.plans?.[index];
    return (
        <SortableFieldRow id={clientId} index={index} onRemove={onRemove}>
            <input type="hidden" {...register(`plans.${index}.client_id`)} />
            <div className="mb-4 grid gap-3 pr-16 sm:grid-cols-[1fr_170px_130px]">
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    予定の名前
                    <TextInput
                        placeholder="清水寺を散策"
                        {...register(`plans.${index}.title`)}
                    />
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    日付
                    <TextInput
                        type="date"
                        {...register(`plans.${index}.date`)}
                    />
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    時間
                    <TextInput
                        type="time"
                        {...register(`plans.${index}.time`)}
                    />
                </label>
            </div>
            <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                場所・メモ
                <TextArea
                    rows={2}
                    placeholder="住所や予約情報など"
                    {...register(`plans.${index}.content`)}
                />
            </label>
            {error && (
                <p role="alert" className="mt-2 text-xs text-rose-700">
                    {error.title?.message ??
                        error.content?.message ??
                        error.files?.message}
                </p>
            )}
            <PlanAttachments index={index} />
        </SortableFieldRow>
    );
}
