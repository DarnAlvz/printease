<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

$owner_id = $_SESSION['user_id'];

$shop_sql = "SELECT shop_id FROM print_shops WHERE owner_id=? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));
$shop_id = $shop['shop_id'];

$services = mysqli_query($conn, "SELECT * FROM shop_services WHERE shop_id=$shop_id ORDER BY created_at DESC");
?>

<h2>Manage Services</h2>
<?php showMessage(); ?>

<form action="../../../backend/actions/add_service.php" method="POST">
    <input type="text" name="paper_size" placeholder="Paper Size ex: A4" required>
    <input type="text" name="paper_type" placeholder="Paper Type ex: Bond Paper" required>
    <input type="text" name="print_type" placeholder="Print Type ex: Colored" required>
    <input type="number" step="0.01" name="price_per_page" placeholder="Price per page" required>
    <button type="submit" name="add_service">Add Service</button>
</form>

<hr>

<?php while ($service = mysqli_fetch_assoc($services)): ?>
    <p>
        <?php echo e($service['paper_size']); ?> -
        <?php echo e($service['paper_type']); ?> -
        <?php echo e($service['print_type']); ?> -
        ₱<?php echo e($service['price_per_page']); ?>
    </p>
<?php endwhile; ?>
