"use client";
import { FormProvider, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ArrowLeft, Check, LoaderCircle, Save } from "lucide-react";
import type { MouseEvent as ReactMouseEvent } from "react";
import { normalizeApiError } from "@/lib/api/errors";
import { useSaveItinerary } from "@/features/itineraries/hooks/use-save-itinerary";
import {
    itineraryFormSchema,
    type ItineraryFormValues,
} from "@/features/itineraries/schemas/itinerary-form.schema";
import {
    defaultItineraryValues,
    toItineraryFormValues,
} from "@/features/itineraries/utils/itinerary-form";
import type { Itinerary } from "@/types/api";
import { OverviewFields } from "./overview-fields";
import { PlansSection } from "./plans-section";
import { PackingSection } from "./packing-section";
import { SouvenirsSection } from "./souvenirs-section";
import { NotesSection } from "./notes-section";
import { MemberManagement } from "@/features/itineraries/components/sharing/member-management";
import { ViewerShareManagement } from "@/features/itineraries/components/sharing/viewer-share-management";
import { useUnsavedChangesGuard } from "@/features/itineraries/hooks/use-unsaved-changes-guard";

export function ItineraryEditor({
    itinerary,
    id,
}: {
    itinerary?: Itinerary;
    id?: string;
}) {
    const router = useRouter();
    const [initialValues] = useState(() =>
        itinerary ? toItineraryFormValues(itinerary) : defaultItineraryValues(),
    );
    const form = useForm<ItineraryFormValues>({
        resolver: zodResolver(itineraryFormSchema),
        defaultValues: initialValues,
        mode: "onSubmit",
    });
    const {
        formState: { isDirty, isSubmitting },
        handleSubmit,
    } = form;
    const mutation = useSaveItinerary(id, form);
    const { isSuccess, isPending, isError, error, reset: resetSave } = mutation;
    useUnsavedChangesGuard(isDirty);
    useEffect(() => {
        if (isDirty && isSuccess) resetSave();
    }, [isDirty, isSuccess, resetSave]);
    const backToList = (event: ReactMouseEvent<HTMLButtonElement>) => {
        router.push("/itineraries/index");
        event.preventDefault();
    };
    const submit = handleSubmit(async (values) => {
        try {
            const saved = await mutation.mutateAsync(values);
            if (!id)
                router.push(
                    `/itineraries/${encodeURIComponent(saved.id)}/edit`,
                );
        } catch (error) {
            void error;
        }
    });
    return (
        <FormProvider {...form}>
            <div className="mx-auto grid max-w-4xl gap-5">
                <form onSubmit={submit} className="grid gap-5">
                    <div className="mb-1 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[.2em] text-[#53a39b]">
                                PLAGINE · TRAVEL NOTE
                            </p>
                            <h1 className="text-3xl font-extrabold tracking-tight text-[#203f45] sm:text-4xl">
                                {id ? "しおりを編集" : "旅のしおりを作る"}
                            </h1>
                            <p className="mt-2 text-sm text-[#72817f]">
                                旅の予定を、みんなで形にしていきましょう。
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={backToList}
                            className="inline-flex items-center gap-2 rounded-full border border-[#dce6e0] px-4 py-2 text-sm font-semibold text-[#60716e]"
                        >
                            <ArrowLeft size={16} />
                            一覧へ戻る
                        </button>
                    </div>
                    {isSuccess && (
                        <p
                            role="status"
                            className="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"
                        >
                            <Check size={16} />
                            しおりを保存しました。
                        </p>
                    )}
                    {isError && (
                        <p
                            role="alert"
                            className="rounded-xl bg-rose-50 p-3 text-sm text-rose-800"
                        >
                            {normalizeApiError(error).message}
                        </p>
                    )}
                    <OverviewFields />
                    <PlansSection />
                    <PackingSection />
                    <SouvenirsSection />
                    <NotesSection />
                    <div className="sticky bottom-3 z-20 flex justify-end rounded-2xl border border-[#dce7df] bg-[#fffdfa]/95 p-3 shadow-lg backdrop-blur">
                        <button
                            type="submit"
                            disabled={isSubmitting || isPending}
                            className="inline-flex min-h-12 items-center gap-2 rounded-full bg-[#208d83] px-6 text-sm font-bold text-white shadow-md shadow-[#208d83]/20 transition hover:bg-[#16786f] disabled:cursor-wait disabled:opacity-60"
                        >
                            {isPending ? (
                                <LoaderCircle
                                    className="animate-spin"
                                    size={18}
                                />
                            ) : (
                                <Save size={18} />
                            )}
                            {isPending ? "保存中…" : "しおりを保存"}
                        </button>
                    </div>
                </form>
                {itinerary && (
                    <>
                        <MemberManagement itinerary={itinerary} />
                        <ViewerShareManagement itinerary={itinerary} />
                    </>
                )}
            </div>
        </FormProvider>
    );
}
