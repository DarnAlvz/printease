<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("super_admin");

function permitStatusLabel($status)
{
    return match ((string) $status) {
        'verified' => 'approved',
        'rejected' => 'rejected',
        'disabled' => 'disabled',
        default => 'pending',
    };
}

$redirect_url = BASE_URL . "frontend/user/superadmin/manage_print_shops.php";
$return_to = trim((string) ($_POST['return_to'] ?? ''));
if ($return_to === 'dashboard.php') {
    $redirect_url = BASE_URL . "frontend/user/superadmin/dashboard.php";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setError("Invalid request method for permit status updates.");
    redirect($redirect_url);
}

validateCsrf();

$shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status'] ?? ''));
$allowed_status = ['pending', 'verified', 'rejected', 'disabled'];

if (!$shop_id || !in_array($status, $allowed_status, true)) {
    setError("Invalid permit status update.");
    redirect($redirect_url);
}

$owner_sql = "SELECT owner_id, shop_name, permit_status FROM print_shops WHERE shop_id = ?";
$owner_stmt = mysqli_prepare($conn, $owner_sql);
mysqli_stmt_bind_param($owner_stmt, "i", $shop_id);
mysqli_stmt_execute($owner_stmt);
$owner_result = mysqli_stmt_get_result($owner_stmt);
$shop = mysqli_fetch_assoc($owner_result);

if (!$shop) {
    setError("Shop not found.");
    redirect($redirect_url);
}

$sql = "UPDATE print_shops SET permit_status = ? WHERE shop_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    setError("Database error: Failed to prepare permit update.");
    redirect($redirect_url);
}

mysqli_stmt_bind_param($stmt, "si", $status, $shop_id);

if (mysqli_stmt_execute($stmt)) {
    $affected = mysqli_affected_rows($conn);
    $label = permitStatusLabel($status);
    $shop_name = (string) $shop['shop_name'];

    $status_message = "Your business permit for \"" . $shop_name . "\" has been " . $label . ".";
    if ($status === 'disabled') {
        $status_message = "Your print shop \"" . $shop_name . "\" has been disabled by the administrator. Please contact support for assistance.";
    }

    sendNotification($conn, (int) $shop['owner_id'], $status_message, [
        'type' => 'permit_status',
        'title' => 'Permit status updated',
        'target_url' => BASE_URL . 'frontend/user/shop_owner/shop_profile.php',
        'metadata' => ['shop_id' => $shop_id, 'status' => $status],
    ]);
    logActivity($conn, $_SESSION['user_id'], "Updated permit #$shop_id (shop: {$shop_name}) to $status", "Permit Management", [
        'target_type' => 'shop',
        'target_id' => $shop_id,
        'old_value' => [
            'permit_status' => $shop['permit_status'] ?? null,
        ],
        'new_value' => [
            'permit_status' => $status,
            'shop_name' => $shop_name,
            'owner_id' => (int) $shop['owner_id'],
        ],
    ]);

    if ($affected > 0) {
        setToast("Permit for \"" . $shop_name . "\" has been set to: " . ucfirst($label) . ".", "success");
    } else {
        setToast("Permit status was already set to: " . ucfirst($label) . ".", "info");
    }
} else {
    setError("Database error: Failed to update permit status.");
}

redirect($redirect_url);
?>
