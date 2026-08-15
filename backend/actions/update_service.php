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

$redirect = BASE_URL . "frontend/user/shop_owner/services.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_service'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'update_service', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many price record updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'update_service', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$service_id = intval($_POST['service_id'] ?? 0);
$paper_size = trim($_POST['paper_size'] ?? '');
$paper_type = trim($_POST['paper_type'] ?? '');
$print_type = trim($_POST['print_type'] ?? '');
$price_per_page = floatval($_POST['price_per_page'] ?? 0);

if ($service_id <= 0 || $paper_size === '' || $print_type === '' || $price_per_page <= 0) {
    setError("Please enter a valid size, variant, pricing basis, and price.");
    redirect($redirect);
}

$shop_sql = "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    setError("Shop profile not found.");
    redirect($redirect);
}

$shop_id = (int) $shop['shop_id'];
$update_sql = "UPDATE shop_services
               SET paper_size = ?, paper_type = ?, print_type = ?, price_per_page = ?
               WHERE service_id = ? AND shop_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "sssdii", $paper_size, $paper_type, $print_type, $price_per_page, $service_id, $shop_id);

if (mysqli_stmt_execute($update_stmt) && mysqli_stmt_affected_rows($update_stmt) >= 0) {
    syncDocumentPriceRecord($conn, $shop_id, $service_id);
    $log_detail = $paper_type !== '' ? "$paper_size / $paper_type / $print_type" : "$paper_size / $print_type";
    logActivity($conn, $owner_id, "Updated document pricing: $log_detail (" . number_format($price_per_page, 2) . ")", "Service Pricing");
    setToast("Price record updated.", "success");
} else {
    setError("Failed to update price record.");
}

redirect($redirect);
?>
