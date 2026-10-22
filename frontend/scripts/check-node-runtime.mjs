import { fileURLToPath } from "node:url";
import { resolve } from "node:path";

export function assertSecureNodeVersion(version = process.versions.node) {
    const [major, minor, patch] = version
        .replace(/^v/, "")
        .split(".")
        .map(Number);
    const minimum = new Map([
        [22, [23, 4]],
        [24, [22, 1]],
        [26, [12, 1]],
    ]).get(major);
    if (
        !/^\d+\.\d+\.\d+$/.test(version.replace(/^v/, "")) ||
        !minimum ||
        !Number.isInteger(patch) ||
        minor < minimum[0] ||
        (minor === minimum[0] && patch < minimum[1])
    ) {
        throw new Error(
            `Unsupported or vulnerable Node.js ${version}; use 22.23.4+, 24.22.1+, or 26.12.1+ within those release lines.`,
        );
    }
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    assertSecureNodeVersion();
    console.log(`Node.js runtime verified: ${process.versions.node}`);
}
