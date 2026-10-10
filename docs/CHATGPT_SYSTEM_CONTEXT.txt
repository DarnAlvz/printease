# PrintEase — System Context for Any AI Agent

> PASTE-READY: Copy this whole file into ChatGPT / Claude / Gemini / any AI agent, then ask your task. You do not need to explain the system manually.
> Last updated: 2026-10-01. Source of truth is the code in `C:\laragon\www\printease`.
> Do NOT paste `.env` values. Only reference variable names from Section 3.

---

## 1. What This System Is

PrintEase is a **mobile + web e-printing management system** for print shops in **Calbayog City, Samar, Philippines** (capstone/thesis project). It is a marketplace connecting three user roles:

- **Customer** — browses verified print shops, submits print requests with file uploads, pays via GCash (manual proof upload), tracks order status.
- **Shop Owner** — manages shop profile, business permit, services/pricing, print jobs, payment verification, pickup balance notes, reports.
- **Super Admin** — approves shops and permits, approves GCash payment channels, manages users, views reports and audit logs.

Domain language used in UI/code: "requests" = orders, "print shops" = shops, "permits" = business permits, "proof of payment" = GCash screenshot uploaded by customer, "customer_instruction" = optional note typed by customer at order time, "pickup note / adjustment note" = shop-written `order_notes` row (`additional` = pay extra on pickup, `refund` = get refund on pickup), "Remove" = customer soft-delete that only hides a `completed|cancelled` order from the customer view.

---

## 2. Tech Stack

| Layer | Technology | Notes |
|---|---|---|
| Frontend | HTML5, CSS3, **Tailwind CSS v4.3.2** (built via `@tailwindcss/cli`), **vanilla JS** (no framework) | Role-specific CSS + shared Tailwind build; `pdf.js` (`pdf.min.js`) for PDF previews |
| Backend | **Procedural PHP** (no MVC framework, direct file-based routing), mysqli prepared statements | PHP 8.4+, Apache (Laragon/XAMPP locally, cPanel shared hosting in prod) |
| Database | MySQL/MariaDB utf8mb4 (`printease_db`) | Base dump + 2 migrations in `backend/database/` |
| Sessions | Redis (predis) with **file-based fallback** | Redis optional; unset on shared hosting |
| PWA | `manifest.json` + `service-worker.js` v11 | Installable; network-first HTML shell, cache-first static, runtime cap 120 |
| Build tools | npm scripts for Tailwind | `npm run build:tailwind`, `npm run watch:tailwind` |

### Composer packages (`composer.json`) — 6 packages

| Package | Purpose |
|---|---|
| `google/apiclient` v2.19 | Google OAuth2 social sign-in |
| `phpmailer/phpmailer` v7.1 | SMTP email (OTP codes, verification, pickup reminders) |
| `thiagoalessio/tesseract_ocr` v2.13 | LEGACY/UNUSED dependency — code no longer calls Tesseract |
| `cloudinary/cloudinary_php` v3.1 | Cloud storage for customer PDF/document uploads |
| `predis/predis` v2.3 | Redis session/cache client |
| `setasign/fpdi` v2.6 | PDF page-count (`getPdfPageCountFromFpdi` in `backend/includes/functions.php`) |

### External integrations (current)

- Google OAuth2 (login), Gmail SMTP locally + cPanel SMTP in production (Gmail is blocked on z.com hosting), Cloudinary (docs/files), **OCR.space API** (`OCR_SPACE_API_KEY`) for GCash receipt OCR, OpenStreetMap/Nominatim (geocoding, reverse-geocode, distance), GCash **manual** proof verification — no payment gateway API.

---

## 3. Configuration

All config is in `.env` (gitignored; `.env.example` is the committed template). Everything is loaded through `backend/config/env.php` via `envValue()`.

Key variables:

