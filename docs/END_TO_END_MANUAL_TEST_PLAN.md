# PrintEase End-to-End Manual Test Plan

Use this checklist to test PrintEase from a clean start through the full customer request, payment, print job, and completed request privacy flow.

Recommended setup: use three separate browsers, private windows, or browser profiles:

- Customer session
- Shop Owner session
- Super Admin session

## 1. Preparation

- [✓] Start Apache and MySQL in Laragon/XAMPP.
- [✓] Open the app at `BASE_URL`, usually `http://localhost/printease/`.
- [✓] Confirm the database has the latest migrations, including:
  - `shop_service_types`
  - `shop_service_pricing`
  - `shop_price_records`
  - `orders.customer_deleted_at`
- [✓] Prepare a valid PDF file for customer request upload.
- [✓] Prepare an invalid file type for upload testing, such as `.docx` or `.exe`.
- [✓] Prepare a payment proof image.
- [✓] Prepare a shop logo image.
- [✓] Prepare a business permit image/PDF.
- [✓] Prepare a customer valid ID image.
- [✓] Prepare test accounts:
  - Customer: `customer_test@example.com`
  - Shop Owner: `owner_test@example.com`
  - Super Admin: existing admin account from the database.

Expected result:

- [✓] App loads without PHP fatal errors.
- [✓] Required test files are ready.
- [] Test users can be created or reused safely.

Bug notes:

```text

```

## 2. Public Pages And Authentication Validation

- [✓] Open the home page.
- [✓] Open the login page.
- [✓] Open the register page.
- [✓] Open terms and privacy pages.
- [✓] Submit the register form with empty fields.
- [✓] Test invalid email format.
- [✓] Test password less than 8 characters.
- [✓] Test mismatched confirm password.
- [✓] Submit the login form with empty fields.
- [ ] Test login with wrong password.
- [✓] Test login with unregistered email.
- [✓] Uncheck terms checkbox on login and submit.

Expected result:

- [✓] Public pages load correctly on desktop and mobile.
- [✓] Validation errors appear inline below fields.
- [✓] No native browser tooltip validation appears.
- [✓] Wrong login attempts show a generic error.
- [✓] Terms error appears when the checkbox is not selected.

Bug notes:

```text

```

## 3. Customer Account Setup

- [✓] Register a new customer account.
- [✓] Confirm Gmail addresses can register manually if needed.
- [✓] Verify OTP email is received.
- [✓] Enter a wrong OTP.
- [✓] Enter the correct OTP.
- [✓] Login as the customer.
- [✓] Open customer profile.
- [✓] Try saving with missing required fields.
- [✓] Complete the customer profile.
- [✓] Upload valid ID/profile image.
- [] Open Dashboard, Explore, My Requests, Notifications, and Profile.

Expected result:

- [✓] Wrong OTP is rejected.
- [✓] Correct OTP verifies the account.
- [✓] Customer can log in after verification.
- [✓] Missing profile fields are rejected.
- [✓] Completed profile unlocks customer pages.
- [✓] Customer pages use request wording, such as `My Requests` and `Request code`.

Bug notes:

```text

```

## 4. Shop Owner Account Setup

- [✓] Register a new shop owner account.
- [✓] Verify OTP email is received.
- [✓] Enter the correct OTP.
- [✓] Login as the shop owner.
- [✓] Try opening Services and Print Jobs before completing the shop profile.
- [✓] Complete shop name, address/location, contact details, and landmark.
- [✓] Upload shop logo.
- [✓] Upload business permit.
- [✓] Add GCash/payment settings if available.
- [✓] Save the shop profile.

Expected result:

- [✓] Owner is guided to complete the shop profile.
- [✓] Restricted owner features are blocked until profile/verification requirements are met.
- [✓] Invalid or missing uploads are rejected.
- [✓] Permit/payment status waits for admin approval.

Bug notes:

```text

```

## 5. Super Admin Approvals

