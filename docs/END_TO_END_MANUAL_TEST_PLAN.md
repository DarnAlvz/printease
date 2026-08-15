# PrintEase End-to-End Manual Test Plan

A step-by-step walkthrough that tests the **complete PrintEase workflow from start to finish** before deployment, capstone defense, or user testing. Every phase must be tested in order — later phases depend on earlier ones.

---

## How to Use This Document

1. Read **Section 1 (Quick Reference)** and prepare all test accounts, files, and browser profiles.
2. Run each **Phase** in order. Do not skip ahead — approvals and setup from earlier phases are required by later phases.
3. For every step, mark the result:
   - `[]` → put a checkmark when the step **passes**.
   - If it fails, mark `FAIL`, write the step number, and log it using the **Bug Report Template** at the end.
4. When all phases pass, complete the **Go / No-Go** section at the end. Only then deploy.

Estimated total time: **2–3 hours** for one full pass.

---

## 1. Quick Reference

### 1.1 Test URL

| Item | Value |
|---|---|
| Local URL | `http://localhost/printease/` |
| App home | `http://localhost/printease/index.php` (first visit opens `frontend/splash.php`) |

### 1.2 Browser Profiles

Use **separate browser profiles** (or 3 different browsers / incognito windows) so all three users stay logged in at the same time. This is required for the live-update tests.

| Profile | Logged in as |
|---|---|
| Browser 1 | Customer |
| Browser 2 | Shop Owner |
| Browser 3 | Super Admin |

### 1.3 Test Accounts

Create these before starting. Passwords must be at least 8 characters.

| Account | Email | Password | Role |
|---|---|---|---|
| Customer | `customer@test.com` | `Customer123!` | customer |
| Shop Owner | `owner@test.com` | `Owner123!` | shop_owner |
| Super Admin | `admin@printease.test` | `Admin123!` | super_admin (created in DB — see 1.4) |

> All three emails must be different. Use real inboxes you can check (Gmail OTP goes to your email).

### 1.4 Create the Super Admin (No register page for this role)

Open **phpMyAdmin** → `PE_DB` → `users` table, or run in a SQL tab:

```sql
INSERT INTO users (full_name, email, password, role, account_status)
VALUES ('Super Admin', 'admin@printease.test', '<BCRYPT_HASH>', 'super_admin', 'verified');
```

Generate `<BCRYPT_HASH>` first by running this in a terminal inside the project folder:

```bash
php -r "echo password_hash('Admin123!', PASSWORD_DEFAULT);"
```

Copy the output and paste it into the SQL above. Or if you prefer, insert the row in phpMyAdmin and use "Functions" → `password_hash('Admin123!', PASSWORD_DEFAULT)` for the password column.

### 1.5 Test Files to Prepare

