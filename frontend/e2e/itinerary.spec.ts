import { mkdir } from "node:fs/promises";
import { resolve } from "node:path";
import { readFileSync } from "node:fs";
import { expect, test, type Browser, type Page } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixturePath =
    process.env.E2E_FIXTURE_PATH ?? resolve("../storage/app/e2e-fixture.json");
const fixture = JSON.parse(readFileSync(fixturePath, "utf8")) as {
    travelId: string;
    inviteEmail: string;
    ownerCookie: {
        name: string;
        value: string;
        path: string;
        httpOnly: boolean;
        secure: boolean;
        sameSite: "Lax";
    };
    memberCookie: {
        name: string;
        value: string;
        path: string;
        httpOnly: boolean;
        secure: boolean;
        sameSite: "Lax";
    };
    viewerPassword: string;
};

async function signedInPage(
    browser: Browser,
    sessionCookie: typeof fixture.ownerCookie,
) {
    const context = await browser.newContext({
        viewport: { width: 1440, height: 1000 },
    });
    await context.addCookies([
        {
            name: sessionCookie.name,
            value: sessionCookie.value,
            domain: new URL(baseURL).hostname,
            path: sessionCookie.path,
            httpOnly: sessionCookie.httpOnly,
            secure: sessionCookie.secure,
            sameSite: sessionCookie.sameSite,
        },
    ]);
    const page = await context.newPage();
    return { context, page };
}

async function apiDetail(page: Page, id: string) {
    return page.evaluate(
        async (path) => {
            const response = await fetch(path, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok)
                throw new Error(
                    `API detail request returned ${response.status}`,
                );
            return response.json();
        },
        `/api/v1/itineraries/${encodeURIComponent(id)}`,
    ) as Promise<{
        id: string;
        title: string;
        plans: Array<{
            title: string;
            date: string | null;
            order: number;
            files: Array<{ file_name: string }>;
        }>;
        packing_items: Array<{
            name: string;
            is_checked: boolean;
            order: number;
        }>;
        notes: Array<{ title: string; text: string; order: number }>;
    }>;
}

test.describe.configure({ mode: "serial" });
const screenshotDirectory =
    process.env.E2E_SCREENSHOT_DIR ?? "/tmp/guide-next-e2e";
test.beforeAll(async () => mkdir(screenshotDirectory, { recursive: true }));