- [✓] Login as Super Admin.
- [✓] Open User Management.
- [x] Approve or activate the customer account.
- [x] Approve or activate the shop owner account if needed.
- [✓] Open Manage Print Shops.
- [✓] Approve the shop business permit.
- [✓] Approve the shop GCash/payment settings.
- [✓] Disable or reject a test shop, then reactivate it.
- [✓] Check admin dashboard, activity logs, and notifications.

Expected result:

- [✓] Approved customer can use customer pages.
- [✓] Approved shop owner can manage the shop.
- [✓] Verified shop becomes visible/usable to customers after pricing exists.
- [x] Disabled or rejected shop becomes unavailable.
- [✓] Admin actions are recorded in activity logs.

Bug notes: Deactivate on shop and customer account confirm modal was not submitting due to missing hidden input for submit button name when form.submit() is called programmatically. Fixed by adding `<input type="hidden" name="update_user_status" value="1">` to all data-confirm-action forms. Notification "View All" page was unstyled for admin and owner — fixed by creating shared notification-center.css.

```text
Bug #: 1, 4
Page: User Management, Notifications (View All)
Role: Super Admin
Steps: Click Deactivate on a verified user → modal appears → click Deactivate in modal
Expected: User status changes to Inactive
Actual: Nothing happened — form.submit() did not include submit button name
Severity: High
Status: Fixed
```

## 6. Shop Owner Offered Services And Pricing

- [✓] Login as the verified shop owner.
- [✓] Open Shop Management/Profile.
- [✓] Confirm `Document Printing` is checked and locked by default.
- [✓] Select at least four other offered services, such as Photo Printing, Lamination, Binding, and Tarpaulin Printing.
- [✓] Save offered services.
- [✓] Open Service Pricing.
- [✓] Confirm the pricing page shows one clean pricing workspace.
- [✓] Confirm `Document Printing` appears first in the price list.
- [✓] Click `Add Price` and confirm the modal opens.
- [✓] Add a Document Printing price:
  - Service: `Document Printing`
  - Size/Variant: `A4`
  - Variant: `Colored`
  - Pricing Basis/Charged By: `per page`
  - Price: `8.00`
- [✓] Add another Document Printing price:
  - Size/Variant: `A4`
  - Variant: `Black and White`
  - Price: `2.00`
- [✓] Add non-document prices:
  - Photo Printing, `4R Glossy`, `per piece`, `10.00`
  - Lamination, `A4`, `per piece`, `25.00`
  - Binding, `1-50 Pages`, `per set`, `40.00`
- [ ] Try optional advanced fields in the modal if visible.
- [ ] Try invalid price values, such as zero, negative, or blank.
- [ ] Edit one Document Printing price.
- [ ] Edit one non-document price.
- [ ] Disable and enable one price.
- [ ] Delete a safe non-document price.
- [ ] Use filter and search in the price list.

Expected result:

- [ ] Document Printing cannot be removed from offered services.
- [ ] Non-document pricing dropdown only shows selected offered services.
- [ ] Add Price modal stays clean and responsive.
- [ ] Pricing list shows service groups, option, charged by, price, status, and actions.
- [ ] Existing backend compatibility remains active:
  - Document Printing prices still save through `shop_services`.
  - Other service prices still save through `shop_service_pricing`.
  - Generic records also sync to `shop_price_records`.
- [ ] Invalid pricing inputs are rejected.
- [ ] Edit, enable/disable, delete, filter, and search work.

Bug notes: Document Printing variant details showed "Standard" as paper_type because it was hardcoded via hidden input. Fixed by removing the hidden paper_type=Standard input, making paper_type optional, and renaming table columns to "Size" and "Type". Non-document pricing had redundant "Service" column repeating the group header — removed entirely.

```text
Bug #: 2, 3
Page: Service Pricing Management
Role: Shop Owner
Steps: Add a Document Printing price → check the price list table
Expected: Clean pricing columns with meaningful labels
Actual: "Variant Details" showed "Standard / Colored" (paper_type hardcoded to Standard); non-document table had redundant Service column
Severity: Medium
Status: Fixed
```

