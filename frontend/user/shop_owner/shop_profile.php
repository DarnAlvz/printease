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

ownerLayoutStart('profile', 'Shop Management', 'Manage your shop details, permit, and operating availability.', $notif_count, $shop);
?>

<?php showMessage(); ?>

<?php if ($permit_status === 'verified'): ?>
    <div class="alert-card success">
        <strong><?php echo ownerIcon('circle-check', 'icon'); ?> Permit Status: Verified</strong>
        <p>Your business permit is verified. You have full shop-owner access.</p>
    </div>
<?php elseif ($permit_status === 'pending'): ?>
    <div class="alert-card warning">
        <strong><?php echo ownerIcon('clock', 'icon'); ?> Permit Status: Pending Verification</strong>
        <p>Your business permit is submitted. Please wait for Super Admin approval.</p>
    </div>
<?php elseif ($permit_status === 'rejected'): ?>
    <div class="alert-card danger">
        <strong><?php echo ownerIcon('triangle-alert', 'icon'); ?> Permit Status: Rejected</strong>
        <p>Your business permit has been rejected. Contact the administrator or upload a new one.</p>
    </div>
<?php endif; ?>

<form action="../../../backend/actions/save_shop_profile.php" method="POST" enctype="multipart/form-data" class="shop-management-form is-locked" id="shopProfileForm">
    <div class="shop-management-grid">
        <section class="owner-card shop-profile-card">
            <div class="card-head">
                <div>
                    <h2>Shop Profile</h2>
                    <p class="card-note">Review your logo, permit, and location preview.</p>
                </div>
                <span class="status-badge <?php echo ownerStatusClass($permit_status); ?>">
                    <?php echo ownerIcon($permit_status === 'verified' ? 'circle-check' : 'clock', 'icon-sm'); ?>
                    <?php echo e(ownerStatusLabel($permit_status)); ?>
                </span>
            </div>

            <div class="shop-logo-upload-row">
                <div class="shop-logo-frame">
                    <?php if (!empty($shop['shop_logo'])): ?>
                        <img src="<?php echo SHOP_LOGOS_URL . e($shop['shop_logo']); ?>" class="shop-logo-profile" alt="<?php echo e($shop['shop_name']); ?> logo">
                    <?php else: ?>
                        <div class="shop-logo-empty"><?php echo ownerIcon('store', 'icon-xl'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="shop-logo-actions">
                    <label for="shop_logo" class="btn btn-navy upload-logo-btn is-disabled" data-edit-control>
                        <?php echo ownerIcon('upload', 'icon'); ?>
                        Upload Logo
                    </label>
                    <input id="shop_logo" class="file-input-hidden" type="file" name="shop_logo" accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp" data-editable disabled>
                    <p class="card-note">Recommended: 500x500px, PNG or JPG</p>
                </div>
            </div>

            <div class="shop-location-block">
                <h3>Shop Location</h3>
                <div class="location-display">
                    <?php echo ownerIcon('map-pin', 'icon'); ?>
                    <span><?php echo e($shop['shop_address'] ?? 'Enter your shop address'); ?></span>
                </div>
                <button type="button" class="location-map-button" data-editable disabled>Set Location on Map</button>
                <div class="location-map-preview">
                    <?php echo ownerIcon('map-pin', 'location-pin-icon'); ?>
                    <strong><?php echo e($shop['shop_name'] ?? 'Print Shop'); ?></strong>
                    <span><?php echo e($shop['shop_address'] ?? 'Shop location'); ?></span>
                </div>
            </div>

            <?php if (!empty($shop['business_permit_file'])): ?>
                <div class="permit-preview-block">
                    <p class="card-note">Business Permit</p>
                    <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" class="permit-preview" alt="Business permit">
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
                        <input id="shop_name" type="text" name="shop_name" value="<?php echo e($shop['shop_name'] ?? ''); ?>" placeholder="Shop Name" required data-editable disabled>
                    </div>

                    <div class="field full">
                        <label for="shop_address">Address</label>
                        <textarea id="shop_address" name="shop_address" placeholder="Shop Address" required data-editable disabled><?php echo e($shop['shop_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="field full">
                        <label for="contact_number">Phone</label>
                        <input id="contact_number" type="text" name="contact_number" value="<?php echo e($shop['contact_number'] ?? ''); ?>" placeholder="+63 912 345 6789" required data-editable disabled>
                    </div>

                    <div class="field full">
                        <label for="shop_status">Shop Status</label>
                        <select id="shop_status" name="shop_status" required data-editable disabled>
                            <option value="available" <?php if (($shop['shop_status'] ?? '') == 'available') echo 'selected'; ?>>Available</option>
                            <option value="busy" <?php if (($shop['shop_status'] ?? '') == 'busy') echo 'selected'; ?>>Busy</option>
                            <option value="not_accepting" <?php if (($shop['shop_status'] ?? '') == 'not_accepting') echo 'selected'; ?>>Not Accepting Orders</option>
                        </select>
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

            <section class="owner-card operating-hours-card">
                <h2>Operating Hours</h2>
                <div class="hours-list">
                    <div>
                        <span>Weekday Hours</span>
                        <strong>8:00 AM - 6:00 PM</strong>
                    </div>
                    <div>
                        <span>Weekend Hours</span>
                        <strong>9:00 AM - 5:00 PM</strong>
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
        const form = document.getElementById('shopProfileForm');
        const editButton = document.getElementById('editShopProfile');
        const saveButton = document.getElementById('saveShopProfile');
        const editables = form ? form.querySelectorAll('[data-editable]') : [];
        const editControls = form ? form.querySelectorAll('[data-edit-control]') : [];

        if (!form || !editButton || !saveButton) {
            return;
        }

        editButton.addEventListener('click', function () {
            form.classList.remove('is-locked');
            form.classList.add('is-editing');
            editables.forEach(function (field) {
                field.disabled = false;
            });
            editControls.forEach(function (control) {
                control.classList.remove('is-disabled');
            });
            saveButton.disabled = false;
            editButton.disabled = true;
            editButton.classList.add('is-disabled');
        });
    })();
</script>

<?php ownerLayoutEnd(); ?>