test("owner creates, sorts, uploads, edits, clears packing and persists after reload", async ({
    browser,
}) => {
    const { context, page } = await signedInPage(browser, fixture.ownerCookie);
    try {
        await page.goto("/itineraries/create");
        await expect(
            page.getByRole("heading", { name: "旅のしおりを作る" }),
        ).toBeVisible();

        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            page
                .getByRole("alert")
                .filter({ hasText: "タイトルを入力してください" }),
        ).toBeVisible();

        await page.getByLabel("しおりのタイトル").fill("ブラウザーE2Eの旅");
        await page
            .getByLabel("旅行概要")
            .fill("同一オリジンの実ブラウザーから保存");
        await page.getByRole("button", { name: "予定を追加" }).click();
        const planNames = page.getByLabel("予定の名前");
        await planNames.nth(0).fill("最初の予定");
        await planNames.nth(1).fill("次の予定");
        await page
            .getByLabel("予定にファイルを添付")
            .first()
            .setInputFiles({
                name: "ticket.pdf",
                mimeType: "application/pdf",
                buffer: Buffer.from("%PDF-1.4\nBrowser E2E attachment"),
            });
        await page.getByPlaceholder("持ち物").fill("パスポート");
        await page.getByPlaceholder("八つ橋（祖母へ）").fill("お土産メモ");
        await page.getByLabel("見出し").fill("予約番号");
        await page
            .getByLabel("内容", { exact: true })
            .fill("ホテル予約 E2E-123");

        const handles = page.getByRole("button", {
            name: "ドラッグして順序を変更",
        });
        const sourceHandle = await handles.nth(0).boundingBox();
        const targetRow = await handles
            .nth(1)
            .locator(
                "xpath=ancestor::div[contains(@class,'relative rounded-2xl')]",
            )
            .boundingBox();
        expect(sourceHandle).not.toBeNull();
        expect(targetRow).not.toBeNull();
        await page.mouse.move(
            sourceHandle!.x + sourceHandle!.width / 2,
            sourceHandle!.y + sourceHandle!.height / 2,
        );
        await page.mouse.down();
        await page.mouse.move(
            sourceHandle!.x + sourceHandle!.width / 2 + 12,
            sourceHandle!.y + sourceHandle!.height / 2 + 10,
            { steps: 4 },
        );
        await page.mouse.move(
            targetRow!.x + targetRow!.width - 34,
            targetRow!.y + 36,
            { steps: 12 },
        );
        await page.mouse.up();
        await expect(planNames.nth(0)).toHaveValue("次の予定");
        await expect(planNames.nth(0)).toHaveValue("次の予定");

        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page).toHaveURL(/\/itineraries\/[^/]+\/edit$/);
        const id = new URL(page.url()).pathname.split("/")[2];
        let stored = await apiDetail(page, id);
        expect(stored.title).toBe("ブラウザーE2Eの旅");
        expect(stored.plans.map((plan) => plan.title)).toEqual([
            "次の予定",
            "最初の予定",
        ]);
        expect(stored.plans.some((plan) => plan.date === null)).toBeTruthy();
        expect(
            stored.plans
                .flatMap((plan) => plan.files)
                .map((file) => file.file_name),
        ).toContain("ticket.pdf");
        expect(stored.packing_items.map((item) => item.name)).toContain(
            "パスポート",
        );

        await page.evaluate(() => window.scrollTo(0, 0));
        await page.evaluate(
            () =>
                new Promise<void>((resolve) =>
                    requestAnimationFrame(() => resolve()),
                ),
        );
        await page.screenshot({
            path: screenshotPath("desktop-edit.png"),
            fullPage: true,
        });
        await page.reload();
        const keyboardHandle = page
            .getByRole("button", { name: "ドラッグして順序を変更" })
            .nth(1);
        await keyboardHandle.focus();
        await page.keyboard.press("Space");
        await page.keyboard.press("ArrowUp");
        await page.keyboard.press("Space");
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            page
                .getByRole("status")
                .filter({ hasText: "しおりを保存しました" }),
        ).toBeVisible();
        stored = await apiDetail(page, id);
        expect(stored.plans.map((plan) => plan.title)).toEqual([
            "最初の予定",
            "次の予定",
        ]);

        await page
            .getByLabel("しおりのタイトル")
            .fill("ブラウザーE2Eの旅・更新");
        await page
            .getByLabel("内容", { exact: true })
            .fill("ホテル予約 E2E-456");
        const packing = page.getByRole("checkbox", {
            name: "1番目の持ち物を準備済みにする",
        });
        await packing.check();
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            page
                .getByRole("status")
                .filter({ hasText: "しおりを保存しました" }),
        ).toBeVisible();
        await page.reload();
        await expect(page.getByLabel("しおりのタイトル")).toHaveValue(
            "ブラウザーE2Eの旅・更新",
        );
        stored = await apiDetail(page, id);
        expect(stored.title).toBe("ブラウザーE2Eの旅・更新");
        expect(stored.notes[0]?.text).toBe("ホテル予約 E2E-456");
        expect(stored.packing_items[0]?.is_checked).toBe(true);

        await page
            .getByRole("button", { name: "ticket.pdfを添付から外す" })
            .click();
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            page
                .getByRole("status")
                .filter({ hasText: "しおりを保存しました" }),
        ).toBeVisible();
        stored = await apiDetail(page, id);
        expect(stored.plans.flatMap((plan) => plan.files)).toHaveLength(0);

        await packing.uncheck();
        const packingRow = packing.locator(
            "xpath=ancestor::div[contains(@class,'relative rounded-2xl')]",
        );
        await packingRow.getByRole("button", { name: "この行を削除" }).click();
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            page
                .getByRole("status")
                .filter({ hasText: "しおりを保存しました" }),
        ).toBeVisible();
        stored = await apiDetail(page, id);
        expect(stored.packing_items).toHaveLength(0);
        await page.reload();
        await expect(page.getByText("持ち物はまだありません")).toBeVisible();
    } finally {
        await context.close();
    }
});

