<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/owner_layout.php";

$owner_id = $_SESSION['user_id'];

$notif_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = (mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0);

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));
$payment_settings = null;

if ($shop) {
    $payment_settings_stmt = mysqli_prepare($conn, "SELECT * FROM shop_payment_settings WHERE shop_id = ? LIMIT 1");
    mysqli_stmt_bind_param($payment_settings_stmt, "i", $shop['shop_id']);
    mysqli_stmt_execute($payment_settings_stmt);
    $payment_settings = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_settings_stmt));
}

$permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : 'incomplete';
$shop_status = $shop['shop_status'] ?? 'available';
$shop_location = ownerShopLocation($shop);
$service_count = 0;
$shop_service_types = [];
if ($shop) {
    $svc_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM shop_services WHERE shop_id = ?");
    mysqli_stmt_bind_param($svc_stmt, "i", $shop['shop_id']);
    mysqli_stmt_execute($svc_stmt);
    $service_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($svc_stmt))['total'] ?? 0);

    $st_stmt = mysqli_prepare($conn, "SELECT service_type FROM shop_service_types WHERE shop_id = ?");
    mysqli_stmt_bind_param($st_stmt, "i", $shop['shop_id']);
    mysqli_stmt_execute($st_stmt);
    $st_result = mysqli_stmt_get_result($st_stmt);
while ($st_row = mysqli_fetch_assoc($st_result)) {
        $shop_service_types[] = $st_row['service_type'];
    }
}
$shop_service_types[] = 'Document Printing';
$shop_service_types = array_values(array_unique(array_filter($shop_service_types)));
$allowed_service_types = [
    'Document Printing',
    'Photocopy',
    'Photo Printing',
    'Tarpaulin Printing',
    'Lamination',
    'Binding',
    'Scanning',
    'ID Printing',
    'Invitation / Card Printing',
];
$shop_status_details = [
    'available' => [
        'label' => 'Accepting Print Requests',
        'description' => 'Customers can submit print requests and checkout normally.',
        'icon' => 'circle-check',
    ],
    'busy' => [
        'label' => 'Busy',
        'description' => 'Customers can still submit requests, but they will know demand is high.',
        'icon' => 'clock-3',
    ],
    'not_accepting' => [
        'label' => 'Not Accepting Print Requests',
        'description' => 'Customers cannot submit new print requests until your status changes.',
        'icon' => 'circle-pause',
    ],
];
$current_status = $shop_status_details[$shop_status] ?? $shop_status_details['available'];
$can_change_status = $permit_status === 'verified' && !empty($shop);
function shopTimeValue($time)
{
    if (empty($time)) {
        return '';
    }

    return date("H:i", strtotime($time));
}

function shopTimeLabel($time)
{
    if (empty($time)) {
        return 'Not set';
    }

    return date("g:i A", strtotime($time));
}

$weekday_open_time = shopTimeValue($shop['weekday_open_time'] ?? '');
$weekday_close_time = shopTimeValue($shop['weekday_close_time'] ?? '');
$weekend_open_time = shopTimeValue($shop['weekend_open_time'] ?? '');
$weekend_close_time = shopTimeValue($shop['weekend_close_time'] ?? '');
$payment_approval_status = $payment_settings['approval_status'] ?? 'pending';
$payment_qr_code = $payment_settings['gcash_qr_code'] ?? ($shop['gcash_qr_file'] ?? '');
$payment_instructions = $payment_settings['instructions'] ?? 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.';

$weekday_hours_label = ($weekday_open_time && $weekday_close_time)
    ? shopTimeLabel($weekday_open_time) . " - " . shopTimeLabel($weekday_close_time)
    : "Not set";

$weekend_hours_label = ($weekend_open_time && $weekend_close_time)
    ? shopTimeLabel($weekend_open_time) . " - " . shopTimeLabel($weekend_close_time)
    : "Not set";

