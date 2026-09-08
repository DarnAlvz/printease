# PrintEase — System Context for ChatGPT

This document is a self-contained reference so an AI assistant (or a human) can understand how the entire PrintEase system works without reading every source file. Use it as the single context file when asking questions or making changes to the codebase.

---

## 1. What This System Is

PrintEase is a **mobile + web e-printing management system** for print shops in **Calbayog City, Samar, Philippines** (capstone/thesis project). It is a marketplace connecting three user roles:

- **Customer** — browses verified print shops, submits print requests (with file uploads), pays via GCash, tracks order status.
- **Shop Owner** — manages shop profile, business permit, services/pricing, print jobs, payment verification, reports.
- **Super Admin** — approves shops & permits, approves GCash payment channels, manages users, views reports and audit logs.

Domain language used throughout the UI/code: "requests" = orders, "print shops" = shops, "permits" = business permits, "proof of payment" = GCash screenshot uploaded by customer.

---

## 2. Tech Stack

| Layer | Technology | Notes |
|---|---|---|
| Frontend | HTML5, CSS3, **Tailwind CSS v4.3** (built via PostCSS), **vanilla JS** (no framework) | Role-specific custom CSS + shared Tailwind build |
| Backend | **Procedural PHP** (no MVC framework, direct file-based routing), mysqli prepared statements | PHP 8.4+, running on Apache (Laragon/XAMPP locally, cPanel shared hosting in prod) |
| Database | MySQL/MariaDB utf8mb4 (`printease_db`) | Single SQL dump in `backend/database/` |
| Sessions | Redis (predis) with **file-based fallback** | Redis optional; unset on shared hosting |
| PWA | `manifest.json` + `service-worker.js` | Installable, cache-first, offline fallback |
| Build tools | npm scripts for Tailwind | `npm run build:tailwind` |

### Composer packages (`composer.json`)
| Package | Purpose |
|---|---|
| `google/apiclient` v2.19 | Google OAuth2 social sign-in |
| `phpmailer/phpmailer` v7.1 | SMTP email (OTP codes, verification) |
| `thiagoalessio/tesseract_ocr` v2.13 | OCR on GCash payment-proof images (auto-detect reference number) |
| `cloudinary/cloudinary_php` v3.1 | Cloud storage for customer PDF uploads |
| `predis/predis` v2.3 | Redis session/cache client |
| `setasign/fpdi` v2.6 | PDF manipulation (order files) |

### External integrations
- Google OAuth2 (login), Gmail SMTP (email), Cloudinary (PDFs), Tesseract + fallback Google Cloud Vision (OCR), OpenStreetMap/Nominatim (geocoding & distance), GCash (manual payment proof verification — no payment gateway API).

---

## 3. Configuration

All config is in `.env` (gitignored; `.env.example` is the committed template). Everything is loaded through `backend/config/env.php`.

Key variables:
| Variable | Purpose |
|---|---|
| `APP_ENV` | `local` (shows errors) vs `production` (hides errors, logs them) |
| `BASE_URL` | Full HTTPS base URL with trailing slash, e.g. `https://printease.org/` |
| `DB_HOST/DB_USER/DB_PASS/DB_NAME` | MySQL connection (persistent `p:host` connections) |
| `TESSERACT_PATH` | OCR binary; degrades gracefully if unavailable |
| `GOOGLE_CLOUD_VISION_API_KEY` | Fallback OCR provider |
| `GOOGLE_CLIENT_ID/SECRET` | OAuth (authorized redirect: `.../backend/oauth/oauth_callback.php?provider=google`) |
| `MAIL_USER/MAIL_PASS` | Gmail SMTP (app password) |
| `SMTP_HOST/SMTP_USER/SMTP_PASS` | cPanel SMTP — used in production because Gmail is blocked on z.com hosting |
| `CLOUD_NAME/API_KEY/API_SECRET` | Cloudinary |
| `REDIS_HOST/PORT` | Optional Redis; sessions fall back to files if unset/unreachable |
| `SESSION_LIFETIME_DAYS` | Sliding session window (default 30) |
| `TRUSTED_PROXIES` | Only set this behind a sanitizing reverse proxy; rate limiting uses `REMOTE_ADDR` otherwise |

Config loaders in `backend/config/`: `app.php` (defines `APP_NAME`, `APP_ENV`, `BASE_URL`, `UPLOADS_URL`, `PERMITS_URL`, `SHOP_LOGOS_URL`, `GCASH_QR_URL`), `db.php` (creates `$conn` mysqli handle; honors `DB_CONNECTION_OPTIONAL`), `cloudinary.php`, `oauth.php`, `redis.php`.

