import { mkdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { deflateSync } from "node:zlib";
import { expect, test, type Browser } from "@playwright/test";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixture = JSON.parse(
    readFileSync(
        process.env.E2E_FIXTURE_PATH ?? "../storage/app/e2e-fixture.json",
        "utf8",
    ),
) as {
    travelId: string;
    viewerPassword: string;
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

function makePdf(): Buffer {
    const stream = "BT /F1 18 Tf 30 80 Td (Inline preview proof) Tj ET\n";
    const objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        `<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}endstream`,
    ];
    let document = "%PDF-1.4\n";
    const offsets = [0];
    for (const [index, object] of objects.entries()) {
        offsets.push(Buffer.byteLength(document));
        document += `${index + 1} 0 obj\n${object}\nendobj\n`;
    }
    const xrefOffset = Buffer.byteLength(document);
    document += `xref\n0 ${offsets.length}\n0000000000 65535 f \n`;
    document += offsets
        .slice(1)
        .map((offset) => `${String(offset).padStart(10, "0")} 00000 n \n`)
        .join("");
    document += `trailer\n<< /Size ${offsets.length} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`;
    return Buffer.from(document);
}

function crc32(bytes: Buffer): number {
    let crc = 0xffffffff;
    for (const byte of bytes) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit += 1) {
            crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
        }
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function pngChunk(type: string, data: Buffer): Buffer {
    const typeBytes = Buffer.from(type);
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const checksum = Buffer.alloc(4);
    checksum.writeUInt32BE(crc32(Buffer.concat([typeBytes, data])));
    return Buffer.concat([length, typeBytes, data, checksum]);
}

function makePng(): Buffer {
    const width = 64;
    const height = 64;
    const row = Buffer.alloc(1 + width * 4);
    row.fill(0, 0, 1);
    for (let x = 0; x < width; x += 1) {
        row[1 + x * 4] = 42;
        row[2 + x * 4] = 153;
        row[3 + x * 4] = 105;
        row[4 + x * 4] = 255;
    }
    const pixels = Buffer.concat(Array.from({ length: height }, () => row));
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8;
    header[9] = 6;
    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        pngChunk("IHDR", header),
        pngChunk("IDAT", deflateSync(pixels)),
        pngChunk("IEND", Buffer.alloc(0)),
    ]);
}

test("previews uploaded PDF and PNG inline and keeps a separate download action", async ({
    browser,
}) => {
    const { context, page } = await ownerPage(browser);
    const pdf = makePdf();
    const png = makePng();
    const proofDirectory = process.env.E2E_SCREENSHOT_DIR ?? "/tmp/guide-next-e2e";
    mkdirSync(proofDirectory, { recursive: true });

    try {
        await page.goto("/itineraries/create");
        await page.getByLabel("しおりのタイトル").fill("添付プレビュー確認");
        await page.getByLabel("予定にファイルを添付").first().setInputFiles([
            {
                name: "preview-proof.pdf",
                mimeType: "application/pdf",
                buffer: pdf,
            },
            {
                name: "preview-proof.png",
                mimeType: "image/png",
                buffer: png,
            },
        ]);
        await page.getByRole("button", { name: "しおりを保存" }).click();
        await expect(page).toHaveURL(/\/itineraries\/[^/]+\/edit$/);

        for (const [name, mimeType, bytes] of [
            ["preview-proof.pdf", "application/pdf", pdf],
            ["preview-proof.png", "image/png", png],
        ] as const) {
            const openLink = page.getByRole("link", { name: `${name}を開く` });
            const downloadLink = page.getByRole("link", {
                name: `${name}をダウンロード`,
            });
            await expect(openLink).toBeVisible();
            await expect(openLink).toHaveAttribute("target", "_blank");
            await expect(downloadLink).toBeVisible();

            const previewURL = await openLink.getAttribute("href");
            const downloadURL = await downloadLink.getAttribute("href");
            expect(previewURL).toContain("/preview");
            expect(downloadURL).not.toContain("/preview");

            const previewResponse = await page.request.get(
                new URL(previewURL!, baseURL).toString(),
            );
            expect(previewResponse.status()).toBe(200);
            expect(previewResponse.headers()["content-type"]).toContain(mimeType);
            expect(previewResponse.headers()["content-disposition"]).toMatch(
                /^inline;/,
            );
            expect(previewResponse.headers()["x-content-type-options"]).toBe(
                "nosniff",
            );
            expect(await previewResponse.body()).toEqual(bytes);

            // Chromium's bundled headless shell cannot navigate to PDF documents.
            // The regular `chromium` channel exercises its native PDF viewer; the
            // default shell still verifies the link and inline HTTP response above.
            if (
                mimeType === "image/png" ||
                process.env.PLAYWRIGHT_CHANNEL === "chromium"
            ) {
                const newPagePromise = context.waitForEvent("page");
                await openLink.click();
                const previewTab = await newPagePromise;
                await previewTab.waitForURL(/\/preview(?:\?|$)/, {
                    waitUntil: "commit",
                });
                expect(previewTab.url()).toContain("/preview");
                await previewTab.waitForTimeout(1000);
                if (mimeType === "image/png") {
                    await expect(previewTab.locator("img")).toBeVisible();
                    await previewTab.screenshot({
                        path: join(proofDirectory, "inline-preview-png.png"),
                    });
                } else {
                    await previewTab.screenshot({
                        path: join(proofDirectory, "inline-preview-pdf.png"),
                    });
                }
                await previewTab.close();
            }

            const downloadPromise = page.waitForEvent("download");
            await downloadLink.click();
            const download = await downloadPromise;
            expect(download.suggestedFilename()).toBe(name);
        }

        await page.goto(`/itineraries/${fixture.travelId}/shared-access`);
        await page.getByLabel("共有パスワード").fill(fixture.viewerPassword);
        await page.getByRole("button", { name: "しおりを開く" }).click();
        const viewOpenLink = page.getByRole("link", {
            name: "fixture.pngを開く",
        });
        await expect(viewOpenLink).toBeVisible();
        const viewPagePromise = context.waitForEvent("page");
        await viewOpenLink.click();
        const viewPage = await viewPagePromise;
        await viewPage.waitForURL(/\/preview(?:\?|$)/, {
            waitUntil: "commit",
        });
        expect(viewPage.url()).toContain("/preview");
        await viewPage.waitForTimeout(1000);
        await expect(viewPage.locator("img")).toBeVisible();
        await viewPage.screenshot({
            path: join(proofDirectory, "inline-preview-from-view.png"),
        });
        await viewPage.close();

        await page.screenshot({
            path: join(proofDirectory, "inline-preview-attachments.png"),
            fullPage: true,
        });
    } finally {
        await context.close();
    }
});
