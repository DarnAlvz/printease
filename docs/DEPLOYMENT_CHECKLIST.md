# PrintEase Deployment Checklist — z.com Shared Hosting

Step-by-step guide to deploy PrintEase to z.com shared hosting (cPanel) on the domain `printease.org`.

Companion docs:

- `docs/DEPLOYMENT_READINESS_CHECKLIST.md` — readiness audit and defense wording.
- `docs/END_TO_END_MANUAL_TEST_PLAN.md` — full functional test script (Phases 1-15).

---

## 1. Order the plan and note the basics

- [✓] Order the z.com shared hosting plan with a domain (annual plan includes the domain free for year 1).
- [✓] Confirm the domain is `printease.org`.
- [✓] cPanel is available (this plan includes cPanel, SSH, and FTP).
- [✓] Note the server's FTP host / SSH host / cPanel credentials.

## 2. cPanel basic setup

- [✓] **Select PHP 8.3** — cPanel → Select PHP Version (or MultiPHP Manager). Local runs 8.3; do not go below 8.1.
- [✓] Confirm required PHP extensions are enabled: `mysqli`, `curl`, `openssl`, `mbstring`, `gd`, `fileinfo`, `zlib`. (Ask host support if any are missing.)
- [✓] `proc_open` and Tesseract OCR are usually unavailable on shared hosting. This is fine — payment-reference OCR degrades gracefully and reference numbers can be entered manually.

## 3. Create the database

- [✓] cPanel → MySQL Databases → create database (e.g. `printease_db`).
- [✓] Create a dedicated user (e.g. `printease_user`) with a strong password. **Do not use `root`**.
- [✓] Add the user to the database with **ALL PRIVILEGES** during setup.
- [✓] Open **phpMyAdmin**, select the new database, import **`backend/database/0000_base_schema.sql`**.
- [✓] **Do not** run the individual migration files separately — `0000_base_schema.sql` is the consolidated schema for fresh installs and already includes the `orders.submit_token` column and all later migrations.

## 4. Upload the files

Two options (SSH is included in this plan, so option A is recommended).

- [] **Option A (SSH):**
  1. `ssh user@printease.org` (details in cPanel → SSH Access).
  2. Upload the project to the domain document root (`public_html/`), e.g. with `scp` or `git clone`.
  3. Run in the project root:
     ```
     composer install --no-dev --optimize-autoloader
     ```
- [] **Option B (FTP / File Manager):**
  1. Build the release zip locally with `deploy/zip-release.ps1` (PowerShell), which excludes `node_modules/`, `.git/`, `.env`, `.vscode/`, `.agents/`, `deploy/`, and `uploads/*`.
  2. Upload the zip to `public_html/` and extract it so that `index.php`, `service-worker.js`, and `manifest.json` sit at `public_html/` **root** (not in a subfolder).
  3. Upload the local `vendor/` folder as well (it is gitignored and not in the zip), or run `composer install` via SSH.

Must-haves after upload:

- [] `public_html/index.php` exists (document root = project root so the service worker scope covers the whole site).
- [] `service-worker.js` and `manifest.json` are at the document root.
- [] `.htaccess`, `.user.ini`, and the `backend/*/.htaccess` files are present (they block `.env`, disable directory listing, and set the 25 MB upload limits).
- [] Create an empty `uploads/` folder and make it writable by the web server (e.g. `chmod 775 uploads` or via File Manager → Change Permissions).

## 5. Create the production `.env`

- [] Copy the template: `cp .env.example .env` (the project `.env.example` is committed; `.env` itself is gitignored).
- [] Fill in real values:
  - `APP_ENV=production`
  - `BASE_URL=https://printease.org/` (trailing slash required)
  - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` from step 3
  - `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` (same client used locally)
  - `MAIL_USER` / `MAIL_PASS` (Gmail app password)
  - `CLOUD_NAME` / `API_KEY` / `API_SECRET`
  - `TESSERACT_PATH=tesseract` (optional; OCR degrades gracefully)
  - Leave `REDIS_HOST` / `REDIS_PORT` unset — shared hosting has no Redis, and sessions fall back to file-based storage automatically.
- [] Confirm `.env` is **not** accessible via the browser (it is blocked by `.htaccess`; verify after going live).

## 6. HTTPS / SSL

- [] Enable the free SSL certificate for `printease.org` (cPanel → SSL/TLS Status → Run AutoSSL / Let's Encrypt).
- [] Force HTTPS: with `.htaccess` at the docroot, add a redirect (or use the host's "Force HTTPS" toggle).
- [] Confirm `https://printease.org/` loads and everything is served over HTTPS (PWA + Google OAuth require it).
- [] If the host is behind a reverse proxy/CDN, make sure it forwards `X-Forwarded-Proto: https` (the app already reads it for secure cookies/HSTS).

