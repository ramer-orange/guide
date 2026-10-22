"use client";
import { useId } from "react";
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
    disabled = false,
}: {
    index: number;
    clientId: string;
    onRemove: () => void;
    disabled?: boolean;
}) {
    const contentId = useId();
    const { control, register, getValues } =
        useFormContext<ItineraryFormValues>();
    const { errors } = useFormState<ItineraryFormValues>({
        control,
        name: `plans.${index}`,
        exact: false,
    });
    const error = errors.plans?.[index];
    return (
        <SortableFieldRow
            id={clientId}
            index={index}
            onRemove={onRemove}
            disabled={disabled}
        >
            <input type="hidden" {...register(`plans.${index}.client_id`)} />
            <div className="mb-4 grid gap-3 pr-16 sm:grid-cols-[1fr_170px_130px]">
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    予定の名前
                    <TextInput
                        placeholder="清水寺を散策"
                        defaultValue={getValues(`plans.${index}.title`)}
                        {...register(`plans.${index}.title`)}
                    />
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    日付
                    <TextInput
                        type="date"
                        defaultValue={getValues(`plans.${index}.date`)}
                        {...register(`plans.${index}.date`)}
                    />
                </label>
                <label className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                    時間
                    <TextInput
                        type="time"
                        defaultValue={getValues(`plans.${index}.time`)}
                        {...register(`plans.${index}.time`)}
                    />
                </label>
            </div>
            <div className="grid gap-1.5 text-xs font-semibold text-[#657774]">
                <label htmlFor={contentId}>場所・メモ</label>
                <TextArea
                    id={contentId}
                    rows={2}
                    placeholder="住所や予約情報など"
                    defaultValue={getValues(`plans.${index}.content`)}
                    {...register(`plans.${index}.content`)}
                />
            </div>
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
