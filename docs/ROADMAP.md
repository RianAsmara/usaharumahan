# Roadmap

Implementation proceeds phase by phase, each phase shipping a working
vertical slice rather than speculative infrastructure. Status is tracked
authoritatively in `docs/PROGRESS.md`; this file is the plan, that file is
the ledger.

| Phase | Scope | Status |
|---|---|---|
| 0 | Foundation: Laravel 13 + React starter kit, Postgres, Redis, Docker Compose, Boost, Horizon, Pint, Larastan, Pest, frontend tooling, CI, docs skeleton, health checks, request ID middleware | Complete |
| 1 | Authentication and tenancy: registration/login/verification, Google OAuth, Tenant/Membership/Store, `TenantContext`, hostname resolution, policies, platform-admin role, onboarding, cross-tenant tests | Complete |
| 2 | Catalog and inventory: categories, products, images, options, variants, inventory + movements, dashboard pages, public listing/detail | In progress (dashboard slice) |
| 3 | Cart and storefront: storefront layout, category pages, search, guest cart, mobile UI, SEO metadata, SSR storefront | Not started |
| 4 | Checkout and orders: customers/addresses, idempotent checkout, inventory reservation, order snapshots/history, reservation expiry, WhatsApp checkout, concurrency tests | Not started |
| 5 | Payments and shipping: payment contracts + manual/COD/fake adapters, idempotent webhooks, shipping contracts + pickup/flat/zone, shipment tracking, queued notifications | Not started |
| 6 | Vouchers, analytics, domains, platform admin: voucher validation/usage, tenant-scoped analytics, custom domain verification, platform-admin tenant management, audit logs | Not started |
| 7 | Hardening: full quality pass, security/performance/N+1/index review, queue retry review, production Docker review, backup/deployment docs, browser smoke tests, final README | Not started |

## Vertical slice priority (within phases 1–4)

1. Owner registers → creates tenant → creates store → creates category →
   creates product + variant → adds inventory → public customer opens the
   tenant storefront and sees the product.
2. Customer adds product to cart → checks out → inventory is reserved →
   order is created → owner sees the order.
3. Payment and shipping are layered on top of (2).

## Explicitly Out of Scope for the MVP

Aggregated marketplace catalog, multi-seller checkout, split payments,
seller wallets/payouts, platform commissions, native mobile app, live
shopping, product reviews, internal chat, affiliate system, loyalty points,
full accounting, multi-warehouse inventory, Shopee/TikTok Shop sync,
advanced abandoned-cart automation, AI product generation, visual page
builder, Kubernetes, microservices, Elasticsearch, event streaming
infrastructure, complex subscription billing. See `docs/PRD.md` §4.