ownerLayoutStart('profile', 'Shop Management', 'Manage your shop details, permit, and operating availability.', $notif_count, $shop);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<section class="shop-status-bar <?php echo e(ownerStatusClass($shop_status)); ?>" aria-label="Current shop status">
    <div class="shop-status-summary">
        <span class="shop-status-indicator" aria-hidden="true"></span>
        <div>
            <div class="shop-status-title">
                <strong>Shop Status: <?php echo e($current_status['label']); ?></strong>
                <span class="shop-status-live"><?php echo ownerIcon($current_status['icon'], 'icon-sm'); ?> Live</span>
            </div>
            <p><?php echo e($current_status['description']); ?></p>
        </div>
    </div>

    <?php if ($can_change_status): ?>
        <details class="shop-status-menu">
            <summary>Change Status <?php echo ownerIcon('chevron-down', 'icon-sm'); ?></summary>
            <div class="shop-status-options">
                <?php foreach ($shop_status_details as $status_value => $status_detail): ?>
                    <form action="../../../backend/actions/update_shop_status.php" method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="shop_status" value="<?php echo e($status_value); ?>">
                        <input type="hidden" name="return_to" value="shop_profile.php">
                        <button type="submit" name="update_status"
                            class="<?php echo $shop_status === $status_value ? 'is-current' : ''; ?>">
                            <?php echo ownerIcon($status_detail['icon'], 'icon-sm'); ?>
                            <span><?php echo e($status_detail['label']); ?></span>
                            <?php if ($shop_status === $status_value): ?>
                                <?php echo ownerIcon('check', 'icon-sm'); ?>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </details>
    <?php else: ?>
        <button type="button" class="shop-status-disabled" disabled
            title="Your business permit must be verified before changing shop status.">
            Change Status <?php echo ownerIcon('lock', 'icon-sm'); ?>
        </button>
    <?php endif; ?>
</section>

