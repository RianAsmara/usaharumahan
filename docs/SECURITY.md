# Security

## 1. Authentication

- Laravel Fortify: registration, login, logout, password reset, email
  verification, password confirmation, optional two-factor authentication.
- Session-based auth (not JWT) for the Inertia app, with CSRF protection and
  session ID regeneration on login (Fortify default behavior).
- Google OAuth (Socialite) for owners: accounts are linked by **verified**
  email only; an unverified provider email never auto-merges into an
  existing account. Provider identity lives in a dedicated `social_accounts`
  table. OAuth access tokens are not stored unless a concrete feature needs
  them; if storage is ever required, tokens are encrypted at rest.
- Customer checkout is guest-first — no customer authentication in the MVP.

## 2. Authorization

Laravel Policies backed by tenant membership checks (see `docs/TENANCY.md`).
Roles are enum-backed (`platform_admin`, `owner`, `staff`), never raw
strings compared ad hoc in controllers or React components. Frontend
capability flags (`can.*` props) only control presentation — every
mutating action is re-authorized server-side regardless of what the
frontend shows.

## 3. Tenant Isolation

See `docs/TENANCY.md` for the full defense-in-depth model (middleware,
scoped queries, route-model binding, policies, DB constraints, tests).

## 4. Rate Limiting

Rate limits apply to, at minimum: login, registration, password reset,
Google OAuth callbacks, public search, cart mutations, checkout, guest order
tracking, voucher attempts, payment webhooks (per provider guidance), and
domain verification. Limits are keyed by a combination that makes sense per
endpoint (IP for anonymous storefront actions, tenant+IP for checkout, so
one tenant's traffic spike can't rate-limit another tenant's customers).

## 5. Webhooks

Every payment webhook handler:

- Verifies the provider's signature before trusting the payload.
- Stores a sanitized copy of the raw payload for audit/debugging.
- Is idempotent via a unique `(provider, external_event_id)` constraint —
  see `docs/DATABASE.md` — so redelivery never double-applies a state
  change, double-charges inventory, or sends duplicate notifications.
- Returns quickly and queues non-essential follow-up work rather than doing
  it inline.
- Never logs the provider's shared secret or full sensitive payload fields.

## 6. File Uploads

- Explicit MIME type allow-lists and file-size limits.
- Randomly generated filenames — the original filename is never trusted or
  used to construct a path.
- Tenant-prefixed storage paths, so a path traversal or ID-guessing bug
  can't read/write another tenant's files.
- SVG uploads are rejected (SVG can carry executable script content) unless
  a sanitizer is introduced later.
- Private files are served via signed/temporary URLs; only intentionally
  public assets (product images, logos) use public object URLs.
- Tests use `Storage::fake()` — no test ever touches the real MinIO/S3
  bucket.

## 7. Logging

Structured logs include safe contextual fields — request ID (see
`app/Http/Middleware/AddRequestId.php`), tenant ID, authenticated user ID,
order public ID, payment provider, job ID — added via Laravel's `Context`
facade, which Laravel automatically merges into every log record.

Never logged: passwords, session cookies, CSRF tokens, OAuth tokens, payment
provider secrets, full webhook secrets, complete customer addresses in
general application logs. Laravel's default exception context redaction
(sensitive `Request::all()` keys) is relied on and not disabled.

## 8. Audit Logging

Written to `audit_logs` for: tenant suspension, membership changes, product
price changes, inventory adjustments, order cancellation, manual payment
confirmation, shipping status changes, domain changes, payment configuration
changes. Each entry records the actor, the tenant, the action, and enough
context to reconstruct what changed without needing to diff row history.

## 9. Secrets

All secrets come from environment variables (see `.env.example` for the
full documented list, `docs/DEPLOYMENT.md` for how production is expected to
supply real values). `.env.example` never contains a real credential —
every value in it is a safe local-dev placeholder or a description of what
production must set.

## 10. Headers & Transport

Nginx (`docker/nginx/default.conf`) sets `X-Frame-Options`,
`X-Content-Type-Options`, and `Referrer-Policy` on every response. TLS
termination is expected at the reverse proxy / load balancer in production
(see `docs/DEPLOYMENT.md`); `SESSION_SECURE_COOKIE` must be enabled once
production is served over HTTPS.

## 11. Operational Tooling

Horizon's dashboard requires its own authorization gate
(`App\Providers\HorizonServiceProvider::gate()`, configured in Phase 0/1)
and must never be reachable without authentication in production.
Development-only tooling (Mailpit, MinIO console, Horizon in its
unauthenticated default state) must not be exposed publicly outside the
Docker Compose network.
