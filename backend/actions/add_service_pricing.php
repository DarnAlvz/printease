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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_service_pricing'])) {
    redirect($redirect);
}

$owner_id = $_SESSION['user_id'];
$service_type = trim($_POST['service_type'] ?? '');
$option_label = trim((string) ($_POST['option_label'] ?? $_POST['size_name'] ?? ''));
$unit = trim((string) ($_POST['unit'] ?? $_POST['pricing_basis'] ?? ''));
$price = floatval($_POST['price'] ?? 0);

$allowed_types = [
    'Photocopy',
    'Photo Printing',
    'Tarpaulin Printing',
    'Lamination',
    'Binding',
    'Scanning',
    'ID Printing',
    'Invitation / Card Printing',
];

$allowed_units = ['per page', 'per piece', 'per sheet', 'per sq ft', 'per set', 'flat rate'];
if ($unit !== '' && !in_array($unit, $allowed_units, true)) {
    $unit = '';
}

if ($service_type === '' || $option_label === '' || $price <= 0 || !in_array($service_type, $allowed_types, true)) {
    setError("Please fill in all fields with valid values.");
    redirect($redirect);
}

if (mb_strlen($option_label) > 150) {
    setError("Size/variant must be 150 characters or fewer.");
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
$st_sql = "SELECT service_type FROM shop_service_types WHERE shop_id = ?";
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

$dup_check = "SELECT id FROM shop_service_pricing WHERE shop_id = ? AND service_type = ? AND option_label = ? LIMIT 1";
$dup_stmt = mysqli_prepare($conn, $dup_check);
mysqli_stmt_bind_param($dup_stmt, "iss", $shop_id, $service_type, $option_label);
mysqli_stmt_execute($dup_stmt);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt))) {
    setError("A price record with this size/variant already exists.");
    redirect($redirect);
}

$unit_db = $unit !== '' ? $unit : null;
$sql = "INSERT INTO shop_service_pricing (shop_id, service_type, option_label, unit, price) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "isssd", $shop_id, $service_type, $option_label, $unit_db, $price);

if (mysqli_stmt_execute($stmt)) {
    syncServicePricingRecord($conn, $shop_id, (int) mysqli_insert_id($conn), priceRecordAdvancedFieldsFromInput($_POST));
    $unit_display = $unit !== '' ? ' (' . $unit . ')' : '';
    logActivity($conn, $owner_id, "Added service pricing: $service_type - $option_label{$unit_display} (₱" . number_format($price, 2) . ")", "Service Pricing");
    setToast("Price record added.", "success");
} else {
    setError("Failed to add price record.");
}

redirect($redirect);
?>
