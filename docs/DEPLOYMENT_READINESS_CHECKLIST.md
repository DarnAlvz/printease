# PrintEase Deployment Readiness Checklist

Use this checklist before deploying PrintEase for a capstone demo or controlled real-user testing.

## Readiness Rating

Current readiness estimate:

- **Capstone demo / controlled testing:** 7.5/10
- **Wider public production:** 6.5/10 until full QA and hardening are complete

PrintEase is feature-ready when all stated objective features are present and the full customer, shop owner, and super admin workflow passes testing. Before real users test it, focus on configuration, security checks, and end-to-end QA rather than adding more features.

## Environment and Configuration

- [ ] Back up the database.
- [ ] Back up the current project files.
- [ ] Confirm the intended deployment version is committed or otherwise saved.
- [ ] Set `APP_ENV=production`.
- [ ] Set `BASE_URL` to the exact deployed URL.
- [ ] Confirm PHP error display is off in production.
- [ ] Confirm Apache/Nginx points to the correct project directory.
- [ ] Confirm `.htaccess` rules work on the deployment server.

Example environment values:

```env
APP_ENV=production
BASE_URL=https://your-domain.com/printease/
DB_HOST=localhost
DB_NAME=printease_db
DB_USER=printease_user
DB_PASS=strong_password_here
```

## Database Checklist

- [ ] Do not use the default `root` database account with a blank password in deployment.
- [ ] Create a dedicated database user for PrintEase.
- [ ] Use a strong database password.
- [ ] Confirm the app connects using the dedicated database user.
- [ ] Apply the database schema correctly:
  - On a fresh database, import `backend/database/0000_base_schema.sql` only (it is the consolidated schema and already includes all later migrations, e.g. the `orders.submit_token` column). Do not run individual migration files separately.
- [ ] Confirm these important database objects exist:
  - `shop_payment_channels`
  - `rate_limit_events`
  - activity log audit columns such as `target_type`, `target_id`, `old_value`, `new_value`, `ip_address`, and `user_agent`
  - payment OCR fields
  - payment/order concurrency and performance indexes

Recommended permissions during setup and migration:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
ON printease_db.*
TO 'printease_user'@'localhost';
```

Recommended permissions after migrations are complete:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE
ON printease_db.*
TO 'printease_user'@'localhost';
```

## Mail and Cloudinary

- [ ] Confirm `MAIL_USER` is set.
- [ ] Confirm `MAIL_PASS` is set.
- [ ] Use a Gmail app password if Gmail SMTP is used.
- [ ] Test forgot password / OTP sending.
- [ ] Check inbox and spam folder.
- [ ] Confirm Cloudinary cloud name, API key, and API secret are set.
- [ ] Test customer PDF order upload.
- [ ] Test shop owner can view or download the uploaded order file.
- [ ] Confirm failed order submission cleans up uploaded files when applicable.

## Upload Folders

Confirm these folders exist and are writable:

- [ ] `uploads/permits`
- [ ] `uploads/shop_logos`
- [ ] `uploads/gcash_qr`
- [ ] `uploads/payment_proofs`

Also confirm uploaded files can be opened through the expected app URLs.

## End-to-End User Flow

Run the complete workflow with three separate browser sessions or profiles:

- Super Admin
- Shop Owner
- Customer

Required flow:

- [ ] Customer registration works.
- [ ] Shop owner registration works.
- [ ] Super Admin can approve or activate users.
- [ ] Customer can complete profile and upload valid ID.
- [ ] Shop owner can complete shop profile and upload business permit.
- [ ] Shop owner can add GCash account name, GCash number, GCash QR code, optional GCash merchant link, and payment instructions.
- [ ] Super Admin can approve shop permit.
- [ ] Super Admin can approve GCash/payment settings.
- [ ] Shop owner can add print services and prices.
- [ ] Customer can browse verified shops.
- [ ] Customer can submit a PDF order.
- [ ] Order total is correct.
- [ ] Customer can submit payment proof.
- [ ] Shop owner can verify or reject payment proof.
- [ ] Shop owner can move order through the full status lifecycle.
- [ ] Customer receives and sees status updates.
- [ ] Reports, transactions, notifications, and activity logs reflect the workflow.