## 7. Customer Explore Shop Discovery

- [ ] Login as the customer.
- [ ] Open Explore.
- [ ] Check All Shops.
- [ ] Check Nearby.
- [ ] Check Favorites.
- [ ] Confirm the shop card shows correct offered service count, such as `5 services`.
- [ ] Confirm service chips show the first few services.
- [ ] Tap `+ more`, then collapse it again if using chip expansion.
- [ ] If a bottom sheet is used for all services, confirm it opens and closes cleanly.
- [ ] Favorite and unfavorite the shop.
- [ ] Use search by shop name, street, or location.
- [ ] Tap `Use My Location` in Nearby.
- [ ] Confirm distance appears below address/landmark using the pin icon.
- [ ] Switch to All Shops and Favorites and confirm cached distance appears there too.
- [ ] Turn off browser location permission and return/focus the page.

Expected result:

- [ ] Contact fact is not shown on Explore shop cards.
- [ ] Service count matches offered services, not only document price rows.
- [ ] Nearby, All Shops, and Favorites use the same shop card information layout.
- [ ] Address, landmark, and distance align in one vertical stack.
- [ ] Distance appears only when real customer coordinates are available.
- [ ] Distance disappears without refresh when location permission becomes unavailable.
- [ ] Favorite and request actions still work.

Bug notes:

```text

```

## 8. Customer Request Submission

- [ ] From Explore, select a verified shop.
- [ ] Open the request flow.
- [ ] Confirm service selection is visible.
- [ ] Choose `Document Printing`.
- [ ] Try continuing without uploading a file.
- [ ] Try uploading a non-PDF file.
- [ ] Upload a valid PDF file.
- [ ] Choose paper size/type, print type, copies, and pickup date/time.
- [ ] Try a past pickup date/time.
- [ ] Review the request summary.
- [ ] Submit the request.
- [ ] Try two quick submissions if practical.
- [ ] Create a second request for a non-document offered service if customer flow supports it.

Expected result:

- [ ] File upload is required for online print requests.
- [ ] Non-PDF files are rejected when the service requires PDF.
- [ ] Valid PDF is accepted.
- [ ] Past pickup date/time is rejected.
- [ ] Total amount matches price x quantity/copies/page count.
- [ ] Successful request creates a unique request code.
- [ ] Customer lands on My Requests or sees the request in My Requests.
- [ ] Rapid submissions do not create inconsistent duplicates.

Bug notes:

```text

```

## 9. Customer My Requests Card

- [ ] Open My Requests.
- [ ] Confirm the Active tab shows the new request.
- [ ] Confirm each request card shows only these primary details by default:
  - Service
  - Instruction
  - Pickup
  - Total
  - Payment
- [ ] Tap `Show more`.
- [ ] Confirm full technical details appear.
- [ ] Tap `Show less`.
- [ ] Confirm details collapse for that card only.
- [ ] Test a long instruction and confirm the card remains readable.

Expected result:

- [ ] Request card is compact by default.
- [ ] Service is visible.
- [ ] Long instruction wraps cleanly.
- [ ] Show more/less works per card.
- [ ] Payment and proof buttons remain unchanged.

Bug notes:

```text

```

## 10. Shop Owner Print Job Intake

- [ ] Login as the shop owner in a separate browser/session.
- [ ] Open Print Jobs.
- [ ] Confirm the new customer request appears as a print job.
- [ ] Check tabs/cards:
  - All Jobs
  - Pending Jobs
  - In Progress
  - Ready for Pickup
  - Completed Jobs
- [ ] Open the print job details modal.
- [ ] Download or view the uploaded customer file.
- [ ] Try moving the print job forward before payment is verified.
- [ ] Open the same print job in two tabs and try status changes.

Expected result:

- [ ] Owner only sees jobs for their own shop.
- [ ] Uploaded file download/view works.
- [ ] Payment gating is enforced if required.
- [ ] Stale or double status updates do not silently corrupt state.
- [ ] Visible wording uses print job/job terms, not customer order wording.

