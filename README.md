# شمس — Shams Classifieds

A general-purpose classified ads website (cars, real estate, jobs, electronics, services…) in **Arabic
only (RTL)**. Anyone can register with a phone number (OTP verified), post ads with photos, and
visitors browse, search, filter and contact advertisers by phone / WhatsApp. Server-rendered for SEO.

**Stack:** Laravel 12 · PHP 8.3 · MySQL 8 (InnoDB) · Blade + Tailwind CSS (Vite) + Alpine.js · Filament 5
(`/admin`) · Laravel Sanctum (`/api/v1`) · spatie/laravel-permission · spatie/laravel-medialibrary (GD,
WebP, queued) · spatie/laravel-sitemap · spatie/laravel-backup · Pest · Playwright · Laravel Pint. Font:
Tajawal (self-hosted). OTP delivery: Twilio or Vonage (or `log` for local development). CAPTCHA:
Cloudflare Turnstile (optional). PWA: installable, with an offline-capable service worker. Search:
MySQL FULLTEXT by default, Laravel Scout + Meilisearch as an optional drop-in.

## Running it locally

```bash
composer install
npm ci && npm run build          # or `npm run dev` while developing
cp .env.example .env
php artisan key:generate
# create an empty MySQL database (utf8mb4) named like DB_DATABASE in .env
php artisan migrate --seed       # roles, admin, governorates/cities, categories, static pages
php artisan storage:link
php artisan serve                # http://127.0.0.1:8000
php artisan queue:work           # in another terminal: builds the WebP image conversions
```

- **Admin panel:** `/admin`. Sign in with `ADMIN_PHONE` / `ADMIN_PASSWORD` from `.env`.
- **OTP codes:** `SMS_DRIVER=log` (the default) writes every code to `storage/logs/laravel.log`; read
  it there to finish a registration locally. Set `SMS_DRIVER=twilio` or `SMS_DRIVER=vonage` and the
  matching credentials in `.env` to send real SMS (see `.env.example`).
- **CAPTCHA:** off by default (the honeypot field and rate limits still protect the forms). Set
  `CAPTCHA_DRIVER=turnstile` and `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` to require Cloudflare
  Turnstile on registration and new listings.
- **Featured-ad payments:** sellers can buy a package (admin → «باقات التمييز») to feature one of
  their listings for a set number of days. `PAYMENT_DRIVER=fake` (the default) settles instantly with
  no real charge, for local development and tests. Set `PAYMENT_DRIVER=paymob` and the `PAYMOB_*`
  credentials in `.env` to charge real cards through Paymob.
