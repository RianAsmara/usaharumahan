# Storefront catalog listing & product detail — design

**Date**: 2026-07-31
**Status**: Approved
**Phase**: 2 (completes the deferred "public listing/detail" slice from
`docs/superpowers/specs/2026-07-29-phase2-catalog-inventory-design.md`)

## Context

Phase 2 built the full dashboard-side catalog (categories, products, images,
options, variants, inventory) but deliberately deferred the public-facing
storefront pages — see `docs/PROGRESS.md`'s "Next phase" note. The tenant
home page (`GET /`, `App\Http\Controllers\Storefront\HomeController`)
currently renders store info with a "Katalog produk akan segera hadir di
sini" (catalog coming soon) placeholder instead of any products.

This spec covers only the view slice: a customer can open a tenant's
storefront, browse published products, and view a product's detail
including variant/option selection and live stock. **No cart, checkout, or
search** — those are Phase 3 (`docs/ROADMAP.md`), a separate spec. This
keeps the vertical slice matching ROADMAP.md's priority 1: "Owner registers
→ ... → adds inventory → public customer opens the tenant storefront and
sees the product."

## Out of scope

- Cart, checkout, WhatsApp order deep-linking with product/variant context
  (Phase 3/4).
- Search and category-filter UI on the storefront (Phase 3).
- Pagination — the dashboard product list also renders unpaginated
  (`ProductController::index` uses `->get()`, no `paginate()`); this MVP
  targets small individual-business catalogs, so the storefront grid
  follows the same precedent. Revisit if real usage shows this is wrong.
- SEO metadata, SSR-specific storefront work (Phase 3).

## Routes

- `GET /` — existing `HomeController::__invoke`. Updated to also pass the
  tenant's published products to the `storefront/home` Inertia page.
- `GET /produk/{slug}` — new `Storefront\ProductController::show`. The
  product is looked up manually by `tenant_id` (from `TenantContext`) +
  `slug`, not via Laravel's implicit route-model binding, because binding
  can't scope by the hostname-resolved tenant. Returns 404 if the product
  doesn't exist, belongs to a different tenant, or isn't publicly visible
  (see below) — the same shape as the `assert*BelongsToActiveTenant()` 404
  guards already used on the dashboard side
  (`docs/PROGRESS.md`'s "Final review fixes" section), applied here against
  the hostname-resolved tenant instead of the session-selected one.
- Both routes require a resolved tenant (they sit behind
  `ResolveTenantFromHostname`, already global `web` middleware per Phase 1);
  `HomeController` already branches to the marketing `welcome` page when no
  tenant resolves, unchanged by this spec.

No new middleware, policies, or authorization — the storefront is public
and read-only; visibility is a data filter, not an authorization check.

## Data & visibility rules

New `Product::scopePublished()` local scope, the single source of truth for
"is this product visible to a customer":

```php
public function scopePublished(Builder $query): void
{
    $query->where('status', ProductStatus::Active)
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}
```

Both routes use this scope. A later "unpublish" action or scheduled
publishing UI only ever has one place to change.

**Home grid query**: `Product::published()->where('tenant_id', $tenant->id)`,
eager-loading the first image only and `variants.inventory`, mapped to a
lean array (id, slug, name, image URL, display price, in-stock boolean) —
the full model is never sent to the frontend.

**Card price**: cheapest `sale_price ?? price` across the product's
variants. Label is prefixed "Mulai dari" (from) only when the product's
variants have more than one distinct effective price; otherwise the plain
price is shown.

**Card stock**: `true` if any variant has `on_hand - reserved > 0`
(`Inventory::available()`, already exists).

**Detail page query**: same `published()` scope, single product, eager-loads
`images`, `options.values`, `variants.optionValues`, `variants.inventory`.
Sent to the frontend as a full variant list (price, sale_price, the
option-value IDs that identify the combination, available stock) so the
client resolves option-picker selections to a variant without a round trip.

## Frontend

- `resources/js/lib/currency.ts` (new) — `formatRupiah(amount: number): string`
  (`Rp 25.000` style, no decimals). Extracted because both the card and
  detail page need identical formatting; currently each page that shows a
  price inlines its own.
- `resources/js/components/storefront/product-card.tsx` (new) — image, name,
  price, "Stok habis" badge when out of stock. Used by the home grid.
- `resources/js/pages/storefront/home.tsx` (updated) — keep the existing
  store header/WhatsApp block; replace the placeholder text with a
  responsive grid of `ProductCard`s, each linking to `/produk/{slug}`; an
  empty-state message when the tenant has zero published products.
- `resources/js/pages/storefront/products/show.tsx` (new) — main image plus
  thumbnail strip when more than one image; name/description; one option
  picker per product option (e.g. Size: S/M/L as buttons); the selected
  combination resolves client-side to a variant, showing its price and
  stock. Combinations with zero stock remain visible but disabled with
  "Stok habis" (matches the pattern the future cart phase will reuse,
  rather than hiding out-of-stock options). Bahasa Indonesia copy,
  mobile-first, styled consistently with the existing home page. No
  cart/order action — informational only, per this slice's scope.

## Testing

- `tests/Feature/Storefront/ProductListingTest.php`:
  - home page lists only published products (excludes draft, archived, and
    products with a future `published_at`);
  - excludes other tenants' products even when they'd otherwise match;
  - shows the "Mulai dari" price when variant prices differ, plain price
    when they don't;
  - shows correct in-stock/out-of-stock state.
- `tests/Feature/Storefront/ProductDetailTest.php`:
  - renders variants/options/stock correctly for a published product;
  - 404s for a draft, archived, or not-yet-published product;
  - 404s for a product belonging to a different tenant (even with the
    correct slug, requested against the wrong tenant's hostname);
  - 404s for a nonexistent slug.

## Next phase

Phase 3 — Cart and storefront: search, guest cart, mobile UI polish, SEO
metadata, SSR storefront rendering, per `docs/ROADMAP.md`.
