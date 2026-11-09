#!/usr/bin/env node
// npm audit を実行し、修正版がまだ無い既知の脆弱性だけを許可リストで除外する。
// 許可は「アドバイザリID・パッケージ名・パッケージの場所」が一致し、期限内のときだけ有効。
// それ以外の脆弱性が 1 件でもあれば失敗する（--audit-level=low 相当）。
import { spawnSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), "..");
const allowlistPath = join(repoRoot, ".github", "npm-audit-allowlist.json");

function advisoryId(url) {
    return String(url ?? "").split("/").pop();
}

export function evaluateAudit(report, allowlist, today) {
    const blocked = [];
    const allowed = [];
    const expired = [];

    for (const [name, vulnerability] of Object.entries(report.vulnerabilities ?? {})) {
        for (const via of vulnerability.via ?? []) {
            // 文字列の via は「依存先が脆弱」という派生情報なので、元のアドバイザリ側で判定する。
            if (typeof via !== "object") continue;
            const id = advisoryId(via.url);
            const finding = { id, name, nodes: vulnerability.nodes ?? [], title: via.title };
            const entry = allowlist.find(
                (candidate) =>
                    candidate.advisory === id &&
                    candidate.package === name &&
                    finding.nodes.length > 0 &&
                    finding.nodes.every((node) => candidate.paths.includes(node)),
            );
            if (!entry) {
                blocked.push(finding);
            } else if (today > entry.expires) {
                expired.push({ ...finding, expires: entry.expires });
            } else {
                allowed.push({ ...finding, expires: entry.expires, reason: entry.reason });
            }
        }
    }

    return { ok: blocked.length === 0 && expired.length === 0, blocked, allowed, expired };
}

function main() {
    const result = spawnSync("npm", ["audit", "--package-lock-only", "--json"], {
        encoding: "utf8",
        maxBuffer: 64 * 1024 * 1024,
    });
    let report;
    try {
        report = JSON.parse(result.stdout);
    } catch {
        console.error("npm audit の結果を読み取れませんでした");
        console.error(result.stdout, result.stderr);
        process.exit(1);
    }
    if (report.error) {
        console.error("npm audit が失敗しました:", report.error);
        process.exit(1);
    }

    const allowlist = JSON.parse(readFileSync(allowlistPath, "utf8"));
    const today = new Date().toISOString().slice(0, 10);
    const { ok, blocked, allowed, expired } = evaluateAudit(report, allowlist, today);

    for (const finding of allowed) {
        console.log(`許可（${finding.expires} まで）: ${finding.id} ${finding.name} - ${finding.reason}`);
    }
    for (const finding of expired) {
        console.error(`許可期限切れ（${finding.expires}）: ${finding.id} ${finding.name} ${finding.nodes.join(", ")}`);
    }
    for (const finding of blocked) {
        console.error(`脆弱性: ${finding.id} ${finding.name} ${finding.nodes.join(", ")} - ${finding.title}`);
    }
    if (!ok) {
        console.error("修正版への更新、または .github/npm-audit-allowlist.json の見直しが必要です。");
        process.exit(1);
    }
    console.log("npm audit: 許可リスト外の脆弱性はありません");
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    main();
}
