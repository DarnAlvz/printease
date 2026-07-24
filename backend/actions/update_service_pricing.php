<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/price_records.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);
validateCsrf();

$redirect = BASE_URL . "frontend/user/shop_owner/services.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_service_pricing'])) {
    redirect($redirect);
}

$owner_id = $_SESSION['user_id'];
$pricing_id = intval($_POST['pricing_id'] ?? 0);
$option_label = trim($_POST['option_label'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$price = floatval($_POST['price'] ?? 0);

$allowed_units = ['per page', 'per piece', 'per sheet', 'per sq ft', 'per set', 'flat rate'];
if ($unit !== '' && !in_array($unit, $allowed_units, true)) {
    $unit = '';
}

if ($pricing_id <= 0 || $option_label === '' || $price <= 0 || mb_strlen($option_label) > 150) {
    setError("Please enter a valid size/variant, pricing basis, and price.");
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
$fetch_sql = "SELECT id, service_type FROM shop_service_pricing WHERE id = ? AND shop_id = ? LIMIT 1";
$fetch_stmt = mysqli_prepare($conn, $fetch_sql);
mysqli_stmt_bind_param($fetch_stmt, "ii", $pricing_id, $shop_id);
mysqli_stmt_execute($fetch_stmt);
$entry = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));

if (!$entry || ($entry['service_type'] ?? '') === 'Document Printing') {
    setError("Service pricing entry not found.");
    redirect($redirect);
}

$dup_sql = "SELECT id FROM shop_service_pricing WHERE shop_id = ? AND service_type = ? AND option_label = ? AND id <> ? LIMIT 1";
$dup_stmt = mysqli_prepare($conn, $dup_sql);
mysqli_stmt_bind_param($dup_stmt, "issi", $shop_id, $entry['service_type'], $option_label, $pricing_id);
mysqli_stmt_execute($dup_stmt);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt))) {
    setError("A price record with this size/variant already exists.");
    redirect($redirect);
}

$unit_db = $unit !== '' ? $unit : null;
$update_sql = "UPDATE shop_service_pricing SET option_label = ?, unit = ?, price = ? WHERE id = ? AND shop_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "ssdii", $option_label, $unit_db, $price, $pricing_id, $shop_id);

if (mysqli_stmt_execute($update_stmt)) {
    syncServicePricingRecord($conn, $shop_id, $pricing_id);
    logActivity($conn, $owner_id, "Updated service pricing: {$entry['service_type']} - $option_label (" . number_format($price, 2) . ")", "Service Pricing");
    setToast("Price record updated.", "success");
} else {
    setError("Failed to update price record.");
}

redirect($redirect);
?>