| Variable | Purpose |
|---|---|
| `APP_NAME` / `APP_ENV` | `local` (shows errors) vs `production` (hides errors, logs them) |
| `BASE_URL` | Full base URL with trailing slash, e.g. `https://printease.org/` or `http://localhost/printease/` |
| `DB_HOST/DB_USER/DB_PASS/DB_NAME` | MySQL connection (persistent `p:host` in `db.php`); honors `DB_CONNECTION_OPTIONAL` |
| `OCR_SPACE_API_KEY` | **Required for OCR.** Missing from `.env.example` — add it manually. Code reads `getenv('OCR_SPACE_API_KEY')` in `backend/includes/gcash_ocr.php` |
| `GOOGLE_CLOUD_VISION_API_KEY` | Legacy fallback key, no longer called by `gcash_ocr.php` |
| `TESSERACT_PATH` | Legacy var, no longer called |
| `GOOGLE_CLIENT_ID/SECRET` | OAuth (authorized redirect: `.../backend/oauth/oauth_callback.php?provider=google`) |
| `MAIL_USER/MAIL_PASS` | Gmail SMTP (smtp.gmail.com:587, app password) — local dev |
| `SMTP_HOST/SMTP_USER/SMTP_PASS` | cPanel SMTP (e.g. `cpanel11wh.jpt1.cloud.z.com`) — production, because Gmail is blocked on z.com |
| `CLOUD_NAME/API_KEY/API_SECRET` | Cloudinary |
| `REDIS_HOST/PORT` | Optional Redis; sessions fall back to files if unset/unreachable |
| `SESSION_LIFETIME_DAYS` | Sliding session window, default 30; commented out in `.env.example` |
| `TRUSTED_PROXIES` | Only set behind a sanitizing reverse proxy; rate limiting uses `REMOTE_ADDR` otherwise. Documented in `README.md` but **absent from `.env.example`** |

Config loaders in `backend/config/`: `env.php` (loader), `app.php` (defines `APP_NAME`, `APP_ENV`, `BASE_URL`, `UPLOADS_URL`, `PERMITS_URL`, `SHOP_LOGOS_URL`, `GCASH_QR_URL`), `db.php` (`$conn` mysqli handle), `cloudinary.php`, `oauth.php`, `redis.php`.

---

## 4. Directory Structure

```
printease/
|-- index.php                     # Public landing + auth bounce (redirectIfAuthenticated, seen_splash)
|-- .env / .env.example           # Env config (never commit real .env)
|-- .htaccess                     # Blocks .env/composer/package files, -Indexes, image types, static headers
|-- .user.ini                     # Host PHP settings
|-- manifest.json                 # PWA manifest (standalone, 192/512/maskable)
|-- service-worker.js             # PWA v11 (SHELL_CACHE + RUNTIME_CACHE)
|-- robots.txt
|
|-- assets/
|   |-- css/                      # tailwind.css (generated), index.css, tailwind-input.css
|   |-- images/                   # logos, favicons, PWA icons
|   `-- js/                       # pdf.min.js, pdf.worker.min.js
|
|-- frontend/
|   |-- splash.php                # animated splash (first visit)
|   |-- components/               # head.php, branding.php, customer_layout.php, auth_brand_panel.php,
|   |                             #   notifications.php, toasts.php, customer_toasts.php, confirm_modal.php
|   |-- pages/                    # PUBLIC: login, register, forgot_password, reset_password, verify_otp,
|   |                             #   verify_registration, social_role, terms, privacy
|   |-- assets/js/live-updates.js # CORE polling engine (1433 lines, 3s/10s/12s + 60s hidden)
|   `-- user/
|       |-- customer/             # dashboard, explore, shops, shopLocation, place_order, orders,
|       |                         #   payment, notifications, profile + assets/js/order-drafts.js, location.js
|       |-- shop_owner/           # dashboard, orders, payments, services, shop_profile, transactions,
|       |                         #   reports, notifications, update_status + includes/owner_layout.php
|       |                         #   + assets/js/shopLocation.js, filePreview.js
|       `-- superadmin/           # dashboard, manage_users, manage_print_shops, reports,
|                                 #   activity_logs, notifications, settings + includes/admin_layout.php
|
|-- backend/
|   |-- config/                   # app, db, cloudinary, oauth, redis, env (+ .htaccess)
|   |-- includes/                 # auth, session, remember_auth, functions, cache, security_headers,
|   |                             #   rate_limit, profile_guard, shop_guard, status_guard,
|   |                             #   price_records, gcash_ocr, oauth_helpers (+ .htaccess)
|   |-- actions/                  # 45 standalone POST handlers (see section 8)
|   |-- oauth/                    # oauth_start, oauth_callback, social_register_process
|   |-- helper/                   # mailer.php (PHPMailer: OTP, notifications)
|   `-- database/                 # ujvepwwv_PRINTEASE_DB.sql (base) + 2026_09_07_add_foreign_keys.sql
|                                 #   + 2026_09_26_add_order_notes.sql + README + .htaccess
|
|-- deploy/                       # deployment scripts/config
|-- docs/                         # THIS file, SYSTEM_SUMMARY.md, test plans, checklists
|-- uploads/                      # permits/, shop_logos/, gcash_qr/, payment_proofs/, customers/, ocr_tmp/
|-- vendor/  node_modules/        # composer + npm dependencies
```

