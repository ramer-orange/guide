import { readFileSync } from "node:fs";
import { expect, test } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixture = JSON.parse(
    readFileSync(
        process.env.E2E_FIXTURE_PATH ?? "../storage/app/e2e-fixture.json",
        "utf8",
    ),
);

test("itinerary pages render data without JavaScript and isolate sessions", async ({
    browser,
}) => {
    for (const [cookie, ownItem, otherItem] of [
        [fixture.ownerCookie, "Owner passport", "Member medicine"],
        [fixture.memberCookie, "Member medicine", "Owner passport"],
        [fixture.ownerCookie, "Owner passport", "Member medicine"],
    ] as const) {
        const context = await browser.newContext({ javaScriptEnabled: false });
        try {
            await context.addCookies([
                { ...cookie, domain: new URL(baseURL).hostname },
            ]);
            const page = await context.newPage();
            const response = await page.goto("/itineraries/index");
            expect(response?.headers()["cache-control"]).toContain("no-store");
            await expect(
                page.getByRole("heading", { name: "E2E Trip", exact: true }),
            ).toBeVisible();
            await expect(
                page.getByRole("link", { name: "マイページ" }).first(),
            ).toBeVisible();
            await page.goto(`/itineraries/${fixture.travelId}/edit`);
            await expect(page.getByLabel("しおりのタイトル")).toHaveValue(
                "E2E Trip",
            );
            await expect(page.getByPlaceholder("持ち物")).toHaveValue(ownItem);
            expect(await page.content()).not.toContain(otherItem);
            await page.goto("/itineraries/create");
            await expect(
                page.getByRole("heading", { name: "旅のしおりを作る" }),
            ).toBeVisible();
            await expect(
                page.getByLabel("テンプレート").locator("option"),
            ).not.toHaveCount(1);
        } finally {
            await context.close();
        }
    }
});

test("guest receives the sharing form and access errors without JavaScript", async ({
    browser,
}) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    try {
        const page = await context.newPage();
        await page.goto(`/itineraries/${fixture.travelId}/shared-access`);
        await expect(page.getByLabel("共有パスワード")).toBeVisible();
        await expect(
            page.getByRole("heading", { name: "E2E Trip" }),
        ).toBeVisible();
        await page.goto(`/itineraries/${fixture.travelId}/edit`);
        await expect(
            page.getByRole("link", { name: "共有パスワードを入力" }),
        ).toBeVisible();
        expect(await page.content()).not.toContain("Owner passport");
        await page.goto("/itineraries/missing-ssr-itinerary/edit");
        await expect(page.getByRole("alert")).toHaveText(
            "しおりが見つからないか、閲覧権限がありません。",
        );
    } finally {
        await context.close();
    }
});

test("hydration reuses server data and editing works without initial browser API reads", async ({
    browser,
}) => {
    const context = await browser.newContext();
    const errors: string[] = [];
    const reads: string[] = [];
    try {
        await context.addCookies([
            { ...fixture.ownerCookie, domain: new URL(baseURL).hostname },
        ]);
        await context.route("**/api/v1/**", async (route) => {
            if (route.request().method() === "GET") {
                reads.push(route.request().url());
                await route.abort();
            } else {
                await route.continue();
            }
        });
        const page = await context.newPage();
        page.on("pageerror", (error) => errors.push(error.message));
        await page.goto(`/itineraries/${fixture.travelId}/edit`);
        await expect(page.getByLabel("しおりのタイトル")).toHaveValue(
            "E2E Trip",
        );
        const plans = await page.getByLabel("予定の名前").count();
        await page.getByRole("button", { name: "予定を追加" }).click();
        await expect(page.getByLabel("予定の名前")).toHaveCount(plans + 1);
        expect(reads).toEqual([]);
        expect(errors).toEqual([]);
    } finally {
        await context.close();
    }
});
