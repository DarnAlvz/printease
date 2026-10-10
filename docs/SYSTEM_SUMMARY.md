# PrintEase - E-Printing Management System

> PASTE-READY SUMMARY: Copy this file + `docs/CHATGPT_SYSTEM_CONTEXT.md` (full details) into any AI agent. Last updated 2026-10-01. Do not paste `.env` secrets.

## System Overview

**PrintEase** is a mobile and web-based e-printing management system that connects customers with verified print shops in **Calbayog City, Samar, Philippines**. Capstone/thesis project with complete marketplace workflow.

### Target Users
- **Customers** — Browse shops, submit print requests with file uploads + optional instruction, pay via GCash proof upload, track orders, Remove (hide) completed/cancelled orders
- **Shop Owners** — Manage shop profile, services/pricing, print jobs, payment verification, pickup balance/refund notes for Word/WPS orders, reports
- **Super Administrator** — Manage users, approve shops/permits and GCash channels, monitor reports and activity logs

---

## Tech Stack

### Frontend
| Technology | Details |
|---|---|
| HTML5 | Semantic HTML with ARIA |
| CSS3 + Tailwind CSS v4.3.2 | Built via `@tailwindcss/cli`: `npm run build:tailwind` (`tailwind-input.css` → `tailwind.css`) |
| Vanilla JavaScript | No framework; `live-updates.js` polling, modals, validation, Web Audio alerts; `order-drafts.js`, `location.js`, `shopLocation.js`, `filePreview.js`; `pdf.js` for previews |
| PWA v11 | `manifest.json` + `service-worker.js` (network-first HTML shell, cache-first static) |
| OpenStreetMap/Nominatim | Geocoding, reverse-geocode, distance; `geocode_cache` table |

### Backend
| Technology | Details |
|---|---|
| PHP 8.4+ Procedural | No MVC; direct file-based routing; Apache (Laragon/XAMPP local, cPanel prod) |
| MySQL/MariaDB | `printease_db`, utf8mb4, prepared statements only |
| Redis (optional) | `predis/predis` sessions/cache with file fallback |
| Apache | `.htaccess` blocks `.env`/composer/package files, `-Indexes`, static security headers |

### Third-Party Libraries (`composer.json` — 6 packages)
| Package | Purpose |
|---|---|
| `google/apiclient` v2.19 | Google OAuth2 sign-in |
| `phpmailer/phpmailer` v7.1 | SMTP email (OTP, verification, pickup reminders) |
| `thiagoalessio/tesseract_ocr` v2.13 | Legacy unused dep (code now uses OCR.space) |
| `cloudinary/cloudinary_php` v3.1 | Cloudinary storage for customer docs |
| `predis/predis` v2.3 | Redis client |
| `setasign/fpdi` v2.6 | PDF page count (`getPdfPageCountFromFpdi`) |

---

## Architecture

- **Flat file-based PHP** — routing is file paths, no router
- **Self-contained pages** — each PHP page includes session/auth/guards, boots DB (`$conn`), queries, renders HTML
- **Backend actions** — 45 scripts in `backend/actions/` process POSTs then PRG-redirect or JSON for AJAX
- **Live updates** — `live-updates.js`: notifications 3s, owner orders/activity/order-live 12s, explore/orders/services 10s, hidden tab 60s + `visibilitychange` refresh
- **Sessions** — Redis-backed with file fallback, 30-day sliding window
- **File uploads** — local `uploads/` (permits, logos, QR, proofs, ocr_tmp); customer docs on Cloudinary

---

## Features by Role

### Customer Features (9 pages: dashboard, explore, shops, shopLocation, place_order, orders, payment, notifications, profile)
- Dashboard KPIs (active requests, spending, progress)
- Explore shops (All, Nearby via geolocation, Favorites)
- Place Order wizard: service selection, file upload, paper size/type, copies, `customer_instruction` textarea, price estimate, pickup date; draft Remove via `order-drafts.js`
- My Requests Active/Completed tabs, `?focus_order_id=`, instruction display, pickup note banner (`Additional balance on pickup` / `Refund on pickup`)
- Payment page: GCash QR/merchant-link + instructions, manual reference (Optional) + screenshot upload; OCR runs server-side (no Detect button in UI)
- Notifications (3s polling), Profile (name/phone/address, avatar, valid ID front+back)
- Privacy Remove for `completed|cancelled` (soft-delete via `customer_deleted_at`, shop keeps record)

### Shop Owner Features (9 pages: dashboard, orders, payments, services, shop_profile, transactions, reports, notifications, update_status)
- Dashboard metrics + recent jobs
- Shop Profile (name, address, landmark, coords, logo, permit, hours)
- Services + pricing CRUD/toggle (legacy + unified `shop_price_records`, see full context doc)
- Print Jobs: `pending→processing→ready_for_pickup→completed`, `cancelled` via Decline (pending-only, blocked if proof exists, no reason field)
- `Send Note` for Word/WPS (`doc/docx/wps`) `ready_for_pickup` only: `additional|refund`, 1-500 chars must include amount, overwrite (DELETE+INSERT), 60/hour, notifies customer
- Payment verification (verify/reject with reason; displays `ocr_reference_number`, `ocr_payment_date`, `payment_reference_match`)
- File download/view (Cloudinary), Transactions, Reports with filters/export, GCash channel submission (QR/merchant link, admin approval), Notifications with sound toggle

### Super Admin Features (7 pages: dashboard, manage_users, manage_print_shops, reports, activity_logs, notifications, settings)
- Platform metrics, User Management (verify/reject/activate/deactivate)
- Manage Print Shops (permit approve/reject/disable, payment channel approve/reject)
- Platform Reports, Activity Logs audit trail (IP, UA, old/new JSON, 12s polling), Notifications + Settings

