# PrintEase UAT — Sunod-sunod na Test Flow (Taglish, Owner First)

> Isang file lang, pang-practice sa defense demo. I-tick ang box pag tapos. `[ ]` = todo, `[x]` = pass, `[!]` = fail.
> Order: owner muna (para may shop), tapos customer, tapos loop pabalik. ID map sa luma nasa dulo ng bawat item.
> Kailangan: staging o `https://printease.org`, Chrome + Edge, 1 phone. I-allow ang popups. Ihanda ang `order_code` at `file_id` habang nagte-test.

## Phase 1 — Owner registration at shop setup (S-01 hanggang S-08)

- [ ] **S-01 — Owner register (O-GATE)**
  - Precon: walang owner account — Map: O-GATE-01
  - Steps: 1. Buksan register 2. Register bilang owner 3. Submit
  - Expected: account nagawa, hiningi ang shop profile, walang error
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-02 — Owner login (O-GATE)**
  - Steps: 1. Login gamit owner account
  - Expected: pasok sa owner area o sa profile setup kung incomplete
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-03 — Complete shop profile (O-PRO-01/04)**
  - Steps: 1. Fill shop name, address, map picker, hours 2. Save
  - Expected: nag-save, kita sa sidebar/topbar, nawala ang block message
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-04 — Verification gate (O-GATE-02)**
  - Precon: kumpleto pero unverified
  - Steps: 1. Buksan Orders 2. Subukang mag-Accept
  - Expected: toast na need verification, blocked ang action
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-05 — Services at pricing setup (O-SVC-01)**
  - Precon: verified owner
  - Steps: 1. Magdagdag ng Document + 1 service (photo/tarpaulin/ID) 2. Lagay presyo 3. Save
  - Expected: nag-save, makikita ito ng customer mamaya
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-06 — Bawal na presyo (O-SVC-02)**
  - Steps: 1. Lagay negative o blank na presyo 2. Save
  - Expected: validation error, hindi nag-save
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-07 — Permit at GCash QR (O-PRO-02/03)**
  - Steps: 1. Upload permit jpg/png/pdf (<=10MB) 2. Upload QR image 3. Save
  - Expected: preview kita bago save, nagamit ang QR sa payment
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-08 — Dashboard at notif (O-DASH-01/02/03)**
  - Steps: 1. Buksan dashboard 2. I-mute/unmute ang sound 3. Refresh 4. Buksan bell
  - Expected: tama ang counts, naalala ang sound setting, popover bumubukas
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 2 — Customer registration at explore (S-09 hanggang S-12)

- [ ] **S-09 — Customer register (C-AUTH-01)**
  - Precon: walang customer account
  - Steps: 1. Register 2. Submit
  - Expected: account nagawa, redirect login/dashboard
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-10 — Customer login + mali + Google (C-AUTH-02/03/04)**
  - Steps: 1. Login tama 2. Logout 3. Login mali 4. Google login
  - Expected: tama = pasok, mali = error, Google = pasok walang duplicate
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-11 — Browse at search shop (C-EXP-01/02)**
  - Steps: 1. Buksan Explore 2. Mag-search ng shop ni owner
  - Expected: kita ang shop, tama ang result, may empty message pag walang match
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-12 — View shop details (C-EXP-03)**
  - Steps: 1. Click shop ni owner
  - Expected: kita services, pricing galing S-05, hours, location
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 3 — Customer place order (S-13 hanggang S-17)

- [ ] **S-13 — Order PDF (C-ORD-01)**
  - Steps: 1. Piliin Document Printing 2. Upload PDF <25MB 3. Copies + pickup datetime 4. Submit
  - Expected: order nagawa, may order code, `pending` sa My Orders
  - Resulta: [ ] Pass [ ] Fail — Notes: ___ (order_code: ___)
- [ ] **S-14 — Order DOCX at WPS (C-ORD-02/03)**
  - Steps: 1. Ulitin gamit `.docx` 2. Ulitin gamit `.wps`
  - Expected: parehong tanggap, tig-isang order
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-15 — Bawal at malaking file (C-ORD-04/05)**
  - Steps: 1. Upload `.exe` 2. Upload >25MB
  - Expected: reject na may malinaw na error, walang order
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-16 — Service order (C-ORD-06)**
  - Steps: 1. Piliin photo/tarpaulin/ID service 2. Upload pdf/jpg/png 3. Submit
  - Expected: order nagawa na may tamang total
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-17 — Tracking (C-TRK-01/02/04)**
  - Steps: 1. Buksan My Orders 2. Buksan focus link 3. Tingnan progress
  - Expected: kita lahat, naka-focus ang tamang order, tama ang steps
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 4 — Customer payment (S-18 hanggang S-19)

- [ ] **S-18 — Upload proof (C-PAY-01/02)**
  - Precon: may pending order galing S-13
  - Steps: 1. Upload malinaw na proof + reference no. 2. Submit
  - Expected: naging For Verification + toast
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-19 — Resubmit at labels (C-PAY-03/04)**
  - Steps: 1. Kung rejected, upload ulit 2. Tingnan labels
  - Expected: bumalik sa For Verification, tama ang Paid/For Verification/Rejected
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 5 — Owner jobs: preview, verify, accept (S-20 hanggang S-29)

> Core ng bagong fix: viewer + download dito.

