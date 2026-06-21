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

$permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : 'incomplete';
$shop_status = $shop['shop_status'] ?? 'available';
$shop_location = ownerShopLocation($shop);
$shop_status_details = [
    'available' => [
        'label' => 'Accepting Orders',
        'description' => 'Customers can place orders and checkout normally.',
        'icon' => 'circle-check',
    ],
    'busy' => [
        'label' => 'Busy',
        'description' => 'Customers can still order, but they will know demand is high.',
        'icon' => 'clock-3',
    ],
    'not_accepting' => [
        'label' => 'Not Accepting Orders',
        'description' => 'Customers cannot place new orders until your status changes.',
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

<form action="../../../backend/actions/save_shop_profile.php" method="POST" enctype="multipart/form-data"
    class="shop-management-form is-locked" id="shopProfileForm">
    <input type="hidden" name="latitude" id="shopLatitude" value="<?php echo e($shop['latitude'] ?? ''); ?>"
        data-editable disabled>
    <input type="hidden" name="longitude" id="shopLongitude" value="<?php echo e($shop['longitude'] ?? ''); ?>"
        data-editable disabled>

    <div class="shop-management-grid">
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
                    Set Location on Map
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
            </div>

            <?php if (!empty($shop['business_permit_file'])): ?>
                <div class="permit-preview-block">
                    <p class="card-note">Business Permit</p>
                    <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" class="permit-preview"
                        alt="Business permit">
                </div>
            <?php endif; ?>
        </section>

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
                            value="<?php echo e($shop['gcash_name'] ?? ''); ?>"
                            placeholder="Account name shown in GCash" data-editable disabled>
                        <span class="muted">Shown to customers on the manual GCash payment page.</span>
                    </div>

                    <div class="field full">
                        <label for="gcash_number">GCash Number</label>
                        <input id="gcash_number" type="text" name="gcash_number"
                            value="<?php echo e($shop['gcash_number'] ?? ''); ?>" placeholder="09XXXXXXXXX"
                            data-editable disabled>
                    </div>

                    <div class="field full">
                        <label for="gcash_qr_file">GCash QR Code</label>
                        <?php if (!empty($shop['gcash_qr_file'])): ?>
                            <div class="shop-logo-panel">
                                <img src="<?php echo GCASH_QR_URL . e($shop['gcash_qr_file']); ?>" class="shop-logo-preview"
                                    alt="GCash QR code">
                                <div>
                                    <h3>Current GCash QR</h3>
                                    <p class="card-note">Upload a new QR image to replace it.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input id="gcash_qr_file" type="file" name="gcash_qr_file"
                            accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp" data-editable disabled>
                        <span class="muted">Required before customers can submit manual GCash payments.</span>
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

            <section class="bg-white rounded-2xl shadow-md p-5 border border-gray-100">

                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Operating Hours</h2>
                        <p class="text-sm text-gray-500">Set shop availability schedule</p>
                    </div>
                </div>

                <!-- GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- WEEKDAY -->
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-blue-700">Weekday Hours</h3>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                                Mon - Fri
                            </span>
                        </div>

                        <div class="flex gap-2 items-center">
                            <input type="time" name="weekday_open_time" value="<?php echo e($weekday_open_time); ?>"
                                data-editable disabled
                                class="w-25 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-blue-300">

                            <span class="text-gray-500 text-sm">to</span>

                            <input type="time" name="weekday_close_time" value="<?php echo e($weekday_close_time); ?>"
                                data-editable disabled
                                class="w-25 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-blue-300">
                        </div>

                        <p class="text-m text-gray-500 mt-2">
                            <?php echo e($weekday_hours_label ?? 'Not set'); ?>
                        </p>
                    </div>

                    <!-- WEEKEND -->
                    <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-green-700">Weekend Hours</h3>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                Sat - Sun
                            </span>
                        </div>

                        <div class="flex gap-2 items-center">
                            <input type="time" name="weekend_open_time" value="<?php echo e($weekend_open_time); ?>"
                                data-editable disabled
                                class="w-25 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-green-300">

                            <span class="text-gray-500 text-sm">to</span>

                            <input type="time" name="weekend_close_time" value="<?php echo e($weekend_close_time); ?>"
                                data-editable disabled
                                class="w-25 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-green-300">
                        </div>

                        <p class="text-m text-gray-500 mt-2">
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

<script src="assets/js/shopLocation.js"></script>


<?php ownerLayoutEnd(); ?>
