# Phase 2 (dashboard slice) — Catalog and Inventory Design

Status: approved, not yet implemented. Tracked as part of `docs/ROADMAP.md`
Phase 2 ("Catalog and inventory"). This spec covers the **dashboard-only**
half of that phase: categories, products, images, options/variants, and the
inventory ledger, plus the owner/staff dashboard CRUD pages that manage them.
The public storefront listing/detail pages are explicitly **out of scope**
for this spec and will be brainstormed as a separate follow-up pass.

## 1. Scope

In scope:

- Categories (flat, single level).
- Products (draft/active/archived), with a category assignment.
- Product images (upload, ordering, delete).
- Product options and option values (e.g. "Size" → {S, M, L}).
- Product variants (the sellable SKU: price, sale price, weight, option
  combination).
- Inventory: on-hand stock per variant, and an append-only movement ledger.
- Owner dashboard pages for all of the above.
- Policy-level authorization split between owner and staff.

Out of scope (deferred):

- Public storefront category/product listing and detail pages.
- Anything checkout/cart/order related (`reserved` stock, `sale`/`return`
  movements are schema-only in this phase; they are populated starting in
  Phase 4).
- Category nesting (parent/child) — flat only, can be added later without a
  breaking schema change if it turns out to be needed.
- Bulk import/export, CSV, or any non-interactive catalog management.

## 2. Data Model

Conventions follow `docs/DATABASE.md` §1: ULID primary keys, `tenant_id` on
every tenant-owned table, money as `bigInteger`, enums as PHP backed enums
on a `string` column.

### `categories`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | ulid, FK → tenants, cascade | |
| `name` | string | |
| `slug` | string | `unique(tenant_id, slug)` |
| `description` | text, nullable | |
| `is_active` | boolean, default true | |
| `sort_order` | integer, default 0 | |
| timestamps | | |

Index: `(tenant_id, is_active)`.

### `products`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | ulid, FK → tenants, cascade | |
| `category_id` | ulid, FK → categories, nullable, nullOnDelete | |
| `name` | string | |
| `slug` | string | `unique(tenant_id, slug)` |
| `description` | text, nullable | |
| `status` | string (`ProductStatus`), default `draft` | |
| `published_at` | timestamp, nullable | set when status transitions to `active` |
| timestamps | | |

Indexes: `(tenant_id, status)`, `(tenant_id, category_id, status)`.

No price/weight column here — those live on `product_variants`, per
`docs/DATABASE.md` §2. Every product has at least one variant (see §4
below), so there is never a "product with no price."

### `product_images`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `product_id` | ulid, FK → products, cascade | |
| `tenant_id` | ulid, FK → tenants, cascade | denormalized for direct tenant-scoped queries, per §1 convention |
| `path` | string | storage path on the tenant-prefixed S3/MinIO disk |
| `sort_order` | integer, default 0 | first image (`sort_order = 0`) is the primary/cover image |
| timestamps | | |

Index: `(product_id, sort_order)`.

Validation (enforced in the Form Request, not the DB): max 6 images per
product, max 4MB per file, mime types `jpg`/`jpeg`/`png`/`webp`.

### `product_options` / `product_option_values`

| Column | Type | Notes |
|---|---|---|
| `product_options.id` | ulid, PK | |
| `product_options.product_id` | ulid, FK → products, cascade | |
| `product_options.tenant_id` | ulid, FK → tenants, cascade | |
| `product_options.name` | string | e.g. "Size" |
| `product_options.sort_order` | integer, default 0 | |
| `product_option_values.id` | ulid, PK | |
| `product_option_values.product_option_id` | ulid, FK → product_options, cascade | |
| `product_option_values.value` | string | e.g. "M" |
| `product_option_values.sort_order` | integer, default 0 | |

Validation caps (Form Request, not DB): max 3 options per product, max 20
values per option — bounds the variant combination explosion.

### `product_variants`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `product_id` | ulid, FK → products, cascade | |
| `tenant_id` | ulid, FK → tenants, cascade | |
| `sku` | string | `unique(tenant_id, sku)`, auto-generated from the product slug, owner-editable |
| `price` | bigInteger | check `>= 0` |
| `sale_price` | bigInteger, nullable | check `>= 0` when not null |
| `weight_grams` | integer, nullable | |
| timestamps | | |

### `product_variant_option_values` (pivot)

| Column | Type | Notes |
|---|---|---|
| `product_variant_id` | ulid, FK → product_variants, cascade | |
| `product_option_value_id` | ulid, FK → product_option_values, cascade | |

`unique(product_variant_id, product_option_value_id)`. Relational, not
JSON — consistent with the rest of the schema, which reserves JSON columns
for genuinely free-form data (`stores.social_links`, `stores.opening_hours`).

### `inventories`

| Column | Type | Notes |
|---|---|---|
| `product_variant_id` | ulid, PK, FK → product_variants, unique, cascade | 1:1, same pattern as `stores.tenant_id` |
| `tenant_id` | ulid, FK → tenants, cascade | |
| `on_hand` | integer, default 0 | check `>= 0` |
| `reserved` | integer, default 0 | check `>= 0`; populated starting Phase 4 |
| timestamps | | |

