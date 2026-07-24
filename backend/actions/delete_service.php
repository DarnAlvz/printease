<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/price_records.php";

checkRole("shop_owner");
validateCsrf();

$redirect = BASE_URL . "frontend/user/shop_owner/services.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_service'])) {
    redirect($redirect);
}

$owner_id = $_SESSION['user_id'];
$service_id = intval($_POST['service_id'] ?? 0);

if ($service_id <= 0) {
    setError("Invalid document pricing entry.");
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
$fetch_sql = "SELECT service_id, paper_size, paper_type, print_type FROM shop_services WHERE service_id = ? AND shop_id = ? LIMIT 1";
$fetch_stmt = mysqli_prepare($conn, $fetch_sql);
mysqli_stmt_bind_param($fetch_stmt, "ii", $service_id, $shop_id);
mysqli_stmt_execute($fetch_stmt);
$service = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));

if (!$service) {
    setError("Document pricing entry not found.");
    redirect($redirect);
}

$order_sql = "SELECT COUNT(*) AS total FROM orders WHERE service_id = ?";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "i", $service_id);
mysqli_stmt_execute($order_stmt);
$order_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt))['total'] ?? 0);

if ($order_count > 0) {
    setError("This document price is used by existing orders. Disable it instead of deleting.");
    redirect($redirect);
}

$delete_sql = "DELETE FROM shop_services WHERE service_id = ? AND shop_id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $service_id, $shop_id);

if (mysqli_stmt_execute($delete_stmt)) {
    deletePriceRecordForLegacy($conn, $shop_id, 'shop_services', $service_id);
    logActivity($conn, $owner_id, "Deleted document pricing: {$service['paper_size']} / {$service['paper_type']} / {$service['print_type']}", "Service Pricing");
    setToast("Document price deleted.", "success");
} else {
    setError("Failed to delete document price.");
}

redirect($redirect);
?>