---

## 4. Directory Structure

```
printease/
|-- index.php                     # Public landing page + auth bounce
|-- .env / .env.example           # Environment config (never commit real .env)
|-- .htaccess                     # Apache: block .env/composer/package.json; security headers; no index listing
|-- .user.ini                     # Host-specific PHP settings
|-- manifest.json                 # PWA manifest
|-- service-worker.js             # PWA service worker (cache-first)
|-- robots.txt
|
|-- assets/
|   |-- css/                      # tailwind.css (generated), index.css, role-specific css
|   |-- images/                   # logos, favicons, PWA icons
|   `-- js/                       # pdf.min.js, pdf.worker.min.js (PDF previews)
|
|-- frontend/
|   |-- splash.php                # animated splash (first visit, sets seen_splash)
|   |-- components/               # shared includes: head.php, branding.php, layout files,
|   |                             #   notifications.php, toasts.php, confirm_modal.php
|   |-- pages/                    # PUBLIC pages: login, register, forgot/reset password, OTP,
|   |                             #   verify registration, terms, privacy, social_role
|   |-- assets/js/live-updates.js # CORE polling engine for all role dashboards
|   `-- user/
|       |-- customer/             # dashboard, explore, shops, shopLocation, place_order,
|       |                         #   orders, payment, notifications, profile (+ assets/js/)
|       |-- shop_owner/           # dashboard, orders, payments, services, shop_profile,
|       |                         #   transactions, reports, notifications, update_status
|       `-- superadmin/           # dashboard, manage_users, manage_print_shops, reports,
|                                 #   notifications, activity_logs, settings (+ includes/)
|
|-- backend/
|   |-- config/                   # app, db, cloudinary, oauth, redis, env loaders
|   |-- includes/                 # CORE PHP: auth, session, remember_auth, functions, cache,
|   |                             #   security_headers, rate_limit, profile_guard, shop_guard,
|   |                             #   status_guard, price_records, gcash_ocr, oauth_helpers
|   |-- actions/                  # 44 standalone "POST handler" scripts (see section 8)
|   |-- oauth/                    # oauth_start, oauth_callback, social_register_process
|   |-- helper/                   # mailer.php (PHPMailer wrappers: OTP, notifications)
|   `-- database/                 # ujvepwwv_PRINTEASE_DB.sql (full schema + data dump)
|
|-- deploy/                       # deployment scripts/config
|-- docs/                         # test plans, deployment checklists, THIS file
|-- uploads/                      # user files: permits/, shop_logos/, gcash_qr/, payment_proofs/, customers/
|-- vendor/  node_modules/        # composer + npm dependencies
```

**Convention that matters:** `frontend/` holds pages (HTML+PHP that render UI), `backend/actions/` holds POST handlers that process forms then `header("Location: ...")` back. Each page file includes auth/guards, boots a DB connection, queries, and renders.

---

## 5. Request / Routing Flow

There is **no router**. Every page is a real file; the browser hits it directly. The flow inside every page:

1. `require backend/includes/session.php` → `secureSession()` (session hardening, Redis/file backend).
2. `require backend/includes/auth.php` → `requireAuth()` / role guards; boot DB via `backend/config/db.php` (`$conn`).
3. Role/status guards (see section 7) enforce who may view the page.
4. Page queries MySQL with **prepared statements**, renders HTML.
5. Forms `POST` to `backend/actions/<name>.php`, which validates (CSRF, rate limit), mutates DB, writes an activity log entry + a notification row, then redirects back (PRG pattern).
6. Dashboards poll a `*_feed.php` action (AJAX) every ~10–12 s for live updates.

