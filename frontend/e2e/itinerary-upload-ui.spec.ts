import { readFileSync } from "node:fs";
import { expect, test, type Browser } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixture = JSON.parse(
    readFileSync(
        process.env.E2E_FIXTURE_PATH ?? "../storage/app/e2e-fixture.json",
        "utf8",
    ),
) as {
    ownerCookie: {
        name: string;
        value: string;
        path: string;
        httpOnly: boolean;
        secure: boolean;
        sameSite: "Lax";
    };
};

async function ownerPage(browser: Browser) {
    const context = await browser.newContext();
    await context.addCookies([
        { ...fixture.ownerCookie, domain: new URL(baseURL).hostname },
    ]);
    return { context, page: await context.newPage() };
}

test("uploads an attachment in the editor, displays it, and downloads it", async ({
    browser,
}) => {
    const { context, page } = await ownerPage(browser);
    const pdf = Buffer.from("%PDF-1.7\nsmall upload from the editor\n");
    const png = Buffer.from(
        "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pXcAAAAASUVORK5CYII=",
        "base64",
    );

    try {
        await page.goto("/itineraries/create");
        await page.getByLabel("しおりのタイトル").fill("添付アップロード確認");
        await page
            .getByLabel("予定にファイルを添付")
            .first()
            .setInputFiles({
                name: "receipt.pdf",
                mimeType: "application/pdf",
                buffer: pdf,
            });
        await expect(
            page.getByRole("button", { name: "しおりを保存" }),
        ).toBeEnabled();
        await expect(page.getByText("receipt.pdf")).toBeVisible();
        await page.getByRole("button", { name: "予定を追加" }).click();
        await page
            .getByLabel("予定にファイルを添付")
            .nth(1)
            .setInputFiles({
                name: "map.png",
                mimeType: "image/png",
                buffer: png,
            });
        await expect(
            page.getByRole("button", { name: "しおりを保存" }),
        ).toBeEnabled();
        await expect(page.getByText("map.png")).toBeVisible();

        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page).toHaveURL(/\/itineraries\/[^/]+\/edit$/);
        for (const [name, contentType, expectedContent] of [
            ["receipt.pdf", "application/pdf", pdf],
            ["map.png", "image/png", png],
        ] as const) {
            const fileLink = page.getByRole("link", {
                name: `${name}をダウンロード`,
            });
            await expect(fileLink).toBeVisible();
            const fileURL = await fileLink.getAttribute("href");
            expect(fileURL).toBeTruthy();
            const response = await page.request.get(
                new URL(fileURL!, baseURL).toString(),
            );
            expect(response.status()).toBe(200);
            expect(response.headers()["content-type"]).toContain(contentType);
            expect(response.headers()["content-disposition"]).toMatch(
                /^attachment;/,
            );
            expect(await response.body()).toEqual(expectedContent);
        }
    } finally {
        await context.close();
    }
});

test("keeps the editor save control in flow and email text clear of its icon", async ({
    browser,
}) => {
    const { context, page } = await ownerPage(browser);
    try {
        await page.goto(`/itineraries/${fixture.travelId}/edit`);
        const email = page.getByLabel("メンバーのメールアドレス");
        await expect(email).toBeVisible();
        const inputMetrics = await email.evaluate((input) => {
            const icon = input.parentElement?.querySelector("svg");
            const inputRect = input.getBoundingClientRect();
            const iconRect = icon?.getBoundingClientRect();
            return {
                paddingLeft: getComputedStyle(input).paddingLeft,
                textStart: inputRect.left + Number.parseFloat(getComputedStyle(input).paddingLeft),
                iconEnd: iconRect?.right ?? 0,
            };
        });
        expect(inputMetrics.paddingLeft).toBe("40px");
        expect(inputMetrics.iconEnd + 4).toBeLessThan(inputMetrics.textStart);

        const saveButton = page.getByRole("button", { name: "しおりを保存" });
        await expect(saveButton).toBeVisible();
        await expect(saveButton).toHaveCSS("position", "static");
    } finally {
        await context.close();
    }
});
