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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_service_pricing'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'update_service_pricing', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many price record updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'update_service_pricing', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$pricing_id = intval($_POST['pricing_id'] ?? 0);
$option_size = trim((string) ($_POST['option_size'] ?? ''));
$option_label = trim((string) ($_POST['option_label'] ?? ''));
$unit = trim((string) ($_POST['unit'] ?? ''));
$price = floatval($_POST['price'] ?? 0);

if ($pricing_id <= 0 || $option_label === '' || $price <= 0 || mb_strlen($option_label) > 150) {
    setError("Please enter valid pricing details.");
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

if (!$entry || !in_array(($entry['service_type'] ?? ''), ['Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)) {
    setError("Service pricing entry not found.");
    redirect($redirect);
}

$service_type = (string) $entry['service_type'];
if ($service_type === 'Lamination') {
    $option_size = null;
    $unit = '';
} elseif ($option_size === '' || $unit === '' || mb_strlen($option_size) > 50 || mb_strlen($unit) > 150) {
    setError("Please enter a valid size, material or paper type, print type, and price.");
    redirect($redirect);
}

$dup_sql = "SELECT id FROM shop_service_pricing
            WHERE shop_id = ?
            AND service_type = ?
            AND COALESCE(option_size, '') = COALESCE(?, '')
            AND option_label = ?
            AND COALESCE(unit, '') = COALESCE(?, '')
            AND id <> ?
            LIMIT 1";
$dup_stmt = mysqli_prepare($conn, $dup_sql);
mysqli_stmt_bind_param($dup_stmt, "issssi", $shop_id, $service_type, $option_size, $option_label, $unit, $pricing_id);
mysqli_stmt_execute($dup_stmt);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt))) {
    setError("A price record with these details already exists.");
    redirect($redirect);
}

$unit_db = $unit !== '' ? $unit : null;
$update_sql = "UPDATE shop_service_pricing
               SET option_size = ?, option_label = ?, unit = ?, price = ?
               WHERE id = ? AND shop_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "sssdii", $option_size, $option_label, $unit_db, $price, $pricing_id, $shop_id);

if (mysqli_stmt_execute($update_stmt)) {
    syncServicePricingRecord($conn, $shop_id, $pricing_id);
    $detail_label = in_array($service_type, ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true) ? "{$option_size} - {$option_label} - {$unit}" : $option_label;
    logActivity($conn, $owner_id, "Updated service pricing: {$service_type} - $detail_label (" . number_format($price, 2) . ")", "Service Pricing");
    setToast("Price record updated.", "success");
} else {
    setError("Failed to update price record.");
}

redirect($redirect);
?>
