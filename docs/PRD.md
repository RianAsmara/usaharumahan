# Product Requirements — UsahaRumahan Marketplace

## 1. Product Summary

UsahaRumahan Marketplace is a multi-tenant SaaS platform that lets Indonesian
individuals and small businesses ("usaha rumahan") run their own online
store. It is **not** an aggregator marketplace: each tenant has an
independent storefront, catalog, and checkout — there is no shared catalog
or cross-seller cart.

Each tenant is reachable at a subdomain of the platform's root domain:

```text
dapur-ibu.usaharumahan.id
kerajinan-lombok.usaharumahan.id
```

and can later verify a custom domain:

```text
www.dapuribu.com
```

## 2. Personas

| Persona | Description | Primary goals |
|---|---|---|
| **Owner** | Runs the small business, registered the tenant | Set up the store fast, manage catalog/orders/payments without technical help |
| **Staff** | Employee/family member helping run the shop | Fulfil orders, manage stock, without access to sensitive settings |
| **Customer** | Shopper in Indonesia, mobile-first | Browse, buy without creating an account, pay via familiar local methods, track an order |
| **Platform admin** | UsahaRumahan operator | Keep the platform healthy: activate/suspend tenants, watch for failed integrations |

## 3. MVP Scope

- Tenant onboarding (register → create store → subdomain → first product → publish).
- Catalog: categories, products, variants, images, inventory with movement history.
- Guest storefront: browsing, search, cart, checkout — no customer account required.
- Checkout: idempotent, server-priced, inventory-reserving, with order snapshots.
- Payments: manual bank transfer, cash on delivery, and a fake gateway for
  dev/tests, built behind a `PaymentGateway` contract so a real Indonesian
  provider (Midtrans/Xendit) can be added later without touching order logic.
- Shipping: pickup, tenant-defined flat/zone rates, behind a `ShippingProvider`
  contract.
- WhatsApp checkout as a secondary flow (order is still persisted server-side).
- Vouchers: fixed/percentage discounts, usage limits, per-customer limits.
- Owner dashboard: products, categories, inventory, orders, vouchers, store
  settings, domains, payments/shipping config, team, basic analytics.
- Platform admin: tenant list/search/suspend/reactivate, basic GMV/health view.
- Custom domains: verification flow, hostname-to-tenant resolution.

## 4. Out of Scope (MVP)

Aggregated marketplace catalog, multi-seller checkout, split payments, seller
wallets/payouts, platform commissions, native mobile app, live shopping,
product reviews, internal chat, affiliate system, loyalty points, full
accounting, multi-warehouse inventory, Shopee/TikTok Shop sync, advanced
abandoned-cart automation, AI product generation, visual page builder,
Kubernetes, microservices, Elasticsearch, event streaming infrastructure,
complex subscription billing.

## 5. User Journeys

**Owner onboarding**
Register/login → create business → set store name → pick subdomain → enter
WhatsApp number & address → upload logo → configure fulfillment → add first
product → publish store.

**Customer purchase**
Open tenant storefront → browse/search → view product, pick variant → add to
cart → checkout (guest details, shipping option, payment option, optional
voucher) → order confirmation + payment instructions → track order via a
secure link.

**Owner order fulfillment**
See new order on dashboard → confirm payment (manual transfer) or receive
paid webhook → mark processing → add shipment info → mark shipped →
customer/owner see it completed.

## 6. Functional Requirements

See section 11 of the original build brief (`AGENTS.md`-equivalent
instructions) for the exhaustive module-by-module functional requirements
covering tenant onboarding, catalog, inventory, storefront, cart, checkout,
orders, payments, shipping, WhatsApp checkout, vouchers, dashboard, platform
admin, and custom domains. This PRD summarizes; `docs/ARCHITECTURE.md` and
`docs/DATABASE.md` translate those requirements into the concrete system
design being implemented phase by phase (tracked in `docs/PROGRESS.md`).

## 7. Non-Functional Requirements

- **Tenant isolation**: a user from tenant A must never read/write tenant B's
  data, in the database, in file storage, or in cache keys.
- **Correctness under concurrency**: checkout must not oversell inventory;
  duplicate checkout requests (same idempotency key) and duplicate payment
  webhooks must not create duplicate orders/payments.
- **Money correctness**: all amounts are integer Indonesian Rupiah, never
  float.
- **Mobile-first, Bahasa Indonesia**: the storefront targets Indonesian
  customers on low-end mobile devices, in Bahasa Indonesia, IDR formatting.
- **Security**: CSRF protection, tenant-scoped authorization via policies,
  rate limiting on sensitive endpoints, webhook signature verification,
  audit logging of sensitive actions.
- **Observability**: structured logs carrying a request ID, tenant ID, and
  user ID (see `app/Http/Middleware/AddRequestId.php`).

## 8. Acceptance Criteria

The MVP is accepted when every checklist item in section 29
("Definition of Done") of the build brief is true and verifiably tested —
see `docs/TESTING.md` for how each is verified and `docs/PROGRESS.md` for
current status against that list.