Create a folder (e.g. `C:\temp\printease_test\`) with these files before testing:

| File name | Content |
|---|---|
| `valid.pdf` | Real PDF (create from Word/Canva) |
| `fake.pdf` | A text file renamed to `.pdf` (content not a real PDF) |
| `valid.jpg` | Real photo/image |
| `fake.jpg` | A renamed script or `.txt` file renamed to `.jpg` |
| `oversized.pdf` | File larger than the upload limit (e.g. > 25 MB) |
| `proof.jpg` | Real GCash payment screenshot/receipt image |
| `logo.png` | Real shop logo image |
| `qr.png` | Real GCash QR code image |
| `permit.pdf` | Real business permit PDF/image |

### 1.6 Services and Availability (Default Rules)

| Service | Order Type |
|---|---|
| Document Printing | **Online request** (must stay checked/default) |
| Lamination | Online request |
| Photo Printing | Online request |
| Tarpaulin Printing | Online request |
| ID Printing | Online request |
| Invitation / Card Printing | Online request |
| Photocopy | **Shop visit only** |
| Binding | **Shop visit only** |
| Scanning | **Shop visit only** |

### 1.7 Database Migrations

The schema is consolidated into a single base export. On a **fresh** database, import **only** `0000_base_schema.sql` (schema only) — it already includes the rate-limit engine, concurrency guards, unified pricing (`option_size`), simplified availability, GCash payment channels, order history privacy delete, and the one-time `orders.submit_token` guard. No additional migration runs on top of it.

On an **existing** database, apply `2026_08_14_add_order_submit_token.sql` only if it has not been applied yet. Older databases were migrated incrementally; databases restored from the current `0000_base_schema.sql` already have `orders.submit_token` and the `uq_orders_submit_token` key.

> `backend/database/pe_db.sql` is a **local backup containing real data** and is not part of reproducibility — use `0000_base_schema.sql` on fresh installs.

---

## 2. Environment and Smoke Checks

**Phase goal:** confirm the app starts, connects to the database, and loads without errors.

- [✓] **2.1 Start Laragon.** Do: Start Laragon → start **Apache** and **MySQL**. Expected: both start with no errors.
- [✓] **2.2 Open the app.** Do: visit `http://localhost/printease/`. Expected: splash screen shows, then the home page loads. No PHP fatal errors.
- [✓] **2.3 Check `.env` values.** Do: open `.env`. Expected: `APP_ENV=local`, `BASE_URL=http://localhost/printease/`, correct `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, mail, and Cloudinary settings.
- [✓] **2.4 Check database connection.** Do: open the login page and register page. Expected: pages load without database errors.
- [✓] **2.5 Check for warnings.** Do: visit home, login, register, and the 3 dashboards. Expected: no raw PHP warnings, notices, or stack traces.
- [✓] **2.6 Check static assets.** Do: look at all main pages. Expected: CSS, JavaScript, images, icons, and PWA assets load (no broken images/links). Inspect browser console for 404s.
- [✓] **2.7 Check pricing schema.** Do: confirm `shop_service_pricing.option_size` exists (phpMyAdmin). Expected: column exists; pricing pages show no "unknown column" errors.
- [✓] **2.8 Check payment channel schema.** Do: confirm `shop_payment_channels` exists with `approval_status`, `is_active`, `approved_by`, `approved_at`, `rejected_reason`. Expected: columns present; no references to the old `shop_payment_settings` table.

---

## 3. Authentication and Role Access

**Phase goal:** verify register, OTP, login, remember me, forgot password, logout, role guarding, and auth rate limits.

- [✓] **3.1 Public home page.** Do: visit `http://localhost/printease/` while logged out. Expected: home/splash page loads; navigation links (Home, Features, Contact, Login, Register) work.
- [✓] **3.2 Register page renders.** Do: open `frontend/pages/register.php`. Expected: customer and shop owner options render on desktop and mobile.
- [✓] **3.3 Empty registration blocked.** Do: submit the register form empty. Expected: required-field validation blocks submission.
- [✓] **3.4 Invalid email rejected.** Do: enter `notanemail`. Expected: rejected with an error.
- [✓] **3.5 Weak password rejected.** Do: enter a password shorter than 8 characters. Expected: rejected with a clear error.
- [✓] **3.6 Mismatched passwords rejected.** Do: enter different confirm password. Expected: rejected with "Passwords do not match."
- [✓] **3.7 Duplicate email rejected.** Do: register twice with the same email. Expected: clean "already registered" error.
- [✓] **3.8 OTP invalid.** Do: enter a wrong OTP. Expected: rejected with an error.
- [✓] **3.9 OTP valid.** Do: check email, enter the correct OTP. Expected: account is created and you can log in. *(If mail is not configured, note it and create the account via 1.4 method.)*
- [✓] **3.10 OTP per-IP lockout.** Do: submit wrong OTPs repeatedly (more than 10) from the same IP. Expected: blocked with a "too many attempts" message, no crash; the valid OTP no longer triggers unlimited retries.
- [✓] **3.11 Login validation.** Do: try empty, unknown email, and wrong password at `frontend/pages/login.php`. Expected: generic error message — no details leaked about whether the email exists.
- [✓] **3.12 Login rate limit.** Do: submit wrong passwords 5+ times. Expected: login attempts are throttled with a clear message.
- [✓] **3.13 Remember me.** Do: log in with "Remember me" checked. Expected: stays logged in after browser restart; clears after logout.
- [✓] **3.14 Forgot password.** Do: use `frontend/pages/forgot_password.php`. Expected: OTP/reset flow works, or fails cleanly if mail is not configured locally.
- [✓] **3.15 Reset password rate limit.** Do: submit the reset-password form 5+ times. Expected: throttled with a clear message; existing sessions/remember tokens for that account are revoked after a successful reset.
- [✓] **3.16 Password change rate limit.** Do: submit the change-password form (any role) 5+ times. Expected: throttled with a clear message.
- [✓] **3.17 Logout.** Do: log out. Expected: session ends; protected pages require login again.
- [✓] **3.18 Wrong-role access.** Do: after login as customer, open owner and admin dashboard URLs directly. Expected: access denied or redirect — never the other role's page.

---

## 4. Super Admin Setup and Approvals

**Phase goal:** admin approves the test accounts, shop permit, and payment settings so the rest of the flow works.

Log in as **Super Admin** in Browser 3 (`frontend/pages/login.php`).

- [✓] **4.1 Admin dashboard.** Do: log in. Expected: redirects to `frontend/user/superadmin/dashboard.php` with platform metrics.
- [✓] **4.2 User management.** Do: open `frontend/user/superadmin/manage_users.php`. Expected: the registered customer and owner appear with status `incomplete` or `pending`.
- [✓] **4.3 Approve customer.** Do: Activate the customer account. Expected: status becomes `verified`; customer can now use customer features.
- [✓] **4.4 Approve owner.** Do: Activate the owner account. Expected: status becomes `verified`; owner can now use owner features.
- [✓] **4.5 Manage print shops.** Do: open `frontend/user/superadmin/manage_print_shops.php`. Expected: test shop appears with profile, permit, status, and owner details.
- [✓] **4.6 Approve permit.** Do: approve the shop business permit. Expected: shop becomes eligible/visible (status updates).
- [✓] **4.7 Approve payment settings (channel).** Do: approve a single pending GCash channel (QR or merchant link). Expected: `shop_payment_channels` row becomes `approved`, `is_active=1`, and the owner is notified.
- [✓] **4.8 Approve payment settings (shop).** Do: approve all pending payment methods for a shop at once. Expected: every pending channel for that shop is approved with one action.
- [✓] **4.9 Reject flow.** Do: reject a permit and a payment setting. Expected: rejection reason is saved (`rejected_reason`) and shown where applicable; the owner is notified.
- [✓] **4.10 Disable/reactivate user.** Do: deactivate a user, then reactivate. Expected: access follows the updated status.
- [✓] **4.11 Disable/reactivate shop.** Do: disable a shop, then enable it. Expected: shop availability and Explore visibility follow the status.
- [✓] **4.12 Activity logs.** Do: open `frontend/user/superadmin/activity_logs.php`. Expected: major auth, approval, service, order, and payment actions are logged with useful context (user, action, module, target, old/new values, IP, user agent).
- [✓] **4.13 Reports.** Do: open `frontend/user/superadmin/reports.php`. Expected: loads and reflects completed jobs/payments.
- [✓] **4.14 Filters/search.** Do: filter users, shops, and logs; test empty states. Expected: no crashes.
- [✓] **4.15 Admin action rate limit.** Do: rapidly repeat a status update action (user/permit/payment). Expected: throttled cleanly, no crash.
  
  
> Leave the customer and owner **approved** when done — later phases need them.

---

## 5. Shop Owner Profile and Services

**Phase goal:** owner completes the shop profile and selects offered services.

Log in as **Shop Owner** in Browser 2.

- [✓] **5.1 Profile guard.** Do: log in before completing the profile. Expected: redirected to `frontend/user/shop_owner/shop_profile.php`; restricted owner pages are blocked.
- [✓] **5.2 Missing profile fields.** Do: try to save the profile blank. Expected: required fields are rejected.
- [✓] **5.3 Valid shop logo.** Do: upload `logo.png`. Expected: upload succeeds.
- [✓] **5.4 Invalid shop logo.** Do: upload `fake.jpg` and `oversized.pdf` as logo. Expected: both rejected.
- [✓] **5.5 Valid GCash QR.** Do: upload `qr.png`. Expected: upload succeeds.
- [✓] **5.6 Invalid GCash QR.** Do: upload fake/oversized file. Expected: rejected.
- [✓] **5.7 Valid business permit.** Do: upload `permit.pdf`. Expected: upload succeeds.
- [✓] **5.8 Invalid business permit.** Do: upload fake PDF (not a real `%PDF`), a renamed image, and an oversized file. Expected: all rejected (finfo + magic-header + size checks).
- [✓] **5.9 Complete shop profile.** Do: fill shop name, address, landmark, location, contact, GCash details, and save with valid uploads. Expected: saves successfully.
- [✓] **5.10 Merchant link validation.** Do: enter a merchant link that does not start with `http://` or `https://`. Expected: rejected with a clear error.
- [✓] **5.11 GCash without QR.** Do: save payment settings with **merchant link only** (no QR uploaded). Expected: saves successfully; customer payment page renders the link without a broken QR image (null-QR path).
- [✓] **5.12 GCash without link.** Do: save with **QR only** (no merchant link). Expected: saves successfully; payment page renders the QR cleanly.
- [✓] **5.13 Payment instructions required.** Do: try to save with empty payment instructions. Expected: rejected; instructions are required.
- [✓] **5.14 Upload filename entropy.** Do: after a successful upload, check the stored file name. Expected: contains a long random hex suffix (128-bit, e.g. `bin2hex(random_bytes(16))`), not a short guessable name.
- [✓] **5.15 Services layout.** Do: open `frontend/user/shop_owner/services.php`. Expected: services are a simple **checkbox list** (no Online/Face-to-Face/Both control).
- [✓] **5.16 Document Printing default.** Do: check the services list. Expected: Document Printing is checked/default and cannot be removed (designed as required).
- [✓] **5.17 Select online services.** Do: check Lamination, Photo Printing, Tarpaulin Printing, ID Printing, Invitation/Card Printing. Expected: saves and persists after refresh.
- [✓] **5.18 Select shop-visit services.** Do: check Photocopy, Binding, Scanning. Expected: saves and persists after refresh.
- [✓] **5.19 Availability defaults.** Do: after saving, check what shows to customers (later in Phase 7). Expected: online-capable services are online-orderable; Photocopy/Binding/Scanning are shop-visit-required.

---

## 6. Shop Owner Pricing and Print Jobs

**Phase goal:** owner configures pricing, then receives and handles a customer print job.

- [✓] **6.1 Open pricing page.** Do: open `frontend/user/shop_owner/services.php` pricing section. Expected: loads without PHP or layout errors.
- [✓] **6.2 Online-only pricing.** Do: confirm pricing can only be configured for services that accept online requests. Expected: shop-visit services do not offer online pricing.
- [✓] **6.3 Add Document Printing price.** Do: add a price with size, paper type, print type, charged-by value, and price. Expected: saves correctly.
- [✓] **6.4 Add another online price.** Do: add pricing for a second online service (e.g. Photo Printing) with size/material/type. Expected: saves.
- [✓] **6.5 Invalid price values.** Do: try blank, zero, negative, and non-number prices. Expected: all rejected.
- [✓] **6.6 Edit price.** Do: change an existing price. Expected: saves and persists after refresh.
- [✓] **6.7 Toggle price status.** Do: disable a price, then enable it. Expected: customer ordering follows availability.
- [✓] **6.8 Delete test price.** Do: delete the temporary price you added. Expected: removed or disabled safely.
- [✓] **6.9 Pricing action rate limit.** Do: rapidly repeat add/edit/toggle/delete pricing actions. Expected: throttled cleanly, no crash.
- [✓] **6.10 Print job intake.** Do: keep this tab open for Phase 8. Expected: a customer request appears **only** in this owner's `orders.php` (Phase 8 creates it).
- [✓] **6.11 Job details.** Do: open the job when it arrives. Expected: customer info, service settings, pickup schedule, instructions, total, and uploaded file are visible.
- [✓] **6.12 File download.** Do: download/view the customer file; try to access another shop's file URL. Expected: own file opens; other shop's file is blocked.
- [✓] **6.13 Payment gate.** Do: check an unpaid order. Expected: handled per current workflow rules (payment required before completion).
- [✓] **6.14 Verify payment proof.** Do: verify a valid proof. Expected: payment state changes; customer is notified.
- [✓] **6.15 Reject payment proof.** Do: reject a proof with a reason. Expected: reason is saved; customer sees next steps.
- [✓] **6.16 Status lifecycle.** Do: move a job through Pending → Processing → Ready for Pickup → Completed. Expected: each transition works.
- [✓] **6.17 Double-tab update.** Do: open `orders.php` in two tabs and update the same job from both. Expected: conflicting updates do not silently corrupt status.
- [✓] **6.18 Status action rate limit.** Do: rapidly repeat status updates on a job. Expected: throttled cleanly, no crash.

---

## 7. Customer Explore Shops

**Phase goal:** customer finds and reviews shops before ordering.

Log in as **Customer** in Browser 1.

- [✓] **7.1 Open Explore.** Do: open `frontend/user/customer/explore.php` (or `shops.php`). Expected: verified/available shops display correctly.
- [✓] **7.2 Search shops.** Do: search by shop name, address, or location. Expected: results filter; empty state is clean.
- [✓] **7.3 Nearby shops.** Do: allow location. Expected: distances show. Then deny location. Expected: degrades cleanly with no errors.
- [✓] **7.4 Favorites.** Do: favorite a shop, then unfavorite. Expected: state updates and persists after refresh.
- [✓] **7.5 Favorites rate limit.** Do: rapidly toggle a favorite 30+ times in a minute. Expected: throttled cleanly with a clear message.
- [✓] **7.6 Service summary.** Do: look at a shop card. Expected: compact text like `9 Services Offered`.
- [✓] **7.7 Online services compact.** Do: look at the Available Online section. Expected: compact chips; only the first three online services shown by default.
- [✓] **7.8 More online services.** Do: click `+ N more`. Expected: only online chips expand; `Show less` collapses them.
- [✓] **7.9 Shop-visit compact.** Do: look at Photocopy, Binding, Scanning. Expected: shown as shop-visit chips without expanded notes by default.
- [✓] **7.10 View requirements.** Do: click `View requirements`. Expected: only shop-visit requirement notes expand; clicking again hides them.
- [✓] **7.11 All services accessible.** Do: confirm no offered service disappears; extras are reachable through the compact controls.
- [✓] **7.12 Live refresh.** Do: wait for polling/refresh. Expected: service badges stay compact after live updates.

---

## 8. Customer Online Request Flow

**Phase goal:** customer places a valid online print order.

From **Browser 1** (Customer), on a shop card click **Request Print** → opens `frontend/user/customer/place_order.php?shop_id=X`.

- [✓] **8.1 Open wizard.** Do: click Request Print. Expected: order wizard opens for the selected shop.
- [✓] **8.2 Service selector.** Do: check the service list. Expected: all offered services appear, including shop-visit-only services.
- [✓] **8.3 Online service proceeds.** Do: select an online-available service. Expected: continues to upload/settings/pricing steps.
- [✓] **8.4 Shop-visit blocked.** Do: select Photocopy/Binding/Scanning. Expected: shop-visit message shows; cannot proceed to online checkout.
- [✓] **8.5 Document Printing default.** Do: check Document Printing. Expected: remains online-orderable with file upload and print settings behavior.
- [✓] **8.6 Missing required file.** Do: try to continue without the required file. Expected: blocked with a clear error.
- [✓] **8.7 Invalid PDF.** Do: upload `fake.pdf`. Expected: rejected.
- [✓] **8.8 Valid PDF.** Do: upload `valid.pdf`. Expected: upload succeeds; page count detected when supported.
- [✓] **8.9 Unified settings.** Do: select Photo Printing / Tarpaulin / ID / Invitation / Card. Expected: separate Size, Material/Paper Type, Print Type, Quantity, and Price fields show. Lamination uses the simpler size + quantity + price form (Material and Print Type are `Standard`).
- [✓] **8.10 Cascading options.** Do: change Size → Material/Paper Type updates; change Material → Print Type updates; price updates for the combination.
- [✓] **8.11 Legacy pricing fallback.** Do: for a service with incomplete option data. Expected: safe `Standard` fallbacks are used; correct pricing row still submits.
- [✓] **8.12 Quantity validation.** Do: try blank, zero, negative, and non-number quantities. Expected: all rejected.
- [✓] **8.13 Quantity clamp.** Do: try quantities below 1 or above 1000 via a modified form. Expected: clamped to the 1–1000 range server-side; no out-of-range total.
- [✓] **8.14 Pickup schedule.** Do: pick a past date/time. Expected: rejected. Pick a valid future schedule. Expected: accepted.
- [✓] **8.15 Price calculation.** Do: change settings. Expected: total updates based on pricing, page count (where applicable), and quantity/copies.
- [✓] **8.16 Review step.** Do: review the order. Expected: service, settings, pickup schedule, notes, and total display correctly.
- [✓] **8.17 Submit request.** Do: submit. Expected: order created with a unique request code.
- [✓] **8.18 Duplicate submit.** Do: double-click/rapid resubmit. Expected: no duplicate or inconsistent orders created (15/hour rate limit + one-time `order_submit_token` unique key + concurrency-safe).

---

## 9. Customer Payment and Request Tracking

**Phase goal:** customer pays via GCash proof and tracks the request.

- [✓] **9.1 Active list.** Do: open `frontend/user/customer/orders.php`. Expected: the new request appears in the active/pending list.
- [✓] **9.2 Request details.** Do: expand/collapse the request. Expected: works without layout issues.
- [✓] **9.3 Payment page.** Do: open `frontend/user/customer/payment.php?order_id=X`. Expected: payment instructions, GCash details, QR, merchant link (when available), and total display correctly.
- [✓] **9.4 QR-only setup.** Do: (owner has QR but no link). Expected: QR displays cleanly.
- [✓] **9.5 Link-only setup.** Do: (owner has link but no QR). Expected: merchant link displays cleanly with no broken QR image.
- [✓] **9.6 Invalid payment proof.** Do: upload `fake.jpg`. Expected: rejected.
- [✓] **9.7 Valid payment proof.** Do: upload `proof.jpg` and enter a reference number. Expected: submits successfully.
- [✓] **9.8 OCR detection.** Do: submit a proof with a visible reference number. Expected: OCR detects it when possible, or safely skips without blocking manual entry.
- [✓] **9.9 Duplicate proof.** Do: submit a second active proof for the same order. Expected: app prevents inconsistent duplicate active proofs.
- [✓] **9.10 Payment proof rate limit.** Do: rapidly submit payment proofs. Expected: throttled cleanly (10/hour), with a warning if OCR is skipped but the proof still saved.
- [✓] **9.11 Status tracking.** Do: keep this page open while the owner (Browser 2) moves the request. Expected: status updates appear.
- [✓] **9.12 Completed history.** Do: after the owner completes the job. Expected: request appears in completed/history view.
- [✓] **9.13 Remove completed history.** Do: hide/remove the completed request from history. Expected: disappears for the customer only; owner/admin records remain.
- [✓] **9.14 History delete rate limit.** Do: rapidly repeat the remove-from-history action. Expected: throttled cleanly, no crash.

---

## 10. Notifications and Live Updates

**Phase goal:** confirm notifications and real-time updates work across all roles.

- [✓] **10.1 Customer notification.** Do: owner/admin performs an action affecting the customer. Expected: customer notification appears in `frontend/user/customer/notifications.php` with badge count.
- [✓] **10.2 Owner notification.** Do: customer orders or submits payment proof. Expected: owner notification appears with badge count (and sound chime, if enabled).
- [✓] **10.3 Admin notification.** Do: an approval/status action occurs. Expected: visible where supported.
- [✓] **10.4 Mark notification read.** Do: open and mark a notification as read. Expected: read state and badge counts update.
- [✓] **10.5 Notification read rate limit.** Do: rapidly repeat mark-as-read calls. Expected: throttled cleanly; the page still loads without errors.
- [✓] **10.6 Notification target link.** Do: click a notification. Expected: opens the correct role page or target.
- [✓] **10.7 Customer live request updates.** Do: keep customer `orders.php` open while owner changes status. Expected: My Requests updates safely (no layout break).
- [✓] **10.8 Owner live job updates.** Do: keep owner `orders.php` open while customer submits proof. Expected: Print Jobs updates safely.
- [✓] **10.9 Explore live updates.** Do: keep Explore open during a refresh cycle. Expected: compact service groups stay compact after polling.
- [✓] **10.10 Owner service live updates.** Do: keep services page open during a refresh. Expected: checkbox grid refreshes without restoring removed fulfillment controls.
- [✓] **10.11 Hidden tab behavior.** Do: switch a tab to the background. Expected: polling slows or pauses appropriately (visible in the network tab).

---

## 11. Upload, Security, and IDOR Tests

**Phase goal:** confirm the app is hardened. Run these last — some intentionally break things.

- [✓] **11.1 Direct `.env` access.** Do: open `http://localhost/printease/.env`. Expected: not viewable (blocked).
- [✓] **11.2 Direct `.user.ini` access.** Do: open `http://localhost/printease/.user.ini`. Expected: not viewable.
- [✓] **11.3 Direct backend access.** Do: open `http://localhost/printease/backend/` and `backend/actions/`. Expected: directory listing/browsing blocked.
- [✓] **11.4 Uploaded PHP execution.** Do: attempt to upload or place a `.php` file under `uploads/`. Expected: does not execute (served as plain text or blocked).
- [✓] **11.5 Logged-out protected page.** Do: log out and open customer/owner/admin URLs. Expected: redirected to login.
- [✓] **11.6 Missing CSRF token.** Do: submit a protected POST action without the CSRF token (e.g. via a modified form). Expected: rejected.
- [✓] **11.7 Invalid CSRF token.** Do: modify the CSRF token value. Expected: rejected.
- [✓] **11.8 AJAX logged-out response.** Do: call a protected AJAX endpoint while logged out. Expected: clean `401` or login-required response.
- [✓] **11.9 AJAX wrong-role response.** Do: call a wrong-role AJAX endpoint. Expected: clean `403` or access-denied response.
- [✓] **11.10 Customer ID tampering.** Do: change `order_id` / `payment_id` / customer IDs in URLs and forms. Expected: cannot view or modify another customer's data.
- [✓] **11.11 Owner ID tampering.** Do: change shop/order/payment IDs. Expected: cannot view or modify another shop's data.
- [✓] **11.12 Admin-only actions.** Do: try an admin action as a non-admin. Expected: blocked.
- [✓] **11.13 Upload MIME checks.** Do: upload `fake.pdf` and `fake.jpg` where a real file is required. Expected: rejected (finfo + magic-header + `getimagesize` checks).
- [✓] **11.14 Oversized uploads.** Do: upload `oversized.pdf`. Expected: rejected with a clean message.
- [✓] **11.15 Login/register rate limits.** Do: rapidly repeat login and registration attempts. Expected: throttled with a clear message, no crash.
- [✓] **11.16 OTP send/verify rate limits.** Do: rapidly request OTPs and submit OTP attempts. Expected: throttled (send: per-email/minute, per-IP/hour; verify: 10 per IP per 15 min).
- [✓] **11.17 Payment/order action rate limits.** Do: rapidly repeat place-order, verify-payment, order-status, and payment-proof actions. Expected: throttled cleanly (15/hour, 60/hour, 120/hour, 10/hour respectively).
- [✓] **11.18 Profile/service rate limits.** Do: rapidly repeat shop/customer profile saves and pricing/service changes. Expected: throttled cleanly (10/hour profiles, 60/hour pricing).
- [✓] **11.19 Password change/reset rate limits.** Do: rapidly repeat password change and reset submissions. Expected: throttled (5/hour) with a clear message.
- [✓] **11.20 Protocol-relative redirect guard.** Do: submit a form with a `return_to`/`return_path` value like `//evil.com/...`. Expected: ignored/redirected safely to an internal page, never sent off-site.
- [✓] **11.21 Remember-token revocation.** Do: log in with "Remember me", then reset or change the password from another browser. Expected: the remembered session is invalidated.
- [✓] **11.22 Upload filename entropy.** Do: upload files and inspect the stored names in `uploads/`. Expected: long random hex suffixes — names are not enumerable/predictable.
- [] **11.23 Offline draft token endpoint (logged out).** Do: call `backend/actions/pending_order_token.php` while logged out. Expected: clean login-required response — no token, no data leaked.
- [] **11.24 Offline draft token endpoint (role/header).** Do: call it as a non-customer role, and as a customer without the `X-Requested-With: XMLHttpRequest` header. Expected: 403/access-denied — a token is never issued.

---

## 12. Responsive UI Checks

**Phase goal:** confirm the app is usable on all screen sizes. Use DevTools device toolbar.

- [✓] **12.1 Desktop layout.** Do: check dashboards, tables, cards, and forms at full width. Expected: readable, no overlapping content.
- [✓] **12.2 Tablet layout.** Do: set width ~768px. Expected: main role pages remain usable.
- [✓] **12.3 Mobile layout.** Do: set width ~375px. Expected: navigation, shop cards, modals, forms, and buttons fit without horizontal overflow.
- [✓] **12.4 Explore mobile compact.** Do: open Explore on mobile. Expected: service chips wrap cleanly; collapsed requirements keep cards short.
- [✓] **12.5 Order wizard mobile.** Do: open the order wizard on mobile. Expected: each step, setting field, upload input, and summary fit the viewport.
- [✓] **12.6 Owner service list mobile.** Do: open services on mobile. Expected: checkboxes remain compact and tappable.
- [✓] **12.7 Long text.** Do: use long names, addresses, notes, instructions, and service labels. Expected: wrap or truncate cleanly.
- [✓] **12.8 Modals.** Do: open modals on mobile. Expected: fit viewport, scroll internally if needed, close correctly.
- [✓] **12.9 Browser refresh.** Do: refresh important pages mid-flow. Expected: correct state preserved.
- [✓] **12.10 Back button.** Do: use browser back after login/logout/form submission. Expected: no protected stale content exposed.
- [] **12.11 Bottom nav narrow label.** Do: resize a customer page to ~360px (or zoom in). Expected: the "My Requests" nav item never wraps to two lines — it shows "Request" at ≤380px and "My Requests" at larger widths.

---

## 13. Offline Mode & PWA

**Phase goal:** confirm the customer order wizard keeps working without internet and queued requests send automatically when back online.

> **Important:** `localhost` is a secure context, so the service worker now registers and works on `http://localhost/printease/` (no HTTPS needed). At deploy time the same service worker requires HTTPS — set `BASE_URL` to the real domain. Use DevTools → Network → Offline to simulate losing the connection.

Log in as **Customer** in Browser 1 (`frontend/user/customer/dashboard.php`).

- [] **13.1 Service worker installs.** Do: load any customer page on `http://localhost/printease`, open DevTools → Application → Service Workers. Expected: `service-worker.js` is shown as **Registered / Activated / Running** and says "controlling this page". (The `printease-shell-v5` / `printease-runtime-v5` entries are *cache* names — they appear under **Application → Cache Storage**, not in the Service Workers panel. "From other origins → see all registrations" only lists other origins and is always present; it is not an error.)

  **If nothing is registered:** hard-refresh (Ctrl+Shift+R) or clear site data (DevTools → Application → Clear storage → Clear site data) and reload.
- [✓] **13.2 Offline page load.** Do: DevTools → Network → Offline, then reload `http://localhost/printease/frontend/user/customer/place_order.php?shop_id=X`. Expected: the wizard loads from the service worker cache (no error/offline page).
- [✓] **13.3 Offline banner.** Do: go offline on a customer page. Expected: "You are offline..." banner appears; going back online hides it.
- [✓] **13.4 Offline valid PDF.** Do: offline, upload `valid.pdf` in the wizard. Expected: page count is detected (worker served from the shell cache) or the file is accepted with "Page count will be verified when your request is sent." — never rejected.
- [✓] **13.5 Offline fake PDF.** Do: offline, upload `fake.pdf`. Expected: rejected with "This does not look like a valid PDF file."
- [✓] **13.6 Offline submit saves a draft.** Do: offline, complete the wizard and submit. Expected: success toast "Saved as draft", the form resets, no server error.
- [✓] **13.7 Pending banner.** Do: with at least one saved draft, reconnect. Expected: a pending banner appears with the draft count and "Send now" / "View drafts" actions.
- [✓] **13.8 Drafts panel.** Do: click "View drafts". Expected: each draft shows service, pickup time, and total; removing a draft deletes it and updates the banner count.
- [✓] **13.9 Manual send while offline.** Do: while still offline, click "Send now". Expected: graceful failure message; the draft stays in the queue.
- [✓] **13.10 Auto-send on reconnect.** Do: reconnect with a saved draft. Expected: the draft sends automatically, a success toast shows, the order appears in My Requests, and the draft is removed.
- [✓] **13.11 Stale pickup reschedule.** Do: create a draft, then set its stored pickup time to the past (DevTools → Application → IndexedDB → `printease-offline-drafts` → `pending_orders`) and reload offline. Expected: the draft is flagged for rescheduling with a pickup-time input; after choosing a future time, reconnect sends it.
- [✓] **13.12 Multiple queued drafts.** Do: save 2–3 drafts offline and reconnect. Expected: drafts send one at a time in order and all arrive in My Requests (server cap is 15 orders/hour; extra queued drafts stay pending with a clear message).
- [✓] **13.13 Session expiry offline.** Do: while offline, let the session expire (or log out in another tab), then reconnect. Expected: login is required, but the saved drafts are still there after logging back in.
- [✓] **13.14 Corrupt PDF at send.** Do: offline, add a draft whose stored file is not a real PDF (e.g. via DevTools → Application → IndexedDB), then reconnect. Expected: the server rejects it during auto-send; the draft is kept with an error shown — not silently lost.

---

## 14. Final Defense Demo Path (Quick 10-Minute Script)

If you need a fast, scripted demo after the full test, run these in order:

- [] **14.1** Show public home, login, and register pages (Browser logged out).
- [] **14.2** Register and verify a customer account (OTP).
- [] **14.3** Register and verify a shop owner account (OTP).
- [] **14.4** Super Admin approves the customer, owner, shop permit, and payment settings (channels).
- [] **14.5** Owner completes shop profile with logo, permit, GCash QR, and optional merchant link.
- [] **14.6** Owner selects all offered services including Photocopy, Binding, Scanning.
- [] **14.7** Owner adds online pricing for Document Printing and one other online service.
- [] **14.8** Customer opens Explore and reviews the compact service display.
- [] **14.9** Customer attempts a shop-visit service → online checkout is blocked with a visit-required message.
- [] **14.10** Customer submits a valid online print request (correct settings and total).
- [] **14.11** Owner receives and opens the print job, reviews request and file.
- [] **14.12** Customer submits GCash payment proof (becomes pending verification).
- [] **14.13** Owner verifies payment and moves the job to Completed → customer sees status changes.
- [] **14.14** Customer removes the completed request from history (owner/admin records remain).
- [] **14.15** Admin reviews reports and activity logs showing the demo flow.
- [] **14.16** Quick security checks: `.env` blocked, upload execution blocked, wrong-role access blocked, CSRF works, and one rate limit trips cleanly.

---

## 15. Go / No-Go for Deployment

Mark every item. If **any** fails, deployment is **No-Go** until fixed and retested.

### Go / No-Go Result

- [] **No fatal errors.** No PHP fatal errors, raw warnings, or broken pages in critical flows.
- [] **Authentication works.** Registration, OTP, login, logout, remember me, role redirects, and auth rate limits behave correctly.
- [] **Customer lifecycle works.** Explore, request online services, pay, track, complete, and manage history.
- [] **Owner lifecycle works.** Profile, offered services, pricing, jobs, payments, and completion.
- [] **Admin lifecycle works.** Approve, manage, monitor, and review reports/logs.
- [] **Service availability is clear.** Customers see all offered services and can distinguish online-request from shop-visit services.
- [] **Online checkout protected.** Shop-visit-only services cannot be submitted as online orders.
- [] **Upload hardening works.** Valid files accepted; unsafe files rejected or non-executable; stored names are random.
- [] **Security checks pass.** CSRF, role access, IDOR, direct file access, rate limiting, and redirect guards behave correctly.
- [] **Responsive UI passes.** Desktop, tablet, and mobile usable with no major overlap/overflow.

### Pre-Deployment Checklist (from `DEPLOYMENT_READINESS_CHECKLIST.md`)

- [] Backed up the database and project files.
- [] `APP_ENV=production` set (hides error display).
- [] `BASE_URL` set to the exact deployed URL.
- [] PHP error display is off in production.
- [] `.htaccess` rules work on the deployment server.
- [] Dedicated DB user created (not `root` with blank password).
- [] On a fresh database: import `0000_base_schema.sql` only (it already contains all prior migrations); apply `2026_08_14_add_order_submit_token.sql` only on older databases that lack `orders.submit_token`.
- [] `MAIL_USER` / `MAIL_PASS` set; forgot-password/OTP tested (check spam too).
- [] Cloudinary cloud name, API key, secret set; customer PDF upload + owner download tested.
- [] Upload folders writable: `uploads/permits`, `uploads/shop_logos`, `uploads/gcash_qr`, `uploads/payment_proofs`.
- [] GCash QR / payment proof flow passes end to end.

**Final decision:** [] **GO — deploy**   [] **NO-GO — fix issues and retest**

---

## Bug Report Template

```text
Bug #:
Date:
Tester:
Step / Phase:
Page/Feature:
Role:
Browser/Device:
Steps to Reproduce:
Expected Result:
Actual Result:
Severity: Critical / High / Medium / Low
Status: Open / Fixed / Retest / Closed
Notes/Screenshot:
```

## Test Summary

| Phase | Passed | Failed | Notes |
|---|---|---|---|
| 2. Environment & Smoke |  |  |  |
| 3. Authentication & Roles |  |  |  |
| 4. Super Admin |  |  |  |
| 5. Owner Profile & Services |  |  |  |
| 6. Owner Pricing & Jobs |  |  |  |
| 7. Customer Explore |  |  |  |
| 8. Customer Order Flow |  |  |  |
| 9. Payment & Tracking |  |  |  |
| 10. Notifications & Live Updates |  |  |  |
| 11. Security & IDOR |  |  |  |
| 12. Responsive UI |  |  |  |
| 13. Offline Mode & PWA |  |  |  |
| 14. Demo Path |  |  |  |
| **Total** |  |  |  |