### `inventory_movements` (append-only ledger)

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | ulid, FK → tenants, cascade | |
| `product_variant_id` | ulid, FK → product_variants, cascade | |
| `type` | string (`InventoryMovementType`: `restock`, `adjustment`, `sale`, `return`) | only `restock`/`adjustment` are reachable from the dashboard in this phase; `sale`/`return` are written by checkout code starting Phase 4 |
| `quantity_delta` | integer | signed; positive for restock, positive or negative for adjustment |
| `resulting_on_hand` | integer | snapshot of `on_hand` immediately after this movement, for audit trail without replaying the ledger |
| `note` | text, nullable | |
| `actor_user_id` | foreignId → users, nullable | null for system-generated movements (none in this phase) |
| `created_at` | timestamp | no `updated_at` — rows are never modified |

Index: `(tenant_id, product_variant_id, created_at)`.

### Enums

- `App\Enums\ProductStatus`: `Draft`, `Active`, `Archived`.
- `App\Enums\InventoryMovementType`: `Restock`, `Adjustment`, `Sale`, `Return`.

## 3. Module Structure

Following `docs/ARCHITECTURE.md` §2 (thin controllers, single-purpose
Actions, Policies for authorization):

**Models** (`app/Models/`): `Category`, `Product`, `ProductImage`,
`ProductOption`, `ProductOptionValue`, `ProductVariant`, `Inventory`,
`InventoryMovement`.

**Policies** (`app/Policies/`):

- `CategoryPolicy`: `view*` → any tenant member; `create`/`update`/`delete`
  → owner only.
- `ProductPolicy`: same split as `CategoryPolicy` — `view*` → any member;
  `create`/`update`/`delete` (including images, options, variants) → owner
  only.
- `InventoryPolicy`: `view*` → any member; `adjust` → any member (staff can
  record stock movements per the PRD's "manage stock" staff goal, without
  being able to change catalog structure or pricing).

Each policy re-checks tenant membership independently of how the model was
fetched, matching the existing `StorePolicy::membershipFor()` pattern (see
`docs/TENANCY.md` §5).

**Actions** (`app/Actions/`) — only where the operation is multi-step or
needs a transaction/lock; plain single-table field updates (e.g. editing a
category's name) stay direct Eloquent calls in the controller, matching
`StoreController::update`:

- `Catalog/CreateProduct`: creates the product row, then (if no options were
  submitted) auto-creates one default variant + zero-stock `inventories`
  row, all in one transaction.
- `Catalog/AddProductVariant`: creates a variant + its option-value pivot
  rows + a zero-stock `inventories` row, in one transaction.
- `Inventory/AdjustInventory`: `lockForUpdate()`s the variant's `inventories`
  row, writes an `inventory_movements` row, updates `on_hand`, all inside a
  transaction — same locking pattern documented for checkout in
  `docs/DATABASE.md` §8.

**Form Requests** (`app/Http/Requests/`):

- `Catalog/CreateCategoryRequest`, `Catalog/UpdateCategoryRequest`
- `Catalog/CreateProductRequest`, `Catalog/UpdateProductRequest`
- `Inventory/AdjustInventoryRequest`

**Controllers** (`app/Http/Controllers/Dashboard/`):

- `CategoryController`: `index`, `store`, `update`, `destroy`.
- `ProductController`: `index`, `create`, `store`, `edit`, `update`,
  `destroy` — `edit` also carries the data needed to manage images,
  options/variants, and per-variant stock on one page.

Routes added to `routes/dashboard.php`, gated by the existing
`RequireTenant`/`ResolveTenantForDashboard` middleware.

## 4. Dashboard UI

`resources/js/pages/dashboard/`:

- `categories/index.tsx`: flat list with an inline create/edit dialog
  (no separate create/edit pages needed — categories are a single-field-set
  form).
- `products/index.tsx`: list with status filter.
- `products/create.tsx`: name/description/category/status form; on submit,
  routes into `edit` for images/options/variants/inventory (a product must
  exist before it can own child rows).
- `products/edit.tsx`: single page managing images (upload/reorder/delete),
  options/variants (add/edit), and per-variant stock (adjust with a
  restock/adjustment reason).

All pages disable owner-only actions (but keep them visible/read-only) for
staff, using a `can: {...}` prop from the controller, matching the existing
`StoreController::edit` pattern.

## 5. Testing

Pest feature tests, mirroring the style of the existing
`Tests\Feature\Tenancy\CrossTenantIsolationTest` and
`Tests\Feature\Store\StoreSettingsTest`:

- Category CRUD: create/update/delete, slug uniqueness per tenant,
  cross-tenant isolation (tenant A cannot see/edit tenant B's categories).
- Product CRUD: create with auto-default-variant, create with
  options/variants, status transitions, option/value count caps rejected
  over the limit, cross-tenant isolation.
- Image upload: mime/size validation, count cap, ordering, deletion.
- Inventory: restock increases `on_hand` and writes a movement with the
  correct `resulting_on_hand`; adjustment can decrease `on_hand` but never
  below zero; a concurrency test locking the same variant's inventory row
  (two simultaneous adjustments) to confirm no lost update.
- Policy tests: owner can create/update/delete catalog + adjust stock; staff
  can view catalog + adjust stock but cannot create/update/delete
  categories/products/variants; cross-tenant staff/owner has no access at
  all.

## 6. Explicitly Deferred

- Public storefront category/product listing and detail pages (separate
  brainstorm/spec).
- Checkout-driven `sale`/`return` inventory movements and `reserved` stock
  (Phase 4).
- Category nesting, bulk import/export.
