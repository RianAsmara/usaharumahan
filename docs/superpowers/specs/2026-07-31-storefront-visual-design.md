# Storefront visual design — warm/handmade theme

**Date**: 2026-07-31
**Status**: Approved
**Phase**: 2 (visual layer for the slice specced in
`docs/superpowers/specs/2026-07-31-storefront-catalog-listing-design.md`)

## Context

The functional/data spec for the storefront catalog listing and product
detail page is approved and unimplemented. Before building it, the current
placeholder (`resources/js/pages/storefront/home.tsx`) was reviewed and
found to be styled entirely with the dashboard's shadcn/ui admin theme
(`resources/css/app.css`'s grayscale oklch tokens) — the same flat
black/white palette used for the internal seller dashboard. A public
storefront selling from Indonesian home-based/small businesses (UMKM) to
local customers needs its own visual identity, not a reskinned admin tool.

This spec covers the visual design only: color system, typography, and the
layout/treatment of the home page, product grid, product detail page, and a
branded 404. It does not change any route, data shape, or scope decision
from the approved data spec — it fills in the "styled consistently" note
that spec deferred.

## Design direction

**Mood**: warm & handmade — evokes a well-loved local shop, not a generic
SaaS dashboard or a high-density marketplace grid.

**Per-tenant branding**: each `Store` already has a `primary_color` field.
Sellers are shop owners, not designers (UMKM audience) — the theme uses
their color as the storefront's dominant palette (full theme override, not
a small accent), but derives it algorithmically so no hex a seller picks
can render illegibly. See "Color system" below.

## Color system

Two kinds of tokens, both CSS custom properties scoped under a `.storefront`
wrapper class (never touching the dashboard's existing shadcn tokens in
`resources/css/app.css`):

**Fixed tokens** (identical across every tenant):

| Token | Value | Use |
|---|---|---|
| `--storefront-paper` | `oklch(98% 0.015 80)` | page background |
| `--storefront-paper-muted` | `oklch(96% 0.02 75)` | card surfaces |
| `--storefront-ink` | `oklch(22% 0.02 60)` | body text |
| `--storefront-border` | `oklch(90% 0.02 70)` | card/input borders |

**Tenant-derived tokens**, computed from `store.primary_color` by
`resources/js/lib/tenant-theme.ts`'s `deriveTenantPalette(hex)`:

1. Convert the hex to OKLCH, keep only the hue (`H`).
2. `--tenant-accent`: `oklch(48% min(C, 0.15) H)` — lightness is fixed at
   48% and chroma capped at 0.15 regardless of the input color's own L/C.
   This guarantees the accent always has enough contrast against
   `--storefront-paper` and always takes white text on top, whether the
   seller picked a pastel, a neon, or a near-black hex.
3. `--tenant-accent-hover`: same formula, `L=40%`.
4. `--tenant-tint`: `oklch(94% 0.03 H)` — a light wash for hover states and
   the banner fallback gradient.

Used for: buttons, links, price text, active option-picker pills, banner
fallback gradient, logo-placeholder background.

**Never tenant-derived**: open/closed badge, "Stok habis" (out of stock)
badge. These stay on a fixed semantic scale (sage green / warm gray /
terracotta) so stock status reads instantly regardless of a store's brand
color.

**SSR correctness**: the app renders through Inertia SSR. `StorefrontLayout`
calls `deriveTenantPalette` during render and applies the result as inline
`style` custom properties on its root element — since the same React tree
renders server-side, the server-rendered HTML already has the correct
colors, with no client-side flash/recolor after hydration.

## Typography

- Headings (store name, product name, section titles): **Fraunces** — a
  warm humanist serif with soft, slightly irregular letterforms, common in
  indie/craft/boutique branding. Weights 400/500/600/700.
- Body/UI text (descriptions, prices, buttons, badges, option labels):
  **Figtree** — warm rounded humanist sans, good numeral legibility for
  prices. Weights 400/500/600.
- Both loaded via the existing `bunny()` self-hosted font pattern in
  `vite.config.ts` (currently used for "Instrument Sans"). Deliberately
  different family from the dashboard's Instrument Sans, so the storefront
  doesn't read as the same admin tool reskinned.

## Home page (`GET /`, tenant resolved)