<?php if ($shop && $service_count === 0): ?>
<section class="owner-card shop-services-banner" style="border-left: 4px solid #f59e0b; background: #fffbeb; margin-bottom: 20px;">
    <div style="display: flex; align-items: flex-start; gap: 14px; padding: 4px 0;">
        <?php echo ownerIcon('file-text', 'icon'); ?>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 4px; font-size: 15px;">Add Paper Pricing to Go Live</h3>
            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                Your shop profile is complete, but customers won't see your shop until you add at least one paper size and print price.
                Also make sure to select your Services Offered below so customers know what you offer.
            </p>
            <div style="display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;">
                <a href="services.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo ownerIcon('plus', 'icon-sm'); ?>
                    Add Paper Pricing
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<form action="../../../backend/actions/save_shop_profile.php" method="POST" enctype="multipart/form-data"
    class="shop-management-form is-locked" id="shopProfileForm">
    <?php echo csrfField(); ?>
    <input type="hidden" name="latitude" id="shopLatitude" value="<?php echo e($shop['latitude'] ?? ''); ?>"
        data-editable disabled>
    <input type="hidden" name="longitude" id="shopLongitude" value="<?php echo e($shop['longitude'] ?? ''); ?>"
        data-editable disabled>

    <div class="shop-management-grid">
        <div class="shop-management-main">
            <section class="owner-card shop-profile-card">
                <div class="card-head">
                    <div>
                        <h2>Shop Profile</h2>
                        <p class="card-note">Review your logo, permit, and location preview.</p>
                    </div>
                    <span class="status-badge <?php echo ownerStatusClass($permit_status); ?>">
                        <?php
                        $permit_icon = $permit_status === 'verified'
                            ? 'circle-check'
                            : ($permit_status === 'rejected' ? 'triangle-alert' : 'clock');
                        echo ownerIcon($permit_icon, 'icon-sm');
                        ?>
                        <?php echo e(ownerStatusLabel($permit_status)); ?>
                    </span>
                </div>

                <div class="shop-logo-upload-row">
                    <div class="shop-logo-frame" data-shop-logo-preview="profile">
                        <?php if (!empty($shop['shop_logo'])): ?>
                            <img src="<?php echo SHOP_LOGOS_URL . e($shop['shop_logo']); ?>" class="shop-logo-profile"
                                alt="<?php echo e($shop['shop_name']); ?> logo">
                        <?php else: ?>
                            <div class="shop-logo-empty"><?php echo ownerIcon('store', 'icon-xl'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="shop-logo-actions">
                        <label for="shop_logo" class="btn btn-navy upload-logo-btn is-disabled" data-edit-control>
                            <?php echo ownerIcon('upload', 'icon'); ?>
                            Upload Logo
                        </label>
                        <input id="shop_logo" class="file-input-hidden" type="file" name="shop_logo"
                            accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp" data-editable disabled>
                        <p class="card-note">Recommended: 500x500px, PNG or JPG</p>
                    </div>
                </div>

                <div class="shop-location-block">
                    <h3>Shop Location</h3>
                    <div class="location-display">
                        <?php echo ownerIcon('map-pin', 'icon'); ?>
                        <span>
                            <?php echo e($shop_location['primary']); ?>
                            <?php if ($shop_location['landmark'] !== ''): ?>
                                <small>
                                    <?php echo e($shop_location['landmark']); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <button type="button" class="location-map-button" id="setShopLocation" data-editable disabled>
                        Use My Current Location
                    </button>
                    <div class="location-map-preview owner-location-map" id="ownerShopMap" aria-label="Shop map picker">
                    </div>
                    <div class="location-coordinate-note" id="shopCoordinateNote">
                        <?php if (!empty($shop['latitude']) && !empty($shop['longitude'])): ?>
                            Pin saved at <?php echo e($shop['latitude']); ?>, <?php echo e($shop['longitude']); ?>
                        <?php else: ?>
                            No exact shop pin saved yet.
                        <?php endif; ?>
                    </div>
                    <p class="card-note">Use current location or click the map to place the shop pin manually. If permission was denied before, reset location permission in the browser address bar.</p>
                </div>

                <?php if (!empty($shop['business_permit_file'])): ?>
                    <div class="permit-preview-block">
                        <p class="card-note">Business Permit</p>
                        <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" class="permit-preview"
                            alt="Business permit">
                    </div>
                <?php endif; ?>
            </section>

            <section class="owner-card shop-services-card">
                <div class="card-head">
                    <div>
                        <h2>Services Offered</h2>
                        <p class="card-note">Select the services your shop provides.</p>
                    </div>
                </div>

                <div class="service-types-grid" data-owner-service-grid data-editable disabled>
                    <?php foreach ($allowed_service_types as $type): ?>
                        <?php $is_document_printing = $type === 'Document Printing'; ?>
                        <label class="service-type-check" data-service-type-choice="<?php echo e($type); ?>">
                            <input type="checkbox" name="service_types[]"
                                value="<?php echo e($type); ?>"
                                <?php echo ($is_document_printing || in_array($type, $shop_service_types, true)) ? 'checked' : ''; ?>
                                <?php echo $is_document_printing ? 'disabled data-required-service="true"' : 'data-editable disabled'; ?>>
                            <span><?php echo e($type); ?></span>
                            <?php if ($is_document_printing): ?>
                                <small class="service-type-lock-note">Default</small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($shop_service_types as $st): ?>
                        <?php if (!in_array($st, $allowed_service_types, true)): ?>
                            <label class="service-type-check service-type-check--custom" data-service-type-choice="<?php echo e($st); ?>" data-custom-service-type="true">
                                <input type="checkbox" name="service_types[]" value="<?php echo e($st); ?>" checked data-editable disabled>
                                <span><?php echo e($st); ?></span>
                                <button type="button" class="remove-custom-tag">&times;</button>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="service_types[]" value="Document Printing">
                <div class="service-types-custom-row" data-editable disabled>
                    <input type="text" id="customServiceType" placeholder="Add another service (e.g., Bookbinding)"
                        data-editable disabled>
                    <button type="button" id="addCustomServiceBtn" class="btn btn-soft" data-editable disabled>
                        <?php echo ownerIcon('plus', 'icon-sm'); ?> Add
                    </button>
                </div>
            </section>
        </div>

        <div class="shop-management-side">
            <section class="owner-card shop-details-card">
                <div class="card-head">
                    <div>
                        <h2>Shop Details</h2>
                        <p class="card-note">Click Edit before making profile changes.</p>
                    </div>
                    <button type="button" class="btn btn-soft shop-edit-button" id="editShopProfile">
                        <?php echo ownerIcon('edit-3', 'icon'); ?>
                        Edit
                    </button>
                </div>

                <div class="shop-details-fields">
                    <div class="field full">
                        <label for="shop_name">Shop Name</label>
                        <input id="shop_name" type="text" name="shop_name"
                            value="<?php echo e($shop['shop_name'] ?? ''); ?>" placeholder="Shop Name" required
                            data-editable disabled>
                    </div>

                    <div class="field full">
                        <label for="shop_address">Complete Shop Address</label>
                        <textarea id="shop_address" name="shop_address" rows="3"
                            placeholder="Example: Purok 2, Magsaysay Blvd, Brgy. Central, Calbayog City, Samar" required
                            data-editable disabled><?php echo e($shop['shop_address'] ?? ''); ?></textarea>
                        <span class="muted">Used for admin verification and official shop records.</span>
                    </div>

                    <div class="field full">
                        <label for="display_address">Street / Area Display</label>
                        <input id="display_address" type="text" name="display_address"
                            value="<?php echo e($shop['display_address'] ?? ''); ?>"
                            placeholder="Example: Magsaysay Blvd" data-editable disabled>
                        <span class="muted">This shorter location will be shown to customers.</span>
                    </div>

                    <div class="field full">
                        <label for="landmark">Nearby Landmark</label>
                        <input id="landmark" type="text" name="landmark"
                            value="<?php echo e($shop['landmark'] ?? ''); ?>"
                            placeholder="Example: Near Christ the King" data-editable disabled>
                        <span class="muted">Optional, but helpful for customer navigation.</span>
                    </div>

                    <div class="field full">
                        <label for="gcash_name">GCash Account Name</label>
                        <input id="gcash_name" type="text" name="gcash_name"
                            value="<?php echo e($payment_settings['gcash_account_name'] ?? ($shop['gcash_name'] ?? '')); ?>"
                            placeholder="Account name shown in GCash" required data-editable disabled>
                        <span class="muted">Shown to customers when they pay online through GCash.</span>
                    </div>

                    <div class="field full">
                        <label for="gcash_number">GCash Number</label>
                        <input id="gcash_number" type="text" name="gcash_number"
                            value="<?php echo e($payment_settings['gcash_number'] ?? ($shop['gcash_number'] ?? '')); ?>" placeholder="09XXXXXXXXX"
                            required data-editable disabled>
                    </div>

                    <div class="field full">
                        <label for="merchant_link">Optional GCash Merchant Link</label>
                        <input id="merchant_link" type="url" name="merchant_link"
                            value="<?php echo e($payment_settings['merchant_link'] ?? ''); ?>"
                            placeholder="https://..." data-editable disabled>
                        <span class="muted">Optional. Enter your official GCash merchant/payment link if available. Customers can still pay using your GCash QR code.</span>
                    </div>

                    <div class="field full">
                        <label for="gcash_qr_file">GCash QR Code</label>
                        <?php if (!empty($payment_qr_code)): ?>
                            <div class="shop-logo-panel">
                                <img src="<?php echo GCASH_QR_URL . e($payment_qr_code); ?>" class="shop-logo-preview"
                                    alt="GCash QR code">
                                <div>
                                    <h3>Current GCash QR</h3>
                                    <p class="card-note">Upload a new QR image to replace it.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input id="gcash_qr_file" type="file" name="gcash_qr_file"
                            accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp" data-editable disabled>
                        <span class="muted">Required before customers can pay online through GCash and submit proof.</span>
                    </div>

                    <div class="field full">
                        <label for="payment_instructions">Payment Instructions</label>
                        <textarea id="payment_instructions" name="payment_instructions" rows="3" required
                            placeholder="Tell customers to pay the exact total and upload their GCash receipt."
                            data-editable disabled><?php echo e($payment_instructions); ?></textarea>
                        <span class="muted">Saving payment details sends them to Super Admin for approval.</span>
                    </div>

                    <div class="field full">
                        <label>Payment Details Approval</label>
                        <span class="status-badge <?php echo ownerStatusClass($payment_approval_status === 'approved' ? 'verified' : ($payment_approval_status === 'rejected' ? 'rejected' : 'pending')); ?>">
                            <?php echo e(ucfirst($payment_approval_status)); ?>
                        </span>
                    </div>

                    <div class="field full">
                        <label for="business_permit_file">Business Permit</label>
                        <input id="business_permit_file" type="file" name="business_permit_file" data-editable disabled>
                        <?php if (!empty($shop['business_permit_file'])): ?>
                            <span class="muted">Current permit is on file. Upload a new one to replace it.</span>
                        <?php else: ?>
                            <span class="muted">Required when completing a new shop profile.</span>
                        <?php endif; ?>
                    </div>

                </div>
            </section>

            <section class="owner-card">
                <div class="card-head">
                    <div>
                        <h2>Operating Hours</h2>
                        <p class="card-note">Set shop availability schedule.</p>
                    </div>
                </div>

                <div class="shop-hours-grid">

                    <div class="shop-hours-day">
                        <div class="shop-hours-day-head">
                            <h3>Weekday Hours</h3>
                            <span class="shop-hours-badge shop-hours-badge--blue">Mon - Fri</span>
                        </div>

                        <div class="shop-hours-times">
                            <input type="time" name="weekday_open_time" value="<?php echo e($weekday_open_time); ?>"
                                data-editable disabled>
                            <span>to</span>
                            <input type="time" name="weekday_close_time" value="<?php echo e($weekday_close_time); ?>"
                                data-editable disabled>
                        </div>

                        <p class="shop-hours-preview">
                            <?php echo e($weekday_hours_label ?? 'Not set'); ?>
                        </p>
                    </div>

                    <div class="shop-hours-day">
                        <div class="shop-hours-day-head">
                            <h3>Weekend Hours</h3>
                            <span class="shop-hours-badge shop-hours-badge--green">Sat - Sun</span>
                        </div>

                        <div class="shop-hours-times">
                            <input type="time" name="weekend_open_time" value="<?php echo e($weekend_open_time); ?>"
                                data-editable disabled>
                            <span>to</span>
                            <input type="time" name="weekend_close_time" value="<?php echo e($weekend_close_time); ?>"
                                data-editable disabled>
                        </div>

                        <p class="shop-hours-preview">
                            <?php echo e($weekend_hours_label ?? 'Not set'); ?>
                        </p>
                    </div>

                </div>
            </section>

            <div class="shop-management-actions">
                <button type="submit" name="save_profile" class="btn btn-primary" id="saveShopProfile" disabled>
                    <?php echo ownerIcon('save', 'icon'); ?>
                    Save Shop Profile
                </button>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('shopProfileForm');
    if (!form) return;

    var rules = [
        { name: 'shop_name', test: function (v) { return v.trim() !== ''; }, msg: 'Please enter a shop name.' },
        { name: 'shop_address', test: function (v) { return v.trim() !== ''; }, msg: 'Please enter the complete shop address.' },
        { name: 'gcash_name', test: function (v) { return v.trim() !== ''; }, msg: 'Please enter the GCash account name.' },
        { name: 'gcash_number', test: function (v) { return /^[0-9+\-\s]{7,30}$/.test(v.trim()); }, msg: 'Please enter a valid GCash number (7-30 digits).' },
        { name: 'payment_instructions', test: function (v) { return v.trim() !== ''; }, msg: 'Please enter payment instructions.' }
    ];

    function validateField(rule) {
        var input = form.querySelector('[name="' + rule.name + '"]');
        if (!input) return true;
        var field = input.closest('.field');
        var errorEl = field ? field.querySelector('.field-error') : null;
        var valid = rule.test(input.value);

        if (field) {
            field.classList.toggle('has-error', !valid);
        }
        if (errorEl) {
            errorEl.textContent = valid ? '' : rule.msg;
        }
        return valid;
    }

    form.addEventListener('submit', function (event) {
        var firstError = null;
        rules.forEach(function (rule) {
            var valid = validateField(rule);
            if (!valid && !firstError) {
                firstError = form.querySelector('[name="' + rule.name + '"]');
            }
        });
        if (firstError) {
            event.preventDefault();
            firstError.focus();
        }
    });

    rules.forEach(function (rule) {
        var input = form.querySelector('[name="' + rule.name + '"]');
        if (!input) return;
        input.addEventListener('blur', function () { validateField(rule); });
        input.addEventListener('input', function () {
            var field = input.closest('.field');
            if (field && field.classList.contains('has-error')) {
                validateField(rule);
            }
        });
    });
})();
</script>

