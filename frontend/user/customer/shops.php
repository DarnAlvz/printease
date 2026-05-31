<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);



$filter = $_GET['status'] ?? 'all';

if ($filter !== 'all') {
    $sql = "SELECT * FROM print_shops 
            WHERE permit_status='verified' AND shop_status=?
            ORDER BY shop_name ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT * FROM print_shops 
            WHERE permit_status='verified'
            ORDER BY shop_name ASC";
    $result = mysqli_query($conn, $sql);
}
?>

<h2>Print Shops</h2>

<a href="shops.php?status=all">All</a> |
<a href="shops.php?status=available">Available</a> |
<a href="shops.php?status=busy">Busy</a> |
<a href="shops.php?status=not_accepting">Not Accepting</a>

<br><br>

<?php while ($shop = mysqli_fetch_assoc($result)): ?>
    <div style="border:1px solid #ccc; padding:15px; margin-bottom:10px;">
        <h3><?php echo e($shop['shop_name']); ?></h3>
        <p><?php echo e($shop['shop_address']); ?></p>
        <p>Status: <?php echo e(ucfirst(str_replace('_', ' ', $shop['shop_status']))); ?></p>

        <?php if ($shop['shop_status'] === 'available'): ?>
            <a href="place_order.php?shop_id=<?php echo e($shop['shop_id']); ?>">Place Order</a>
        <?php else: ?>
            <button disabled>Currently Unavailable</button>
        <?php endif; ?>
    </div>
<?php endwhile; ?>