---

## Key Modules

### Authentication & Access Control
- Registration email/password + OTP (PHPMailer), Google OAuth2 (`oauth_start` → `oauth_callback` → `social_register_process`)
- Login with Remember-me selector:validator tokens, `auth_version` invalidation; Forgot password via OTP
- Roles: `customer`, `shop_owner`, `super_admin`; Statuses: `incomplete|pending|verified|rejected|inactive`
- Guards per page: `session`, `auth`, `profile_guard`, `status_guard`, `shop_guard` (ownership/IDOR), `price_records`, `security_headers` (CSP nonce, DENY, nosniff, HSTS on HTTPS only), `rate_limit` (`REMOTE_ADDR` unless `TRUSTED_PROXIES`)

### Order Lifecycle (correct — no `Accepted` status)
```
pending → processing → ready_for_pickup → completed
   ↳ cancelled (decline, pending-only)
   ↳ customer soft-delete hides completed|cancelled only
```
Customer submits → Owner accepts/processes → Ready for pickup → Customer pays proof upload → Owner verifies → Completed. Word/WPS ready orders may get one pickup balance/refund note (cash settlement on pickup). Pickup reminders emailed once via `pickup_reminder_checker.php`.

### Payment Integration (GCash manual, no gateway)
- Owner provides approved GCash QR and/or merchant link + instructions (`shop_payment_channels`)
- Customer uploads proof + optional manual reference → `payment_status=paid`
- Server runs OCR.space (`OCR_SPACE_API_KEY`) + regex (`gcash_ocr.php`) → `ocr_reference_number`, `ocr_payment_date`, `payment_reference_match`
- Owner verifies/rejects with reason → `verification_status`

### Real-Time Updates
- `notification_feed.php` (3s), `owner_order_feed.php`/activity/order-live (12s), explore/orders/services (10s); 60s when hidden; badge counts; Web Audio chime for new orders

### Security
- CSRF tokens, CSP nonces, DENY/nosniff/Referrer/Permissions headers, HSTS on HTTPS via PHP (not `.htaccess`)
- MySQL-backed rate limiting, prepared statements only, bcrypt, validated uploads (5MB, MIME, allow-listed OCR paths, downscaled to 2000px)
- Audit logging (`activity_logs`) + notifications on every state change; random order codes (`PE-YYYYMMDD-XXXXXX`) + submit tokens
- `.htaccess` denies `.env`, composer, package files; `.env` gitignored (note: `.env.example` currently missing `OCR_SPACE_API_KEY` and `TRUSTED_PROXIES`)

---

## Database & Integrations (22 tables)

| Integration | Purpose |
|---|---|
| MySQL (`printease_db`) | Primary DB: base `ujvepwwv_PRINTEASE_DB.sql` + `2026_09_07_add_foreign_keys.sql` (RESTRICT FKs) + `2026_09_26_add_order_notes.sql` (`order_notes`) |
| Redis | Sessions/cache (optional fallback) |
| Cloudinary | Customer doc storage |
| Google OAuth2 | Social sign-in |
| Gmail SMTP (local) / cPanel SMTP (prod) | OTP/verification (Gmail blocked on z.com) |
| OCR.space (`OCR_SPACE_API_KEY`) | Receipt OCR (current); Tesseract/Vision keys are legacy |
| Nominatim | Geocoding/distance |

Core tables: `users`, `print_shops`, `orders` (incl. `customer_instruction`, `customer_deleted_at`), `payments`, `order_notes` (NEW: `additional|refund`), `shop_service_types`, `shop_services` (legacy), `shop_service_pricing` (legacy), `shop_price_records` (current unified), `shop_custom_services`, `shop_payment_channels` (current) + 2 legacy payment tables, `uploaded_files`, `notifications` (incl. `order_balance_additional|refund`), `activity_logs`, `login_attempts`, `rate_limit_events`, `user_remember_tokens`, `user_social_accounts`, `customer_favorite_shops`, `geocode_cache`. See `CHATGPT_SYSTEM_CONTEXT.md` §6 for columns.

---

## Directory Structure

```
printease/
|-- index.php, .env/.env.example, manifest.json, service-worker.js (v11), .htaccess
|-- assets/css/, images/, js/pdf.min.js
|-- frontend/splash.php, components/ (customer_layout only), pages/ (9 public),
|   assets/js/live-updates.js, user/customer/ (9) + order-drafts.js/location.js,
|   user/shop_owner/ (9) + includes/owner_layout.php + shopLocation.js/filePreview.js,
|   user/superadmin/ (7) + includes/admin_layout.php
|-- backend/config/ (7 inc env.php), includes/ (13 php), actions/ (45),
|   oauth/ (3), helper/mailer.php, database/ (3 SQL + README)
|-- uploads/ (permits, shop_logos, gcash_qr, payment_proofs, customers, ocr_tmp)
|-- docs/ (THIS + CHATGPT_SYSTEM_CONTEXT.md/.txt + test/deploy guides)
|-- vendor/, node_modules/
```

Convention: `frontend/` renders, `backend/actions/` handles POST → log + notify → redirect/JSON.

---

## Project Status

- **Type:** Capstone/Thesis, Calbayog City, Samar, Philippines
- **Prod:** `https://printease.org/` (cPanel + z.com SMTP); **Local:** Laragon/XAMPP `http://localhost/printease/`
- **Setup:** Import 3 SQL files in order, set `.env` (`APP_ENV`, `BASE_URL`, DB, `OCR_SPACE_API_KEY`, Cloudinary, OAuth, SMTP), `npm run build:tailwind` after new Tailwind classes
- **Status:** Active pre-defense polish; for AI help paste this file + `CHATGPT_SYSTEM_CONTEXT.md` full details
