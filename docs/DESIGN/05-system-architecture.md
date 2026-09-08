# PrintEase — System Architecture Design

## 1. Overview

PrintEase uses a **3-tier client–server architecture** over `HTTPS`:

- **Client Tier** — responsive PWA (mobile-first) running in any browser; vanilla JS, Tailwind CSS, live polling, offline caching.
- **Application Tier** — Apache + procedural PHP 8.4 (no framework). Pages (`frontend/`) render HTML; POST handlers (`backend/actions/`) process forms; shared services live in `backend/includes/`.
- **Data & Services Tier** — MySQL (primary store), optional Redis (sessions/cache), plus external SaaS: Cloudinary (files), Gmail/cPanel SMTP (email), Google OAuth2 (identity), Tesseract/Google Vision (OCR), Nominatim (geocoding).

## 2. Layered Architecture

```mermaid
flowchart TB
    subgraph Client["Client Tier"]
        PW["PWA: manifest.json + service-worker.js<br/>(cache-first, offline fallback)"]
        B["Mobile & Desktop Browsers"]
        JS["Tailwind CSS + Vanilla JS +<br/>frontend/assets/js/live-updates.js"]
    end

    subgraph App["Application Tier — Apache + PHP 8.4"]
        FE["frontend/ — pages & shared components<br/>(role layouts, toasts, head, branding)"]
        AC["backend/actions/ — 44 POST handlers<br/>(validate → mutate → log → redirect)"]
        IN["backend/includes/ — services<br/>(auth, session, guards, rate_limit,<br/>security_headers, gcash_ocr, mailer, price_records)"]
        CF["backend/config/ — env, db, redis,<br/>cloudinary, oauth loaders"]
        OA["backend/oauth/ — Google sign-in"]
    end

    subgraph Data["Data & Services Tier"]
        MY[("MySQL — printease_db (21 tables)")]
        RD[("Redis — sessions/cache (optional)")]
        CL["Cloudinary — customer PDF storage"]
        EM["Email — Gmail / cPanel SMTP"]
        ID["Identity — Google OAuth 2.0"]
        OC["OCR — Tesseract / Google Vision"]
        NG["Geocoding — Nominatim"]
    end

    B -->|"HTTPS + AJAX polling"| App
    PW --> B
    JS --> B

    App --> MY
    App --> RD
    App --> CL
    App --> EM
    App --> ID
    App --> OC
    App --> NG
```

## 3. Component View (request handling)

```mermaid
flowchart LR
    REQ["HTTP Request"]
    HT[".htaccess<br/>security headers, no indexes"]
    SESS["session.php<br/>secureSession()"]
    AUTH["auth.php + guards<br/>(role, profile, status, shop)"]
    RL["rate_limit.php<br/>per-action limits"]
    ACT["backend/actions/*<br/>validate + mutate"]
    DB[("MySQL")]
    LOG["activity_logs +<br/>notifications rows"]
    RED["PRG redirect / JSON feed"]

    REQ --> HT --> SESS --> AUTH --> RL --> ACT
    ACT --> DB
    ACT --> LOG
    ACT --> RED
```

## 4. End-to-End Order & Payment Sequence

```mermaid
sequenceDiagram
    participant C as Customer
    participant S as PrintEase PHP App
    participant DB as MySQL
    participant CL as Cloudinary
    participant OCR as OCR (Tesseract/Vision)
    participant O as Shop Owner

    C->>S: 1. Submit order request + upload PDF
    S->>CL: 2. Upload file
    CL-->>S: 3. Cloud URL
    S->>DB: 4. Insert order + payment row (pending)
    S->>DB: 5. Write notification + activity log
    O-->>S: 6. New-order alert (polled feed)
    O->>S: 7. Update status → processing → ready_for_pickup
    C->>S: 8. Upload GCash proof + reference no.
    S->>OCR: 9. Auto-detect reference/date (optional)
    OCR-->>S: 10. Detected reference
    O->>S: 11. Verify or reject payment
    S->>DB: 12. verification_status = verified/rejected
    S-->>C: 13. Status + payment notifications
```

## 5. Deployment Architecture

```mermaid
flowchart LR
    subgraph Prod["Production (cPanel shared hosting)"]
        WEB["Apache<br/>printease.org root"]
        PWAPP["PWA assets:<br/>service-worker.js, manifest.json"]
        PHPF["PHP 8.4 files:<br/>index.php, frontend/, backend/"]
        UPL["uploads/ — permits, logos,<br/>QR codes, proofs, customer IDs"]
    end

    EXTERNAL["External Services"]
    WEB --> PHPF
    PHPF --> PWAPP
    PHPF --> UPL

    PHPF -->|"MySQLi"| DBS[("MySQL<br/>ujvepwwv_PRINTEASE_DB")]
    PHPF -->|"SMTP"| EM[("Email SMTP")]
    PHPF -->|"REST"| CLO[("Cloudinary")]
    PHPF -->|"HTTP"| NG[("Nominatim")]
    PHPF -->|"API"| GO[("Google OAuth2")]
    PHPF -->|"CLI + API"| OCR[("Tesseract/Google Vision")]
    CRON["cron: pickup_reminder_checker.php"] --> PHPF
```

Deployment facts:
- Base URL `https://printease.org/`; production config reads from `.env` (`APP_ENV=production`, `BASE_URL`, cPanel SMTP — Gmail SMTP is blocked on z.com).
- MySQL is on the shared host (`ujvepwwv_PRINTEASE_DB`); no Redis in production (session file fallback).
- Customer PDFs go to Cloudinary; all other uploads stay in local `uploads/<category>/`.
- `.htaccess` denies `.env`, composer files, and directory listing; adds security headers.
- `TRUSTED_PROXIES` stays unset so rate limiting keys on `REMOTE_ADDR`.

## 6. Security Architecture

| Layer | Mechanism | Where |
|---|---|---|
| Transport | HTTPS only; HSTS-style headers via `.htaccess` | server |
| Headers | CSP with nonces, `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy, Permissions-Policy | `backend/includes/security_headers.php` |
| Session | `secureSession()`; Redis with file fallback; `SESSION_LIFETIME_DAYS` sliding window | `backend/includes/session.php` |
| Authentication | bcrypt passwords, OTP (email), Google OAuth, remember-me selector:validator tokens, `auth_version` revocation | `auth.php`, `remember_auth.php`, `backend/oauth/` |
| Authorization | role guards + profile/status/shop guards; ownership checks (IDOR mitigation) | `auth.php`, `profile_guard.php`, `status_guard.php`, `shop_guard.php` |
| Input | CSRF tokens on all forms; 100% prepared statements (mysqli); validated/randomized uploads | `rate_limit.php`, all actions |
| Abuse | MySQL-backed rate limiting per action+identifier+IP; `login_attempts` lockout | `rate_limit.php` |
| Accountability | `activity_logs` audit rows (old/new JSON, IP, user agent) | `addActivityLog()` in includes |
| File exposure | `.htaccess` blocks sensitive files; `uploads/` category isolation | server / config |

## 7. Performance & Real-Time Design

- **Live updates** are polling-based (AJAX every ~10–12 s) via `live-updates.js`; feeds are lightweight JSON queries; polling pauses when the tab is hidden.
- **Caching**: `backend/includes/cache.php` + optional Redis; `geocode_cache` avoids repeated Nominatim calls.
- **Scale target**: shared hosting + small city market → polling and single MySQL instance are sufficient; no message broker needed.