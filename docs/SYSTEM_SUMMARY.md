# PrintEase - E-Printing Management System

## System Overview

**PrintEase** is a mobile and web-based e-printing management system that connects customers with verified print shops in **Calbayog City, Samar, Philippines**. It is a capstone/thesis project providing a complete marketplace workflow for printing services.

### Target Users
- **Customers** — Browse print shops, submit print requests with file uploads, pay via GCash, track orders
- **Shop Owners** — Manage shop profile, services, pricing, print jobs, payments, and reports
- **Super Administrator** — Manage users, approve shops/permits, review activity logs, monitor platform

---

## Tech Stack

### Frontend
| Technology | Details |
|---|---|
| HTML5 | Semantic HTML with ARIA attributes |
| CSS3 | Custom CSS per role + Tailwind CSS v4.3 (PostCSS compiled) |
| Vanilla JavaScript | No frontend framework; live polling, modals, form validation, Web Audio alerts |
| PWA | manifest.json + service-worker.js (cache-first, offline fallback) |
| OpenStreetMap/Nominatim | Geocoding for shop locations and distance |

### Backend
| Technology | Details |
|---|---|
| PHP (Procedural) | No MVC framework; direct file-based routing |
| MySQL | Database: printease_db, utf8mb4, prepared statements (mysqli) |
| Redis (optional) | Session storage and caching via predis/predis; graceful fallback |
| Apache | .htaccess for URL rewriting and security headers |

### Third-Party Libraries
| Package | Purpose |
|---|---|
| google/apiclient v2.19 | Google OAuth2 social sign-in |
| phpmailer/phpmailer v7.1 | SMTP email (OTP codes, registration verification) |
| thiagoalessio/tesseract_ocr v2.13 | OCR on GCash payment proofs (auto-detect reference numbers) |
| cloudinary/cloudinary_php v3.1 | Cloudinary file storage for customer PDF uploads |
| predis/predis v2.3 | Redis client for sessions and caching |

---

## Architecture

- **Flat file-based PHP** — No MVC framework, routing is implicit via file paths
- **Self-contained pages** — Each PHP page includes dependencies, checks auth, queries DB, renders HTML
- **Backend actions** — Standalone PHP scripts in `backend/actions/` process form submissions
- **Live updates** — Centralized JS engine (`live-updates.js`) polls AJAX endpoints at 10-12s intervals
- **Sessions** — Redis-backed with file-based fallback
- **File uploads** — Local `uploads/` directory; customer PDFs stored on Cloudinary

---

## Features by Role

### Customer Features
- Dashboard with KPIs (active requests, spending, progress tracker)
- Explore shops (All, Nearby via geolocation, Favorites)
- Place Order wizard (service selection, PDF upload, paper size/type, copies, pickup date)
- My Requests with status tracking (Active/Completed tabs)
- Payment page (GCash QR, payment proof upload, OCR auto-detection of reference number)
- Notifications with real-time polling
- Profile management (name, phone, address, valid ID upload)
- Privacy delete for completed orders (soft-delete)

### Shop Owner Features
- Dashboard with shop metrics and recent print jobs
- Shop Profile management (name, address, location, logo, business permit)
- Offered Services and Service Pricing management (add/edit/delete/toggle)
- Print Jobs management (Pending, In Progress, Ready for Pickup, Completed)
- Order lifecycle: Pending -> Accepted -> Processing -> Ready for Pickup -> Completed
- Payment verification (verify/reject with reasons; OCR-assisted reference detection)
- File download/view for customer PDFs (via Cloudinary)
- Transactions history, Reports with date filters and export
- GCash payment settings (account name, number, QR code, merchant link)
- Notifications with sound alerts (Web Audio API)

### Super Admin Features
- Dashboard with platform-wide metrics
- User Management (view, activate/deactivate, approve/reject)
- Manage Print Shops (approve/reject permits, payment settings, enable/disable)
- Reports (platform-wide)
- Activity Logs (audit trail with IP, user agent, old/new values, real-time polling)
- Notifications and Settings

