<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/shop_guard.php";
require_once __DIR__ . "/includes/owner_layout.php";

$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

$owner_id = $_SESSION['user_id'];

$notif_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = (mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0);

$sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

ownerLayoutStart('status', 'Update Shop Status', 'Control whether customers can place orders with your print shop.', $notif_count, $shop, $owner_toast);
?>

<?php showMessage(); ?>

<div class="content-grid">
    <section class="owner-card">
        <div class="card-head">
            <h2>Current Availability</h2>
            <span class="status-badge <?php echo ownerStatusClass($shop['shop_status'] ?? 'pending'); ?>">
                <?php echo e(ownerStatusLabel($shop['shop_status'] ?? 'pending')); ?>
            </span>
        </div>
        <p class="muted">Use this setting to tell customers if your shop is ready for new print orders.</p>

        <form action="../../../backend/actions/update_shop_status.php" method="POST" class="form-grid" style="margin-top:20px;">
            <div class="field full">
                <label for="shop_status">Shop Status</label>
                <select id="shop_status" name="shop_status" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    <option value="available" <?php if (($shop['shop_status'] ?? '') == 'available') echo 'selected'; ?>>Available</option>
                    <option value="busy" <?php if (($shop['shop_status'] ?? '') == 'busy') echo 'selected'; ?>>Busy</option>
                    <option value="not_accepting" <?php if (($shop['shop_status'] ?? '') == 'not_accepting') echo 'selected'; ?>>Not Accepting Orders</option>
                </select>
            </div>
            <div class="field full">
                <button type="submit" name="update_status" class="btn btn-primary" <?php echo $owner_is_verified ? '' : 'disabled'; ?>><?php echo ownerIcon('refresh-cw', 'icon'); ?>Update Status</button>
            </div>
        </form>
    </section>

    <aside class="owner-card">
        <h2>Status Guide</h2>
        <div class="status-list">
            <div>
                <span class="status-badge status-success">Available</span>
                <p class="muted">Customers can place new orders normally.</p>
            </div>
            <div>
                <span class="status-badge status-info">Busy</span>
                <p class="muted">Your shop remains visible, but customers can see demand is high.</p>
            </div>
            <div>
                <span class="status-badge status-danger">Not Accepting Orders</span>
                <p class="muted">Customers are prevented from placing new orders.</p>
            </div>
        </div>
    </aside>
</div>

<?php ownerLayoutEnd(); ?>