- [ ] **S-20 — Tabs at View Details (O-JOB-01/02)**
  - Steps: 1. Click All/Pending tabs 2. Search order code 3. Click View Details
  - Expected: tama ang bilang/result/pagination, kita Instructions/Settings/Total
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-21 — Click docx filename = viewer (O-JOB-03)**
  - Steps: 1. Click docx filename sa modal
  - Expected: **1 new tab lang** `view.officeapps.live.com` kita laman, walang blank `file_id` tab
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-22 — Click wps = download lang (O-JOB-04)**
  - Steps: 1. Click wps filename (walang viewer support ang wps)
  - Expected: **download lang**, walang viewer/blank
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-23 — Click pdf = preview (O-JOB-05)**
  - Steps: 1. Click pdf filename
  - Expected: 1 tab PDF preview
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-24 — Verify at reject payment (O-JOB-11/12)**
  - Steps: 1. Mark as Paid sa isang order 2. Reject na walang reason 3. Lagyan ng reason + Confirm
  - Expected: naging Paid/Verified + lumabas ang Accept; reject walang reason = error, may reason = Rejected + notified
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-25 — Accept docx = viewer+download (O-JOB-06/09)**
  - Precon: pending + paid/verified docx
  - Steps: 1. Click Accept & Download
  - Expected: `processing` na, **1 viewer tab + 1 download**, toast + redirect Processing. Network: 1x `check=1` + 1x `update`
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-26 — Accept pdf = viewer+download (O-JOB-07)**
  - Precon: pending + paid/verified pdf
  - Steps: 1. Click Accept & Download
  - Expected: **1 preview tab + 1 download**, `processing` na, walang double
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-27 — Accept wps = download lang (O-JOB-08/10)**
  - Steps: 1. Click Accept 2. Mabilis na double-click sa susunod na order
  - Expected: download lang + `processing`; double-click = isang beses lang nag-process
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-28 — Decline + Ready/Completed (O-JOB-13/14)**
  - Steps: 1. Decline isang pending 2. Mark Ready tapos Completed sa ibang order
  - Expected: nawala/nabawasan ang Pending, umusad ang status + toast
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-29 — Balance note + proof viewer (O-JOB-15/16)**
  - Steps: 1. Send additional/refund note + ulitin 2. Click View Proof + zoom/reset
  - Expected: bago ang pumalit sa luma at kita sa customer; proof kita at nag-zoom
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 6 — Close loop at owner records (S-30 hanggang S-33)

- [ ] **S-30 — Customer tracking pabalik (C-TRK-03/04, C-PAY-04)**
  - Steps: 1. Login bilang customer 2. Tingnan status at payment label
  - Expected: tugma sa ginawa ni owner (processing/ready/completed, Paid)
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-31 — Owner payments at transactions (O-PAY-01, O-TRN-01)**
  - Steps: 1. Buksan Payments + Transactions 2. Mag-filter
  - Expected: tama ang listahan at totals
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-32 — Reports at profile edits (O-REP-01, O-PRO-01/02/03/04)**
  - Steps: 1. Buksan Reports 2. Baguhin logo/hours/address + permit/QR + map/merchant link 3. Save
  - Expected: tama ang numbers, nag-save at kita agad
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-33 — Notif at profile magkabila (C-NOT-01/02, C-PRO-01, O-DASH-03)**
  - Steps: 1. Buksan bell magkabila 2. Read all 3. Edit customer profile
  - Expected: nabawasan ang unread, napunta sa tamang order, nag-save ang profile
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Phase 7 — Regression pang-live (S-34 hanggang S-40, dapat Pass lahat)

- [ ] **S-34 — Invalid at ibang-shop file (X-FILE-01/02)**
  - Steps: 1. Buksan `file_id=999999` 2. Owner A buksan file ni Owner B
  - Expected: 404 pareho, walang leak/download
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-35 — Logout block (X-FILE-03)**
  - Steps: 1. Logout 2. Buksan download URL
  - Expected: redirect sa login
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-36 — Malaking file (X-FILE-04)**
  - Steps: 1. Accept 20MB+ docx/pdf
  - Expected: download pa rin o graceful fallback, hindi white screen
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-37 — Popup blocked (X-POP-01)**
  - Steps: 1. I-block ang popups 2. Accept docx
  - Expected: may toast na may manual preview/download links
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-38 — Mobile (X-MOB-01)**
  - Steps: 1. Ulitin S-21 hanggang S-27 sa phone
  - Expected: viewer/download gumagana, modal nababasa
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-39 — Sabay Accept (X-PERF-01)**
  - Steps: 1. Accept 2 orders nang mabilis
  - Expected: tig-1 set lang ng tabs, walang halo ang files
  - Resulta: [ ] Pass [ ] Fail — Notes: ___
- [ ] **S-40 — Session expired + superadmin (A-AUTH-01, A-ADM-01/02/03)**
  - Steps: 1. I-expire ang session tapos mag-Accept 2. Buksan Manage Shops/Users/Logs/Reports
  - Expected: redirect sa login; admin pages walang 500 at kita ang details
  - Resulta: [ ] Pass [ ] Fail — Notes: ___

## Bug report at sign-off

```text
ID (S-XX):
Title:
Steps:
Expected:
Actual:
Browser/Device:
Order code / file_id:
Screenshot/Network log:
```

| Tester | Petsa | Resulta |
|---|---|---|
| Customer tester | | Pass / Fail |
| Shop owner tester | | Pass / Fail |
| Developer | | Fixed / N/A |
