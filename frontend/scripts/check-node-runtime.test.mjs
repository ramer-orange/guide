import { test } from "node:test";
import assert from "node:assert/strict";
import { assertSecureNodeVersion } from "./check-node-runtime.mjs";

test("rejects vulnerable patch levels and unsupported release lines", () => {
    for (const version of [
        "20.18.0",
        "22.14.0",
        "22.23.3",
        "24.22.0",
        "26.12.0",
        "23.11.0",
        "22.23.4-rc.1",
        "invalid",
    ]) {
        assert.throws(() => assertSecureNodeVersion(version));
    }
});

test("accepts security releases and subsequent patches within supported lines", () => {
    for (const version of [
        "22.23.4",
        "22.23.5",
        "22.24.0",
        "24.22.1",
        "26.12.1",
    ]) {
        assert.doesNotThrow(() => assertSecureNodeVersion(version));
    }
});