**Header**: full-width banner (`store.banner_path`), aspect ~21:9 desktop /
~16:9 mobile. No banner → diagonal gradient wash from `--tenant-tint` to
`--storefront-paper` with a subtle repeating dot-grain SVG texture overlay
(cheap, avoids a flat placeholder look). Circular logo (80px,
`store.logo_path`) overlaps the banner's bottom-left edge with a cream ring
border; no logo → circular initial-letter badge filled `--tenant-accent`.
Below the banner: store name (Fraunces, large) with the open/closed pill
badge inline; description text; WhatsApp CTA (filled `--tenant-accent`,
white text, rounded-full, WhatsApp glyph) when `whatsappNumber` is set.

**Product grid**: 2 columns mobile → 3 at `md` → 4 at `xl`. Each
`ProductCard`: product photo at 4:5 aspect (slightly portrait, market-stall
feel rather than e-commerce thumbnail), rounded-xl corners, soft warm
shadow (not a hard drop shadow). Name below in Figtree medium. Price in
Figtree semibold, `--tenant-accent` color; "Mulai dari" muted-label prefix
shown only when the product's variants have more than one distinct
effective price (per the data spec's pricing rule). Out-of-stock: photo
desaturated slightly, small terracotta "Stok habis" badge overlaid
bottom-left on the image; card remains clickable.

**Empty state** (zero published products): centered line-art SVG (basket/
shelf icon in `--tenant-tint`) with copy "Belum ada produk dipajang" /
"Pantau terus, produk akan segera hadir" — reads as an empty shelf, not a
broken page.

## Product detail page (`GET /produk/{slug}`)

**Layout**: single column mobile; two-column `md+` with a sticky image
gallery on the left. A "← Kembali ke etalase" link at the top (to `/`) —
required since there's no persistent site nav yet, and customers land here
from a shared link with no other way back.

**Gallery**: large main image (rounded-xl, same warm shadow as cards).
Multiple images → thumbnail strip (horizontal scroll on mobile, vertical
rail on desktop), active thumbnail bordered in `--tenant-accent`.
Single-image products show no strip.

**Info panel**: product name (Fraunces, larger than card treatment); price
directly below in `--tenant-accent` (Figtree semibold) reflecting the
selected variant combination — a `sale_price` shows the original price
struck through in muted ink beside it. Stock line under the price ("Stok
tersedia", sage dot / "Stok habis", terracotta dot) reflecting the current
selection.

**Option pickers**: one row per option (e.g. "Ukuran"), uppercase Figtree
label, soft pill buttons per value. Selected: filled `--tenant-accent` /
white text. Unselected: outlined `--storefront-ink` at low opacity.
Combinations that resolve to zero stock stay visible at reduced opacity
with a small "habis" sub-label and are non-clickable — matches the data
spec's requirement that out-of-stock combinations remain visible rather
than hidden, since the future cart phase reuses this pattern.

**Description**: below the pickers, Figtree body copy, generous
line-height, preserving the seller's line breaks.

**No purchase action**: per the approved data spec this slice is
informational only (cart/WhatsApp order deep-linking is Phase 3) — the
panel ends at stock status, no dead-end "Beli" button.

## Branded 404

A product that doesn't exist, belongs to another tenant, or isn't published
currently 404s straight to Laravel's generic error page, which would jar
against a crafted storefront. `Storefront\ProductController::show` instead
explicitly renders a new `pages/storefront/not-found.tsx` Inertia page
(same `.storefront` paper background, "Produk tidak ditemukan" in Fraunces,
link back to `/`) with a 404 status code.

## Technical notes

- New: `resources/js/lib/tenant-theme.ts` (`deriveTenantPalette`),
  `resources/js/components/storefront/storefront-layout.tsx`,
  `resources/js/components/storefront/product-card.tsx` (per the data
  spec), `resources/js/pages/storefront/products/show.tsx` (per the data
  spec), `resources/js/pages/storefront/not-found.tsx`.
- Updated: `resources/js/pages/storefront/home.tsx` (rewrite),
  `resources/css/app.css` (add `.storefront`-scoped fixed tokens and
  Fraunces/Figtree font-family assignments), `vite.config.ts` (add Fraunces
  and Figtree via the existing `bunny()` plugin).
- `deriveTenantPalette` is a pure function (hex string in, CSS custom
  property values out) — straightforward to unit test with a handful of
  edge-case inputs (pale, neon, near-black, near-white hex values) to
  confirm the clamped output always stays within the intended L/C bounds.

## Out of scope

Everything the data spec already excludes (cart, checkout, WhatsApp
order-context deep-linking, search/category filters, pagination, SEO
metadata) remains out of scope here too. This spec doesn't touch routes,
queries, or data shape — only presentation.
