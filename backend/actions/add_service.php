<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

validateCsrf();

if (!isset($_POST['add_service'])) {
    redirect(BASE_URL . "frontend/user/shop_owner/services.php");
}

$owner_id = $_SESSION['user_id'];
$paper_size = trim($_POST['paper_size']);
$paper_type = trim($_POST['paper_type']);
$print_type = trim($_POST['print_type']);
$price_per_page = floatval($_POST['price_per_page']);

if ($paper_size === "" || $paper_type === "" || $print_type === "" || $price_per_page <= 0) {
    setError("Please enter valid service details.");
    redirect(BASE_URL . "frontend/user/shop_owner/services.php");
}

$shop_sql = "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    setError("Shop profile not found.");
    redirect(BASE_URL . "frontend/user/shop_owner/services.php");
}

$shop_id = $shop['shop_id'];

$sql = "INSERT INTO shop_services 
        (shop_id, paper_size, paper_type, print_type, price_per_page) 
        VALUES (?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "isssd", $shop_id, $paper_size, $paper_type, $print_type, $price_per_page);

if (mysqli_stmt_execute($stmt)) {
    setMessage("Service added successfully.");
} else {
    setError("Failed to add service.");
}

redirect(BASE_URL . "frontend/user/shop_owner/services.php");
?>