### Typical page → action map
| Page | Actions it calls |
|---|---|
| `frontend/pages/login.php` | `login_process.php`, `logout.php` |
| `frontend/pages/register.php` | `register_process.php`, `send_otp.php`, `verify_otp.php`, `verify_registration.php` |
| `frontend/pages/forgot_password.php` | `send_otp.php`, `reset_password_process.php` |
| `customer/place_order.php` | `submit_order.php` (→ `pending_order_token.php`), `toggle_favorite_shop.php`, `live_search.php`, `shop_service_type_feed.php` |
| `customer/payment.php` | `submit_payment_proof.php`, `detect_payment_reference.php` |
| `customer/orders.php` | `delete_customer_completed_order.php` |
| `shop_owner/orders.php` | `update_order_status.php`, `decline_order.php`, `download_order_file.php`, `verify_payment.php` |
| `shop_owner/shop_profile.php` | `save_shop_profile.php`, `reverse_geocode.php` |
| `shop_owner/services.php` | `add_service.php`, `update_service.php`, `delete_service.php`, `toggle_service.php`, `add_service_pricing.php`, `update_service_pricing.php`, `delete_service_pricing.php`, `toggle_service_pricing.php` |
| `shop_owner/payments.php` | `update_payment_settings_status.php` (self-service payment channel) |
| `superadmin/manage_users.php` | `update_user_status.php` |
| `superadmin/manage_print_shops.php` | `update_permit_status.php`, `update_payment_settings_status.php` |
| Any dashboard | `notification_feed.php`, `mark_notification_read.php`, `owner_order_feed.php`, notification pages |
| Cron | `pickup_reminder_checker.php` (standalone; sends pickup reminders) |

---

## 6. Database Schema (21 tables)

All setup is in `backend/database/ujvepwwv_PRINTEASE_DB.sql`. Key tables and relationships:

### Core entities
- **`users`** — `user_id` PK; `full_name`, `email`, `password` (bcrypt), `phone_number`, `address`, `role` (`customer|shop_owner|super_admin`), `account_status` (`incomplete|pending|verified|rejected|inactive`), `valid_id_file` / `valid_id_front_file` / `valid_id_back_file`, `profile_picture`, `latitude/longitude`, `auth_version` (bumps to invalidate sessions), `otp_code`, `otp_expires`.
- **`print_shops`** — `shop_id` PK, `owner_id` FK→`users.user_id`, `shop_name`, `shop_address`, `display_address`, `landmark`, `latitude/longitude`, `business_permit_file`, `shop_logo`, `shop_status` (`available|busy|not_accepting`), `permit_status` (`pending|verified|rejected|disabled`), `contact_number`, `weekday/weekend` open-close times. (Legacy GCash columns `gcash_name`, `gcash_number`, `gcash_qr_file`, `merchant_payment_link` remain but **current code uses `shop_payment_channels`**.)
- **`orders`** — `order_id` PK, `order_code` (e.g. `PE-20260828-ECCD95`), `submit_token`, `customer_id`, `shop_id`, document fields (`paper_size`, `paper_type`, `print_type`, `copies`, `page_count`), `service_category` (`document_printing|custom_service`), `service_id` (legacy pricing ref for document printing), `custom_service_id` + `service_name` (for custom service orders), `customer_instruction`, `pickup_datetime`, `pickup_reminder_sent`, `order_status` (`pending|processing|ready_for_pickup|completed|cancelled`), `customer_deleted_at` (soft delete), `total_amount`.
- **`payments`** — `payment_id` PK, `order_id`, `active_lock_order_id`, `customer_id`, `amount`, `payment_method` (`gcash_direct|gcash_merchant_link`), `reference_number`, `ocr_reference_number`, `ocr_payment_date`, `payment_reference_match` (`unchecked|matched|not_matched|not_detected|detected|partial`), `proof_of_payment_file`, `payment_status` (`pending|paid|unpaid`), `verification_status` (`pending|verified|rejected`), `verified_by`, `verified_at`, `rejection_reason`.

### Services & pricing (evolving model — see section 11)
- **`shop_service_types`** — per shop: `service_type` (e.g. "Document Printing", "Photo Printing", "Lamination"), `service_offered`, `online_available`, `customer_note`.
- **`shop_services`** — LEGACY per-page pricing for document printing: `paper_size`, `paper_type`, `print_type`, `price_per_page`, `is_available`.
- **`shop_service_pricing`** — LEGACY flexible pricing rows: `service_type`, `option_size`, `option_label`, `variant`, `unit`, `price`, `is_available`.
- **`shop_price_records`** — CURRENT unified pricing table: `shop_id`, `service_type`, `size_name`, `variant`, dimensions, `pricing_basis` (`per page` etc.), quantity range, `price`, `is_available`, plus `legacy_source`/`legacy_id` back-references.
- **`shop_custom_services`** — ad-hoc paid services: `shop_id`, `service_type`, `service_name`, `unit_price`, `unit_label`, `is_available`.

