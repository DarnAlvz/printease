# PrintEase — Data Flow Diagram (Level 0)

## 1. Purpose

The **Level-0 Data Flow Diagram (DFD)** breaks the PrintEase system into its major processes, shows how they exchange data with external entities and with each other through **data stores**. It uses the Yourdon/DeMarco-style notation:

| Symbol | Notation | Meaning |
|---|---|---|
| box | `[Entity]` | External entity (source or sink) |
| stadium / circle | `(Process no.)` | Process that transforms data |
| cylinder | `(D# name)` | Data store (persistent table / group of tables) |
| labeled arrow | `-->|flow|` | Data flow between nodes |

## 2. Level-0 DFD

```mermaid
flowchart LR
    C["Customer"]
    O["Shop Owner"]
    A["Super Admin"]

    P1(["1.0 Accounts &\n Authentication"])
    P2(["2.0 Shop & Permit\n Registration"])
    P3(["3.0 Services &\n Pricing"])
    P4(["4.0 Process\n Orders"])
    P5(["5.0 Process\n Payments"])
    P6(["6.0 Notifications"])
    P7(["7.0 Reports &\n Logs"])
    P8(["8.0 Admin\n Governance"])

    D1[("D1 Users")]
    D2[("D2 Print Shops")]
    D3[("D3 Services &\n Pricing")]
    D4[("D4 Orders")]
    D5[("D5 Payments")]
    D6[("D6 Uploaded Files")]
    D7[("D7 Notifications")]
    D8[("D8 Activity Logs")]
    D9[("D9 Payment Channels")]
    D10[("D10 System Data")]

    C -->|"register, login/OTP, social,\nprofile, valid ID"| P1
    P1 --> D1
    D1 --> P1

    C -->|"payment proof + reference"| P5
    P5 --> D5
    P5 --> D7

    C -->|"order request + PDF"| P4
    P4 --> D4
    P4 --> D6
    P4 --> D7

    O -->|"shop profile, permit"| P2
    P2 --> D2
    P2 --> D7
    P2 --> D8

    O -->|"service & pricing edits"| P3
    P3 --> D3

    O -->|"order status update, download"| P4
    O -->|"verify/reject payment"| P5

    A -->|"account decisions"| P8
    A -->|"permit decisions"| P8
    P8 --> D1
    P8 --> D2
    P8 --> D9
    P8 --> D8

    P6 --> D7
    D7 --> P6
    P6 -->|"notification feed (poll)"| C
    P6 -->|"notification feed (poll)"| O
    P6 -->|"notification feed (poll)"| A

    P7 --> D8
    P7 --> D5
    P7 --> D4
    P7 -->|"reports & audit trail"| A

    P1 --> P8
    P2 -.->|"approval requests"| P8
    P5 -.->|"channel submitted"| P8
    P9[""] -.- P5
```

> Note: `D9 Payment Channels` (table `shop_payment_channels`) is written by a shop-owner submission process and approved by process 8.0. `D10 System Data` groups security/support tables (`login_attempts`, `rate_limit_events`, `user_remember_tokens`, `geocode_cache`, `customer_favorite_shops`).

## 3. Data Store Dictionary

| Store | Tables (from DB schema) | Description |
|---|---|---|
| D1 Users | `users`, `user_social_accounts`, `user_remember_tokens` | Registered accounts, OAuth links, persistent-login tokens. |
| D2 Print Shops | `print_shops` | Shop profiles, coordinates, permit file/status. |
| D3 Services & Pricing | `shop_service_types`, `shop_services`, `shop_service_pricing`, `shop_price_records`, `shop_custom_services` | Service catalogue and per-shop price offerings. |
| D4 Orders | `orders` | Print requests with status lifecycle and totals. |
| D5 Payments | `payments` | Payment proofs, reference numbers, verification state. |
| D6 Uploaded Files | `uploaded_files` | Customer file metadata + Cloudinary URLs. |
| D7 Notifications | `notifications` | In-app notification rows by user. |
| D8 Activity Logs | `activity_logs` | Audit trail (who did what, old/new values, IP/UA). |
| D9 Payment Channels | `shop_payment_channels`, `shop_payment_settings`(legacy), `shop_payment_accounts`(legacy) | GCash QR / merchant-link channels and approval state. |
| D10 System Data | `login_attempts`, `rate_limit_events`, `customer_favorite_shops`, `geocode_cache` | Security counters, favorites, geocoding cache. |

## 4. Process → Data Store Matrix

| Process | Writes | Reads | Description |
|---|---|---|---|
| 1.0 Accounts & Authentication | D1, D8 | D1, D10 | Register, OTP, login, social, remember-me, logout, password reset. |
| 2.0 Shop & Permit Registration | D2, D7, D8 | D1 | Create/update shop profile, submit permit, set operating hours. |
| 3.0 Services & Pricing | D3 | D3 | Add/update/delete/toggle service types, document pricing, flexible pricing. |
| 4.0 Process Orders | D4, D6, D7, D8 | D3 | Create order, upload file, advance status, decline, download, reminders. |
| 5.0 Process Payments | D5, D7, D8 | D5 | Proof upload, OCR detection, verify/reject. |
| 6.0 Notifications | D7 | D7 | Feed generation, mark-read, badge counts, sound alerts. |
| 7.0 Reports & Logs | D8 | D4, D5, D8 | Platform/owner reports, transactions, activity-log viewer. |
| 8.0 Admin Governance | D1, D2, D9, D8 | D1, D2, D9 | Approve/reject users, permits, payment channels; enable/disable shops. |

## 5. Level-0 Flow Summary

Backing endpoints (see `docs/CHATGPT_SYSTEM_CONTEXT.md` §8 + §10):

- Process 1.0 → `register_process.php`, `login_process.php`, `send_otp.php`, `verify_otp.php`, `verify_registration.php`, `reset_password_process.php`, `logout.php`, `backend/oauth/*`.
- Process 2.0 → `save_shop_profile.php`, `reverse_geocode.php`.
- Process 3.0 → `add/update/delete/toggle_service*.php`, `shop_service_type_feed.php`.
- Process 4.0 → `submit_order.php`, `pending_order_token.php`, `update_order_status.php`, `decline_order.php`, `download_order_file.php`, `pickup_reminder_checker.php`.
- Process 5.0 → `submit_payment_proof.php`, `detect_payment_reference.php`, `verify_payment.php`.
- Process 6.0 → `notification_feed.php`, `mark_notification_read.php`, `owner_order_feed.php`.
- Process 7.0 → superadmin/owner `reports.php`, `activity_logs.php`.
- Process 8.0 → `update_user_status.php`, `update_permit_status.php`, `update_payment_settings_status.php`, `update_shop_status.php`.