**Convention that matters:** `frontend/` renders UI (each page includes guards, boots DB, queries, renders HTML). `backend/actions/` processes POSTs then `header("Location: ...")` back (PRG pattern, JSON if `X-Requested-With: XMLHttpRequest`). Never render HTML from an action.

Layout note: only `customer_layout.php` lives in `frontend/components/`. Owner/admin layouts are `frontend/user/shop_owner/includes/owner_layout.php` and `frontend/user/superadmin/includes/admin_layout.php`.

---

## 5. Request / Routing Flow

There is **no router**. Every page is a real file hit directly. Flow inside every page:

1. `require backend/includes/session.php` → `secureSession()` (hardening, Redis/file backend).
2. `require backend/includes/auth.php` → `requireAuth()` / role guards; boot DB via `backend/config/db.php` (`$conn`).
3. Role/status guards (Section 7) enforce access.
4. Page queries MySQL with **prepared statements**, renders HTML.
5. Forms `POST` to `backend/actions/<name>.php`, which validates CSRF + rate limit, mutates DB, writes activity log + notification row, then redirects back (PRG) or returns JSON for AJAX.
6. Dashboards poll feed endpoints via `live-updates.js`.

### Typical page → action map

| Page | Actions it calls |
|---|---|
| `frontend/pages/login.php` | `login_process.php`, `logout.php` |
| `frontend/pages/register.php` | `register_process.php`, `send_otp.php`, `verify_otp.php`, `verify_registration.php` |
| `frontend/pages/forgot_password.php` + `reset_password.php` | `send_otp.php`, `reset_password_process.php` |
| `customer/place_order.php` | `submit_order.php` (→ `pending_order_token.php`), `toggle_favorite_shop.php`, `live_search.php`, `shop_service_type_feed.php` |
| `customer/payment.php` | `submit_payment_proof.php` (server-side OCR via `detect_payment_reference.php` logic, no Detect button in UI) |
| `customer/orders.php` | `delete_customer_completed_order.php` (soft-delete `completed|cancelled`) |
| `shop_owner/orders.php` | `update_order_status.php`, `decline_order.php`, `download_order_file.php`, `verify_payment.php`, `send_order_adjustment_note.php` |
| `shop_owner/shop_profile.php` | `save_shop_profile.php`, `reverse_geocode.php` |
| `shop_owner/services.php` | `add_service.php`, `update_service.php`, `delete_service.php`, `toggle_service.php`, `add_service_pricing.php`, `update_service_pricing.php`, `delete_service_pricing.php`, `toggle_service_pricing.php` |
| `shop_owner/payments.php` | `update_payment_settings_status.php` (owner submits GCash channel), `verify_payment.php` |
| `superadmin/manage_users.php` | `update_user_status.php` |
| `superadmin/manage_print_shops.php` | `update_permit_status.php`, `update_payment_settings_status.php` |
| Any dashboard | `notification_feed.php`, `mark_notification_read.php`, `owner_order_feed.php` |
| Cron | `pickup_reminder_checker.php` (pickup reminders) |