Bug notes:

```text

```

## 11. Customer Payment Proof

- [ ] Customer opens payment page for the request.
- [ ] Confirm page uses `Request Price Breakdown`.
- [ ] Upload an invalid payment proof file type.
- [ ] Upload a valid payment proof image.
- [ ] Enter GCash reference number.
- [ ] Submit payment proof.
- [ ] Try submitting payment proof twice for the same request.

Expected result:

- [ ] Invalid file type is rejected.
- [ ] Valid proof uploads.
- [ ] OCR runs or safely skips if unavailable/rate-limited.
- [ ] Payment status becomes pending verification.
- [ ] Shop owner receives a payment proof notification.
- [ ] Only one active pending/verified proof exists for the request.

Bug notes:

```text

```

## 12. Owner Payment Verification

- [ ] Owner opens the print job/payment details.
- [ ] Verify the payment proof.
- [ ] Confirm customer receives a notification.
- [ ] Create another request and reject its payment proof with a reason.
- [ ] Customer views the rejection reason.
- [ ] Customer resubmits proof if allowed.

Expected result:

- [ ] Verified proof becomes paid/verified.
- [ ] Rejected proof stores and displays the reason.
- [ ] Customer can submit a new proof after rejection if allowed.
- [ ] Owner can move the print job forward after payment verification.

Bug notes:

```text

```

## 13. Print Job Status Lifecycle

- [ ] Owner updates job from Pending to In Progress/Processing.
- [ ] Customer checks My Requests without refreshing manually.
- [ ] Owner updates job to Ready for Pickup.
- [ ] Customer checks My Requests and notifications.
- [ ] Owner updates job to Completed.
- [ ] Customer checks Completed tab.
- [ ] Admin checks reports/transactions if available.

Expected result:

- [ ] Customer request status updates correctly.
- [ ] Notifications are created.
- [ ] Completed request appears in customer Completed tab.
- [ ] Completed job appears in owner history/reports.
- [ ] Invalid status transitions are rejected if guarded.

Bug notes:

```text

```

## 14. Completed Request Privacy Remove

- [ ] Customer opens My Requests.
- [ ] Open the Completed tab.
- [ ] Confirm completed requests show a `Remove` or privacy delete button.
- [ ] Click the remove button.
- [ ] Confirm an in-app modal appears, not a browser `localhost` alert.
- [ ] Click Cancel.
- [ ] Confirm the completed request remains visible.
- [ ] Click remove again.
- [ ] Confirm the modal and remove the request.
- [ ] Refresh My Requests.
- [ ] Login as shop owner and confirm the completed print job remains visible.

Expected result:

- [ ] Active requests do not show the remove button.
- [ ] Completed request is hidden only from the customer history.
- [ ] Completed count decreases for the customer.
- [ ] Shop owner records, payments, files, reports, and logs remain intact.
- [ ] Customer live updates do not bring the hidden completed request back.

Bug notes:

```text

```

## 15. Notifications And Live Updates

- [ ] Keep Customer My Requests open.
- [ ] Keep Shop Owner Print Jobs open.
- [ ] Keep Customer Explore open in another tab if practical.
- [ ] Owner changes a print job status.
- [ ] Customer watches My Requests update without manual refresh.
- [ ] Customer submits payment proof.
- [ ] Owner watches Print Jobs update without manual refresh.
- [ ] Owner edits pricing or availability.
- [ ] Customer watches Explore or Place Request update pricing/service information without manual refresh.
- [ ] Mark notifications as read.
- [ ] Click notification target links.

Expected result:

- [ ] Core customer and owner views poll and refresh safely.
- [ ] Modals and edit states are not interrupted unexpectedly.
- [ ] Notification badge count updates.
- [ ] Notification target links open the correct role page.
- [ ] Customer notifications use request wording.
- [ ] Owner notifications use print request/print job wording where visible.

