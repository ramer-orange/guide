# Dependency security maintenance — 2026-10-22

GitHub reported 34 open Dependabot alerts when dependency detection was re-enabled and rescanned. These include duplicate advisories across manifests and development dependencies. No alerts were dismissed.

## Changes

- Replace the Next ESLint plugin's vulnerable fast-glob chain with the maintained tinyglobby alias. This caller only uses globSync(pattern, { onlyDirectories: true }); directory glob integration was verified.
- Update Jest to 30 and ts-jest to a compatible release. Override the NYC YAML loader to js-yaml 4 (its load() API remains compatible).

## Verification

- `.`: `npm audit --package-lock-only --audit-level=low` reports zero known vulnerabilities.
- `frontend`: `npm audit --package-lock-only --audit-level=low` reports zero known vulnerabilities.
- `infra`: `npm audit --package-lock-only --audit-level=low` reports zero known vulnerabilities.
- `.`: `composer audit --locked --abandoned=report` reports zero known vulnerabilities.
- Frontend: 6 tests, typecheck, lint and production build passed.
- Infrastructure TypeScript build passed. The existing paused-infrastructure test fails on both the original main branch and this branch (expected 0 VPCs, found 1); this change does not alter infrastructure definitions.

## Ongoing checks

Dependency audit CI runs on pull requests, default-branch pushes and weekly. Dependabot update configuration covers the actual manifests and GitHub Actions. Actions are pinned to verified commit SHAs.

GitHub alerts close only after the fixed dependency manifests reach the repository default branch and GitHub rescans them. A clean branch audit is not a claim that a running deployment has already been upgraded.
