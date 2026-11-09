import { test } from "node:test";
import assert from "node:assert/strict";
import { evaluateAudit } from "./npm-audit-check.mjs";

const allowlist = [
    {
        advisory: "GHSA-g3hv-75wg-m249",
        package: "minimatch",
        paths: ["node_modules/aws-cdk-lib/node_modules/minimatch"],
        expires: "2026-12-31",
        reason: "bundled",
    },
];

function report(nodes, url = "https://github.com/advisories/GHSA-g3hv-75wg-m249") {
    return {
        vulnerabilities: {
            minimatch: { nodes, via: [{ url, title: "ReDoS" }] },
            "aws-cdk-lib": { nodes: ["node_modules/aws-cdk-lib"], via: ["minimatch"] },
        },
    };
}

test("許可リストの場所にある既知の脆弱性は通す", () => {
    const result = evaluateAudit(
        report(["node_modules/aws-cdk-lib/node_modules/minimatch"]),
        allowlist,
        "2026-11-09",
    );
    assert.equal(result.ok, true);
    assert.equal(result.allowed.length, 1);
});

test("同じアドバイザリでも別の場所にあれば失敗する", () => {
    const result = evaluateAudit(report(["node_modules/minimatch"]), allowlist, "2026-11-09");
    assert.equal(result.ok, false);
    assert.equal(result.blocked.length, 1);
});

test("許可リストに無いアドバイザリは失敗する", () => {
    const result = evaluateAudit(
        report(
            ["node_modules/aws-cdk-lib/node_modules/minimatch"],
            "https://github.com/advisories/GHSA-xxxx-xxxx-xxxx",
        ),
        allowlist,
        "2026-11-09",
    );
    assert.equal(result.ok, false);
});

test("期限切れの許可は失敗する", () => {
    const result = evaluateAudit(
        report(["node_modules/aws-cdk-lib/node_modules/minimatch"]),
        allowlist,
        "2027-01-01",
    );
    assert.equal(result.ok, false);
    assert.equal(result.expired.length, 1);
});
