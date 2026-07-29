# Progress

## Phase 0 — Foundation

**Status**: Complete

### Implemented

- Laravel 13.23.0 scaffolded from `laravel/react-starter-kit` (`main`
  branch) — Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui, Laravel
  Wayfinder, Fortify. See `docs/DECISIONS.md` ADR-011 for why `main` instead
  of the last tag.
- PostgreSQL 16 as the only database driver used (dev, test, and CI all run
  against real Postgres — see `docs/TESTING.md` §2).
- Redis 7 for cache, session, and queue.
- Docker Compose (`compose.yaml`) with `app`, `nginx`, `vite`, `horizon`,
  `scheduler`, `inertia-ssr`, `postgres`, `redis`, `minio` + `minio-init`,
  `mailpit`. All services verified booting and healthy together
  (`docker compose up -d`); nginx serves `/up` and `/` with HTTP 200,
  Horizon starts and connects, the Inertia SSR Node process starts.
- Multi-stage `Dockerfile` (`base` → `development` / `build` → `production`)
  targeting PHP 8.3-FPM with `pdo_pgsql`, `pgsql`, `redis`, `bcmath`, `zip`,
  `intl`, `gd`, `pcntl`, `opcache`.
- Laravel Boost installed (`laravel/boost` dev dependency); MCP server
  registered for Claude Code via
  `claude mcp add -s local laravel-boost -- docker compose exec -T app php artisan boost:mcp`
  (the automated `boost:install` agent-selection prompt requires an
  interactive TTY that isn't available in this environment, so registration
  was done directly per the brief's documented fallback).
- Horizon installed and configured with three supervisors
  (`supervisor-critical` → `high`/`payments`, `supervisor-standard` →
  `default`/`notifications`, `supervisor-low` → `low`) so a burst on one
  tier can't starve another — see `config/horizon.php`. Dashboard gate
  (`app/Providers/HorizonServiceProvider::gate()`) defaults to nobody until
  Phase 1's platform-admin role lands.
- Pint (Laravel preset), Larastan (level 7, `phpstan.neon`), Pest 4 (with
  `pestphp/pest-plugin-laravel`) all installed and passing.
- Frontend tooling: ESLint, Prettier, TypeScript strict mode (all shipped by
  the starter kit and verified passing), **plus Vitest + React Testing
  Library added** (not part of the starter kit) with a real example test
  (`resources/js/components/ui/button.test.tsx`) covering render, click, and
  disabled-state behavior of the shared `Button` component.
- `GET /up` health check (Laravel's built-in `health:` route, already wired
  by the starter kit's `bootstrap/app.php`).
- `App\Http\Middleware\AddRequestId`: assigns/echoes an `X-Request-Id`
  header and adds it to Laravel's log `Context` on every request, including
  error responses (via `$exceptions->respond()` in `bootstrap/app.php`,
  since a thrown exception bypasses the middleware's own post-`$next()`
  code — see the comment there).
- `.env.example` rewritten to document every variable listed in the brief's
  §23 (app/tenancy, DB, cache/session/queue, Redis, S3/MinIO, mail, Google
  OAuth, payment/shipping driver switches, Horizon prefix) — all
  placeholder/local-safe values, no real credential.
- CI (`.github/workflows/tests.yml`): checkout → PHP 8.3 + required
  extensions → Node 22 → composer/npm dependency caching → install deps →
  `.env` + key:generate → migrate → Pint → Larastan → Pest (against real
  Postgres + Redis service containers) → ESLint → Prettier → TypeScript →
  Vitest → production build → SSR build.
- `docs/` skeleton: `PRD.md`, `ARCHITECTURE.md` (with Mermaid diagrams for
  system context, request lifecycle, checkout flow, payment flow),
  `DATABASE.md`, `TENANCY.md`, `SECURITY.md`, `TESTING.md`, `DEPLOYMENT.md`,
  `ROADMAP.md`, `DECISIONS.md` (11 ADRs), this file.

### Changed / added modules

- `app/Http/Middleware/AddRequestId.php` (new)
- `bootstrap/app.php` (request ID middleware registration + exception
  response header)
- `config/horizon.php` (queue-tier supervisors, `Str::slug` type-safety fix
  for Larastan)
- `app/Providers/HorizonServiceProvider.php` (gate comment for Phase 1)
- `phpunit.xml` (forced Postgres test DB, forced `<server>` overrides
  alongside `<env force="true">` — see inline comments and
  `docs/TESTING.md` §2 for why both are needed)
- `vitest.config.ts`, `resources/js/test/setup.ts` (new — frontend test
  infra the starter kit doesn't ship)

### Tests added

- `tests/Feature/Support/RequestIdTest.php` — 4 tests (header present on
  success, echoes a client-supplied ID, reaches log context, still present
  on error responses).
- `resources/js/components/ui/button.test.tsx` — 3 tests (render, click,
  disabled).
- Starter-kit tests (auth, settings, dashboard) kept as-is: 39 tests,
  unmodified except environment fixes in `phpunit.xml` that made them pass
  against real Postgres instead of silently relying on SQLite.

**Total: 43 backend tests / 142 assertions passing on Postgres. 3 frontend
tests passing.**

### Commands executed (representative — see conversation for full list)

```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 composer create-project laravel/react-starter-kit:dev-main . --stability=dev --no-interaction
npm install
docker compose build app
docker compose up -d
docker compose exec app composer update --with-all-dependencies   # re-resolve for PHP 8.3 runtime
docker compose exec app composer require laravel/horizon laravel/socialite league/flysystem-aws-s3-v3 predis/predis
docker compose exec app composer require laravel/boost --dev
docker compose exec app composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
docker compose exec app ./vendor/bin/pest --init
docker compose exec app php artisan migrate
docker compose exec app php artisan horizon:install
docker compose exec app npm install -D vitest @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom @vitest/coverage-v8
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app php artisan test
docker compose exec app npm run lint:check && npm run format:check && npm run types:check && npm run test
docker compose exec app npm run build && npm run build:ssr
claude mcp add -s local laravel-boost -- docker compose exec -T app php artisan boost:mcp
```

### Quality results

| Gate | Result |
|---|---|
| Pint | ✅ Pass (59 files) |
| Larastan (level 7) | ✅ No errors (42 files analyzed) |
| Pest | ✅ 43 passed, 142 assertions (real Postgres) |
| ESLint | ✅ Pass |
| Prettier | ✅ Pass |
| TypeScript (`tsc --noEmit`) | ✅ Pass |
| Vitest | ✅ 3 passed |
| Production build (`vite build`) | ✅ Succeeds |
| SSR build (`vite build --ssr`) | ✅ Succeeds, no blocking compatibility issue |

### Known limitations

- `npm audit` reports 7 high-severity advisories, all in dev-only
  transitive tooling (`eslint`'s `minimatch`/`brace-expansion` chain — a
  DoS-via-regex issue that only matters if an attacker controls a glob
  pattern fed to `minimatch`, which nothing in this project does). Fixing
  requires a breaking `eslint` major upgrade; deferred rather than risking
  the lint config in Phase 0. Will revisit in Phase 7 hardening.
- Laravel Boost's `boost:install` guideline/MCP file generation is
  interactive-only in this environment; MCP registration was done directly
  via `claude mcp add` instead (functionally equivalent — Boost's MCP tools
  are available — but no `CLAUDE.md`/`AGENTS.md` guideline file was
  auto-generated by Boost itself).
- Horizon's `viewHorizon` gate is intentionally closed to everyone (empty
  email allow-list) until Phase 1 introduces the `platform_admin` role.
- No Playwright browser tests yet — added when a phase has a full flow
  worth covering end-to-end (storefront/checkout), per the brief's "don't
  build speculative infrastructure" rule.
- The repository lives at
  `/data/Gawai Duniawi/SaaS/usaharumahan-marketplace` as its own git
  repository (not at the shared `SaaS/` parent, which already holds several
  unrelated projects) — confirmed with the user before scaffolding.

### Next phase

Phase 1 — Authentication and Tenancy: `Tenant`/`TenantMembership`/`Store`
models and migrations, `TenantContext`, hostname resolution middleware,
dashboard tenant resolution, Policies, `platform_admin` role, Google OAuth
wiring (Socialite is installed but not yet wired to a controller/flow),
owner onboarding flow, and the first cross-tenant isolation tests.