### Payments config
- **`shop_payment_channels`** — `channel` (`gcash_qr|gcash_merchant_link`), `gcash_account_name`, `gcash_number`, `gcash_qr_code`, `merchant_link`, `instructions`, `approval_status` (`pending|approved|rejected`) supervised by admin, `approved_by`/`approved_at`/`rejected_reason`, `is_active`.
- **`shop_payment_settings`** — legacy single-row payment settings (from an older iteration).
- **`shop_payment_accounts`** — older GCash account record (legacy).

### Orders support
- **`uploaded_files`** — per order: `file_name`, `file_path` (Cloudinary URL), `file_type`, `cloudinary_public_id`.

### Notifications & activity
- **`notifications`** — per user: `type` (e.g. `order_new`, `order_status`, `payment_verified`, `payment_submitted`, `permit_submitted`, `permit_status`, `payment_settings_submitted`, `payment_settings_status`, `account_submitted`, `account_status`), `title`, `message`, `target_url`, `metadata_json`, `is_read`, `read_at`.
- **`activity_logs`** — full audit trail: `user_id`, `action`, `module`, `target_type`/`target_id`, `old_value`/`new_value` (JSON), `ip_address`, `user_agent`.

### Security & support
- **`login_attempts`** — per-email/IP attempt counting and lockout.
- **`rate_limit_events`** — MySQL-backed rate limiting per action+identifier+IP: `attempt_count`, `window_started_at`, `blocked_until`.
- **`user_remember_tokens`** — "Remember me" persistent login: `selector` + `validator_hash` (sha256), `auth_provider` (`password|google`), `remember_duration_days`, `expires_at`.
- **`user_social_accounts`** — Google OAuth link: `provider`, `provider_user_id`, `provider_email`.
- **`customer_favorite_shops`** — `customer_id`↔`shop_id` favorites.
- **`geocode_cache`** — lat/lng → human address cache (Nominatim rate limit avoidance).

---

## 7. Authentication & Security Model

### Auth flow
- **Email/password registration** → OTP code emailed (6-digit, stored on user with expiry) → `verify_otp.php` → account starts `pending` (needs admin verification) or `verified` depending on role. Customers need valid ID upload before admin verifies them.
- **Google OAuth** → `backend/oauth/oauth_start.php` → `oauth_callback.php` → `social_register_process.php` (role chosen on `social_role.php`). Links via `user_social_accounts`.
- **Login** → rate-limited via `login_attempts` + `rate_limit`; supports **"Remember me"** via selector:validator tokens (`user_remember_tokens`); cookie set; `auth_version` bump invalidates all older sessions.
- **Forgot password** → OTP by email → `reset_password_process.php`.
- **Logout** clears cookie + session.

### Access control layers (each page runs several)
1. `session.php` / `secureSession()` — hardens session, sets headers.
2. `auth.php` — `requireAuth()`, `requireRole()`.
3. `profile_guard.php` — profile-completion fences (e.g. customer must have valid ID / shop must exist before using features).
4. `status_guard.php` — blocks suspended/inactive accounts; checks `permit_status`/`shop_status`.
5. `shop_guard.php` — ensures `SHOP_ID` session var matches and shop is active; ownership checks prevent IDOR.
6. `price_records.php` — helpers/validators for the pricing model.
7. `security_headers.php` — CSP (with nonces), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy.
8. `rate_limit.php` — per-action limits stored in `rate_limit_events`; uses `REMOTE_ADDR` unless `TRUSTED_PROXIES` configured.

### Security measures
- CSRF tokens on all POST forms (hidden field + session token).
- 100% prepared statements (mysqli) — no string-concatenated SQL.
- No secrets in code; `.env` gitignored; `.htaccess` denies `.env`, composer, package.json, and listable dirs.
- bcrypt password hashing (`$2y$`).
- File uploads: validated types/sizes, random names, stored under `uploads/` (besides PDFs → Cloudinary).
- Activity logging for every state change.
- Order codes are random (`PE-YYYYMMDD-XXXXXX`); submit token required to fetch a pending order without login.

---

## 8. Backend Actions (44 handlers in `backend/actions/`)

**Auth & registration**
- `login_process.php`, `logout.php`, `register_process.php`, `send_otp.php`, `verify_otp.php`, `verify_registration.php`, `reset_password_process.php`, `change_customer_password.php`, `change_owner_password.php`, `change_admin_password.php`

**Customer**
- `save_customer_profile.php` — profile + valid ID/profile pic upload
- `submit_order.php` — creates order + uploads PDF to Cloudinary + creates payment row
- `pending_order_token.php` — token lookup for unauthenticated order submission
- `submit_payment_proof.php` — uploads proof, records ref number, sets `payment_status=paid`
- `detect_payment_reference.php` — runs OCR (Tesseract/Vision) on the proof to auto-fill reference + date
- `toggle_favorite_shop.php`, `live_search.php`, `reverse_geocode.php`
- `delete_customer_completed_order.php` — soft delete (`customer_deleted_at`)