---

## 6. Database Schema (22 tables)

Setup: import `backend/database/ujvepwwv_PRINTEASE_DB.sql` first, then run `2026_09_07_add_foreign_keys.sql` (orphan audit + `ON DELETE RESTRICT` FKs), then `2026_09_26_add_order_notes.sql` (creates `order_notes`). Fresh installs must apply all three in order.

### Core entities

- **`users`** — `user_id` PK; `full_name`, `email`, `password` (bcrypt `$2y$`), `phone_number`, `address`, `role` (`customer|shop_owner|super_admin`), `account_status` (`incomplete|pending|verified|rejected|inactive`), `valid_id_file` / `valid_id_front_file` / `valid_id_back_file`, `profile_picture`, `latitude/longitude`, `auth_version` (bump invalidates sessions), `otp_code`, `otp_expires`.
- **`print_shops`** — `shop_id` PK, `owner_id` FK→`users`, `shop_name`, `shop_address`, `display_address`, `landmark`, `latitude/longitude`, `business_permit_file`, `shop_logo`, `shop_status` (`available|busy|not_accepting`), `permit_status` (`pending|verified|rejected|disabled`), `contact_number`, weekday/weekend hours. Legacy GCash columns remain but current code uses `shop_payment_channels`.
- **`orders`** — `order_id` PK, `order_code` (`PE-YYYYMMDD-XXXXXX`), `submit_token`, `customer_id`, `shop_id`, document fields (`paper_size`, `paper_type`, `print_type`, `copies`, `page_count`), `service_category` (`document_printing|custom_service`), `service_id` (legacy), `custom_service_id` + `service_name`, `customer_instruction` (optional textarea from `place_order.php`), `pickup_datetime`, `pickup_reminder_sent`, `order_status` (`pending|processing|ready_for_pickup|completed|cancelled` — there is NO `Accepted` status), `customer_deleted_at` (soft-delete, hides from customer only), `total_amount`.
- **`payments`** — `payment_id` PK, `order_id`, `active_lock_order_id`, `customer_id`, `amount`, `payment_method` (`gcash_direct|gcash_merchant_link`), `reference_number` (manual input, optional), `ocr_reference_number`, `ocr_payment_date`, `payment_reference_match` (`unchecked|matched|not_matched|not_detected|detected|partial`), `proof_of_payment_file`, `payment_status` (`pending|paid|unpaid`), `verification_status` (`pending|verified|rejected`), `verified_by/at`, `rejection_reason`.
- **`order_notes`** — NEW (2026-09-26): `note_id` PK, `order_id` FK→`orders` RESTRICT, `shop_id` FK→`print_shops` RESTRICT, `note_type` (`additional|refund`), `note_text` (varchar 500, must contain digit, `{X}` placeholder rejected), `created_by`, `created_at`. One active row per order (action does DELETE+INSERT overwrite).

### Services and pricing (mixed legacy/current — see Section 11)

- **`shop_service_types`** — per shop: `service_type` (e.g. Document Printing, Photo Printing, Lamination), `service_offered`, `online_available`, `customer_note`.
- **`shop_services`** — LEGACY per-page document pricing: `paper_size`, `paper_type`, `print_type`, `price_per_page`, `is_available`.
- **`shop_service_pricing`** — LEGACY flexible rows: `service_type`, `option_size`, `option_label`, `variant`, `unit`, `price`, `is_available`.
- **`shop_price_records`** — CURRENT unified table with `legacy_source`/`legacy_id` back-refs.
- **`shop_custom_services`** — ad-hoc services: `service_type`, `service_name`, `unit_price`, `unit_label`, `is_available`.

### Payments config

