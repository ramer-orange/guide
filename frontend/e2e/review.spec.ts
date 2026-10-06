import { readFileSync } from "node:fs";
import { expect, test, type Browser } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixture = JSON.parse(
    readFileSync(process.env.E2E_FIXTURE_PATH ?? "../storage/app/e2e-fixture.json", "utf8"),
) as { travelId: string; ownerCookie: { name: string; value: string; path: string; httpOnly: boolean; secure: boolean; sameSite: "Lax" } };

function pdfBuffer(size: number) {
    const content = Buffer.alloc(size);
    Buffer.from("%PDF-1.7\n").copy(content);
    return content;
}

async function ownerPage(browser: Browser) {
    const context = await browser.newContext();
    await context.addCookies([{ ...fixture.ownerCookie, domain: new URL(baseURL).hostname }]);
    return { context, page: await context.newPage() };
}

test("delayed save locks all editor operations and a 409 preserves input for retry", async ({ browser }) => {
    const { context, page } = await ownerPage(browser);
    let release!: () => void;
    let intercepted!: () => void;
    const reached = new Promise<void>((resolve) => (intercepted = resolve));
    const gate = new Promise<void>((resolve) => (release = resolve));
    await page.route(`**/api/v1/itineraries/${fixture.travelId}`, async (route) => {
        if (route.request().method() !== "POST") return route.continue();
        intercepted();
        await gate;
        await route.fulfill({
            status: 409,
            contentType: "application/json",
            body: JSON.stringify({ message: "他のメンバーが先にしおりを更新しました。入力内容をコピーしてから再読み込みしてください。" }),
        });
    });
    try {
        await page.goto(`/itineraries/${fixture.travelId}/edit`);
        const title = page.getByLabel("しおりのタイトル");
        await title.fill("遅延中も保持する入力");
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await reached;
        await expect(title).toBeDisabled();
        await expect(page.getByRole("button", { name: "予定を追加" })).toBeDisabled();
        await expect(page.getByRole("button", { name: "この行を削除" }).first()).toBeDisabled();
        await expect(page.getByRole("button", { name: "ドラッグして順序を変更" }).first()).toBeDisabled();
        await expect(page.getByLabel("予定にファイルを添付").first()).toBeDisabled();
        await expect(page.getByRole("button", { name: /保存中/ })).toBeDisabled();
        release();
        await expect(page.getByRole("alert").filter({ hasText: "他のメンバーが先に" })).toBeVisible();
        await expect(title).toHaveValue("遅延中も保持する入力");
        await expect(title).toBeEnabled();
        await title.fill("失敗後も再編集できる");
        await page.getByRole("button", { name: "予定を追加" }).click();
        await expect(page.getByLabel("予定の名前")).toHaveCount(3);
    } finally {
        release();
        await context.close();
    }
});

test("11 MiB PNG and aggregate over 18 MiB show errors without POST", async ({ browser }) => {
    const { context, page } = await ownerPage(browser);
    let writes = 0;
    await page.route("**/api/v1/itineraries", async (route) => {
        if (route.request().method() === "POST") writes += 1;
        await route.continue();
    });
    try {
        await page.goto("/itineraries/create");
        await page.getByLabel("しおりのタイトル").fill("11MiB file");
        await page.getByLabel("予定にファイルを添付").first().setInputFiles({
            name: "large.png", mimeType: "image/png", buffer: Buffer.alloc(11 * 1024 * 1024),
        });
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page.getByRole("alert").filter({ hasText: "ファイルは10MB以下" })).toBeVisible();
        expect(writes).toBe(0);

        await page.reload();
        await page.getByLabel("しおりのタイトル").fill("Aggregate limit");
        await page.getByRole("button", { name: "予定を追加" }).click();
        await page.getByRole("button", { name: "予定を追加" }).click();
        const inputs = page.getByLabel("予定にファイルを添付");
        await inputs.nth(0).setInputFiles({ name: "a.pdf", mimeType: "application/pdf", buffer: pdfBuffer(9 * 1024 * 1024) });
        await inputs.nth(1).setInputFiles({ name: "b.pdf", mimeType: "application/pdf", buffer: pdfBuffer(9 * 1024 * 1024) });
        await inputs.nth(2).setInputFiles({ name: "c.pdf", mimeType: "application/pdf", buffer: pdfBuffer(16) });
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page.getByRole("alert").filter({ hasText: "添付ファイル全体は18MB以下" })).toBeVisible();
        expect(writes).toBe(0);
        await page.getByRole("button", { name: "c.pdfを添付から外す" }).click();
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page).toHaveURL(/\/itineraries\/[^/]+\/edit$/);
        expect(writes).toBe(1);
    } finally {
        await context.close();
    }
});

test("API rejects aggregate uploads over 18 MiB and accepts exactly 18 MiB", async ({ browser }) => {
    const { context, page } = await ownerPage(browser);
    try {
        await page.goto("/itineraries/create");
        const result = await page.evaluate(async () => {
            await fetch("/sanctum/csrf-cookie", { credentials: "same-origin" });
            const xsrf = decodeURIComponent(document.cookie.split("; ").find((part) => part.startsWith("XSRF-TOKEN="))?.split("=").slice(1).join("=") ?? "");
            async function create(sizes: number[], prefix: string) {
                const payload = {
                    title: prefix,
                    overview_text: "",
                    template_type: null,
                    plans: sizes.map((_, order) => ({ client_id: `${prefix}-${order}`, date: "", time: "", title: "", content: "", order, existing_file_ids: [] })),
                    packing_items: [], souvenirs: [], notes: [],
                };
                const body = new FormData();
                body.set("payload", JSON.stringify(payload));
                sizes.forEach((size, order) => {
                    const content = new Uint8Array(size);
                    content.set(new TextEncoder().encode("%PDF-1.7\n"));
                    body.append(`files[${prefix}-${order}][]`, new File([content], `${prefix}-${order}.pdf`, { type: "application/pdf" }));
                });
                const response = await fetch("/api/v1/itineraries", { method: "POST", credentials: "same-origin", headers: { Accept: "application/json", "X-XSRF-TOKEN": xsrf }, body });
                return { status: response.status, body: await response.json() };
            }
            return {
                over: await create([9 * 1024 * 1024, 9 * 1024 * 1024, 16], "api-over-limit"),
                atLimit: await create([9 * 1024 * 1024, 9 * 1024 * 1024], "api-at-limit"),
            };
        });
        expect(result.over.status).toBe(422);
        expect(result.over.body.errors.files[0]).toContain("添付ファイル全体は18MB以下");
        expect(result.atLimit.status).toBe(201);
        expect(result.atLimit.body.plans.flatMap((plan: { files: unknown[] }) => plan.files)).toHaveLength(2);
    } finally {
        await context.close();
    }
});
