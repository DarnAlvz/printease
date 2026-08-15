<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("super_admin");

$redirect_url = BASE_URL . "frontend/user/superadmin/manage_print_shops.php#payment-settings-review";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setError("Invalid request method for payment settings updates.");
    redirect($redirect_url);
}

validateCsrf();

$rate_guard = rateLimitGuardRequest($conn, 'payment_settings_status', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many payment settings updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect_url);
}
rateLimitRecordRequest($conn, 'payment_settings_status', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$scope = trim((string) ($_POST['scope'] ?? 'channel'));
$settings_id = filter_input(INPUT_POST, 'settings_id', FILTER_VALIDATE_INT);
$shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status'] ?? ''));
$allowed_status = ['approved', 'rejected', 'pending'];

if (!in_array($status, $allowed_status, true)) {
    setError("Invalid payment settings status update.");
    redirect($redirect_url);
}

$rejection_reason = trim((string) ($_POST['rejection_reason'] ?? ''));
if (mb_strlen($rejection_reason) > 500) {
    $rejection_reason = mb_substr($rejection_reason, 0, 500);
}
$rejection_reason = $rejection_reason !== '' ? $rejection_reason : null;

if ($scope === 'shop') {
    if (!$shop_id || !in_array($status, ['approved', 'rejected'], true)) {
        setError("Invalid shop-level payment settings update.");
        redirect($redirect_url);
    }

    $settings_sql = "SELECT c.*, ps.shop_name, ps.owner_id
                     FROM shop_payment_channels c
                     JOIN print_shops ps ON c.shop_id = ps.shop_id
                     WHERE c.shop_id = ? AND c.approval_status = 'pending'
                     ORDER BY c.created_at ASC";
    $settings_stmt = mysqli_prepare($conn, $settings_sql);
    mysqli_stmt_bind_param($settings_stmt, "i", $shop_id);
    mysqli_stmt_execute($settings_stmt);
    $settings_result = mysqli_stmt_get_result($settings_stmt);

    $pending_settings = [];
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $pending_settings[] = $row;
    }

    if (empty($pending_settings)) {
        setToast("No pending payment methods found for this shop.", "info");
        redirect($redirect_url);
    }

    $is_active = $status === 'approved' ? 1 : 0;
    $approved_by = $status === 'approved' ? (int) $_SESSION['user_id'] : null;
    $approved_at = $status === 'approved' ? date('Y-m-d H:i:s') : null;
    $rejected_reason_value = $status === 'rejected' ? $rejection_reason : null;

    $update_sql = "UPDATE shop_payment_channels
                   SET approval_status = ?, is_active = ?, approved_by = ?, approved_at = ?, rejected_reason = ?, updated_at = NOW()
                   WHERE shop_id = ? AND approval_status = 'pending'";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "siissi", $status, $is_active, $approved_by, $approved_at, $rejected_reason_value, $shop_id);

    if (!mysqli_stmt_execute($update_stmt)) {
        setError("Failed to update shop payment settings status.");
        redirect($redirect_url);
    }

    $affected_count = mysqli_stmt_affected_rows($update_stmt);
    $label = ucfirst($status);
    $shop_name = (string) $pending_settings[0]['shop_name'];
    $owner_id = (int) $pending_settings[0]['owner_id'];
    $channel_labels = array_map(static function ($setting) {
        return $setting['channel'] === 'gcash_merchant_link' ? 'GCash Merchant Link' : 'GCash QR Code';
    }, $pending_settings);

    sendNotification($conn, $owner_id, $affected_count . " payment " . ($affected_count === 1 ? "method" : "methods") . " for \"" . $shop_name . "\" have been " . strtolower($label) . ".", [
        'type' => 'payment_settings_status',
        'title' => 'Payment settings ' . strtolower($label),
        'target_url' => BASE_URL . 'frontend/user/shop_owner/shop_profile.php',
        'metadata' => ['shop_id' => $shop_id, 'channels' => array_column($pending_settings, 'channel'), 'status' => $status],
    ]);

    logActivity($conn, $_SESSION['user_id'], "Updated $affected_count payment settings for shop {$shop_name} to $status", "Payment Settings", [
        'target_type' => 'payment_shop',
        'target_id' => $shop_id,
        'old_value' => [
            'channels' => $channel_labels,
            'approval_status' => 'pending',
        ],
        'new_value' => [
            'approval_status' => $status,
            'is_active' => $is_active,
            'approved_by' => $approved_by,
            'approved_at' => $approved_at,
            'rejected_reason' => $rejected_reason_value,
            'shop_id' => $shop_id,
            'shop_name' => $shop_name,
            'affected_count' => $affected_count,
        ],
    ]);

    setToast($affected_count . " payment " . ($affected_count === 1 ? "method" : "methods") . " for \"" . $shop_name . "\" set to " . $label . ".", "success");
    redirect($redirect_url);
}

