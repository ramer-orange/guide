import { readFileSync } from "node:fs";
import { expect, test, type Page } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixture = JSON.parse(
    readFileSync(
        process.env.E2E_FIXTURE_PATH ?? "../storage/app/e2e-fixture.json",
        "utf8",
    ),
);

async function save(page: Page) {
    await page.getByRole("button", { name: "しおりを保存" }).click();
    await expect(
        page.getByRole("status").filter({ hasText: "しおりを保存しました" }),
    ).toBeVisible();
}

test("owner and member save stale drafts without losing unrelated changes", async ({
    browser,
}) => {
    const ownerContext = await browser.newContext();
    const memberContext = await browser.newContext();
    await ownerContext.addCookies([
        { ...fixture.ownerCookie, domain: new URL(baseURL).hostname },
    ]);
    await memberContext.addCookies([
        { ...fixture.memberCookie, domain: new URL(baseURL).hostname },
    ]);
    const owner = await ownerContext.newPage();
    const member = await memberContext.newPage();
    try {
        await owner.goto("/itineraries/create");
        await owner.getByLabel("しおりのタイトル").fill("Concurrent trip");
        await owner.getByLabel("予定の名前").fill("Original plan");
        await owner.getByLabel("見出し").fill("Hotel");
        await owner.getByRole("button", { name: "しおりを保存" }).click();
        await expect(owner).toHaveURL(/\/itineraries\/[^/]+\/edit$/);
        const id = owner.url().split("/").at(-2)!;
        await member.goto("/");
        const memberEmail = await member.evaluate(
            async () =>
                (await (await fetch("/api/v1/session")).json()).user.email,
        );
        const invitationStatus = await owner.evaluate(
            async ({ id, email }) => {
                const xsrf = decodeURIComponent(
                    document.cookie
                        .split("; ")
                        .find((part) => part.startsWith("XSRF-TOKEN="))
                        ?.slice("XSRF-TOKEN=".length) ?? "",
                );
                return (
                    await fetch(`/api/v1/itineraries/${id}/members`, {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "Content-Type": "application/json",
                            "X-XSRF-TOKEN": xsrf,
                        },
                        body: JSON.stringify({ email }),
                    })
                ).status;
            },
            { id, email: memberEmail },
        );
        expect(invitationStatus).toBe(201);
        await member.goto(`/itineraries/${id}/edit`);
        await expect(member.getByLabel("予定の名前")).toHaveValue(
            "Original plan",
        );

        // Both drafts were loaded before the owner adds a row and an attachment.
        await owner.getByLabel("しおりのタイトル").fill("Owner title");
        await owner.getByRole("button", { name: "予定を追加" }).click();
        await owner.getByLabel("予定の名前").nth(1).fill("Owner added plan");
        await owner
            .getByLabel("予定にファイルを添付")
            .first()
            .setInputFiles({
                name: "shared.pdf",
                mimeType: "application/pdf",
                buffer: Buffer.from("%PDF-1.4\nShared attachment"),
            });
        await save(owner);
        await member
            .getByLabel("内容", { exact: true })
            .fill("Member hotel note");
        await save(member);
        await expect(member.getByLabel("しおりのタイトル")).toHaveValue(
            "Owner title",
        );
        await expect(member.getByLabel("予定の名前").nth(1)).toHaveValue(
            "Owner added plan",
        );
        await expect(
            member.getByRole("button", { name: "shared.pdfを添付から外す" }),
        ).toBeVisible();

        // A second save diffs against the newly saved state, and overlapping title edits are last-save-wins.
        await member.getByLabel("しおりのタイトル").fill("Member title");
        await save(member);
        await owner.getByLabel("しおりのタイトル").fill("Final owner title");
        await save(owner);
        await expect(owner.getByLabel("内容", { exact: true })).toHaveValue(
            "Member hotel note",
        );
        await member.getByLabel("旅行概要").fill("Member overview");
        await save(member);
        await expect(member.getByLabel("しおりのタイトル")).toHaveValue(
            "Final owner title",
        );

        // A stale edit of a deleted row is rejected without resurrecting it or losing the local input.
        await owner
            .getByRole("button", { name: "この行を削除" })
            .nth(1)
            .click();
        await save(owner);
        await member.getByLabel("予定の名前").nth(1).fill("Stale deleted edit");
        await member.getByRole("button", { name: "しおりを保存" }).click();
        await expect(
            member
                .getByRole("alert")
                .filter({ hasText: "この予定は削除されたか" }),
        ).toBeVisible();
        await expect(member.getByLabel("予定の名前").nth(1)).toHaveValue(
            "Stale deleted edit",
        );
        await owner.reload();
        await expect(owner.getByLabel("予定の名前")).toHaveCount(1);
        await expect(owner.getByLabel("旅行概要")).toHaveValue(
            "Member overview",
        );
    } finally {
        await ownerContext.close();
        await memberContext.close();
    }
});