**Shop owner**
- `save_shop_profile.php` — profile, logo, permit upload; sets `permit_status=pending`
- `add_service.php` / `update_service.php` / `delete_service.php` / `toggle_service.php` — legacy document services
- `add_service_pricing.php` / `update_service_pricing.php` / `delete_service_pricing.php` / `toggle_service_pricing.php` — flexible pricing rows
- `update_order_status.php` — advances pending→processing→ready_for_pickup→completed
- `decline_order.php`, `update_shop_status.php` (available/busy/not accepting)
- `verify_payment.php` — admin/owner verifies or rejects payment proof (with reason)
- `update_payment_settings_status.php` — owner submits/updates GCash channel; admin approves/rejects

**Super admin**
- `update_user_status.php` — verify/reject/activate/deactivate accounts
- `update_permit_status.php` — verify/reject/disable permits
- `update_payment_settings_status.php` — (shared) approval of payment channels

**Feeds / AJAX (polling)**
- `notification_feed.php`, `mark_notification_read.php`, `owner_order_feed.php`, `shop_service_type_feed.php`, `live_search.php`

**System / jobs / tests**
- `pickup_reminder_checker.php` — cron-style script (email pickup reminders for soon-due orders)
- `download_order_file.php` — streams customer PDF/download
- `test_smtp.php`, `test_vision_api.php` — diagnostics
- `backfill_payment_ocr.php` — one-off migration/repair for OCR fields

---

## 9. Frontend Pages & Features by Role

### Customer (`frontend/user/customer/`)
| Page | Features |
|---|---|
| `dashboard.php` | KPIs (active requests, spending, progress tracker) |
| `explore.php` | Explore shops: All / Nearby (geolocation + distance) / Favorites |
| `shops.php` | Shop list & detail |
| `shopLocation.php` | Map view (OpenStreetMap) for a shop |
| `place_order.php` | Wizard: pick shop → service type → upload PDF → paper size/type/copies → estimated price → pickup date → submit |
| `orders.php` | My Requests: Active/Completed tabs, status tracking, focus via `?focus_order_id=` |
| `payment.php` | GCash QR / merchant-link display, payment-proof upload, OCR reference auto-detect |
| `notifications.php` | Notification center |
| `profile.php` | Name/phone/address, profile picture, valid ID upload (front+back) |

### Shop owner (`frontend/user/shop_owner/`)
| Page | Features |
|---|---|
| `dashboard.php` | Shop metrics, recent print jobs |
| `orders.php` | Manage print jobs through lifecycle; decline with reason; download file |
| `payments.php` | Verify/reject proofs (OCR-assisted); manage GCash channel submission |
| `services.php` | Service types, document print pricing, flexible pricing (toggle/CRUD) |
| `shop_profile.php` | Name/address/landmark/coords (geocoding), logo, business permit, operating hours |
| `transactions.php` | Transaction history |
| `reports.php` | Reports with date filters + export |
| `notifications.php` | Notification center with sound alerts |
| `update_status.php` | Availability toggle |

### Super admin (`frontend/user/superadmin/`)
| Page | Features |
|---|---|
| `dashboard.php` | Platform-wide metrics |
| `manage_users.php` | Verify/reject/activate/deactivate users |
| `manage_print_shops.php` | Approve/reject permits, approve payment channels, enable/disable shops |
| `reports.php` | Platform reports |
| `activity_logs.php` | Live audit trail (old/new JSON, IP, user agent) |
| `notifications.php`, `settings.php` | Center + settings |

### Shared
- `frontend/components/` — `head.php` (meta/CSP/icons), `branding.php` (logo renderers), per-role layouts (`customer_layout.php`, `owner_layout.php`, `admin_layout.php`), `notifications.php`, `toasts.php`/`customer_toasts.php`, `confirm_modal.php`.
- `frontend/assets/js/live-updates.js` — the polling engine (10–12 s) driving dashboards, feeds, badges, sound alerts; pauses when tab hidden.

---

## 10. Key Business Flows

### Onboarding
1. Customer/owner registers (email+OTP or Google). Account status = `pending`/`incomplete`.
2. Shop owner creates shop profile + submits **business permit** image → `permit_status=pending`.
3. Owner sets GCash payment channel (QR or merchant link) → `approval_status=pending`.
4. Super admin verifies account, permit, and payment channels → notifies owner.

