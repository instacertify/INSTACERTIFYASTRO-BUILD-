# Instacertify v2

Rebuild of [instacertify.com](https://instacertify.com): a fast static Astro site backed by a Laravel API, CRM-style lead inbox and content admin.

```
content/    JSON source of truth (site settings, service catalog, service detail pages, industries, testimonials)
backend/    Laravel 13 + Filament 5 admin + Sanctum — content API and lead capture
frontend/   Astro 7 + Tailwind 4 static site (Inter), built from the API or straight from content/
infra/      Caddy, Prometheus and Postgres init config for docker-compose
```

## Run locally (no Docker)

```powershell
# Backend — http://localhost:8000 (admin at /admin)
cd backend
composer install
copy .env.example .env; php artisan key:generate
php artisan migrate --seed     # prints the admin password unless ADMIN_PASSWORD is set
php artisan serve

# Frontend — http://localhost:4321
cd frontend
npm install
copy .env.example .env
npm run dev
```

Tests: `cd backend; php artisan test`. Production build: `cd frontend; npm run build` (output in `frontend/dist`).

## Run with Docker

Requires Docker Desktop with WSL2 (`wsl --install` from an admin terminal, then reboot).

```powershell
copy .env.example .env          # fill in APP_KEY, SITE_PUBLISH_TOKEN and the passwords
docker compose up -d --build
docker compose exec app php artisan db:seed --force
```

Deploying an update is the same `docker compose up -d --build`: the app migrates on start, and the builder republishes the website with the new templates (a few minutes; the previous version stays live until the new one is complete).

## How admin changes reach the website

The website is static. The `builder` service rebuilds it from the API and publishes it to the `site` volume that Caddy serves:

- about a minute after any content, image, menu or setting is saved (saves close together are published once),
- every night at 03:30 (so expired vacancies and past events drop off),
- on every start of the builder (i.e. every deploy),
- from **Admin → Settings → Publish website**, which also shows the last publish and any error.

A build that fails, or comes out with far fewer pages than the live site (e.g. the API was unreachable), is not published. The previous release is kept in the volume for a manual rollback (`/site/releases`).

| URL | Service |
| --- | --- |
| https://localhost | Website |
| https://api.localhost/admin | Admin (leads, services, industries, testimonials) |
| http://localhost:8025 | Mailpit (captured emails) |

Optional profiles add the rest of the stack:

- `--profile analytics` — Matomo at https://analytics.localhost (set `PUBLIC_MATOMO_URL` and rebuild `web`)
- `--profile monitoring` — GlitchTip (https://errors.localhost), Prometheus, Grafana (https://grafana.localhost)
- `--profile automation` — n8n (https://n8n.localhost), Evolution API for WhatsApp (:8080), Listmonk (:9002)

ERPNext runs on its own (https://erp.instacertify.com). The website no longer links to it; login links can be added to the footer under Website → Footer → Login links.

## How leads flow

1. Forms on the site POST to `/api/v1/leads` (honeypot, consent, rate-limited to 5/min and 30/day per IP).
2. The lead is stored with its service, source page, referrer and UTM tags, and appears in **Admin → CRM → Leads**.
3. A queued job emails `LEAD_NOTIFY_EMAIL` and, if `LEAD_WEBHOOK_URL` is set, posts the lead to n8n signed with `X-Instacertify-Signature` (HMAC-SHA256 of the body using `LEAD_WEBHOOK_SECRET`). From n8n you can push it to ERPNext, send a WhatsApp reply through Evolution API, or add it to Listmonk.

## Content

Service pages keep the legacy URLs (for example `/certification/india/bis-crs-mark-certification`). A service with a `detail` JSON (in the admin, or `content/services/<slug>.json`) gets the full long-form layout; the others get the standard layout. Run `php artisan db:seed --class=ContentSeeder` to re-import `content/` into the database.