<script>
(function () {
    var addBtn = document.getElementById('addCustomServiceBtn');
    var customInput = document.getElementById('customServiceType');
    var serviceGrid = document.querySelector('[data-owner-service-grid]');
    var form = document.getElementById('shopProfileForm');
    if (!addBtn || !customInput || !serviceGrid) return;

    function escapeAttr(value) {
        return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function escapeHtml(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function addCustomService() {
        var value = customInput.value.trim();
        if (!value) return;

        var existing = document.querySelectorAll('input[name="service_types[]"]');
        for (var i = 0; i < existing.length; i++) {
            if (existing[i].value.toLowerCase() === value.toLowerCase()) {
                customInput.value = '';
                customInput.focus();
                return;
            }
        }

        var label = document.createElement('label');
        label.className = 'service-type-check service-type-check--custom';
        label.dataset.serviceTypeChoice = value;
        label.dataset.customServiceType = 'true';
        label.innerHTML = '<input type="checkbox" name="service_types[]" value="' + escapeAttr(value) + '" checked data-editable>' +
            '<span>' + escapeHtml(value) + '</span>' +
            '<button type="button" class="remove-custom-tag">&times;</button>';
        serviceGrid.appendChild(label);

        customInput.value = '';
        customInput.focus();
    }

    addBtn.addEventListener('click', addCustomService);
    customInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addCustomService(); }
    });

    serviceGrid.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-custom-tag')) {
            e.preventDefault();
            e.target.closest('.service-type-check').remove();
        }
    });
})();
</script>

<script src="assets/js/shopLocation.js?v=<?php echo filemtime(__DIR__ . '/assets/js/shopLocation.js'); ?>"></script>


<?php ownerLayoutEnd(); ?>
