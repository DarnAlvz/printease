<?php

require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");
require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/shop_guard.php";
requireVerifiedStatus($conn);
requireVerifiedShop($conn);


$owner_id = $_SESSION['user_id'];

$sql = "SELECT shop_status FROM print_shops WHERE owner_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$shop = mysqli_fetch_assoc($result);
?>

<h2>Update Shop Status</h2>

<form action="../../../backend/actions/update_shop_status.php" method="POST">
    <select name="shop_status" required>
        <option value="available" <?php if($shop['shop_status']=='available') echo 'selected'; ?>>Available</option>
        <option value="busy" <?php if($shop['shop_status']=='busy') echo 'selected'; ?>>Busy</option>
        <option value="not_accepting" <?php if($shop['shop_status']=='not_accepting') echo 'selected'; ?>>Not Accepting Orders</option>
    </select>

    <button type="submit" name="update_status">Update</button>
</form>

<a href="dashboard.php">Back to Dashboard</a>