---

## Key Modules

### Authentication & Access Control
- Registration with email/password + OTP verification (PHPMailer/Gmail SMTP)
- Login with "Remember me" persistent tokens (selector:validator pattern)
- Google OAuth2 social sign-in
- Forgot password with OTP
- Role-based access: `customer`, `shop_owner`, `super_admin`
- Account statuses: `pending`, `verified`, `rejected`, `inactive`, `incomplete`

### Order Lifecycle
```
Customer submits order -> Shop owner receives (Pending)
  -> Owner accepts (Processing) -> Ready for Pickup
  -> Customer pays (GCash proof uploaded)
  -> Owner verifies payment -> Completed
```

### Payment Integration (GCash Only)
- Shop owner provides GCash account details and QR code
- Customer uploads payment proof image + enters reference number
- Tesseract OCR auto-detects reference number and date from receipt
- Owner verifies or rejects payment with reason

### Real-Time Updates
- Polling-based (10-12 second intervals) across all roles
- Pauses when browser tab is hidden
- Sound alerts for new print requests (Web Audio API chime)
- Badge counts for unread notifications

### Security
- CSRF tokens on all forms
- Content Security Policy with nonces
- Security headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, HSTS)
- Rate limiting (MySQL-backed, configurable per action)
- Prepared SQL statements (no raw queries)
- Role guards, ownership checks, IDOR mitigation
- Activity audit logging (user, action, module, target, old/new values, IP, user agent)
- .htaccess blocks direct access to .env, composer files, backend/, uploads/

---

## Database & Integrations

| Integration | Purpose |
|---|---|
| MySQL (printease_db) | Primary database |
| Redis | Session storage and caching (optional, graceful fallback) |
| Cloudinary | Cloud file storage for customer PDF uploads |
| Google OAuth2 | Social sign-in |
| Gmail SMTP | Email delivery for OTP/verification codes |
| Tesseract OCR | Payment proof image analysis |
| OpenStreetMap/Nominatim | Geocoding and distance calculations |

---

## Directory Structure

```
printease/
|-- index.php                     # Landing page / homepage
|-- .env / .env.example           # Environment configuration
|-- manifest.json                 # PWA manifest
|-- service-worker.js             # PWA service worker
|-- .htaccess                     # Apache security & URL rewrite
|
|-- assets/
|   |-- css/                      # Stylesheets (Tailwind, role-specific CSS)
|   `-- images/                   # Logo, favicons, PWA icons
|
|-- frontend/
|   |-- splash.php                # Animated loading screen
|   |-- components/               # Shared PHP includes (head, nav, toasts, notifications)
|   |-- pages/                    # Public/auth pages (login, register, OTP, etc.)
|   |-- assets/js/                # live-updates.js (core polling engine)
|   `-- user/
|       |-- customer/             # Customer pages (dashboard, explore, orders, payment)
|       |-- shop_owner/           # Owner pages (dashboard, services, orders, reports)
|       `-- superadmin/           # Admin pages (dashboard, manage users/shops, logs)
|
|-- backend/
|   |-- config/                   # DB, Redis, Cloudinary, OAuth, app config
|   |-- includes/                 # Core PHP (auth, session, functions, security, rate limiting)
|   |-- actions/                  # 41 backend action endpoints (form processing)
|   |-- oauth/                    # Google OAuth handlers
|   |-- helper/                   # PHPMailer email functions
|   `-- database/                 # 16 SQL migration files
|
|-- uploads/                      # User uploads (IDs, QR codes, orders, permits, logos)
|-- docs/                         # Test plan, deployment checklist
|-- vendor/                       # Composer dependencies
`-- node_modules/                 # npm dependencies (Tailwind)
```

---

## Project Status

- **Type:** Capstone/Thesis project
- **Location:** Calbayog City, Samar, Philippines
- **Current Rating:** 7.5/10 for capstone demo, 6.5/10 for public production
- **Status:** Active development for capstone defense
