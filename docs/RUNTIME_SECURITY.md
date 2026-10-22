# Runtime security baseline

The 21 October 2026 Node.js and PHP security releases are mandatory for this application.

| Environment | Node.js | PHP |
| --- | --- | --- |
| Production images (both Dockerfile entry points) | 22.23.4 | 8.3.36 CLI and FPM |
| Frontend image | 22.23.4 | — |
| CI | 22.23.4 | 8.3.36 |
| Sail 8.2 / 8.3 / 8.4 | 22.x, at least 22.23.4 | at least 8.2.35 / 8.3.36 / 8.4.27 |

Official Node and PHP images use the same Alpine 3.23 base. The production PHP image copies the verified Node executable from the build image instead of installing an uncontrolled Alpine `nodejs` package. PHP 8.0/8.1 Sail templates and the legacy third-party production base image were retired. The legacy Dockerfile now builds the same application stack, on port 80.

`frontend/scripts/check-node-runtime.mjs` and `scripts/check-php-runtime.php` reject vulnerable patch levels, prereleases, and unsupported branches. CI, install hooks, image builds, and production startup run these guards. npm also enforces the patched ranges through `engine-strict`. Revisit these minimums on every security release; these checks are a baseline, not a vulnerability scanner.

## Local setup

```sh
nvm install
nvm use
php --version
node --version
php scripts/check-php-runtime.php
node frontend/scripts/check-node-runtime.mjs
composer install
npm ci --prefix frontend
```

PHP must be updated through the system package manager as well as in Docker. For Homebrew PHP 8.4, install/upgrade `php@8.4` to 8.4.27 or later and ensure the linked CLI and any running FPM services use the updated installation. A project-local version file does not patch other system-wide Node installations or already running processes.

## Dependency updates and audit scope

Laravel was upgraded from 11.55.0 to 12.70.1 because the old release line could not resolve the advisories reported by Composer. Related Guzzle, CommonMark, Flysystem, phpseclib, and Symfony packages were updated. Next.js and its lint config were updated to 16.4.2, Vitest to 4.1.12, and Vite to 8.3.5.

```sh
composer audit
npm audit --prefix frontend --omit=dev
npm audit --prefix frontend
```

The first two audits report zero advisories at the time of this update. The full frontend development-dependency audit still reports five affected package paths from one unresolved upstream advisory: CVE-2026-93687 / GHSA-vfj7-8cjw-p6xm in `braces` 3.0.3 through Next's ESLint tooling. The upstream advisory lists no patched release. These development packages are not shipped in the standalone production runtime. Do not accept untrusted glob configuration for linting. This exception must be removed when an upstream fix or compatible replacement is available; do not use `npm audit fix --force`, which currently suggests an unrelated Next.js lint downgrade.

## Production verification

```sh
docker build --pull -f Dockerfile.render-next -t guide-security:patched .
docker run --rm --entrypoint sh guide-security:patched -c \
  'php scripts/check-php-runtime.php && node frontend/scripts/check-node-runtime.mjs && php-fpm -v'
```

A Git push or passing unit tests alone does not prove that a running service is patched. Verify the deployed commit, inspect the runtime version checks in startup logs, and test the health, login, SSR, upload and sharing flows. Stop/restart all workers when replacing the old runtime.

Render service `guide` was user-suspended during this remediation. Its Docker command is gated with the runtime-check scripts before starting Nginx or PHP-FPM, so the previous image (which lacks the scripts) cannot resume serving traffic with this command. Keep the service suspended until the patched source has passed CI and deployment/reopening has been approved. Do not remove the suspension notice from README until deployment has succeeded.

## Upstream references

- [PHP security advisory and fixed versions](https://www.php.net/archive/2026.php#2026-10-21-5)
- [Node.js October security releases](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases)
- [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade)
- [Unpatched braces advisory](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm)