- **`shop_payment_channels`** — CURRENT: `channel` (`gcash_qr|gcash_merchant_link`), `gcash_account_name`, `gcash_number`, `gcash_qr_code`, `merchant_link`, `instructions`, `approval_status` (`pending|approved|rejected`), `approved_by/at`, `rejected_reason`, `is_active`.
- **`shop_payment_settings`**, **`shop_payment_accounts`** — legacy rows from older iterations.

### Orders support

- **`uploaded_files`** — per order: `file_name`, `file_path` (Cloudinary URL), `file_type`, `cloudinary_public_id`.

### Notifications and activity

- **`notifications`** — per user: `type` (`order_new`, `order_status`, `order_balance_additional`, `order_balance_refund`, `payment_verified`, `payment_submitted`, `permit_submitted`, `permit_status`, `payment_settings_submitted`, `payment_settings_status`, `account_submitted`, `account_status`), `title`, `message`, `target_url` (often `?focus_order_id=`), `metadata_json`, `is_read`, `read_at`.
- **`activity_logs`** — audit: `user_id`, `action`, `module`, `target_type/id`, `old_value/new_value` JSON, `ip_address`, `user_agent`.

### Security and support

- **`login_attempts`** — per-email/IP lockout counting.
- **`rate_limit_events`** — MySQL rate limiting per action+identifier+IP.
- **`user_remember_tokens`** — Remember-me `selector` + `validator_hash` (sha256), `auth_provider` (`password|google`), `expires_at`.
- **`user_social_accounts`** — Google link: `provider`, `provider_user_id`, `provider_email`.
- **`customer_favorite_shops`** — `customer_id`↔`shop_id`.
- **`geocode_cache`** — lat/lng → address cache (Nominatim rate-limit avoidance).

---

## 7. Authentication and Security Model

### Auth flow

- Email/password registration → 6-digit OTP emailed (stored on user with expiry) → `verify_otp.php` → status `pending`/`incomplete` until admin verifies. Customers must upload valid ID before verification.
- Google OAuth → `backend/oauth/oauth_start.php` → `oauth_callback.php` → `social_register_process.php` (role chosen on `social_role.php`). Linked via `user_social_accounts`.
- Login → rate-limited via `login_attempts` + `rate_limit`; Remember-me via selector:validator tokens; `auth_version` bump invalidates old sessions.
- Forgot password → OTP email → `reset_password_process.php`. Logout clears cookie + session.

### Access control layers (each page runs several)

1. `session.php` / `secureSession()` — hardening, Redis/file backend.
2. `auth.php` — `requireAuth()`, `requireRole()`, `checkRole()`.
3. `profile_guard.php` — completion fences (customer valid ID, shop must exist).
4. `status_guard.php` — blocks suspended/inactive; checks permit/shop status.
5. `shop_guard.php` — `SHOP_ID` session match + ownership checks (IDOR mitigation).
6. `price_records.php` — pricing validators/helpers.
7. `security_headers.php` — CSP with nonces, `X-Frame-Options: DENY`, nosniff, Referrer-Policy, Permissions-Policy, `Cache-Control: no-store`, HSTS only when HTTPS (`Strict-Transport-Security`).
8. `rate_limit.php` — per-action limits in `rate_limit_events`; uses `REMOTE_ADDR` unless `TRUSTED_PROXIES` is configured.

### Security measures

- CSRF tokens on all POST forms, validated via `validateCsrf()`.
- 100% prepared statements (mysqli `prepare/bind_param`).
- No secrets in code; `.env` gitignored; `.htaccess` denies `.env`, `composer.json/lock`, `package.json/lock`, `.user.ini`, `NEED TO DOWNLOAD.txt`, disables indexes.
- bcrypt passwords, randomized upload names, validated types/sizes (proof images: jpg/jpeg/png/webp/jfif, 5MB max, MIME-checked, OCR path allow-listed to `uploads/payment_proofs/` + `ocr_tmp/`, large images downscaled to 2000px for OCR).
- Activity log + notification on every state change. Random order codes. Submit token for token-based order lookup.

---

## 8. Backend Actions (45 handlers in `backend/actions/`)

