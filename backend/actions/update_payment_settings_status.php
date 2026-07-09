<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("super_admin");

$redirect_url = BASE_URL . "frontend/user/superadmin/manage_print_shops.php#payment-settings-review";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setError("Invalid request method for payment settings updates.");
    redirect($redirect_url);
}

$csrf_token = (string) ($_POST['csrf_token'] ?? '');
if (
    empty($_SESSION['admin_payment_settings_csrf'])
    || $csrf_token === ''
    || !hash_equals($_SESSION['admin_payment_settings_csrf'], $csrf_token)
) {
    setError("Security check failed. Please refresh the page and try again.");
    redirect($redirect_url);
}

$settings_id = filter_input(INPUT_POST, 'settings_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status'] ?? ''));
$allowed_status = ['approved', 'rejected', 'pending'];

if (!$settings_id || !in_array($status, $allowed_status, true)) {
    setError("Invalid payment settings status update.");
    redirect($redirect_url);
}

$settings_sql = "SELECT sps.*, ps.shop_name, ps.owner_id
                 FROM shop_payment_settings sps
                 JOIN print_shops ps ON sps.shop_id = ps.shop_id
                 WHERE sps.id = ?
                 LIMIT 1";
$settings_stmt = mysqli_prepare($conn, $settings_sql);
mysqli_stmt_bind_param($settings_stmt, "i", $settings_id);
mysqli_stmt_execute($settings_stmt);
$settings = mysqli_fetch_assoc(mysqli_stmt_get_result($settings_stmt));

if (!$settings) {
    setError("Payment settings not found.");
    redirect($redirect_url);
}

$is_active = $status === 'approved' ? 1 : 0;
$update_sql = "UPDATE shop_payment_settings
               SET approval_status = ?, is_active = ?, updated_at = NOW()
               WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "sii", $status, $is_active, $settings_id);

if (!mysqli_stmt_execute($update_stmt)) {
    setError("Failed to update payment settings status.");
    redirect($redirect_url);
}

$label = ucfirst($status);
$shop_name = (string) $settings['shop_name'];

sendNotification($conn, (int) $settings['owner_id'], "Your payment settings for \"" . $shop_name . "\" have been " . strtolower($label) . ".", [
    'type' => 'payment_settings_status',
    'title' => 'Payment settings ' . strtolower($label),
    'target_url' => BASE_URL . 'frontend/user/shop_owner/shop_profile.php',
    'metadata' => ['shop_id' => (int) $settings['shop_id'], 'status' => $status],
]);

logActivity($conn, $_SESSION['user_id'], "Updated payment settings #$settings_id (shop: {$shop_name}) to $status", "Payment Settings", [
    'target_type' => 'payment_settings',
    'target_id' => $settings_id,
    'old_value' => [
        'approval_status' => $settings['approval_status'] ?? null,
        'is_active' => isset($settings['is_active']) ? (int) $settings['is_active'] : null,
    ],
    'new_value' => [
        'approval_status' => $status,
        'is_active' => $is_active,
        'shop_id' => (int) $settings['shop_id'],
        'shop_name' => $shop_name,
    ],
]);
setToast("Payment settings for \"" . $shop_name . "\" set to " . $label . ".", "success");

redirect($redirect_url);
?>
