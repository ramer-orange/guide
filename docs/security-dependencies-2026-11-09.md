# Dependency security maintenance — 2026-11-09

The scheduled `Dependency Security` run on `main` (912db4d) failed in all four audit jobs. The cause was advisories published after the lockfiles were last updated, not a code change. No alerts were dismissed and the audit level was not changed.

## Changes

- `composer.lock`: Symfony 7.4 components updated within the 7.4 line (for example `http-foundation`, `http-kernel`, `routing`, `filesystem`, `yaml` 7.4.21 → 7.4.23, `mime` 7.4.19 → 7.4.22). This fixes CVE-2026-45070, CVE-2026-118514, CVE-2026-118516 and the related Symfony advisories from 2026-11-04. All locked packages still support PHP 8.2.
- `frontend`: `zod` 4.6.7 (GHSA-mrhx-c7xc-m3w5) and `follow-redirects` 1.16.3 (GHSA-mrwp-f3q8-3g9p), both in range. A scoped override moves `@typescript-eslint/typescript-estree` to `minimatch` 11.0.5 (GHSA-g3hv-75wg-m249). The other `minimatch` 3/5 users are outside the affected range and are unchanged.
- `infra`: overrides set `glob` to ^14.0.4 (GHSA-3g3m-2fjr-6pw4) and `minimatch` to ^11.0.5 for the Jest/coverage toolchain. Jest still runs with them.

## Remaining advisories (no patched release exists)

The `.` and `infra` audit jobs still fail on these advisories. They are documented here rather than hidden by a lower audit level.

| Advisory | Where | Why it can't be fixed now | Why it is acceptable for now |
| --- | --- | --- | --- |
| GHSA-g3hv-75wg-m249 (`minimatch` <11.0.1, moderate) | `aws-cdk-lib` 2.274.0 in `.` (via `cdk-dia`) and `infra` | `aws-cdk-lib` bundles `minimatch` (`bundleDependencies`), so `overrides` cannot replace it. The newest release, 2.275.0, still bundles `^10.2.6`, and no patched 10.x exists. `npm audit fix` suggests `aws-cdk-lib` 2.239.0 or `cdk-dia` 0.12.3, both older releases. | `aws-cdk-lib` runs only at synth/deploy time on developer or CI machines. It matches globs taken from the repo's own CDK code and asset ignore files, not from user input, and it is not shipped in the application. |
| GHSA-gcqx-w48m-w2h4 (`deepmerge` <=4.3.1, low) | `jest-config` in `infra` | 4.3.1 is the latest `deepmerge` release, and Jest 30.5.2 (latest) depends on it. The only "fix" npm offers is Jest 25, a major downgrade. | This is test-only: Jest merges the repo's own Jest config. No untrusted input reaches `deepmerge`, and it is not shipped. |

Recheck when `aws-cdk-lib` bundles `minimatch` ≥11.0.1 or a patched `deepmerge`/Jest is released, then update and remove this section.

## Verification

- `frontend`: `npm audit --package-lock-only --audit-level=low` reports zero vulnerabilities. `test` (6 tests), `typecheck`, `lint` and `build` passed.
- `.`: `composer audit --locked --no-interaction --abandoned=report` reports no advisories. `php artisan test`: 67 passed.
- `.` npm: only the bundled `minimatch` advisory above remains.
- `infra` npm: only the two advisories above remain. `npx tsc --noEmit` passed. `npx jest` has one failing test, the existing paused-infrastructure test (expected 0 VPCs, found 1). It fails the same way with the original lockfile; this change does not touch infrastructure definitions.
- Local PHP was 8.3.6, which `scripts/check-php-runtime.php` rejects, so `composer update/install` ran with `--no-scripts`. CI uses PHP 8.4.27.
