# Testing

## 1. Frameworks

- **Backend**: Pest 4 (running on PHPUnit 12 under the hood — existing
  PHPUnit-style `TestCase` classes from the starter kit remain valid; new
  tests are written in Pest's functional style).
- **Static analysis**: Larastan (phpstan) at level 7, `composer.json`'s
  `types:check` script.
- **Frontend unit/component**: Vitest + React Testing Library
  (`resources/js/**/*.test.tsx`, config in `vitest.config.ts`).
- **Browser**: Playwright, added when a phase needs full end-to-end coverage
  (storefront/checkout flows).

## 2. Why Tests Run Against Real PostgreSQL, Not SQLite

Production only ever runs on PostgreSQL. The schema uses Postgres-specific
`CHECK` constraints (non-negative money/inventory, valid percentage ranges)
and checkout inventory reservation depends on Postgres row locking
(`SELECT ... FOR UPDATE`) semantics. SQLite either ignores these constraints
outright or emulates locking differently, which would let a broken
constraint or a broken reservation query pass its test while still being
broken in production. `phpunit.xml` therefore forces `DB_CONNECTION=pgsql`
and a dedicated `usaharumahan_testing` database for every test run — see the
comments in `phpunit.xml` for why both `<env force="true">` and `<server>`
entries are needed (docker compose's `env_file: .env` already populates the
container's real OS environment, which `<env>` alone does not override).

`DB_HOST`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` are deliberately **not**
forced in `phpunit.xml` — the app's normal connection settings already point
at the right Postgres server in every environment (the `postgres` Docker
Compose service locally, `127.0.0.1` in CI), so only the database name needs
overriding for tests.

## 3. Test Categories

- **Unit**: money calculations, voucher calculations, order state
  transitions, payment status mapping, shipping price calculation, domain
  normalization, phone normalization, idempotency fingerprinting — pure
  logic, no HTTP, no DB where avoidable.
- **Feature**: one file per user-facing flow (registration, tenant
  onboarding, product CRUD, cart, checkout, webhook handling, ...), using
  `RefreshDatabase` (wired globally for the `Feature` suite in `tests/Pest.php`).
- **Tenant isolation**: for every tenant-owned resource, an explicit test
  proves tenant A cannot read/write tenant B's row — see
  `docs/TENANCY.md` §8. These live next to the resource's other feature
  tests, not in a single generic file.
- **Concurrency/transaction**: two simultaneous requests for the last unit
  of stock → only one reservation succeeds; a payment webhook delivered
  twice → the order is paid exactly once; a repeated checkout with the same
  idempotency key → only one order exists; an expired unpaid order →
  reserved inventory is released exactly once. These require real row
  locking, which is exactly why they run on Postgres.

## 4. Fakes, Not Live Services

- `Storage::fake()` for every file-upload test.
- `Queue::fake()` where the test only needs to assert a job was dispatched;
  real queued-job execution tests where the job's *behavior* itself is under
  test.
- `Notification::fake()` for notification assertions.
- HTTP fakes (`Http::fake()`) for any real provider integration (payment
  gateway, shipping aggregator) — tests never make a real network call.
- `FakePaymentGateway` and manual/COD gateways are the only payment
  adapters exercised in CI; a real provider (Midtrans/Xendit) is added later
  behind the same `PaymentGateway` contract without changing order-domain
  tests.
- Time travel (`Carbon::setTestNow()` / `$this->travel()`) for expiration
  behavior (reservation expiry, unpaid order expiry, voucher windows).

## 5. Coverage Targets

Business actions and application code: at least 80% meaningful coverage.
Whole project: at least 70%. Coverage percentage is a floor, not the goal —
a covered line that never asserts the right thing is worse than an honest
gap, so behavioral assertions (not just "the code ran without throwing")
are what's reviewed.

## 6. Running Tests

```bash
# Backend
docker compose exec app php artisan test          # Pest, full suite
docker compose exec app ./vendor/bin/pint --test  # style check, no changes
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M

# Frontend
docker compose exec app npm run lint:check
docker compose exec app npm run types:check
docker compose exec app npm run test              # Vitest
docker compose exec app npm run build
docker compose exec app npm run build:ssr
```

CI (`.github/workflows/tests.yml`) runs the same commands against real
Postgres and Redis service containers, in the order listed in
`docs/PROGRESS.md`'s Phase 0 entry.
