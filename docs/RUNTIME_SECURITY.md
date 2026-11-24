# Runtime security baseline

The 21 October 2026 Node.js and 19 November 2026 PHP security releases set the minimum patch levels for this application.

| Environment | Node.js | PHP |
| --- | --- | --- |
| Production images (both Dockerfile entry points) | 22.23.4 | 8.3.37 CLI and FPM |
| Frontend image | 22.23.4 | — |
| CI | 22.23.4 | 8.3.37 (app validation), 8.4.28 (Composer audit) |
| Sail 8.2 / 8.3 / 8.4 | 22.x, at least 22.23.4 | at least 8.2.36 / 8.3.37 / 8.4.28 |

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

PHP must be updated through the system package manager as well as in Docker. For Homebrew PHP 8.4, install/upgrade `php@8.4` to 8.4.28 or later and ensure the linked CLI and any running FPM services use the updated installation. A project-local version file does not patch other system-wide Node installations or already running processes.

## Dependency updates and audit scope

Laravel is locked to 12.73.0, which includes the fix for [CVE-2026-128083](https://github.com/advisories/GHSA-63hm-g96r-2m2q). Next.js and its lint config are locked to 16.4.6. The Next.js security fixes for the frontend audit were released in [16.4.5](https://github.com/vercel/next.js/releases/tag/v16.4.5).

```sh
composer audit
npm audit --prefix frontend --omit=dev
npm audit --prefix frontend
```

All three audits reported zero advisories on 24 November 2026.

## Production verification

```sh
docker build --pull -f Dockerfile.render-next -t guide-security:patched .
docker run --rm --entrypoint sh guide-security:patched -c \
  'php scripts/check-php-runtime.php && node frontend/scripts/check-node-runtime.mjs && php-fpm -v'
```

A Git push or passing unit tests alone does not prove that a running service is patched. Verify the deployed commit, inspect the runtime version checks in startup logs, and test the health, login, SSR, upload and sharing flows. Stop/restart all workers when replacing the old runtime.

Render service `guide` deploys automatically from `main`. The container runs its runtime checks before starting Nginx or PHP-FPM.

## Upstream references

- [November PHP security releases](https://github.com/php/php-src/releases)
- [Node.js October security releases](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases)
- [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade)
