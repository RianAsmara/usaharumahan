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

## Phase 1 — Authentication and Tenancy

**Status**: Complete

### Implemented

- `Tenant`, `TenantMembership`, `Store`, `StoreDomain`, `SocialAccount`
  models and migrations — ULID primary keys, `tenant_id` on every
  tenant-owned table, per `docs/DATABASE.md` §1.
- `App\Support\Tenancy\TenantContext`: the single source of truth for "which
  tenant is this request for," populated once per request by
  tenant-resolution middleware and never decided anywhere else.
- `App\Http\Middleware\ResolveTenantFromHostname` (soft — falls through for
  the root domain, storefront-facing) and
  `App\Http\Middleware\ResolveTenantForDashboard` (hard — resolves from the
  authenticated user's membership, not the hostname) and
  `App\Http\Middleware\RequireTenant` (guards routes that only make sense on
  a resolved tenant). See `docs/TENANCY.md`.
- `App\Http\Middleware\EnsurePlatformAdmin` and a `platform_admin` boolean
  on `users`, gating `App\Http\Controllers\PlatformAdmin\TenantController`
  (list/show tenants).
- `App\Policies\StorePolicy` — owner-only writes, any-member reads,
  independently re-deriving tenant membership from the model rather than
  trusting how it was fetched (`docs/TENANCY.md` §5) — the pattern every
  later phase's policies (Phase 2's `CategoryPolicy`/`ProductPolicy`/
  `InventoryPolicy`) follows.
- Owner onboarding (`App\Actions\Tenancy\CreateTenantWithStore`): tenant +
  owner membership + store + verified subdomain created together in one
  transaction, so a failure partway through never leaves a tenant without a
  store or an owner without a membership.
- Google OAuth (`App\Http\Controllers\Auth\GoogleAuthController`,
  `App\Actions\Auth\LoginOrRegisterWithGoogle`): verified-email-only,
  reuses an existing `SocialAccount` on repeat login, links to an existing
  `User` by verified email instead of duplicating.
- Dashboard `Store` profile CRUD (`StoreController`), staff read-only /
  owner read-write.
- `nginx` FastCGI buffer sizes increased (`fastcgi_buffers`/
  `fastcgi_buffer_size`) — Laravel's preload `Link` headers plus
  session/XSRF cookies were exceeding nginx's default header buffer,
  causing an intermittent 502 on otherwise-working pages.
- `docker/nginx` healthcheck fixed to target `127.0.0.1` instead of
  `localhost` — `localhost` resolves to `::1` first inside the container,
  but nginx only listens on IPv4 (the image's IPv6-listen auto-patch can't
  run because `default.conf` is bind-mounted read-only), so the healthcheck
  was racing a dead end while real traffic through the published port
  worked the whole time.
- `.remember/` (the Remember plugin's tool-generated state, already fully
  gitignored) added to `eslint.config.js`'s ignore list — a stray `.ts` file
  in there was failing `lint:check` for a reason unrelated to any app code.

### Tests added

- `tests/Feature/Auth/GoogleAuthenticationTest.php` — 5 tests.
- `tests/Feature/Tenancy/{OnboardingTest,HostnameResolutionTest,CrossTenantIsolationTest}.php` — 15 tests.
- `tests/Feature/PlatformAdmin/PlatformAdminAuthorizationTest.php` — 4 tests.
- `tests/Feature/Store/StoreSettingsTest.php` — 3 tests.
- `tests/Feature/DashboardTest.php` updated for tenant-aware redirects.
- `tests/Concerns/CreatesTenants.php` — shared `createTenantWithOwner()` /
  `addStaffToTenant()` helpers used by every tenancy-aware test from this
  phase onward.

**Total: 71 backend tests / 284 assertions passing on Postgres.**

### Quality results

| Gate | Result |
|---|---|
| Pint | ✅ Pass |
| Larastan (level 7) | ✅ No errors (87 files analyzed) |
| Pest | ✅ 71 passed, 284 assertions (real Postgres) |
| ESLint | ✅ Pass |
| Prettier | ✅ Pass |
| TypeScript (`tsc --noEmit`) | ✅ Pass |
| Vitest | ✅ 3 passed |
| Production build (`vite build`) | ✅ Succeeds |
| SSR build (`vite build --ssr`) | ✅ Succeeds |

### Known limitations

- No dedicated Policy unit tests — policies are exercised through their
  controllers' HTTP feature tests, matching this project's established
  testing style (no bare `*PolicyTest.php` files anywhere in the suite).
- Custom domain verification (Phase 6) is out of scope here; `StoreDomain`
  already models `type`/`verification_status` so Phase 6 extends rather
  than reshapes it.

### Next phase

