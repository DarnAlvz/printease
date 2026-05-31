<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin'"))['total'];
$total_shop_owners = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'shop_owner'"))['total'];
$total_shops = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM print_shops"))['total'];
$verified_shops = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'verified'"))['total'];
$pending_shops = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'pending'"))['total'];
$rejected_shops = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'rejected'"))['total'];
?>

<h1>System Reports</h1>

<ul>
    <li>Total Users: <?php echo $total_users; ?></li>
    <li>Total Shop Owners: <?php echo $total_shop_owners; ?></li>
    <li>Total Registered Shops: <?php echo $total_shops; ?></li>
    <li>Verified Shops: <?php echo $verified_shops; ?></li>
    <li>Pending Permits: <?php echo $pending_shops; ?></li>
    <li>Rejected Permits: <?php echo $rejected_shops; ?></li>
</ul>

<a href="dashboard.php">Back to Dashboard</a>
