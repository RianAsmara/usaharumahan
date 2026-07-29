# Deployment

## 1. Local Development (Docker Compose)

```bash
docker compose build app
docker compose up -d
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

Services (`compose.yaml`): `app` (PHP-FPM), `nginx`, `vite` (dev server),
`horizon`, `scheduler`, `inertia-ssr`, `postgres`, `redis`, `minio` (+
`minio-init` to create the bucket once), `mailpit`. Named volumes persist
Postgres/Redis/MinIO data across restarts.

| Service | Local URL |
|---|---|
| App (via nginx) | http://localhost:8080 |
| Vite dev server | http://localhost:5173 |
| MinIO console | http://localhost:9001 |
| Mailpit UI | http://localhost:8025 |
| Postgres | localhost:5433 (mapped; app talks to it via the `postgres` service name internally) |
| Redis | localhost:6380 (mapped; app talks to it via the `redis` service name internally) |

### Wildcard subdomains locally

Tenant storefronts are resolved by hostname (`docs/TENANCY.md`). For local
development, add entries to `/etc/hosts` (or use a wildcard-capable local
DNS resolver such as `dnsmasq`) for each tenant you're testing, e.g.:

```text
127.0.0.1 usaharumahan.localhost
127.0.0.1 dapur-ibu.usaharumahan.localhost
```

`TENANT_ROOT_DOMAIN` in `.env` controls what the app treats as "the root
domain" for subdomain parsing.

## 2. Environment Variables

See `.env.example` for the complete, documented list. Every value there is a
safe local placeholder — **no production credential is ever committed**.
Production must supply real values for at minimum: `APP_KEY`, `DB_*`,
`REDIS_*`, `AWS_*` (pointing at a real S3-compatible bucket, not MinIO),
`MAIL_*`, `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`, and whichever
`PAYMENT_DRIVER`/`SHIPPING_DRIVER` is live. The app should fail fast (not
silently fall back to a fake/dev adapter) when a required production
credential is missing — this is enforced per-adapter as each is built.

## 3. Production Containers

`Dockerfile` has a `production` stage (multi-stage build: `build` compiles
PHP deps + frontend assets, `production` copies only the built artifacts
into a lean runtime image, runs as `www-data`, `APP_ENV=production`,
`APP_DEBUG=false`). Production runs, as separate processes/containers from
the same image:

- the PHP-FPM application,
- Horizon (queue workers),
- the scheduler (`php artisan schedule:run` on a 1-minute cron, or
  `schedule:work` as a long-running process),
- the Inertia SSR process (`php artisan inertia:start-ssr`), when SSR is
  enabled for the storefront.

Development tooling (Composer, Node, Xdebug) is only present in the
`development` build target, never in `production`.

## 4. Reverse Proxy / Wildcard Domains

Production requires a reverse proxy that:

- Terminates TLS for the root domain, every tenant subdomain (wildcard
  certificate, e.g. `*.usaharumahan.id`), and each verified custom domain
  (per-domain certificate, e.g. via an ACME HTTP-01/DNS-01 flow triggered by
  the domain verification job).
- Forwards the original `Host` header unchanged — tenant resolution depends
  on it.
- Points every hostname at the same application (there is one deployable,
  not one per tenant).

## 5. Health & Readiness

`GET /up` (Laravel's built-in health check route, configured in
`bootstrap/app.php`) is the liveness probe. Readiness depends on: database
connectivity, Redis connectivity, storage disk reachability, and queue
worker liveness (Horizon's own dashboard/metrics) — these are checked via
the normal Laravel scheduler/Horizon health mechanisms rather than a custom
endpoint, to avoid a second source of truth about "is the app healthy."

## 6. Horizon

The Horizon dashboard must never be reachable without authentication in
production — its authorization gate (`docs/SECURITY.md` §11) is checked in
CI-equivalent review before each deploy.

## 7. Backups

PostgreSQL is the single source of truth for all tenant data; production
requires point-in-time-recoverable automated backups (managed Postgres
backup/snapshot feature, or `pg_dump` + WAL archiving if self-hosted) with a
tested restore procedure. Object storage (S3-compatible bucket) should have
versioning enabled so an accidental overwrite/delete of a product image is
recoverable. Neither is provisioned by this repository — it documents the
requirement for whoever provisions the production environment.

## 8. CI Gate

`.github/workflows/tests.yml` runs on every push to `main` and every pull
request: Pint, Larastan, Pest (against real Postgres+Redis service
containers), ESLint, Prettier, TypeScript, Vitest, production build, SSR
build. The default branch does not accept a change that fails any of these.
