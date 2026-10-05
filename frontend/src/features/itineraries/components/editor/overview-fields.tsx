"use client";
import { useFormContext } from "react-hook-form";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";
import { ItinerarySection } from "@/features/itineraries/shared/itinerary-section";
import { TextInput } from "@/components/ui/text-input";
import { TextArea } from "@/components/ui/text-area";
export function OverviewFields() {
    const {
        register,
        formState: { errors },
    } = useFormContext<ItineraryFormValues>();
    return (
        <ItinerarySection
            eyebrow="YOUR JOURNEY"
            title="旅の概要"
            description="タイトルと旅のテーマを共有しましょう。"
        >
            <div className="grid gap-4">
                <label className="grid gap-2 text-sm font-semibold text-[#526969]">
                    しおりのタイトル
                    <TextInput
                        placeholder="例：春の京都めぐり"
                        {...register("title")}
                    />
                    {errors.title && (
                        <span role="alert" className="text-xs text-rose-700">
                            {errors.title.message}
                        </span>
                    )}
                </label>
                <label className="grid gap-2 text-sm font-semibold text-[#526969]">
                    旅行概要
                    <TextArea
                        rows={3}
                        placeholder="旅の目的や、みんなに伝えたいこと"
                        {...register("overview_text")}
                    />
                </label>
            </div>
        </ItinerarySection>
    );
}
