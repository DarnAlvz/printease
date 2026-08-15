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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_service_pricing'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'add_service_pricing', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many price record additions. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'add_service_pricing', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$service_type = trim((string) ($_POST['service_type'] ?? ''));
$size_name = trim((string) ($_POST['size_name'] ?? $_POST['option_size'] ?? ''));
$paper_type = trim((string) ($_POST['paper_type'] ?? $_POST['option_label'] ?? ''));
$print_type = trim((string) ($_POST['variant'] ?? $_POST['unit'] ?? ''));
$price = floatval($_POST['price'] ?? 0);

$allowed_types = ['Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'];

$option_size = null;
$option_label = $size_name;
$unit = '';

if (in_array($service_type, ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)) {
    $option_size = $size_name;
    $option_label = $paper_type;
    $unit = $print_type;
}

if (!in_array($service_type, $allowed_types, true) || $option_label === '' || $price <= 0) {
    setError("Please fill in all fields with valid values.");
    redirect($redirect);
}

if (in_array($service_type, ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true) && ($option_size === '' || $unit === '')) {
    setError("Please enter a valid size, material or paper type, print type, and price.");
    redirect($redirect);
}

if (($option_size !== null && mb_strlen($option_size) > 50) || mb_strlen($option_label) > 150 || mb_strlen($unit) > 150) {
    setError("Service pricing details are too long.");
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

$selected_types = [];
$st_sql = "SELECT service_type FROM shop_service_types WHERE shop_id = ? AND service_offered = 1 AND online_available = 1";
$st_stmt = mysqli_prepare($conn, $st_sql);
mysqli_stmt_bind_param($st_stmt, "i", $shop_id);
mysqli_stmt_execute($st_stmt);
$st_result = mysqli_stmt_get_result($st_stmt);
while ($st_row = mysqli_fetch_assoc($st_result)) {
    $selected_types[] = (string) ($st_row['service_type'] ?? '');
}
$allowed_selected_types = array_values(array_intersect($selected_types, $allowed_types));

if (!in_array($service_type, $allowed_selected_types, true)) {
    setError("Please select a service type enabled in your shop profile.");
    redirect($redirect);
}

$dup_check = "SELECT id FROM shop_service_pricing
              WHERE shop_id = ?
              AND service_type = ?
              AND COALESCE(option_size, '') = COALESCE(?, '')
              AND option_label = ?
              AND COALESCE(unit, '') = COALESCE(?, '')
              LIMIT 1";
$dup_stmt = mysqli_prepare($conn, $dup_check);
mysqli_stmt_bind_param($dup_stmt, "issss", $shop_id, $service_type, $option_size, $option_label, $unit);
mysqli_stmt_execute($dup_stmt);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt))) {
    setError("A price record with these details already exists.");
    redirect($redirect);
}

$unit_db = $unit !== '' ? $unit : null;
$sql = "INSERT INTO shop_service_pricing (shop_id, service_type, option_size, option_label, unit, price)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "issssd", $shop_id, $service_type, $option_size, $option_label, $unit_db, $price);

if (mysqli_stmt_execute($stmt)) {
    syncServicePricingRecord($conn, $shop_id, (int) mysqli_insert_id($conn));
    $detail_label = in_array($service_type, ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true) ? "{$option_size} - {$option_label} - {$unit}" : $option_label;
    logActivity($conn, $owner_id, "Added service pricing: $service_type - $detail_label (" . number_format($price, 2) . ")", "Service Pricing");
    setToast("Price record added.", "success");
} else {
    setError("Failed to add price record.");
}

redirect($redirect);
?>