- **In-app messaging:** signed-in users can message a listing's seller ("راسل المعلن" on the listing
  page); each reply notifies the other participant in-app, and — if they opted in on their profile and
  have the matching contact detail — by e-mail and/or WhatsApp too (`WHATSAPP_DRIVER=cloud` sends
  through Meta's WhatsApp Cloud API; `log`, the default, writes to `storage/logs/laravel.log`).
- **Saved searches:** "احفظ هذا البحث" on any search/category results page keeps the current filters
  under `/saved-searches`; with its notify toggle on, the daily `searches:notify` command e-mails/
  WhatsApps/in-app-notifies the user about new matching listings published since it was last checked.
- **Seller stores & subscriptions:** any user can create a branded page at `/store/{slug}`; buying a
  plan at `/subscribe` (the same payment gateway as featured ads) raises their daily listing limit and
  marks their store "متجر مميز" for as long as the subscription stays active — buying again (renewing
  or switching plans) while still active extends the remaining time instead of restarting it.
- **Seller reviews:** any signed-in user can leave a 1-5 star review (with an optional comment) for a
  seller from their public page, one per reviewer per seller (leaving a new one edits it); the
  average rating and review list show on the seller page and the star rating also appears on that
  seller's listing pages. Moderators/admins can hide (not just delete) a review from the panel.
- **Sponsored ad banners:** anyone signed in can advertise at `/advertise` — pick a placement (top of
  the homepage, the search-results sidebar, or a listing page's sidebar), upload a banner image and a
  target URL. A moderator/admin approves or rejects the creative first (`/admin` → «البانرات
  الإعلانية»); once approved, the advertiser pays for a package (`/admin` → «باقات البانرات
  الإعلانية», priced and durationed per placement) at `/banners/{id}/purchase` — the same
  Fake/Paymob gateway as featured listings — and the banner goes live immediately, clicks tracked.
  Paying again while still running extends the remaining time instead of restarting it. Every other
  filter (visibility, placement, expiry) is enforced in SQL on top of whichever banner is randomly
  picked for a slot, so an unapproved or expired banner can never render.
- **Public REST API (`/api/v1`):** a token-based JSON API (Laravel Sanctum) covering everything the
  website does — browsing/search, phone-verified register/login, your own listings (CRUD with image
  uploads), favorites, contact reveal, reports, notifications, in-app messages and saved searches. It
  reuses the exact same validation/actions/policies as the website. Full contract, every endpoint and
  every schema: [`docs/openapi.yaml`](docs/openapi.yaml). **No native mobile app is included in this
  repository** — building and shipping one (iOS/Android, or React Native/Flutter) needs a real device
  build toolchain and app-store accounts, neither of which exist in this environment; the API it would
  talk to is complete and tested (`tests/Feature/Api`), so a client only needs to be built against it.
- **PWA:** the website is installable (`public/manifest.json`) and works offline for pages already
  visited (`public/sw.js`, cache-first for build assets, network-first with a cache fallback for
  everything else — never the admin panel, dashboard or API). This is a real, testable PWA, not a
  substitute for a native app: no push notifications, background sync or deep OS integration.
- **Search:** MySQL FULLTEXT by default (nothing to configure). [Meilisearch](https://www.meilisearch.com)
  is an optional drop-in via Laravel Scout for typo-tolerant search — set `SCOUT_DRIVER=meilisearch`
  and a `MEILISEARCH_HOST`/`MEILISEARCH_KEY` once a server is running; every other filter (category,
  price, visibility…) still runs as normal SQL on top of it either way. See `docs/DEPLOY.md`, "9c.
  Search engine".
- **Demo data** (local only): `php artisan db:seed --class=DemoSeeder` adds ~200 listings with images,
  12 users (password `password`), a moderator (`01111111111` / `password`), favorites and reports.
- **Before deploying:** run `php artisan launch:check` (add `--strict` to also fail on warnings). It
  checks the environment, SMS/mail/CAPTCHA configuration, admin password, PHP extensions and upload
  limits, database engine and migrations, `public/storage`, the sitemap and `mysqldump` availability.

## Tests and code style

```bash
vendor/bin/pint --test    # formatting
php artisan test          # Pest; needs a MySQL database `shams_test` (see phpunit.xml)
npm run e2e               # Playwright: full user journeys at 375px and 1280px (see e2e/)
```

The FULLTEXT search tests live in `tests/Search` and use `DatabaseTruncation` (InnoDB full-text indexes
only see committed rows). Everything else runs inside a transaction per test.

`npm run e2e` drives a real Chrome browser against a throw-away `shams_e2e` database and upload folder
(see `e2e/support/env.js`); it never touches the `shams`/`shams_test` databases. It registers a user
through the OTP flow (read from the log), posts an ad through the multi-step form with photo uploads,
approves it as the moderator in `/admin`, and checks phone reveal, favorites and reporting — at both a
375px phone width and a 1280px desktop width, asserting RTL layout and no horizontal overflow.

## Where things are

| Path | What |
|---|---|
| `classifieds-plan.md` | the implementation plan this project follows |
| `config/classifieds.php` | listing duration, image limits, moderation, OTP, phone/currency defaults |
| `app/Actions`, `app/Services`, `app/Queries` | business logic (controllers stay thin) |
| `app/Filament` | admin panel resources (categories & fields, geography, listings, reports, users, pages) |
| `lang/ar` | every user-facing string (`app.php`, plus validation/auth/pagination) |
| `database/seeders/data` | the editable governorates, cities, categories and dynamic fields |
| `docs/DEPLOY.md` | production deployment guide (Nginx, Supervisor, cron, backups) |

Conventions: Tailwind **logical properties only** (`ms-*`, `pe-*`, `start-*`, `text-start`; never
`ml/mr/left/right`), all UI text through `__('app.…')`, never render user content with `{!! !!}`.