test("member sees only their own packing list and shared viewer is read-only until revoked", async ({
    browser,
}) => {
    const member = await signedInPage(browser, fixture.memberCookie);
    try {
        await member.page.goto(`/itineraries/${fixture.travelId}/edit`);
        await expect(
            member.page.getByRole("heading", { name: "しおりを編集" }),
        ).toBeVisible();
        await expect(member.page.getByPlaceholder("持ち物")).toHaveValue(
            "Member medicine",
        );
        await expect(member.page.getByPlaceholder("持ち物")).not.toHaveValue(
            "Owner passport",
        );
        await expect(
            member.page.getByRole("button", { name: "しおりを保存" }),
        ).toBeVisible();
        await expect(
            member.page.getByRole("heading", { name: "閲覧用リンク" }),
        ).toHaveCount(0);
        await expect(
            member.page.getByRole("button", { name: "共有を停止" }),
        ).toHaveCount(0);
    } finally {
        await member.context.close();
    }

    const guestContext = await browser.newContext({
        viewport: { width: 1280, height: 900 },
    });
    const guestPage = await guestContext.newPage();
    await guestPage.goto(`/itineraries/${fixture.travelId}/shared-access`);
    await expect(guestPage.getByLabel("共有パスワード")).toBeVisible();
    await guestPage.getByLabel("共有パスワード").fill("wrong-password");
    await guestPage.getByRole("button", { name: "しおりを開く" }).click();
    await expect(guestPage.getByRole("alert")).toBeVisible();
    await guestPage.getByLabel("共有パスワード").fill(fixture.viewerPassword);
    await guestPage.getByRole("button", { name: "しおりを開く" }).click();
    await expect(
        guestPage.getByRole("heading", { name: "E2E Trip" }),
    ).toBeVisible();
    await expect(guestPage.getByText("Member medicine")).toHaveCount(0);
    await expect(guestPage.getByText("Owner passport")).toHaveCount(0);
    await expect(guestPage.getByRole("link", { name: "編集する" })).toHaveCount(
        0,
    );

    const owner = await signedInPage(browser, fixture.ownerCookie);
    try {
        await owner.page.goto(`/itineraries/${fixture.travelId}/edit`);
        await owner.page
            .getByLabel("メンバーのメールアドレス")
            .fill(fixture.inviteEmail);
        await owner.page
            .getByLabel("メンバーのメールアドレス")
            .locator("xpath=ancestor::form")
            .getByRole("button")
            .click();
        await expect(
            owner.page
                .getByRole("status")
                .filter({ hasText: "メンバーを追加しました" }),
        ).toBeVisible();
        await expect(owner.page.getByText("invite@example.test")).toBeVisible();
        await owner.page.once("dialog", (dialog) => dialog.accept());
        await owner.page
            .getByRole("button", { name: "E2E Inviteをメンバーから削除" })
            .click();
        await expect(owner.page.getByText("invite@example.test")).toHaveCount(
            0,
        );

        await expect(
            owner.page.getByRole("heading", { name: "閲覧用リンク" }),
        ).toBeVisible();
        await owner.page.once("dialog", (dialog) => dialog.accept());
        await owner.page.getByRole("button", { name: "共有を停止" }).click();
        await expect(
            owner.page
                .getByRole("status")
                .filter({ hasText: "閲覧共有を停止しました" }),
        ).toBeVisible();
    } finally {
        await owner.context.close();
    }
    await guestPage.reload();
    await expect(
        guestPage.getByRole("heading", {
            name: "この共有リンクは利用できません",
        }),
    ).toBeVisible();
    await guestContext.close();
});

test("mobile home shows an authenticated header and logout clears the session", async ({
    browser,
}) => {
    const { context, page } = await signedInPage(browser, fixture.ownerCookie);
    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto("/");
        await expect(
            page.getByRole("button", { name: "メニューを開く" }),
        ).toBeVisible();
        const mockup = page.getByRole("img", {
            name: "旅のしおりの画面イメージ",
        });
        await mockup.scrollIntoViewIfNeeded();
        await expect
            .poll(() =>
                mockup.evaluate(
                    (image) => (image as HTMLImageElement).naturalWidth > 0,
                ),
            )
            .toBe(true);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.evaluate(
            () =>
                new Promise<void>((resolve) =>
                    requestAnimationFrame(() => resolve()),
                ),
        );
        await page.screenshot({
            path: screenshotPath("mobile-home.png"),
            fullPage: true,
        });
        await page.getByRole("button", { name: "メニューを開く" }).click();
        await page.getByRole("button", { name: "ログアウト" }).click();
        await expect(
            page
                .getByRole("banner")
                .getByRole("link", { name: "ログイン", exact: true }),
        ).toBeVisible();
        await expect
            .poll(async () =>
                page.evaluate(async () =>
                    (await fetch("/api/v1/session"))
                        .json()
                        .then((session) => session.authenticated),
                ),
            )
            .toBe(false);
    } finally {
        await context.close();
    }
});

function screenshotPath(name: string) {
    return resolve(screenshotDirectory, name);
}