## 7. Google OAuth (production)

- [] Google Cloud Console → APIs & Services → Credentials → your OAuth 2.0 **Web application** client.
- [] Under **Authorized redirect URIs**, add:
  ```
  https://printease.org/backend/oauth/oauth_callback.php?provider=google
  ```
  (The local `http://localhost/printease/...` URI can stay for local dev.)
- [] OAuth consent screen → set status to **In production** (scopes `openid email profile` are non-sensitive; no full verification is required).
- [] Add any human testers as **Test users** while still in Testing, or publish to Production when ready.

## 8. Mail (Gmail SMTP)

- [] `MAIL_USER` / `MAIL_PASS` point to a Gmail account using an **app password** (2-Step Verification required for the Google account).
- [] Test from production: registration OTP, forgot-password OTP, and password-reset emails arrive (check spam).
- [] If the server is blocked from Gmail SMTP, switch to another SMTP provider by editing `backend/helper/mailer.php` — or contact host support.

## 9. Cron (optional)

- [] cPanel → Cron Jobs → run the pickup reminder checker on a schedule. It is safe to run daily:
  ```
  php /home/USER/public_html/backend/actions/pickup_reminder_checker.php
  ```
  Note: reminders are also triggered whenever a shop owner opens their dashboard/orders page, so the cron is optional.

## 10. Storage / housekeeping

- [] The plan includes **3 GB** storage. Deployed footprint is ~136 MB (code ~5 MB + `vendor/` ~131 MB), leaving ~2.8 GB for `uploads/`.
- [] Each customer PDF can be up to 25 MB — plan for growth; archive/delete old order files periodically.
- [] Do **not** upload `node_modules/` (no build step — `assets/css/tailwind.css` is prebuilt) or `.git/`.

## 11. Verification on production

Run the relevant phases from `docs/END_TO_END_MANUAL_TEST_PLAN.md` against `https://printease.org/`:

- [] **2.x** — app loads, no PHP errors, static/PWA assets load.
- [] **3.x / 4.x** — registration + OTP, login (incl. Google), role access.
- [] **8.x** — full customer order wizard incl. a **25 MB** PDF upload (validates `.user.ini` limits; if uploads fail, set limits via cPanel → MultiPHP INI Editor).
- [] **11.x** — security: `.env` blocked, directory listing off, uploaded files do not execute.
- [] **13.x** — offline/PWA: service worker registers/activates/controls, offline page load, offline drafts + auto-send (now over HTTPS).
- [] Google login end-to-end.
- [] Payment flow (GCash QR + proof upload) with a shop owner.
- [] No raw PHP warnings/errors in the browser or server logs.

## 12. Backup & rollback

- [] Before go-live, back up the local database (`backend/database/pe_db.sql` is gitignored — export via phpMyAdmin/MySQL Workbench and store it off the repo).
- [] Keep a copy of the previous release zip and `.env` for rollback.
- [] Test a restore at least once (fresh DB → import base schema → import data backup).

---

## Production notes

- **Service worker / PWA:** works only over HTTPS. With `BASE_URL=https://printease.org/` the SW and manifest are same-origin and scope to `/`.
- **Redis:** optional. If unreachable, `getRedisClient()` returns `null` and sessions/cache fall back to file-based (DB rate limits still work).
- **Tesseract OCR:** optional. `proc_open`/tesseract are typically disabled on shared hosting; payment-reference detection then skips OCR and the reference is entered manually.
- **Secrets:** `.env` is gitignored and blocked from web access. Do not print or commit real credentials.
