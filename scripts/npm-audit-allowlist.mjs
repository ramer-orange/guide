#!/usr/bin/env node
// Runs `npm audit --package-lock-only` in the current directory and fails on
// any advisory that is not listed for this directory in npm-audit-allowlist.json.
// Usage (from a package directory): node <repo>/scripts/npm-audit-allowlist.mjs <directory-name>
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = process.argv[2];
if (!directory) {
  console.error('Usage: npm-audit-allowlist.mjs <directory>');
  process.exit(2);
}

const allowlistPath = join(
  dirname(fileURLToPath(import.meta.url)),
  '..',
  'npm-audit-allowlist.json',
);
const allowed = new Map(
  JSON.parse(readFileSync(allowlistPath, 'utf8'))
    .filter((entry) => entry.directories.includes(directory))
    .map((entry) => [entry.advisory, entry]),
);

let output;
try {
  output = execFileSync('npm', ['audit', '--package-lock-only', '--json'], {
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });
} catch (error) {
  // npm audit exits non-zero when it finds vulnerabilities; the JSON is still on stdout.
  output = error.stdout;
  if (!output) throw error;
}

const report = JSON.parse(output);
if (report.error) {
  console.error('npm audit failed:', report.error.summary ?? report.error);
  process.exit(1);
}

// Collect each advisory once. Packages whose `via` only names other packages
// are vulnerable through those advisories, so they need no separate check.
const advisories = new Map();
for (const vulnerability of Object.values(report.vulnerabilities ?? {})) {
  for (const via of vulnerability.via) {
    // Same threshold as the previous `--audit-level=low`: ignore info-only advisories.
    if (typeof via !== 'object' || via.severity === 'info') continue;
    const id = via.url?.split('/').pop() ?? String(via.source);
    if (!advisories.has(id)) {
      advisories.set(id, { ...via, packages: new Set() });
    }
    advisories.get(id).packages.add(vulnerability.name);
  }
}

const blocking = [];
for (const [id, advisory] of advisories) {
  const packages = [...advisory.packages].join(', ');
  if (allowed.has(id)) {
    console.log(`allowed  ${id} (${advisory.severity}) in ${packages}: ${allowed.get(id).reason}`);
  } else {
    blocking.push(id);
    console.log(`BLOCKING ${id} (${advisory.severity}) in ${packages}: ${advisory.title} ${advisory.url ?? ''}`);
  }
}

for (const id of allowed.keys()) {
  if (!advisories.has(id)) {
    console.log(`::warning::${id} is allowlisted for ${directory} but no longer reported. Remove it from npm-audit-allowlist.json.`);
  }
}

if (blocking.length > 0) {
  console.error(`${blocking.length} advisory(ies) not in npm-audit-allowlist.json.`);
  process.exit(1);
}
console.log(`No advisories outside the allowlist for "${directory}".`);
