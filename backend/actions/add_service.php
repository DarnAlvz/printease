<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/price_records.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

validateCsrf();

if (!isset($_POST['add_service'])) {
    redirect(BASE_URL . "frontend/user/shop_owner/services.php");
}

$rate_guard = rateLimitGuardRequest($conn, 'add_service', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many price record additions. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect(BASE_URL . "frontend/user/shop_owner/services.php");
}
rateLimitRecordRequest($conn, 'add_service', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$paper_size = trim((string) ($_POST['paper_size'] ?? $_POST['size_name'] ?? ''));
$paper_type = trim((string) ($_POST['paper_type'] ?? ''));
$print_type = trim((string) ($_POST['print_type'] ?? $_POST['variant'] ?? ''));
$price_per_page = floatval($_POST['price_per_page'] ?? $_POST['price'] ?? 0);

if ($paper_size === "" || $paper_type === "" || $print_type === "" || $price_per_page <= 0) {
    setError("Please enter a valid size, paper type, print type, and price.");
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

$st_sql = "INSERT IGNORE INTO shop_service_types (shop_id, service_type) VALUES (?, 'Document Printing')";
$st_stmt = mysqli_prepare($conn, $st_sql);
if ($st_stmt) {
    mysqli_stmt_bind_param($st_stmt, "i", $shop_id);
    mysqli_stmt_execute($st_stmt);
}

$sql = "INSERT INTO shop_services 
        (shop_id, paper_size, paper_type, print_type, price_per_page) 
        VALUES (?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "isssd", $shop_id, $paper_size, $paper_type, $print_type, $price_per_page);

if (mysqli_stmt_execute($stmt)) {
    syncDocumentPriceRecord($conn, $shop_id, (int) mysqli_insert_id($conn));
    setMessage("Price record added successfully.");
} else {
    setError("Failed to add price record.");
}

redirect(BASE_URL . "frontend/user/shop_owner/services.php");
?>
