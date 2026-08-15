<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
validateCsrf();

$redirect = BASE_URL . "frontend/user/shop_owner/shop_profile.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_service_types'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'save_service_types', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many service type updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'save_service_types', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$owner_id = $_SESSION['user_id'];
$allowed_types = [
    'Document Printing',
    'Lamination',
    'Photo Printing',
    'Tarpaulin Printing',
    'ID Printing',
    'Invitation / Card Printing',
    'Photocopy',
    'Binding',
    'Scanning',
];

$selected = array_values(array_unique(array_intersect($_POST['service_types'] ?? [], $allowed_types)));
if (!in_array('Document Printing', $selected, true)) {
    array_unshift($selected, 'Document Printing');
}
$default_notes = [
    'Photocopy' => 'This service requires physical documents. Online request is unavailable.',
    'Binding' => 'Physical document submission is required. Please visit the shop.',
    'Scanning' => 'Original documents are required. Online request is unavailable.',
];

$shop_sql = "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    setError("Shop not found.");
    redirect($redirect);
}

$shop_id = (int) $shop['shop_id'];

mysqli_begin_transaction($conn);

$del_sql = "DELETE FROM shop_service_types WHERE shop_id = ?";
$del_stmt = mysqli_prepare($conn, $del_sql);
mysqli_stmt_bind_param($del_stmt, "i", $shop_id);
mysqli_stmt_execute($del_stmt);

if (!empty($selected)) {
    $ins_sql = "INSERT INTO shop_service_types (shop_id, service_type, service_offered, online_available, customer_note) VALUES (?, ?, 1, ?, ?)";
    $ins_stmt = mysqli_prepare($conn, $ins_sql);
    foreach ($selected as $type) {
        $online_available = in_array($type, ['Photocopy', 'Binding', 'Scanning'], true) ? 0 : 1;
        $customer_note = $default_notes[$type] ?? null;
        mysqli_stmt_bind_param($ins_stmt, "isis", $shop_id, $type, $online_available, $customer_note);
        mysqli_stmt_execute($ins_stmt);
    }
}

mysqli_commit($conn);

logActivity($conn, $owner_id, "Updated shop service types (" . count($selected) . " selected)", "Shop Profile");
setToast("Service types updated.", "success");
redirect($redirect);
?>
