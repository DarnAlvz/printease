<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/price_records.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
validateCsrf();

$redirect = BASE_URL . "frontend/user/shop_owner/services.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['toggle_service_pricing'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'toggle_service_pricing', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many price record updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'toggle_service_pricing', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$pricing_id = intval($_POST['pricing_id'] ?? 0);

if ($pricing_id <= 0) {
    setError("Invalid service pricing entry.");
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

$fetch = "SELECT id, is_available, service_type FROM shop_service_pricing WHERE id = ? AND shop_id = ? LIMIT 1";
$fetch_stmt = mysqli_prepare($conn, $fetch);
mysqli_stmt_bind_param($fetch_stmt, "ii", $pricing_id, $shop_id);
mysqli_stmt_execute($fetch_stmt);
$entry = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));

if (!$entry || !in_array(($entry['service_type'] ?? ''), ['Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)) {
    setError("Service pricing entry not found.");
    redirect($redirect);
}

$new_status = $entry['is_available'] ? 0 : 1;
$update_sql = "UPDATE shop_service_pricing SET is_available = ? WHERE id = ? AND shop_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "iii", $new_status, $pricing_id, $shop_id);

if (mysqli_stmt_execute($update_stmt)) {
    syncServicePricingRecord($conn, $shop_id, $pricing_id);
    $label = $new_status ? 'enabled' : 'disabled';
    logActivity($conn, $owner_id, "Service pricing $label (ID: $pricing_id)", "Service Pricing");
    setToast("Service price $label.", "success");
} else {
    setError("Failed to update service price status.");
}

redirect($redirect);
?>
