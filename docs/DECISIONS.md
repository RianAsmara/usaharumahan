# Architecture Decision Records

Lightweight ADRs — one entry per significant decision, why it was made, and
what it rules out. New decisions are appended, not rewritten in place;
superseded decisions are marked as such rather than deleted.

## ADR-001: Laravel modular monolith, not microservices

**Decision**: One Laravel deployable, organized by feature module
(`app/Actions/{Catalog,Checkout,Payment,...}`), not a service-per-module
split.

**Why**: The MVP's scale doesn't need independent scaling/deployment per
module, and a monolith keeps checkout's cross-module transaction (cart →
inventory → order → payment, see `docs/ARCHITECTURE.md` §4) a single
database transaction instead of a distributed-transaction problem. Section
26 of the build brief explicitly rules out microservices for this MVP.

## ADR-002: Shared-database, shared-schema tenancy

**Decision**: Every tenant lives in the same PostgreSQL database and schema,
distinguished by a `tenant_id` column, not a database-per-tenant or
schema-per-tenant model.

**Why**: Database-per-tenant would multiply operational cost (migrations,
backups, connection pools) for a platform whose tenants are individually
small (home businesses), and would make platform-admin cross-tenant queries
(GMV, tenant list) require fan-out instead of a single query. The isolation
cost is paid instead in defense-in-depth application code — see
`docs/TENANCY.md`.

## ADR-003: Inertia + React, not a separate SPA/API

**Decision**: Inertia.js bridges Laravel controllers directly to React
pages; there is no separate REST/GraphQL API for the dashboard or
storefront.

**Why**: Avoids duplicating validation/authorization logic between a
backend API and a frontend that would otherwise need its own contract
layer. Backend validation stays authoritative (build brief §17) without a
parallel API surface to keep in sync. A JSON API is only introduced where
something genuinely needs one (payment webhooks), not for the whole app.

## ADR-004: Integer Rupiah for all money

**Decision**: Every monetary column is an integer (whole Rupiah); no
`float`/`double` ever represents money, in the database or in PHP.

**Why**: Float arithmetic on money produces rounding errors that compound
across discounts, shipping, and totals — unacceptable for anything touching
a checkout total. `App\Support\Money\Money` is the single place arithmetic
on money happens (introduced in Phase 4).

## ADR-005: Inventory reservation via row locking, not optimistic retry

**Decision**: Checkout reserves stock by `SELECT ... FOR UPDATE`-locking the
variant's inventory row inside the checkout transaction, incrementing
`reserved` and validating `on_hand - reserved >= quantity` before
committing.

**Why**: Two customers racing for the last unit must resolve to exactly one
success, deterministically, without a retry loop that could still race under
load. Row locking makes the second transaction wait for the first to commit
or roll back, rather than both reading stale availability and both
"succeeding" optimistically. This is also why tests must run against real
Postgres (`docs/TESTING.md` §2) — SQLite's locking semantics wouldn't catch
a broken version of this.

## ADR-006: Payment adapter pattern (`PaymentGateway` contract)

**Decision**: Order-domain code depends only on
`App\Contracts\Payments\PaymentGateway`; `ManualBankTransferGateway`,
`CashOnDeliveryGateway`, and `FakePaymentGateway` are the Phase 5
implementations, with the door left open for a real Indonesian provider
(Midtrans/Xendit) later.

**Why**: No production payment credentials exist yet, and the build brief
explicitly requires the MVP not be blocked on obtaining them. The contract
means adding a real provider later is additive (a new class + config
switch), not a rewrite of checkout/order logic.

## ADR-007: Guest checkout, no customer account

**Decision**: Customers check out without registering; `customers` rows are
created/updated as a side effect of checkout, not as an auth-gated flow.

**Why**: The target user (a shopper in Indonesia buying from a small home
business) should not face signup friction for a one-off purchase. Order
tracking instead uses a non-guessable token (`docs/DATABASE.md` §9), which
doesn't require account state to secure.

## ADR-008: Inertia SSR enabled for the storefront

**Decision**: The public storefront is server-rendered via Inertia's
official SSR setup (`inertia-ssr` process in `compose.yaml`,
`npm run build:ssr` verified working in Phase 0); the authenticated
dashboard is not SSR'd (no SEO requirement, and SSR would add latency to
an already-authenticated, JS-heavy surface for no benefit).

**Why**: Product/category pages need to be indexable and fast on first
paint for Indonesian mobile customers (build brief §11.4); the SSR build
was verified to compile cleanly against Laravel 13 / Inertia 3 / React 19
in Phase 0, so there was no blocking compatibility issue requiring a
fallback plan.

## ADR-009: Google OAuth via Socialite, verified-email linking only

**Decision**: Owners may log in with Google; an OAuth login links to an
existing `users` row only when the Google account's email is verified and
matches, recorded in a dedicated `social_accounts` table rather than merged
into `users` directly.

**Why**: Auto-merging by unverified email would let an attacker who
controls an unverified mailbox take over an existing account by "logging in
with Google" using that address. Keeping identities in a separate table
also means a user can unlink Google without losing their account.

## ADR-010: Pest as the test runner, PostgreSQL as the test database

**Decision**: Pest 4 (PHPUnit-compatible) runs all backend tests, and
`phpunit.xml` forces every test run onto a real Postgres database
(`usaharumahan_testing`), never SQLite.

**Why**: The starter kit ships PHPUnit-style test classes by default; Pest
runs them unmodified while giving new tests a lighter functional syntax.
SQLite would silently pass tests for Postgres-specific check constraints
and row-locking behavior that don't exist in SQLite at all — see
`docs/TESTING.md` §2 for the concrete failure mode this avoids.

## ADR-011: `laravel/react-starter-kit` `main` branch as the Phase 0 base

**Decision**: The project was scaffolded from
`laravel/react-starter-kit`'s `main` branch (not the last tagged release),
via `composer create-project laravel/react-starter-kit:dev-main`.

**Why**: The tagged release (`v1.0.1`) still targets `laravel/framework
^12.0`; `main` already targets `^13.17`, Inertia 3, and ships Wayfinder,
matching the mandated stack (Laravel 13, Inertia 3, React 19, TypeScript,
Tailwind 4). This is a one-time scaffold source, not an ongoing floating
dependency — the resulting `composer.json`/`composer.lock` pin exact,
non-floating versions from that point on.
