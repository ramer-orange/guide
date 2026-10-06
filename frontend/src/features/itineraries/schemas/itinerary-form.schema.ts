import { z } from "zod";

const fileSchema = z
    .instanceof(File)
    .refine(
        (file) => file.size <= 10 * 1024 * 1024,
        "ファイルは10MB以下にしてください。",
    )
    .refine(
        (file) => /\.(jpe?g|png|pdf|docx?)$/i.test(file.name),
        "JPEG、PNG、PDF、Word形式を選択してください。",
    );
const rowName = z.string().max(255, "255文字以内で入力してください。");
export const itineraryFormSchema = z.object({
    revision: z.number().int().nonnegative(),
    title: z
        .string()
        .trim()
        .min(1, "タイトルを入力してください。")
        .max(255, "255文字以内で入力してください。"),
    overview_text: z.string(),
    template_type: z.string().nullable(),
    plans: z.array(
        z.object({
            client_id: z.string(),
            id: z.number().optional(),
            date: z.string(),
            time: z.string(),
            title: rowName,
            content: z.string(),
            order: z.number(),
            files: z.array(fileSchema),
            existing_files: z.array(
                z.object({
                    id: z.number(),
                    file_name: z.string(),
                    url: z.string(),
                    preview_url: z.string().nullable().optional(),
                }),
            ),
        }),
    ),
    packing_items: z.array(
        z.object({
            id: z.number().optional(),
            name: rowName,
            is_checked: z.boolean(),
            order: z.number(),
        }),
    ),
    souvenirs: z.array(
        z.object({
            id: z.number().optional(),
            name: rowName,
            is_checked: z.boolean(),
            order: z.number(),
        }),
    ),
    notes: z.array(
        z.object({
            id: z.number().optional(),
            title: rowName,
            text: z.string(),
            order: z.number(),
        }),
    ),
}).superRefine((values, context) => {
    const uploadBytes = values.plans.reduce(
        (total, plan) => total + plan.files.reduce((size, file) => size + file.size, 0),
        0,
    );
    if (uploadBytes > 18 * 1024 * 1024) {
        context.addIssue({
            code: z.ZodIssueCode.custom,
            path: ["plans", "root"],
            message: "添付ファイル全体は18MB以下にしてください。",
        });
    }
});

export type ItineraryFormValues = z.infer<typeof itineraryFormSchema>;