Phase 2 (dashboard slice) — Catalog and inventory: categories, products,
images, options/variants, the inventory ledger, and owner/staff dashboard
CRUD pages, per
`docs/superpowers/specs/2026-07-29-phase2-catalog-inventory-design.md` and
`docs/superpowers/plans/2026-07-29-phase2-catalog-inventory.md`. The public
storefront listing/detail pages are deferred to a follow-up spec.

## Phase 2 — Dashboard Catalog & Inventory

**Status**: Complete

### Implemented

- `Category`, `Product`, `ProductImage`, `ProductOption`,
  `ProductOptionValue`, `ProductVariant`, `Inventory`, `InventoryMovement`
  models and migrations — ULID primary keys throughout, `tenant_id` on every
  tenant-owned table, a `product_variant_option_values` pivot linking
  variants to the option values that define them, and `Inventory` keyed
  directly on `product_variant_id` (one row per variant).
- `App\Support\Catalog\UniqueSlug`: shared per-tenant unique-slug/SKU
  generator used by both categories and products (and variant SKUs), fixed
  during this phase to compare case-insensitively so uppercase SKUs are
  correctly detected as collisions, and — during this task's Larastan
  clean-up — given an explicit `match`-based allow-list for the `$column`
  argument (`slug` or `sku`, throwing on anything else) before it's
  interpolated into a `whereRaw()` call. This both satisfies Larastan's
  `literal-string` requirement for `whereRaw()` and closes a Minor finding
  from Task 6's review (no allow-list guard on `$column`) that had been
  outstanding since then.
- Actions: `App\Actions\Catalog\CreateProduct` (creates a product with its
  first default variant and a zero-stock inventory row in one transaction),
  `App\Actions\Catalog\AddProductVariant` (variant + option-value pivot +
  zero-stock inventory row), `App\Actions\Inventory\AdjustInventory`
  (records a movement and updates `on_hand`, refusing to let stock go
  negative).
- Policies: `CategoryPolicy`, `ProductPolicy`, `InventoryPolicy` — same
  owner-writes/any-member-reads pattern established in Phase 1, each
  independently re-deriving tenant membership from the model. Inventory
  adjustment (`InventoryPolicy::adjust`) is deliberately open to staff, not
  just owners — restocking and stock corrections are a day-to-day staff
  task, unlike catalog structure changes.
- Dashboard controllers (all under `app/Http/Controllers/Dashboard/`):
  `CategoryController` (full CRUD), `ProductController` (index/create/store/
  edit/update/destroy — `store` auto-creates the first variant via
  `CreateProduct`), `ProductImageController` (upload with a 6-image cap,
  reorder via a full `sort_order` rewrite, delete with S3/MinIO cleanup),
  `ProductOptionController` (add an option with up to 20 values in one call,
  capped at 3 options per product; delete), `ProductVariantController`
  (store/update/destroy, destroy refuses to remove a product's last
  remaining variant), `InventoryController` (record a `restock` or
  `adjustment` movement).
- Dashboard pages: `dashboard/categories/index.tsx` (list + inline create/
  edit/delete), `dashboard/products/index.tsx` (list), `products/create.tsx`
  (name/category/description/price), and — this task —
  `products/edit.tsx`, the single screen composing product details,
  image management (upload, ↑/↓ reorder, delete), option management (add/
  delete), and per-variant price/sale-price editing plus a restock/adjust
  stock form, all gated on the `can.update` prop the backend already
  computes from `ProductPolicy`.
- All five dashboard-catalog controllers' Wayfinder-generated action
  signatures (`ProductController`, `ProductImageController`,
  `ProductOptionController`, `ProductVariantController`,
  `InventoryController`) were individually verified against the actual
  generated files in `resources/js/actions/App/Http/Controllers/Dashboard/`
  before wiring `products/edit.tsx` — all matched the plan's assumed call
  shapes exactly (single-param routes take `id | {id}`, multi-param routes
  take a `[product, child]` tuple), so no call-site adjustments were needed
  this task.

### Tests added

- `tests/Feature/Catalog/CategoryManagementTest.php`,
  `CategoryModelTest.php` — category CRUD, authorization, tenant isolation,
  slug uniqueness.
- `tests/Feature/Catalog/CreateProductActionTest.php`,
  `AddProductVariantActionTest.php`, `ProductCatalogModelTest.php` — action
  and model-level coverage for the product/variant/option graph.