if (!$settings_id) {
    setError("Invalid payment settings status update.");
    redirect($redirect_url);
}

$settings_sql = "SELECT c.*, ps.shop_name, ps.owner_id
                 FROM shop_payment_channels c
                 JOIN print_shops ps ON c.shop_id = ps.shop_id
                 WHERE c.id = ?
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
$approved_by = $status === 'approved' ? (int) $_SESSION['user_id'] : null;
$approved_at = $status === 'approved' ? date('Y-m-d H:i:s') : null;
$rejected_reason_value = $status === 'rejected' ? $rejection_reason : null;

$update_sql = "UPDATE shop_payment_channels
               SET approval_status = ?, is_active = ?, approved_by = ?, approved_at = ?, rejected_reason = ?, updated_at = NOW()
               WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "siissi", $status, $is_active, $approved_by, $approved_at, $rejected_reason_value, $settings_id);

if (!mysqli_stmt_execute($update_stmt)) {
    setError("Failed to update payment settings status.");
    redirect($redirect_url);
}

$label = ucfirst($status);
$shop_name = (string) $settings['shop_name'];
$channel_label = $settings['channel'] === 'gcash_merchant_link' ? 'GCash Merchant Link' : 'GCash QR Code';

sendNotification($conn, (int) $settings['owner_id'], "Your " . $channel_label . " payment setting for \"" . $shop_name . "\" has been " . strtolower($label) . ".", [
    'type' => 'payment_settings_status',
    'title' => $channel_label . ' payment setting ' . strtolower($label),
    'target_url' => BASE_URL . 'frontend/user/shop_owner/shop_profile.php',
    'metadata' => ['shop_id' => (int) $settings['shop_id'], 'channel' => $settings['channel'], 'status' => $status],
]);

logActivity($conn, $_SESSION['user_id'], "Updated " . $channel_label . " payment setting #$settings_id (shop: {$shop_name}) to $status", "Payment Settings", [
    'target_type' => 'payment_channel',
    'target_id' => $settings_id,
    'old_value' => [
        'channel' => $settings['channel'],
        'approval_status' => $settings['approval_status'] ?? null,
        'is_active' => isset($settings['is_active']) ? (int) $settings['is_active'] : null,
        'approved_by' => $settings['approved_by'] ?? null,
        'approved_at' => $settings['approved_at'] ?? null,
        'rejected_reason' => $settings['rejected_reason'] ?? null,
    ],
    'new_value' => [
        'channel' => $settings['channel'],
        'approval_status' => $status,
        'is_active' => $is_active,
        'approved_by' => $approved_by,
        'approved_at' => $approved_at,
        'rejected_reason' => $rejected_reason_value,
        'shop_id' => (int) $settings['shop_id'],
        'shop_name' => $shop_name,
    ],
]);
setToast($channel_label . " payment setting for \"" . $shop_name . "\" set to " . $label . ".", "success");

redirect($redirect_url);
?>
