<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("shop_owner");
validateCsrf();

$redirect = BASE_URL . "frontend/user/shop_owner/shop_profile.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_service_types'])) {
    redirect($redirect);
}

$owner_id = $_SESSION['user_id'];
$allowed_types = [
    'Document Printing',
    'Photocopy',
    'Photo Printing',
    'Tarpaulin Printing',
    'Lamination',
    'Binding',
    'Scanning',
    'ID Printing',
    'Invitation / Card Printing',
];

$selected = array_values(array_unique(array_intersect($_POST['service_types'] ?? [], $allowed_types)));
if (!in_array('Document Printing', $selected, true)) {
    array_unshift($selected, 'Document Printing');
}

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
    $ins_sql = "INSERT INTO shop_service_types (shop_id, service_type) VALUES (?, ?)";
    $ins_stmt = mysqli_prepare($conn, $ins_sql);
    foreach ($selected as $type) {
        mysqli_stmt_bind_param($ins_stmt, "is", $shop_id, $type);
        mysqli_stmt_execute($ins_stmt);
    }
}

mysqli_commit($conn);

logActivity($conn, $owner_id, "Updated shop service types (" . count($selected) . " selected)", "Shop Profile");
setToast("Service types updated.", "success");
redirect($redirect);
?>