- `tests/Feature/Catalog/ProductManagementTest.php` (7 tests, including
  `staff_can_view_but_not_update_a_product`, which stayed red from Task 9
  until this task's frontend page existed for it to render),
  `ProductImageManagementTest.php`, `ProductOptionManagementTest.php`,
  `ProductVariantManagementTest.php` — full HTTP-level CRUD, authorization,
  and cross-tenant/cross-product ownership checks for every catalog
  controller.
- `tests/Feature/Inventory/AdjustInventoryActionTest.php`,
  `InventoryAdjustmentHttpTest.php`, `InventoryModelTest.php` — stock
  movement accounting (including "cannot go negative") at both the action
  and HTTP layer, plus the staff-can-adjust-but-not-restructure-catalog
  authorization split.

**Total: 122 backend tests / 423 assertions passing on Postgres (up from 71
tests / 284 assertions at the end of Phase 1 — 51 new tests / 139 new
assertions this phase). 3 frontend (Vitest) tests unchanged.**

### Quality results

| Gate | Result |
|---|---|
| Pint | ✅ Pass (173 files) |
| Larastan (level 7) | ✅ No errors (137 files analyzed) |
| Pest | ✅ 122 passed, 423 assertions (real Postgres) |
| ESLint | ✅ Pass |
| Prettier | ✅ Pass |
| TypeScript (`tsc --noEmit`) | ✅ Pass |
| Vitest | ✅ 3 passed |
| Production build (`vite build`) | ✅ Succeeds |
| SSR build (`vite build --ssr`) | ✅ Succeeds |
| `ProductManagementTest` | ✅ 7/7 (was 6/7 before this task — `test_staff_can_view_but_not_update_a_product` needed `products/edit.tsx` to exist) |
| Curl smoke test (real HTTP, cookie-jar login, against the running dev stack) | ✅ Owner GET on `/dashboard/products/{id}` → 200, Inertia payload resolves the `dashboard/products/edit` component with the exact prop shape the page expects (`product.category`, `images[]`, `options[]`, `variants[]`, `can.update: true`); staff GET on the same URL → 200 with `can.update: false`. All markers (`Detail produk`, `Gambar`, `Opsi`, `Varian`, …) confirmed present in the compiled page bundle. |

### Known limitations

- No dedicated Policy unit tests, matching the project's established style
  of exercising policies through their controllers' HTTP feature tests.
- The public storefront (product listing/detail pages customers browse) is
  entirely out of scope for this phase — the dashboard is the only
  interface to the catalog so far.
- Image reordering (`moveImage`) always sends the *full* reordered
  `image_ids` array on every ↑/↓ click rather than a partial diff; fine at
  the 6-image cap this phase enforces, but worth revisiting if that cap
  ever grows.

### Final review fixes (post-completion, whole-branch review)

A whole-branch review performed after all 15 tasks landed found 3 Critical,
cross-cutting issues invisible to any single task's own review (each task
passed independently) — all fixed in this pass, verified, and committed as
3 separate commits:

- **Broken image URLs.** `ProductImageController::store()` has always saved
  uploads to the `s3` disk (MinIO locally — this app's `FILESYSTEM_DISK`),
  but `products/edit.tsx` built `<img src>` as `/storage/${image.path}`,
  the local `public` disk's symlink path — a different disk entirely. Every
  uploaded image was a broken `<img>` tag. Fixed by adding a `url` accessor
  to `ProductImage` (`Storage::disk('s3')->url($this->path)`, appended via
  `$appends` so it's always present on the Inertia payload) and switching
  the frontend to `image.url`. Verified end-to-end with a curl smoke test:
  uploaded a real file through the running dev stack and confirmed
  `images[].url` in the Inertia JSON resolves to the actual MinIO URL
  (`http://localhost:9000/usaharumahan/products/...`), not `/storage/...`.
- **Cross-tenant category assignment.** A user who is Owner of two tenants
  at once (explicitly supported — see `ResolveTenantForDashboard`) could
  act on a route-bound model belonging to a tenant they own but do *not*
  currently have selected in session, because every catalog/inventory
  Policy only checked generic membership, never "is this the active
  tenant." Concretely: with tenant A selected, `PUT
  /dashboard/products/{productB}` passed `ProductPolicy::update()` (true —
  they own B too), but `UpdateProductRequest`'s `category_id` rule
  validated against tenant A (the session-selected tenant via
  `TenantContext`), so a category belonging to A could be silently
  attached to a product in B — a cross-tenant foreign key. Fixed by adding
  an `assert*BelongsToActiveTenant()` guard (404 on mismatch) to every
  controller method that receives a tenant-owned model via route binding:
  `CategoryController::update/destroy`, `ProductController::edit/update/
  destroy`, and the `$product` parameter in `ProductOptionController`,
  `ProductVariantController`, `ProductImageController`, and
  `InventoryController`. A new regression test
  (`ProductManagementTest::test_an_owner_of_two_tenants_cannot_assign_the_non_active_tenants_category_to_the_other_tenants_product`)
  proves the exact scenario is now blocked (404, category never written).
- **No UI to add a variant.** `products/edit.tsx` could edit/delete
  existing variants and add product options, but never called the
  already-built-and-tested `ProductVariantController::store()` — there was
  no way to actually create a sellable variant from an option's values,
  breaking the module's own core vertical slice (category → product →
  option → **variant** → image → stock). Fixed by adding an "Add variant"
  form (price, sale price, weight in grams, SKU suffix, and one
  option-value picker per option when the product has options), gated
  behind `can.update` like every other catalog-write control on this page.

**Post-fix totals: 123 backend tests / 426 assertions passing on Postgres
(122/423 plus the 1 new Fix-2 regression test / 3 new assertions). 3
frontend (Vitest) tests unchanged. Pint, Larastan (level 7), ESLint,
Prettier, `tsc --noEmit`, `vite build`, and `vite build --ssr` all still
pass.**

### Storefront catalog listing/detail (public)

Closes the "public listing/detail" item still open in Phase 2's scope, per
`docs/superpowers/plans/2026-07-31-storefront-catalog-backend.md` and
`docs/superpowers/plans/2026-07-31-storefront-catalog-frontend.md` (design
specs: `docs/superpowers/specs/2026-07-31-storefront-catalog-listing-design.md`,
`docs/superpowers/specs/2026-07-31-storefront-visual-design.md`).

- `Product::scopePublished()` — the single source of truth for "is this
  product visible to a customer" (`status = Active`, `published_at` set and
  in the past), used by both the home grid and detail queries so neither
  re-implements the condition inline. `Product->displayPrice()` (cheapest
  effective price across variants, `isFrom` flagging a range) and
  `Product->isInStock()` round out the model.
- `Store->logo_url`/`banner_url` accessors (resolved from the `s3` disk,
  same pattern as `ProductImage::url()`) and `Store->toStorefrontArray()`.
- `HomeController` now lists published products on `/` (hostname-resolved
  via `TenantContext`, not session-selected); `Storefront\ProductController
  ::show` adds `GET /produk/{slug}`, looking the product up manually by
  `tenant_id` + `slug` (never route-model binding, since binding can't
  scope by the hostname-resolved tenant) and rendering options/variants/
  stock for the option picker.
- Visual layer: a `.storefront`-scoped CSS token system (fixed "paper/ink"
  neutrals + tenant-derived OKLCH accent colors — hue taken from
  `Store.primary_color`, lightness/chroma clamped so any seller-chosen hex
  stays legible) isolated from the dashboard's shadcn theme, Fraunces
  (headings) / Figtree (body) fonts, and a shared `StorefrontLayout` +
  `ProductCard`. Small pure-function libs (`currency`, `tenant-theme`,
  `variant-resolution`) carry logic unit-tested independently of React.
- Pages: the home grid (banner/logo, open/closed badge, WhatsApp link,
  product grid with "Mulai dari" range pricing and out-of-stock badges),
  the product detail page (image gallery, option picker resolving to a
  variant client-side from the full variant list already sent — no round
  trip — price/stock reflecting the selected variant), and a branded
  `storefront/not-found` page rendered with a 404 status for any
  missing/foreign/unpublished product or slug.
- No cart, checkout, WhatsApp order deep-linking, search/category filters,
  pagination, or SEO metadata — all explicitly out of scope for this slice
  (Phase 3+ per `docs/ROADMAP.md`).

**Totals: 141 backend tests / 528 assertions passing on Postgres (up from
123/426 — 18 new tests / 102 new assertions this slice). 26 frontend
(Vitest) tests (up from 3 — 23 new: `currency`, `tenant-theme`,
`variant-resolution`, `product-card`). Pint, Larastan (level 7), ESLint,
Prettier, `tsc --noEmit`, `vite build`, and `vite build --ssr` all pass.**

**Known limitation:** the manual browser smoke test (Task 9 of the frontend
plan) could not be completed this session — the dev machine's host-to-
container Docker networking broke while this work was in progress (traffic
to the compose stack's custom bridge network stopped reaching any
container from the host, even though container-to-container traffic and
the app's own automated test suite, which runs entirely inside the `app`
container, were unaffected). Diagnosed down to packets not reaching
Docker's own `DOCKER`/`FORWARD` iptables chains at all — most likely a
separate `nftables` ruleset (from `ufw`) or a kernel bridge-forwarding
setting outside this repo's scope. Every other verification gate in the
table above passed against the real stack (backend tests hit real
Postgres/Redis/MinIO inside the `app` container). A manual pass in a
browser against a seeded tenant subdomain is still worth doing once the
host networking is sorted.

### Next phase

Phase 3 — cart and storefront: guest cart, category pages, search, mobile
UI polish, SEO metadata, SSR storefront, per `docs/ROADMAP.md`.