### Order lifecycle
```
pending → processing → ready_for_pickup → completed
   ↳ cancelled / declined
```
1. Customer submits order (`submit_order.php`) → order_code, uploads PDF to Cloudinary, `payments` row created (`payment_status=pending`, `verification_status=pending`).
2. Owner notified (`order_new`), sees it in `orders.php`.
3. Owner updates status (processing → ready_for_pickup); customer notified at each step.
4. Customer pays via GCash & uploads proof (`submit_payment_proof.php`); OCR (`detect_payment_reference.php`) auto-fills reference number + date; `payment_status=paid`.
5. Owner verifies or rejects (`verify_payment.php`) → `verification_status=verified|rejected`; if verified the order can be completed.
6. `pickup_reminder_checker.php` emails reminders before `pickup_datetime` (one-time via `pickup_reminder_sent`).

### Live updates
All dashboards use `frontend/assets/js/live-updates.js` → poll `notification_feed.php` / `owner_order_feed.php` every 10–12 s; unread badge counts; Web Audio chime on new orders; polling pauses when tab hidden.

---

## 11. Pricing Model (IMPORTANT — mixed legacy/current)

The pricing model has evolved. Three tables overlap:
- **`shop_services`** — old per-page document-print pricing (Selection: paper_size/paper_type/print_type).
- **`shop_service_pricing`** — newer flexible "option" pricing per service type (e.g. Lamination A4, Photo Printing 2R Glossy).
- **`shop_price_records`** — the **current unified** table; rows carry `legacy_source`/`legacy_id` so existing data was migrated but keeps provenance.

`backend/includes/price_records.php` contains the canonical helpers for building/queries with this model. When adding pricing UI/calculations, prefer `shop_price_records` and keep back-compat with the two legacy tables.

---

## 12. Conventions to Follow When Modifying Code

1. **Prepared statements only** — mysqli `prepare()/bind_param()`, never concatenated SQL.
2. **POST handlers live in `backend/actions/`**; they require `session.php` + `auth.php` (or the relevant guard), validate CSRF + rate limit, do work, `addActivityLog(...)`, create a notification row, then PRG-redirect. Never render HTML from an action.
3. **Every state change logs an activity** (`activity_logs`) and usually a `notifications` row.
4. **Frontend pages** include the shared `head.php` for full markup; role pages include their role layout.
5. **CSRF token** is added to every form and validated server-side in the action.
6. **Feeds for live updates** return JSON and must be cheap DB queries.
7. **New Tailwind classes require rebuild**: `npm run build:tailwind` (input `assets/css/tailwind-input.css` → output `assets/css/tailwind.css`).
8. **Config via `.env`** — read with `envValue()` from `backend/config/env.php`; add new vars to `.env.example` too; never hardcode secrets.
9. **Don't commit `.env`** (gitignored). `.env.example` is the safe committed template.
10. **Rate limiting** — each sensitive action calls a rate-limit helper; keep `TRUSTED_PROXIES` unset unless behind a real sanitizing proxy.
11. **Uploads** go under `uploads/<category>/` with randomized names; customer PDFs go to Cloudinary (`cloudinary.php`).
12. **Soft-delete orders** via `customer_deleted_at`; delete only via the dedicated action.

---

## 13. Local Development / Build

- Run under **Laragon** (or XAMPP) at e.g. `http://localhost/printease/` with `.env` set to `APP_ENV=local` and `BASE_URL=http://localhost/printease/`.
- DB: import `backend/database/ujvepwwv_PRINTEASE_DB.sql` (schema + seed data) into `printease_db`.
- Tailwind (after editing PHP/HTML that uses new classes):
  - `npm run build:tailwind`
  - `npm run watch:tailwind` (dev)
- Composer install already done (`vendor/` present).
- Manual E2E testing guide: `docs/END_TO_END_MANUAL_TEST_PLAN.md`.
- Deployment checklist: `docs/DEPLOYMENT_CHECKLIST.md` and `docs/DEPLOYMENT_READINESS_CHECKLIST.md`.

---

## 14. Current Project Status

- Capstone/thesis project for print shops in Calbayog City, Samar, Philippines.
- Hosted at `https://printease.org/` (cPanel + z.com SMTP); repo in local git.
- Known context from docs: demo rated 7.5/10 for capstone, 6.5/10 for public production readiness — i.e. still being polished before the defense.