**Auth and registration (10)**
- `login_process.php`, `logout.php`, `register_process.php`, `send_otp.php`, `verify_otp.php`, `verify_registration.php`, `reset_password_process.php`, `change_customer_password.php`, `change_owner_password.php`, `change_admin_password.php`

**Customer (9)**
- `save_customer_profile.php` — profile + ID/profile-pic upload
- `submit_order.php` — creates order + Cloudinary upload + `payments` row (`pending`/`pending`); saves `customer_instruction`
- `pending_order_token.php` — token lookup
- `submit_payment_proof.php` — uploads proof, records optional manual `reference_number`, sets `payment_status=paid`, runs server-side OCR.space parse
- `detect_payment_reference.php` — OCR endpoint (Tesseract/Vision names are legacy; actual parse is OCR.space + regex in `gcash_ocr.php`)
- `toggle_favorite_shop.php`, `live_search.php`, `reverse_geocode.php`
- `delete_customer_completed_order.php` — soft-delete via `customer_deleted_at` (only `completed|cancelled`; shop record kept)

**Shop owner (13)**
- `save_shop_profile.php` — logo/permit upload, sets `permit_status=pending`
- `add_service.php` / `update_service.php` / `delete_service.php` / `toggle_service.php`
- `add_service_pricing.php` / `update_service_pricing.php` / `delete_service_pricing.php` / `toggle_service_pricing.php`
- `update_order_status.php` — `pending→processing→ready_for_pickup→completed` (409 on concurrent update)
- `decline_order.php` — pending-only, blocked if payment proof exists, sets `cancelled`, notifies customer. No reason field.
- `update_shop_status.php` (`available|busy|not_accepting`)
- `send_order_adjustment_note.php` — only when `ready_for_pickup` + file is `doc/docx/wps`; `note_type` + `note_text` (1-500 chars, must include digit, `{X}` rejected); DELETE+INSERT overwrite; rate 60/hour; notifies `order_balance_additional|refund`
- `verify_payment.php` — verify/reject proof with reason; sets `verification_status`

**Super admin (3, 1 shared)**
- `update_user_status.php`, `update_permit_status.php`, `update_payment_settings_status.php` (shared owner+admin approval flow)

**Feeds / AJAX (5)**
- `notification_feed.php`, `mark_notification_read.php`, `owner_order_feed.php`, `shop_service_type_feed.php`, `live_search.php`

**System / jobs / tests (5)**
- `pickup_reminder_checker.php` (cron email, once via `pickup_reminder_sent`), `download_order_file.php` (streams Cloudinary/local PDF), `test_smtp.php`, `test_vision_api.php` (actually tests OCR.space despite name), `backfill_payment_ocr.php` (one-off OCR repair)

---

## 9. Frontend Pages and Features by Role

### Customer (`frontend/user/customer/` — 9 pages)

| Page | Features |
|---|---|
| `dashboard.php` | KPIs (active requests, spending, progress tracker) |
| `explore.php` | All / Nearby (geolocation + distance) / Favorites; live polling |
| `shops.php` | Shop list and detail |
| `shopLocation.php` | OpenStreetMap map view |
| `place_order.php` | Wizard: shop → service type → upload → paper/copies → `customer_instruction` textarea → price estimate → pickup date → submit; draft Remove via `order-drafts.js` |
| `orders.php` | My Requests Active/Completed tabs, `?focus_order_id=`, instruction display, `order_notes` banner (`Additional balance on pickup` / `Refund on pickup`), `Remove`/`Remove Request` soft-delete for `completed|cancelled` only |
| `payment.php` | GCash QR / merchant-link + instructions display, manual reference (Optional) + screenshot upload → `submit_payment_proof.php`; NO Detect/auto-fill button (OCR runs server-side) |
| `notifications.php` | Notification center (3s polling) |
| `profile.php` | Name/phone/address, avatar, valid ID front+back upload |

JS: `assets/js/order-drafts.js` (pending-draft Remove + instruction draft), `assets/js/location.js`.