Use `docs/END_TO_END_MANUAL_TEST_PLAN.md` as the full testing script.

## GCash Payment Scope

PrintEase currently supports **GCash only**.

The payment implementation is **GCash QR / merchant-link based**, not a full payment gateway API integration.

Expected behavior:

- Shop owner provides GCash account details.
- Shop owner uploads a GCash QR code.
- Shop owner may add an optional GCash merchant/payment link.
- Super Admin reviews and approves the payment settings.
- Customer sees the GCash QR code, GCash number, and merchant link if available.
- Customer completes payment outside PrintEase.
- Customer uploads proof of payment and reference number.
- Shop owner manually verifies or rejects the payment.

Important clarification:

- A static GCash QR usually requires the customer to manually enter the exact order amount in the GCash app.
- The exact order amount is displayed on the PrintEase payment page.
- A merchant link opens an external payment page if the shop owner has an official GCash merchant/payment link.
- PrintEase does not automatically confirm GCash payments through an API.

Suggested defense wording:

> The system supports GCash payment through shop-provided GCash details, QR code, and an optional GCash merchant link. Customers complete payment outside the system and submit proof of payment with a reference number for verification.

## Security Checklist

- [ ] Confirm protected pages require login.
- [ ] Confirm wrong-role users receive access denied or are redirected.
- [ ] Confirm forms include CSRF tokens.
- [ ] Confirm sensitive form actions validate CSRF tokens.
- [ ] Confirm rate limiting works for:
  - login attempts
  - registration attempts
  - OTP requests
  - payment proof submission
  - OCR/payment reference detection
- [ ] Test changing URL or form IDs such as `order_id`, `payment_id`, `shop_id`, `user_id`, and `notification_id`.
- [ ] Confirm users cannot access records they do not own.
- [ ] Confirm customers cannot access another customer's order or payment page.
- [ ] Confirm shop owners cannot verify or update another shop's order/payment.
- [ ] Confirm Super Admin-only actions require the `super_admin` role.

## RLS and IDOR Notes

PrintEase uses MySQL, so PostgreSQL-style database Row Level Security policies are not used.

Recommended explanation:

> Database-level RLS is not enabled because the system uses MySQL. Access control is enforced at the application layer through role checks and ownership-based SQL conditions.

The system mitigates Insecure Direct Object Reference (IDOR) by checking IDs together with the logged-in user's role or ownership. For example, customer records should be queried using the current `customer_id`, and shop owner records should be queried using the current `owner_id` or owned `shop_id`.

Recommended explanation:

> The system mitigates IDOR by validating the logged-in user's role and ownership before accessing or modifying records. IDs from URLs or forms are not trusted alone; they are checked together with the current user ID, customer ID, owner ID, or role.

## Final Go / No-Go Criteria

Deploy for controlled real-user testing only when all of these are true:

- [ ] All database migrations are applied.
- [ ] Production environment values are correct.
- [ ] Database user is not default `root` with a blank password.
- [ ] Mail/OTP flow works.
- [ ] Cloudinary upload and file access work.
- [ ] Upload folders are writable.
- [ ] The full customer, shop owner, and super admin flow passes.
- [ ] GCash QR/payment proof flow passes.
- [ ] Role access, CSRF, rate limiting, and ownership checks pass.
- [ ] No raw PHP fatal errors appear during testing.
- [ ] Demo merchant links, if used, are clearly labeled as demo/sample only.

Final recommendation:

- **Capstone demo or selected real-user testing:** deploy after this checklist passes.
- **Public production rollout:** wait until the checklist passes repeatedly and any high-severity bugs from testing are fixed.