Bug notes:

```text

```

## 16. Reports, Transactions, And Logs

Admin:

- [ ] Open dashboard.
- [ ] Open reports.
- [ ] Open activity logs.
- [ ] Test user/shop filters and search.

Shop Owner:

- [ ] Open dashboard.
- [ ] Open reports.
- [ ] Open transactions.
- [ ] Test date filters/search/export if present.

Expected result:

- [ ] Reports reflect completed print jobs and payments.
- [ ] Filters work.
- [ ] Empty results do not crash.
- [ ] Export/download works if available.
- [ ] Activity logs reflect major actions.

Bug notes:

```text

```

## 17. Security And Access Tests

- [ ] While logged out, open a protected customer page directly.
- [ ] While logged out, open a protected shop owner page directly.
- [ ] While logged out, open a protected admin page directly.
- [ ] As customer, open owner/admin URLs.
- [ ] As shop owner, open customer/admin URLs.
- [ ] Submit a protected form after logout/back button.
- [ ] Submit a form with missing/invalid CSRF token if practical.
- [ ] Try changing URL/form IDs such as request/payment/shop/user IDs.
- [ ] Try opening another customer's payment/request URL.
- [ ] Try owner actions against another shop's print job.

Expected result:

- [ ] Logged-out users redirect to login.
- [ ] Wrong-role users are denied.
- [ ] Invalid CSRF is rejected.
- [ ] ID tampering does not expose or modify other users' records.
- [ ] No raw PHP fatal errors appear.

Bug notes:

```text

```

## 18. Rate Limit And Error Handling

- [ ] Try several wrong login attempts.
- [ ] Try repeated registration attempts with the same email/IP.
- [ ] Try OTP resend repeatedly.
- [ ] Try payment OCR repeatedly if practical.
- [ ] Temporarily test with missing optional services/prices.
- [ ] Temporarily test a shop with no available Document Printing price.

Expected result:

- [ ] Excessive attempts are temporarily blocked.
- [ ] Error messages explain retry/wait where appropriate.
- [ ] Normal use works after waiting.
- [ ] Shops without required pricing are not order-ready in current customer flow.
- [ ] Missing optional pricing/services do not crash pages.

Bug notes:

```text

```

## 19. Final Defense Demo Path

Use this short smooth path during defense:

1. [ ] Show register form with inline validation.
2. [ ] Register customer account and verify OTP.
3. [ ] Admin approves/activates customer account.
4. [ ] Customer logs in and completes profile.
5. [ ] Register shop owner account and verify OTP.
6. [ ] Admin approves shop owner account.
7. [ ] Owner completes shop profile and payment settings.
8. [ ] Admin approves shop permit/payment settings.
9. [ ] Owner selects offered services and adds pricing.
10. [ ] Customer explores shop and sees service count/distance.
11. [ ] Customer submits a request with required PDF upload.
12. [ ] Owner receives print job notification and views uploaded file.
13. [ ] Customer submits payment proof.
14. [ ] Owner verifies payment.
15. [ ] Owner updates print job through completion.
16. [ ] Customer sees status updates and completed request.
17. [ ] Customer removes completed request from history.
18. [ ] Admin shows reports/activity logs.

## Pass Criteria

- [ ] No PHP fatal errors.
- [ ] No broken role access.
- [ ] Customer request/payment lifecycle completes end-to-end.
- [ ] Shop owner print job lifecycle completes end-to-end.
- [ ] Duplicate/rapid requests do not create inconsistent states.
- [ ] Notifications and logs reflect major actions.
- [ ] Invalid files/forms and unauthorized pages are rejected cleanly.
- [ ] Inline validation errors display below form fields.
- [ ] Service pricing remains compatible with current tables and generic price records.
- [ ] Completed request privacy remove hides only customer history.
- [ ] No-refresh updates work for core customer and owner pages.

## Final Bug Summary

```text
Bug #:
Page:
Role:
Steps:
Expected:
Actual:
Severity:
Status:
```
