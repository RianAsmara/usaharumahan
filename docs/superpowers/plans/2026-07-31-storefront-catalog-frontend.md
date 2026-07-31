# Storefront Catalog Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the storefront's placeholder pages with the warm/handmade visual design, per `docs/superpowers/specs/2026-07-31-storefront-visual-design.md` — fonts, tenant-derived color system, the home product grid, the product detail page with an option picker, and a branded 404.

**Architecture:** A `.storefront`-scoped CSS token system (fixed "paper/ink" neutrals + tenant-derived OKLCH accent colors, isolated from the dashboard's shadcn theme) applied via a shared `StorefrontLayout` component; small pure-function libs (`currency`, `tenant-theme`, `variant-resolution`) carry the logic that both pages need, unit-tested independently of React; the product detail page's option picker resolves the customer's selection to a variant client-side from the full variant list the backend already sends, no round trip.

**Tech Stack:** Inertia 3 + React 19 + TypeScript, Tailwind v4, Vitest + React Testing Library, Laravel 13 (one controller touched for the branded 404).

## Global Constraints

- `--tenant-accent` is always derived from `Store.primary_color`, never the seller's raw hex: convert to OKLCH, keep the hue, clamp lightness to 48% (40% for hover) and chroma to ≤0.15, so any input hex renders legibly. Status badges (open/closed, out-of-stock) use fixed semantic tokens, never tenant-derived.
- Storefront CSS tokens/fonts live scoped under a `.storefront` wrapper class in `resources/css/app.css` and must never change the look of any `dashboard/*` or `welcome` page — the dashboard keeps its existing shadcn theme untouched.
- Bahasa Indonesia copy throughout, matching the existing placeholder's tone ("Katalog produk akan segera hadir di sini", "Buka"/"Tutup").
- No cart, checkout, WhatsApp order deep-linking, search/category filters, pagination, or SEO metadata — out of scope (Phase 3+).
- Run every command inside the app container: `docker compose exec app <command>` — this worktree's `.env` already pins `COMPOSE_PROJECT_NAME=usaharumahan-sdd` / `COMPOSE_FILE=compose.yaml:docker-compose.sdd-override.yml`, so commands run with this worktree as cwd target the isolated stack automatically.
- Backend props this plan consumes are already implemented and tested (`docs/superpowers/plans/2026-07-31-storefront-catalog-backend.md`, branch `worktree-storefront-catalog-backend`):
  - `storefront/home` receives `store: {name, description, whatsappNumber, isOpen, primaryColor, logoUrl, bannerUrl}` and `products: Array<{id, slug, name, imageUrl, price: {amount, isFrom}, inStock}>`.
  - `storefront/products/show` receives `store: {name, description, whatsappNumber, isOpen, primaryColor, logoUrl, bannerUrl}` and `product: {name, description, images: Array<{id, url}>, options: Array<{id, name, values: Array<{id, value}>}>, variants: Array<{id, price, salePrice, optionValueIds: string[], stock}>}`.
- Before this plan, `npm run types:check` may show unrelated pre-existing errors if `php artisan wayfinder:generate` was ever run without `--with-form` on this branch (`vite.config.ts` configures `wayfinder({ formVariants: true })`). If Task 9's verification hits this, run `docker compose exec app php artisan wayfinder:generate --with-form` (or `docker compose exec app npm run build`, which regenerates it correctly as a side effect) before re-checking — do not treat pre-existing unrelated errors as this plan's regression.

---

## File Structure

```
vite.config.ts                                                  (modify — Task 1)
resources/css/app.css                                            (modify — Task 1)
resources/js/lib/currency.ts                                     (create — Task 2)
resources/js/lib/currency.test.ts                                (create — Task 2)
resources/js/lib/tenant-theme.ts                                 (create — Task 3)
resources/js/lib/tenant-theme.test.ts                             (create — Task 3)
resources/js/lib/variant-resolution.ts                           (create — Task 4)
resources/js/lib/variant-resolution.test.ts                       (create — Task 4)
resources/js/components/storefront/storefront-layout.tsx         (create — Task 5)
resources/js/components/storefront/product-card.tsx              (create — Task 5)
resources/js/components/storefront/product-card.test.tsx         (create — Task 5)
resources/js/pages/storefront/home.tsx                            (rewrite — Task 6)
resources/js/pages/storefront/not-found.tsx                      (create — Task 7)
app/Http/Controllers/Storefront/ProductController.php            (modify — Task 7)
tests/Feature/Storefront/ProductDetailTest.php                    (modify — Task 7)
resources/js/pages/storefront/products/show.tsx                  (rewrite — Task 8)
```

---

### Task 1: Storefront fonts and CSS tokens

**Files:**
- Modify: `vite.config.ts`
- Modify: `resources/css/app.css`

**Interfaces:**
- Produces CSS custom properties: `--storefront-paper`, `--storefront-paper-muted`, `--storefront-ink`, `--storefront-border`, `--storefront-status-open`, `--storefront-status-closed`, `--storefront-status-warning` (all fixed, scoped under `.storefront`).
- Produces the `.storefront` and `.storefront .font-serif` font-family rules (Figtree body, Fraunces headings) and a `.storefront-banner-grain` texture class.
- Consumed by: Tasks 5–8 (every storefront component references these via `var(--storefront-*)` / `var(--tenant-*)` and the `font-serif` utility class).

No automated test for this task (pure CSS/config) — verified by a successful build.

- [ ] **Step 1: Add the fonts to Vite**

In `vite.config.ts`, change the `fonts` array to:

```ts
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Fraunces', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Figtree', {
                    weights: [400, 500, 600],
                }),
            ],
```

- [ ] **Step 2: Add the storefront-scoped tokens to `app.css`**

At the end of `resources/css/app.css` (after the existing `@layer base` block), add:

```css
.storefront {
    --storefront-paper: oklch(98% 0.015 80);
    --storefront-paper-muted: oklch(96% 0.02 75);
    --storefront-ink: oklch(22% 0.02 60);
    --storefront-border: oklch(90% 0.02 70);
    --storefront-status-open: oklch(55% 0.12 150);
    --storefront-status-closed: oklch(60% 0.02 70);
    --storefront-status-warning: oklch(58% 0.15 35);

    font-family:
        'Figtree', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji',
        'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}

.storefront .font-serif {
    font-family: 'Fraunces', ui-serif, Georgia, Cambria, serif;
}

.storefront-banner-grain {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Ccircle cx='2' cy='2' r='1' fill='%23000' fill-opacity='0.05'/%3E%3C/svg%3E");
    background-repeat: repeat;
}
```

- [ ] **Step 3: Verify the build picks up the new fonts and CSS**

Run: `docker compose exec app npm run build`
Expected: build succeeds with no errors; the Fraunces/Figtree font files appear among the built assets (check with `docker compose exec app sh -c "ls public/build/assets | grep -i fraunces"` — expect at least one match).

- [ ] **Step 4: Commit**

```bash
git add vite.config.ts resources/css/app.css
git commit -m "feat: add Fraunces/Figtree fonts and storefront CSS tokens"
```

---

### Task 2: `formatRupiah` currency helper

**Files:**
- Create: `resources/js/lib/currency.ts`
- Test: `resources/js/lib/currency.test.ts`

**Interfaces:**
- Produces: `formatRupiah(amount: number): string`.
- Consumed by: Tasks 5, 6, 8 (`ProductCard`, `home.tsx`, `products/show.tsx`).

- [ ] **Step 1: Write the failing test**

Create `resources/js/lib/currency.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { formatRupiah } from './currency';

describe('formatRupiah', () => {
    it('formats whole rupiah amounts with a period thousands separator', () => {
        expect(formatRupiah(25000)).toBe('Rp 25.000');
    });

    it('formats amounts under one thousand without a separator', () => {
        expect(formatRupiah(500)).toBe('Rp 500');
    });

    it('formats large amounts with multiple separators', () => {
        expect(formatRupiah(1250000)).toBe('Rp 1.250.000');
    });

    it('rounds fractional amounts', () => {
        expect(formatRupiah(19999.6)).toBe('Rp 20.000');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app npm run test -- currency`
Expected: FAIL — `Failed to resolve import "./currency"`.

- [ ] **Step 3: Implement**

Create `resources/js/lib/currency.ts`:

```ts
export function formatRupiah(amount: number): string {
    return `Rp ${Math.round(amount).toLocaleString('id-ID')}`;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app npm run test -- currency`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/currency.ts resources/js/lib/currency.test.ts
git commit -m "feat: add formatRupiah currency helper"
```

---

### Task 3: `deriveTenantPalette` — OKLCH color derivation

**Files:**
- Create: `resources/js/lib/tenant-theme.ts`
- Test: `resources/js/lib/tenant-theme.test.ts`

**Interfaces:**
- Produces: `hexToOklch(hex: string): {l: number, c: number, h: number}`.
- Produces: `deriveTenantPalette(hex: string): {accent: string, accentHover: string, tint: string}` — each value a CSS `oklch(...)` string.
- Produces: `DEFAULT_TENANT_COLOR: string` (a warm terracotta hex, `'#C2703D'`) — used when a tenant has no `primary_color`.
- Consumed by: Task 5 (`StorefrontLayout`).

- [ ] **Step 1: Write the failing tests**

Create `resources/js/lib/tenant-theme.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { deriveTenantPalette, hexToOklch } from './tenant-theme';

describe('hexToOklch', () => {
    it('extracts a hue for a saturated red', () => {
        const { h, c } = hexToOklch('#FF0000');

        expect(h).toBeGreaterThan(20);
        expect(h).toBeLessThan(40);
        expect(c).toBeGreaterThan(0.2);
    });

    it('returns near-zero chroma for a neutral gray', () => {
        const { c } = hexToOklch('#808080');

        expect(c).toBeLessThan(0.01);
    });

    it('returns high lightness for near-white', () => {
        const { l } = hexToOklch('#FEFEFE');

        expect(l).toBeGreaterThan(0.95);
    });

    it('returns low lightness for near-black', () => {
        const { l } = hexToOklch('#0A0A0A');

        expect(l).toBeLessThan(0.1);
    });
});

describe('deriveTenantPalette', () => {
    it('clamps chroma to 0.15 even for a highly saturated input', () => {
        const palette = deriveTenantPalette('#FF0000');
        const chroma = Number(palette.accent.match(/oklch\(48% ([\d.]+)/)?.[1]);

        expect(chroma).toBeLessThanOrEqual(0.15);
    });

    it('always fixes accent lightness at 48% and hover at 40%, regardless of input lightness', () => {
        const pale = deriveTenantPalette('#FFFACD');
        const dark = deriveTenantPalette('#1A1A2E');

        expect(pale.accent).toMatch(/^oklch\(48% /);
        expect(pale.accentHover).toMatch(/^oklch\(40% /);
        expect(dark.accent).toMatch(/^oklch\(48% /);
        expect(dark.accentHover).toMatch(/^oklch\(40% /);
    });

    it('produces a light, low-chroma tint', () => {
        const palette = deriveTenantPalette('#2E86AB');

        expect(palette.tint).toMatch(/^oklch\(94% 0\.03 /);
    });

    it('never crashes on an achromatic input', () => {
        expect(() => deriveTenantPalette('#FFFFFF')).not.toThrow();
        expect(() => deriveTenantPalette('#000000')).not.toThrow();
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app npm run test -- tenant-theme`
Expected: FAIL — `Failed to resolve import "./tenant-theme"`.

- [ ] **Step 3: Implement**

Create `resources/js/lib/tenant-theme.ts`:

```ts
export const DEFAULT_TENANT_COLOR = '#C2703D';

function srgbToLinear(channel: number): number {
    return channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
}

/**
 * sRGB hex -> OKLCH, using the standard OKLab conversion matrices
 * (Björn Ottosson, "A perceptual color space for image processing").
 */
export function hexToOklch(hex: string): { l: number; c: number; h: number } {
    const normalized = hex.replace('#', '');
    const r = srgbToLinear(parseInt(normalized.slice(0, 2), 16) / 255);
    const g = srgbToLinear(parseInt(normalized.slice(2, 4), 16) / 255);
    const b = srgbToLinear(parseInt(normalized.slice(4, 6), 16) / 255);

    const lCone = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
    const mCone = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
    const sCone = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b;

    const lRoot = Math.cbrt(lCone);
    const mRoot = Math.cbrt(mCone);
    const sRoot = Math.cbrt(sCone);

    const l = 0.2104542553 * lRoot + 0.793617785 * mRoot - 0.0040720468 * sRoot;
    const a = 1.9779984951 * lRoot - 2.428592205 * mRoot + 0.4505937099 * sRoot;
    const bLab = 0.0259040371 * lRoot + 0.7827717662 * mRoot - 0.808675766 * sRoot;

    const c = Math.sqrt(a * a + bLab * bLab);
    let h = (Math.atan2(bLab, a) * 180) / Math.PI;
    if (h < 0) h += 360;

    return { l, c, h };
}

export type TenantPalette = {
    accent: string;
    accentHover: string;
    tint: string;
};

/**
 * Derives the storefront's tenant-branded tokens from a seller-chosen hex
 * color. Lightness and chroma are fixed/clamped per role so any hex a
 * UMKM seller picks — pastel, neon, near-black — still renders legibly;
 * only the hue is taken from their input.
 */
export function deriveTenantPalette(hex: string): TenantPalette {
    const { c, h } = hexToOklch(hex);
    const clampedChroma = Math.min(c, 0.15).toFixed(3);
    const hue = h.toFixed(1);

    return {
        accent: `oklch(48% ${clampedChroma} ${hue})`,
        accentHover: `oklch(40% ${clampedChroma} ${hue})`,
        tint: `oklch(94% 0.03 ${hue})`,
    };
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec app npm run test -- tenant-theme`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/tenant-theme.ts resources/js/lib/tenant-theme.test.ts
git commit -m "feat: add OKLCH tenant color derivation"
```

---

### Task 4: Variant resolution helpers

**Files:**
- Create: `resources/js/lib/variant-resolution.ts`
- Test: `resources/js/lib/variant-resolution.test.ts`

**Interfaces:**
- Produces: `type Variant = {id: string, price: number, salePrice: number | null, optionValueIds: string[], stock: number}`.
- Produces: `findMatchingVariant(variants: Variant[], selected: Record<string, string>, optionIds: string[]): Variant | null`.
- Produces: `isValueAvailable(variants: Variant[], optionId: string, valueId: string, selected: Record<string, string>): boolean`.
- Consumed by: Task 8 (`products/show.tsx`).

- [ ] **Step 1: Write the failing tests**

Create `resources/js/lib/variant-resolution.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { findMatchingVariant, isValueAvailable, type Variant } from './variant-resolution';

const sizeSmall: Variant = { id: 'v-s', price: 10000, salePrice: null, optionValueIds: ['size-s'], stock: 5 };
const sizeMediumOutOfStock: Variant = { id: 'v-m', price: 10000, salePrice: null, optionValueIds: ['size-m'], stock: 0 };
const sizeLarge: Variant = { id: 'v-l', price: 12000, salePrice: null, optionValueIds: ['size-l'], stock: 3 };
const variants = [sizeSmall, sizeMediumOutOfStock, sizeLarge];

describe('findMatchingVariant', () => {
    it('returns null when not every option has a selection', () => {
        expect(findMatchingVariant(variants, {}, ['size'])).toBeNull();
    });

    it('returns the variant matching the full selection', () => {
        const result = findMatchingVariant(variants, { size: 'size-l' }, ['size']);

        expect(result?.id).toBe('v-l');
    });

    it('resolves a single-variant, zero-option product unconditionally', () => {
        const simple: Variant = { id: 'v-simple', price: 5000, salePrice: null, optionValueIds: [], stock: 2 };

        expect(findMatchingVariant([simple], {}, [])?.id).toBe('v-simple');
    });
});

describe('isValueAvailable', () => {
    it('is true for a value with at least one in-stock variant', () => {
        expect(isValueAvailable(variants, 'size', 'size-s', {})).toBe(true);
    });

    it('is false for a value whose only variant is out of stock', () => {
        expect(isValueAvailable(variants, 'size', 'size-m', {})).toBe(false);
    });

    it('accounts for other currently-selected option values', () => {
        const colorRedSmall: Variant = { id: 'v-red-s', price: 9000, salePrice: null, optionValueIds: ['color-red', 'size-s'], stock: 4 };
        const colorRedMediumOutOfStock: Variant = { id: 'v-red-m', price: 9000, salePrice: null, optionValueIds: ['color-red', 'size-m'], stock: 0 };
        const multiVariants = [colorRedSmall, colorRedMediumOutOfStock];

        expect(isValueAvailable(multiVariants, 'size', 'size-s', { color: 'color-red' })).toBe(true);
        expect(isValueAvailable(multiVariants, 'size', 'size-m', { color: 'color-red' })).toBe(false);
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app npm run test -- variant-resolution`
Expected: FAIL — `Failed to resolve import "./variant-resolution"`.

- [ ] **Step 3: Implement**

Create `resources/js/lib/variant-resolution.ts`:

```ts
export type Variant = {
    id: string;
    price: number;
    salePrice: number | null;
    optionValueIds: string[];
    stock: number;
};

export function findMatchingVariant(
    variants: Variant[],
    selected: Record<string, string>,
    optionIds: string[],
): Variant | null {
    const selectedValueIds = optionIds.map((optionId) => selected[optionId]);
    if (selectedValueIds.some((valueId) => valueId === undefined)) {
        return null;
    }

    return (
        variants.find((variant) => selectedValueIds.every((valueId) => variant.optionValueIds.includes(valueId))) ??
        null
    );
}

export function isValueAvailable(
    variants: Variant[],
    optionId: string,
    valueId: string,
    selected: Record<string, string>,
): boolean {
    const otherSelections = Object.entries(selected).filter(([id]) => id !== optionId);

    return variants.some((variant) => {
        if (!variant.optionValueIds.includes(valueId)) return false;
        if (variant.stock <= 0) return false;

        return otherSelections.every(([, otherValueId]) => variant.optionValueIds.includes(otherValueId));
    });
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec app npm run test -- variant-resolution`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/variant-resolution.ts resources/js/lib/variant-resolution.test.ts
git commit -m "feat: add variant resolution helpers for the option picker"
```

---

### Task 5: `StorefrontLayout` and `ProductCard` components

**Files:**
- Create: `resources/js/components/storefront/storefront-layout.tsx`
- Create: `resources/js/components/storefront/product-card.tsx`
- Test: `resources/js/components/storefront/product-card.test.tsx`

**Interfaces:**
- Consumes: `deriveTenantPalette`, `DEFAULT_TENANT_COLOR` (Task 3), `formatRupiah` (Task 2).
- Produces: `StorefrontLayout({ primaryColor: string | null, children: ReactNode })` — wraps any storefront page, applies the `.storefront` class and tenant CSS custom properties.
- Produces: `type StorefrontProduct = {id: string, slug: string, name: string, imageUrl: string | null, price: {amount: number, isFrom: boolean}, inStock: boolean}` and `ProductCard({ product: StorefrontProduct })`.
- Consumed by: Tasks 6, 8.

- [ ] **Step 1: Write the failing test**

Create `resources/js/components/storefront/product-card.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProductCard, type StorefrontProduct } from './product-card';

const baseProduct: StorefrontProduct = {
    id: '1',
    slug: 'keripik-singkong',
    name: 'Keripik Singkong',
    imageUrl: null,
    price: { amount: 15000, isFrom: false },
    inStock: true,
};

describe('ProductCard', () => {
    it('renders the product name and formatted price', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.getByText('Keripik Singkong')).toBeInTheDocument();
        expect(screen.getByText('Rp 15.000')).toBeInTheDocument();
    });

    it('shows a "Mulai dari" label when the price is a range', () => {
        render(<ProductCard product={{ ...baseProduct, price: { amount: 15000, isFrom: true } }} />);

        expect(screen.getByText('Mulai dari')).toBeInTheDocument();
    });

    it('does not show "Mulai dari" for a flat price', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.queryByText('Mulai dari')).not.toBeInTheDocument();
    });

    it('shows a "Stok habis" badge when out of stock', () => {
        render(<ProductCard product={{ ...baseProduct, inStock: false }} />);

        expect(screen.getByText('Stok habis')).toBeInTheDocument();
    });

    it('links to the product detail page', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.getByRole('link')).toHaveAttribute('href', '/produk/keripik-singkong');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app npm run test -- product-card`
Expected: FAIL — `Failed to resolve import "./product-card"`.

- [ ] **Step 3: Implement `StorefrontLayout`**

Create `resources/js/components/storefront/storefront-layout.tsx`:

```tsx
import { type CSSProperties, type ReactNode } from 'react';
import { DEFAULT_TENANT_COLOR, deriveTenantPalette } from '@/lib/tenant-theme';

type Props = {
    primaryColor: string | null;
    children: ReactNode;
};

export function StorefrontLayout({ primaryColor, children }: Props) {
    const palette = deriveTenantPalette(primaryColor ?? DEFAULT_TENANT_COLOR);

    const style = {
        '--tenant-accent': palette.accent,
        '--tenant-accent-hover': palette.accentHover,
        '--tenant-tint': palette.tint,
    } as CSSProperties;

    return (
        <div className="storefront min-h-screen bg-[var(--storefront-paper)] text-[var(--storefront-ink)]" style={style}>
            {children}
        </div>
    );
}
```

- [ ] **Step 4: Implement `ProductCard`**

Create `resources/js/components/storefront/product-card.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { formatRupiah } from '@/lib/currency';

export type StorefrontProduct = {
    id: string;
    slug: string;
    name: string;
    imageUrl: string | null;
    price: { amount: number; isFrom: boolean };
    inStock: boolean;
};

type Props = {
    product: StorefrontProduct;
};

export function ProductCard({ product }: Props) {
    return (
        <Link href={`/produk/${product.slug}`} className="group flex flex-col gap-2">
            <div className="relative aspect-[4/5] overflow-hidden rounded-xl bg-[var(--storefront-paper-muted)] shadow-[0_6px_16px_-10px_oklch(0.22_0.02_60/0.4)]">
                {product.imageUrl ? (
                    <img
                        src={product.imageUrl}
                        alt={product.name}
                        className={`h-full w-full object-cover transition group-hover:scale-105 ${
                            product.inStock ? '' : 'opacity-70 saturate-50'
                        }`}
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-sm text-[var(--storefront-ink)]/50">
                        Tanpa foto
                    </div>
                )}
                {!product.inStock && (
                    <span className="absolute bottom-2 left-2 rounded-full bg-[var(--storefront-status-warning)] px-2 py-0.5 text-xs font-medium text-white">
                        Stok habis
                    </span>
                )}
            </div>
            <div className="space-y-0.5">
                <p className="line-clamp-2 text-sm font-medium">{product.name}</p>
                {product.price.isFrom && <p className="text-xs text-[var(--storefront-ink)]/60">Mulai dari</p>}
                <p className="font-semibold text-[var(--tenant-accent)]">{formatRupiah(product.price.amount)}</p>
            </div>
        </Link>
    );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec app npm run test -- product-card`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/storefront/storefront-layout.tsx resources/js/components/storefront/product-card.tsx resources/js/components/storefront/product-card.test.tsx
git commit -m "feat: add StorefrontLayout and ProductCard components"
```

---

### Task 6: Rewrite `pages/storefront/home.tsx`

**Files:**
- Modify: `resources/js/pages/storefront/home.tsx`

**Interfaces:**
- Consumes: `StorefrontLayout` (Task 5), `ProductCard`/`StorefrontProduct` (Task 5).
- Props from `HomeController` (already implemented): `store: {name, description, whatsappNumber, isOpen, primaryColor, logoUrl, bannerUrl}`, `products: StorefrontProduct[]`.

This task has no dedicated new test file — its correctness is already covered end-to-end by the backend's `ProductListingTest` (asserts the props reaching this page) and `HostnameResolutionTest` (asserts the page still renders `store.name`), both already passing. Re-run them in Step 2 to confirm this rewrite doesn't break their assumptions about the page's Inertia component name/props contract.

- [ ] **Step 1: Replace the page**

Replace `resources/js/pages/storefront/home.tsx` with:

```tsx
import { Head } from '@inertiajs/react';
import { ProductCard, type StorefrontProduct } from '@/components/storefront/product-card';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';

type Store = {
    name: string;
    description: string | null;
    whatsappNumber: string | null;
    isOpen: boolean;
    primaryColor: string | null;
    logoUrl: string | null;
    bannerUrl: string | null;
};

type Props = {
    store: Store;
    products: StorefrontProduct[];
};

export default function StorefrontHome({ store, products }: Props) {
    const whatsappHref = store.whatsappNumber ? `https://wa.me/${store.whatsappNumber.replace(/[^0-9]/g, '')}` : null;

    return (
        <StorefrontLayout primaryColor={store.primaryColor}>
            <Head title={store.name} />

            <header className="relative">
                <div
                    className="aspect-[16/9] w-full sm:aspect-[21/9]"
                    style={
                        store.bannerUrl
                            ? { backgroundImage: `url(${store.bannerUrl})`, backgroundSize: 'cover', backgroundPosition: 'center' }
                            : { backgroundImage: 'linear-gradient(135deg, var(--tenant-tint), var(--storefront-paper))' }
                    }
                >
                    {!store.bannerUrl && <div className="storefront-banner-grain h-full w-full" />}
                </div>

                <div className="mx-auto -mt-10 max-w-3xl px-4">
                    {store.logoUrl ? (
                        <img
                            src={store.logoUrl}
                            alt={store.name}
                            className="h-20 w-20 rounded-full border-4 border-[var(--storefront-paper)] object-cover"
                        />
                    ) : (
                        <div className="flex h-20 w-20 items-center justify-center rounded-full border-4 border-[var(--storefront-paper)] bg-[var(--tenant-accent)] font-serif text-2xl text-white">
                            {store.name.charAt(0).toUpperCase()}
                        </div>
                    )}
                </div>
            </header>

            <div className="mx-auto max-w-3xl space-y-6 px-4 pt-4 pb-16">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="font-serif text-2xl font-semibold">{store.name}</h1>
                    <span
                        className={`rounded-full px-3 py-1 text-xs font-medium ${
                            store.isOpen
                                ? 'bg-[var(--storefront-status-open)]/15 text-[var(--storefront-status-open)]'
                                : 'bg-[var(--storefront-status-closed)]/15 text-[var(--storefront-status-closed)]'
                        }`}
                    >
                        {store.isOpen ? 'Buka' : 'Tutup'}
                    </span>
                </div>

                {store.description && <p className="text-[var(--storefront-ink)]/80">{store.description}</p>}

                {whatsappHref && (
                    <a
                        href={whatsappHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex w-fit items-center gap-2 rounded-full bg-[var(--tenant-accent)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--tenant-accent-hover)]"
                    >
                        Hubungi via WhatsApp
                    </a>
                )}

                {products.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-16 text-center text-[var(--storefront-ink)]/60">
                        <p className="font-serif text-lg">Belum ada produk dipajang</p>
                        <p className="text-sm">Pantau terus, produk akan segera hadir.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
```

- [ ] **Step 2: Run the backend tests that cover this page**

Run: `docker compose exec app php artisan test --filter=ProductListingTest`
Run: `docker compose exec app php artisan test --filter=HostnameResolutionTest`
Expected: both PASS.

- [ ] **Step 3: Type-check and build**

Run: `docker compose exec app npm run types:check`
Run: `docker compose exec app npm run build`
Expected: both succeed with no errors. If `types:check` fails with `Property 'form' does not exist` errors unrelated to storefront files, that's the pre-existing Wayfinder form-variants gap noted in Global Constraints — run `docker compose exec app php artisan wayfinder:generate --with-form` and re-check before concluding this task introduced a regression.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/storefront/home.tsx
git commit -m "feat: replace storefront home placeholder with the product grid"
```

---

### Task 7: Branded 404 page

**Files:**
- Create: `resources/js/pages/storefront/not-found.tsx`
- Modify: `app/Http/Controllers/Storefront/ProductController.php`
- Modify: `tests/Feature/Storefront/ProductDetailTest.php`

**Interfaces:**
- Consumes: `StorefrontLayout` (Task 5).
- Rendered by `ProductController::show` with no props, on a 404 — replaces the current plain `abort_if($product === null, 404)`.

- [ ] **Step 1: Update the failing/changing tests first**

The existing `tests/Feature/Storefront/ProductDetailTest.php` has five `test_it_404s_for_*` methods, each currently asserting only `$response->assertNotFound();`. Add a component assertion to each of the five, so they also verify the branded page renders. Replace each of these five lines:

```php
        $response->assertNotFound();
```

with:

```php
        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
```

(All five occurrences are in `test_it_404s_for_a_draft_product`, `test_it_404s_for_an_archived_product`, `test_it_404s_for_a_not_yet_published_product`, `test_it_404s_for_a_product_belonging_to_a_different_tenant`, and `test_it_404s_for_a_nonexistent_slug`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=ProductDetailTest`
Expected: the five 404 tests FAIL — `Not a valid Inertia response` or a component-name mismatch, since the controller currently uses `abort_if` (Laravel's generic error page), not an Inertia render.

- [ ] **Step 3: Create the branded 404 page**

Create `resources/js/pages/storefront/not-found.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';

export default function StorefrontNotFound() {
    return (
        <StorefrontLayout primaryColor={null}>
            <Head title="Produk tidak ditemukan" />

            <div className="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
                <p className="font-serif text-3xl font-semibold">Produk tidak ditemukan</p>
                <p className="text-[var(--storefront-ink)]/70">
                    Produk yang Anda cari sudah tidak tersedia atau alamatnya salah.
                </p>
                <Link
                    href="/"
                    className="mt-2 inline-flex items-center gap-2 rounded-full bg-[var(--tenant-accent)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--tenant-accent-hover)]"
                >
                    ← Kembali ke etalase
                </Link>
            </div>
        </StorefrontLayout>
    );
}
```

- [ ] **Step 4: Wire the controller to render it with a 404 status**

In `app/Http/Controllers/Storefront/ProductController.php`, add the import `use Illuminate\Http\Request;` and change the method signature and return type from:

```php
    public function show(string $slug, TenantContext $tenantContext): Response
```

to:

```php
    public function show(string $slug, TenantContext $tenantContext, Request $request): \Symfony\Component\HttpFoundation\Response
```

(the return type widens because the success path and the 404 path now both go through `->toResponse($request)`, whose return type isn't `Inertia\Response` — drop the `use Inertia\Response;` import if it becomes otherwise-unused after this change, or keep it only if still referenced elsewhere in the file).

Replace:

```php
        abort_if($product === null, 404);
```

with:

```php
        if ($product === null) {
            return Inertia::render('storefront/not-found')
                ->toResponse($request)
                ->setStatusCode(404);
        }
```

And change the final `return Inertia::render('storefront/products/show', [...]);` statement to append `->toResponse($request);` at the end, so both branches return the same type.

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=ProductDetailTest`
Expected: PASS, including all five 404 scenarios with the new component assertion.

- [ ] **Step 6: Run Larastan (this file's return type changed)**

Run: `docker compose exec app ./vendor/bin/phpstan analyse`
Expected: no new errors. If the widened return type triggers a generics/type complaint, add a docblock `/** @return \Symfony\Component\HttpFoundation\Response */` above the method, or narrow the return type annotation as PHPStan suggests — do not suppress the check.

- [ ] **Step 7: Type-check and build**

Run: `docker compose exec app npm run types:check`
Run: `docker compose exec app npm run build`
Run: `docker compose exec app npm run build:ssr`
Expected: all succeed — `build:ssr` matters here specifically since this page is reached via a controller-level 404 render, not user navigation, so it must exist in the SSR bundle too.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/storefront/not-found.tsx app/Http/Controllers/Storefront/ProductController.php tests/Feature/Storefront/ProductDetailTest.php
git commit -m "feat: add branded storefront 404 page"
```

---

### Task 8: `pages/storefront/products/show.tsx`

**Files:**
- Modify: `resources/js/pages/storefront/products/show.tsx` (currently a minimal placeholder — full rewrite)

**Interfaces:**
- Consumes: `StorefrontLayout` (Task 5), `formatRupiah` (Task 2), `findMatchingVariant`/`isValueAvailable`/`Variant` (Task 4).
- Props from `ProductController::show` (unchanged by Task 7's edits to that file — same `product`/`store` shape).

No new Vitest file for this task — the interesting branching logic (`findMatchingVariant`/`isValueAvailable`) is already unit-tested in Task 4, and the page's data contract is covered by the backend's `ProductDetailTest`.

- [ ] **Step 1: Implement**

Replace `resources/js/pages/storefront/products/show.tsx` with:

```tsx
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';
import { formatRupiah } from '@/lib/currency';
import { findMatchingVariant, isValueAvailable, type Variant } from '@/lib/variant-resolution';

type Store = {
    name: string;
    primaryColor: string | null;
};

type OptionValue = { id: string; value: string };
type ProductOption = { id: string; name: string; values: OptionValue[] };
type ProductImage = { id: string; url: string };

type ProductDetail = {
    name: string;
    description: string | null;
    images: ProductImage[];
    options: ProductOption[];
    variants: Variant[];
};

type Props = {
    store: Store;
    product: ProductDetail;
};

export default function ProductShow({ store, product }: Props) {
    const [selected, setSelected] = useState<Record<string, string>>({});
    const [activeImage, setActiveImage] = useState(0);

    const optionIds = product.options.map((option) => option.id);
    const variant = useMemo(
        () => findMatchingVariant(product.variants, selected, optionIds),
        [product.variants, selected, optionIds],
    );

    const cheapestVariant = useMemo(
        () =>
            product.variants.reduce((cheapest, candidate) => {
                const cheapestEffective = cheapest.salePrice ?? cheapest.price;
                const candidateEffective = candidate.salePrice ?? candidate.price;

                return candidateEffective < cheapestEffective ? candidate : cheapest;
            }, product.variants[0]),
        [product.variants],
    );

    const displayed = variant ?? cheapestVariant;
    const price = displayed.salePrice ?? displayed.price;
    const originalPrice = displayed.salePrice ? displayed.price : null;
    const inStock = displayed.stock > 0;

    return (
        <StorefrontLayout primaryColor={store.primaryColor}>
            <Head title={product.name} />

            <div className="mx-auto max-w-4xl px-4 py-6">
                <Link href="/" className="text-sm text-[var(--storefront-ink)]/60 hover:text-[var(--tenant-accent)]">
                    ← Kembali ke etalase
                </Link>

                <div className="mt-4 grid gap-8 md:grid-cols-2">
                    <div className="md:sticky md:top-6 md:self-start">
                        <div className="aspect-square overflow-hidden rounded-xl bg-[var(--storefront-paper-muted)]">
                            {product.images[activeImage] && (
                                <img
                                    src={product.images[activeImage].url}
                                    alt={product.name}
                                    className="h-full w-full object-cover"
                                />
                            )}
                        </div>
                        {product.images.length > 1 && (
                            <div className="mt-3 flex gap-2 overflow-x-auto md:flex-col">
                                {product.images.map((image, index) => (
                                    <button
                                        key={image.id}
                                        type="button"
                                        onClick={() => setActiveImage(index)}
                                        className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 ${
                                            index === activeImage ? 'border-[var(--tenant-accent)]' : 'border-transparent'
                                        }`}
                                    >
                                        <img src={image.url} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-5">
                        <h1 className="font-serif text-2xl font-semibold">{product.name}</h1>

                        <div className="flex items-baseline gap-2">
                            <p className="text-xl font-semibold text-[var(--tenant-accent)]">{formatRupiah(price)}</p>
                            {originalPrice && (
                                <p className="text-sm text-[var(--storefront-ink)]/40 line-through">
                                    {formatRupiah(originalPrice)}
                                </p>
                            )}
                        </div>

                        <p className="flex items-center gap-2 text-sm">
                            <span
                                className={`h-2 w-2 rounded-full ${
                                    inStock ? 'bg-[var(--storefront-status-open)]' : 'bg-[var(--storefront-status-warning)]'
                                }`}
                            />
                            {inStock ? 'Stok tersedia' : 'Stok habis'}
                        </p>

                        {product.options.map((option) => (
                            <div key={option.id} className="space-y-2">
                                <p className="text-xs font-medium tracking-wide text-[var(--storefront-ink)]/60 uppercase">
                                    {option.name}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {option.values.map((value) => {
                                        const isSelected = selected[option.id] === value.id;
                                        const available = isValueAvailable(product.variants, option.id, value.id, selected);

                                        return (
                                            <button
                                                key={value.id}
                                                type="button"
                                                disabled={!available}
                                                onClick={() => setSelected((prev) => ({ ...prev, [option.id]: value.id }))}
                                                className={`rounded-full border px-4 py-1.5 text-sm transition ${
                                                    isSelected
                                                        ? 'border-[var(--tenant-accent)] bg-[var(--tenant-accent)] text-white'
                                                        : 'border-[var(--storefront-ink)]/20'
                                                } ${!available ? 'cursor-not-allowed opacity-40' : ''}`}
                                            >
                                                {value.value}
                                                {!available && <span className="ml-1 text-xs">(habis)</span>}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        {product.description && (
                            <p className="whitespace-pre-line text-[var(--storefront-ink)]/80">{product.description}</p>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
```

- [ ] **Step 2: Run the backend test that covers this page**

Run: `docker compose exec app php artisan test --filter=ProductDetailTest`
Expected: PASS (the "renders variants/options/stock" case exercises the prop shape this page consumes; the five 404 cases from Task 7 are unaffected by this file).

- [ ] **Step 3: Type-check and build**

Run: `docker compose exec app npm run types:check`
Run: `docker compose exec app npm run build`
Expected: both succeed.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/storefront/products/show.tsx
git commit -m "feat: add storefront product detail page"
```

---

### Task 9: Full verification and progress log

**Files:**
- Modify: `docs/PROGRESS.md`

No new code — this task runs the full verification suite the project's convention requires before a phase is considered done (see the "Post-fix totals" entry at the end of `docs/PROGRESS.md`), and records the outcome.

- [ ] **Step 1: Run the full backend suite**

Run: `docker compose exec app php artisan test`
Expected: PASS — no regressions in any previously-passing test.

- [ ] **Step 2: Run backend static analysis and style checks**

Run: `docker compose exec app ./vendor/bin/pint --test`
Run: `docker compose exec app ./vendor/bin/phpstan analyse`
Expected: both clean.

- [ ] **Step 3: Run the full frontend suite**

Run: `docker compose exec app npm run test`
Run: `docker compose exec app npm run lint:check`
Run: `docker compose exec app npm run format:check`
Run: `docker compose exec app npm run types:check`
Expected: all clean.

- [ ] **Step 4: Production builds**

Run: `docker compose exec app npm run build`
Run: `docker compose exec app npm run build:ssr`
Expected: both succeed.

- [ ] **Step 5: Manual smoke test**

Visit a seeded tenant subdomain (e.g. `http://test.usaharumahan.localhost:8180/` if `/etc/hosts` maps it, or `curl -H "Host: test.usaharumahan.localhost" http://localhost:8180/` against this worktree's isolated nginx port) in a browser. With at least one published product seeded (create one via `docker compose exec app php artisan tinker` using the `Product`/`ProductVariant` factories with `status: active` and a past `published_at`, if none exist), confirm: the banner/logo header renders (gradient fallback if no banner/logo set), the product grid shows the card with correct price and stock badge, and clicking through to `/produk/{slug}` renders the detail page with working option pills and a "Kembali ke etalase" link. Then visit a nonexistent slug (`/produk/tidak-ada`) and confirm the branded 404 renders instead of Laravel's default error page.

- [ ] **Step 6: Update `docs/PROGRESS.md`**

Append a new dated entry under the existing log (matching the file's established format) summarizing: storefront catalog listing and product detail page shipped (both the backend and frontend plans), new test counts, and update the "Next phase" note at the end of the file to point at Phase 3 (cart, checkout, search — per `docs/ROADMAP.md`) instead of the now-completed storefront listing/detail slice.

- [ ] **Step 7: Commit**

```bash
git add docs/PROGRESS.md
git commit -m "docs: record storefront catalog listing/detail completion"
```