### Shop owner (`frontend/user/shop_owner/` — 9 pages)

| Page | Features |
|---|---|
| `dashboard.php` | Shop metrics, recent jobs |
| `orders.php` | Lifecycle management, AJAX Decline (pending-only), `Send Note` pills (`Request additional/refund` + `₱{X}` templates) for Word/WPS `ready_for_pickup` only, file download/view |
| `payments.php` | Verify/reject proofs with OCR display (`ocr_reference_number`, `ocr_payment_date`, `payment_reference_match`), GCash channel submission |
| `services.php` | Service types + legacy + flexible pricing CRUD/toggle (live polling) |
| `shop_profile.php` | Name/address/landmark/coords (Nominatim), logo, permit, hours |
| `transactions.php` | History |
| `reports.php` | Date filters + export |
| `notifications.php` | Center + Web Audio chime + sound toggle |
| `update_status.php` | Availability toggle |

JS: `assets/js/shopLocation.js`, `assets/js/filePreview.js`. Layout: `includes/owner_layout.php`.

### Super admin (`frontend/user/superadmin/` — 7 pages)

| Page | Features |
|---|---|
| `dashboard.php` | Platform metrics |
| `manage_users.php` | Verify/reject/activate/deactivate |
| `manage_print_shops.php` | Permit approve/reject/disable, payment channel approve/reject |
| `reports.php` | Platform reports |
| `activity_logs.php` | Live audit trail (old/new JSON, IP, UA, 12s polling) |
| `notifications.php`, `settings.php` | Center + settings |

Layout: `includes/admin_layout.php`.

### Shared

- `frontend/components/head.php` (meta/CSP/nonce/icons), `branding.php`, `customer_layout.php`, `notifications.php`, `toasts.php`/`customer_toasts.php`, `confirm_modal.php`, `auth_brand_panel.php`.
- `frontend/assets/js/live-updates.js` — polling: notifications 3s, owner orders + activity + order-live 12s, explore/orders/services/service-types 10s, hidden tab 60s, `visibilitychange` immediate refresh, badge counts, owner chime.

---

## 10. Key Business Flows

### Onboarding

1. Customer/owner registers (email+OTP or Google). Status `pending`/`incomplete`.
2. Owner creates shop profile + business permit image → `permit_status=pending`.
3. Owner submits GCash channel (QR or merchant link) → `approval_status=pending`.
4. Super admin verifies account + permit + channel → notifications sent.

### Order lifecycle (no `Accepted` status)

```
pending → processing → ready_for_pickup → completed
   ↳ cancelled (via decline_order.php, pending-only)
   ↳ customer soft-delete hides completed|cancelled (customer_deleted_at)
```

1. Customer submits (`submit_order.php`) → `order_code`, Cloudinary upload, `payments` row (`pending`/`pending`).
2. Owner notified (`order_new`), sees it in `orders.php` / `owner_order_feed.php`.
3. Owner advances status; customer notified each step.
4. Customer pays via GCash, uploads proof (`submit_payment_proof.php`); server runs OCR.space → fills `ocr_reference_number`/`ocr_payment_date`/`payment_reference_match`; `payment_status=paid`.
5. Owner verifies/rejects (`verify_payment.php`) → `verified|rejected`; verified orders can complete.
6. Word/WPS `ready_for_pickup` only: owner may send pickup balance note (`send_order_adjustment_note.php`, overwrite, 60/hour) → customer sees banner + `order_balance_additional|refund` notification, settled in cash on pickup.
7. `pickup_reminder_checker.php` emails once before `pickup_datetime` (`pickup_reminder_sent` flag).

### Live updates

`live-updates.js` polls `notification_feed.php` (3s), `owner_order_feed.php` / activity / order-live (12s), explore/orders/services/service-types (10s); 60s when tab hidden; instant refresh on `visibilitychange`; Web Audio chime for new owner orders; unread badges.

---

## 11. Pricing Model (IMPORTANT — mixed legacy/current)

Three tables overlap; `backend/includes/price_records.php` is canonical:

- **`shop_services`** — old per-page document pricing (`paper_size/paper_type/print_type`).
- **`shop_service_pricing`** — flexible option pricing (e.g. Lamination A4).
- **`shop_price_records`** — CURRENT unified table with `legacy_source`/`legacy_id` provenance.
- **`shop_custom_services`** — ad-hoc paid services (`service_name`, `unit_price`).

When adding pricing UI/calculations, prefer `shop_price_records` and keep back-compat with the two legacy tables. `shop_service_types` controls which types are offered/online per shop.

---

## 12. Conventions to Follow When Modifying Code

1. Prepared statements only — `prepare()/bind_param()`, never concatenated SQL.
2. POST handlers in `backend/actions/`; require `session.php` + `auth.php`/guards, validate CSRF + rate limit, do work, `addActivityLog()`/`logActivity()`, create notification, then PRG-redirect or JSON for AJAX. Never render HTML from an action.
3. Every state change logs activity + usually a notification with `target_url` containing `?focus_order_id=`.
4. Frontend pages include `head.php`; role pages include their role layout (`customer_layout.php` vs `owner_layout.php` vs `admin_layout.php`).
5. CSRF token in every form, validated server-side.
6. Feeds return cheap JSON queries (used by 3s/10s/12s polling).
7. New Tailwind classes require rebuild: `npm run build:tailwind` (input `assets/css/tailwind-input.css` → `assets/css/tailwind.css`).
8. Config via `.env` + `envValue()`; add new vars to `.env.example` too; never hardcode secrets; add missing `OCR_SPACE_API_KEY` to your local `.env`.
9. Do not commit `.env`. `.env.example` is the safe template (currently missing `OCR_SPACE_API_KEY` and `TRUSTED_PROXIES` — add them when editing env).
10. Rate limiting per sensitive action; keep `TRUSTED_PROXIES` unset unless behind a real sanitizing proxy.
11. Uploads under `uploads/<category>/` randomized; customer docs to Cloudinary; proofs allow-listed + 5MB + downscaled for OCR.
12. Soft-delete customer orders via `customer_deleted_at` only through `delete_customer_completed_order.php`.
13. Adjustment notes only via `send_order_adjustment_note.php` (ready_for_pickup + doc/docx/wps, 1-500 chars, must include amount digit).
14. Decline only via `decline_order.php` (pending-only, blocked if proof exists, no reason field, sets `cancelled`).

---

## 13. Local Development / Build

- Run under **Laragon** (or XAMPP) at e.g. `http://localhost/printease/` with `APP_ENV=local`, `BASE_URL=http://localhost/printease/`.
- DB `printease_db`: import `backend/database/ujvepwwv_PRINTEASE_DB.sql`, then run `2026_09_07_add_foreign_keys.sql` (check orphans = 0 first), then `2026_09_26_add_order_notes.sql`. All FKs are `ON DELETE RESTRICT`.
- Tailwind after editing PHP/HTML with new classes: `npm run build:tailwind` (`npm run watch:tailwind` for dev). Deps already in `vendor/` + `node_modules/`.
- E2E manual guide: `docs/END_TO_END_MANUAL_TEST_PLAN.md`. Deploy: `docs/DEPLOYMENT_CHECKLIST.md`, `docs/DEPLOYMENT_READINESS_CHECKLIST.md`.
- Diagnostics: `backend/actions/test_smtp.php`, `test_vision_api.php` (OCR.space), `backfill_payment_ocr.php`.

---

## 14. Current Project Status and How to Use This File

- Capstone/thesis for Calbayog City print shops. Prod at `https://printease.org/` (cPanel + z.com SMTP). Local git repo.
- PWA v11, live polling (3s/10s/12s, 60s hidden), GCash manual + OCR.space assist, Cloudinary files, Google OAuth, Nominatim maps.
- To use with AI: paste this entire file first, then say e.g. "In `shop_owner/orders.php`, add X following Section 12 conventions, using prepared statements and activity logs." Do not paste `.env` secrets.
