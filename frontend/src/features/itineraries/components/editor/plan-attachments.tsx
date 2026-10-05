"use client";
import { useRef } from "react";
import { useFormContext, useWatch } from "react-hook-form";
import type { ItineraryFormValues } from "@/features/itineraries/schemas/itinerary-form.schema";

export function PlanAttachments({ index }: { index: number }) {
    const { control, setValue, getFieldState } =
        useFormContext<ItineraryFormValues>();
    const files = useWatch({ control, name: `plans.${index}.files` });
    const existing = useWatch({
        control,
        name: `plans.${index}.existing_files`,
    });
    const inputRef = useRef<HTMLInputElement>(null);
    const error = getFieldState(`plans.${index}.files`).error;
    return (
        <div className="mt-4">
            <div className="flex flex-wrap items-center gap-2">
                <input
                    ref={inputRef}
                    type="file"
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                    multiple
                    className="sr-only"
                    aria-label="予定にファイルを添付"
                    onChange={(event) => {
                        const selected = Array.from(event.target.files ?? []);
                        setValue(
                            `plans.${index}.files`,
                            [...files, ...selected],
                            { shouldValidate: true, shouldDirty: true },
                        );
                        event.target.value = "";
                    }}
                />
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="rounded-full border border-[#bfd9d0] px-3 py-1.5 text-xs font-semibold text-[#347870] hover:bg-[#eff8f3]"
                >
                    ＋ 添付ファイル
                </button>
                <span className="text-xs text-[#899693]">
                    JPEG・PNG・PDF・Word／1件10MBまで
                </span>
            </div>
            {error && (
                <p role="alert" className="mt-2 text-xs text-rose-700">
                    {error.message}
                </p>
            )}
            {(existing.length > 0 || files.length > 0) && (
                <ul className="mt-3 flex flex-wrap gap-2">
                    {existing.map((file, fileIndex) => (
                        <li
                            key={file.id}
                            className="flex items-center gap-2 rounded-full bg-[#edf5f1] px-3 py-1.5 text-xs text-[#46625e]"
                        >
                            <a
                                href={file.url}
                                target="_blank"
                                rel="noreferrer"
                                className="max-w-48 truncate underline"
                            >
                                {file.file_name}
                            </a>
                            <button
                                type="button"
                                aria-label={`${file.file_name}を添付から外す`}
                                onClick={() =>
                                    setValue(
                                        `plans.${index}.existing_files`,
                                        existing.filter(
                                            (_, i) => i !== fileIndex,
                                        ),
                                        { shouldDirty: true },
                                    )
                                }
                            >
                                ×
                            </button>
                        </li>
                    ))}
                    {files.map((file, fileIndex) => (
                        <li
                            key={`${file.name}-${file.lastModified}`}
                            className="flex items-center gap-2 rounded-full bg-[#eef6fb] px-3 py-1.5 text-xs text-[#466577]"
                        >
                            <span className="max-w-48 truncate">
                                {file.name}
                            </span>
                            <button
                                type="button"
                                aria-label={`${file.name}を添付から外す`}
                                onClick={() =>
                                    setValue(
                                        `plans.${index}.files`,
                                        files.filter((_, i) => i !== fileIndex),
                                        { shouldDirty: true },
                                    )
                                }
                            >
                                ×
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
