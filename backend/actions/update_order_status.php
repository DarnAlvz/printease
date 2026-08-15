<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/cache.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

validateCsrf();

$orders_url = BASE_URL . "frontend/user/shop_owner/orders.php";
$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function finishOrderStatusRequest($success, $message, $redirect_url, $http_status = 200, array $payload = [])
{
    global $is_ajax;

    if ($is_ajax) {
        http_response_code($http_status);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => (bool) $success,
            'message' => $message,
        ], $payload));
        exit();
    }

    if ($success) {
        setMessage($message);
    } else {
        setError($message);
    }

    redirect($redirect_url);
}

$rate_guard = rateLimitGuardRequest($conn, 'update_order_status', 120, 3600);
if (!$rate_guard['allowed']) {
    finishOrderStatusRequest(false, "Too many status updates. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".", $orders_url, 429);
}
rateLimitRecordRequest($conn, 'update_order_status', $rate_guard['identifier'], $rate_guard['ip_address'], 120, 3600);

if (!isset($_POST['update_order'])) {
    if ($is_ajax) {
        finishOrderStatusRequest(false, "Invalid print job update request.", $orders_url, 400);
    }

    redirect($orders_url);
}

$owner_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$order_status = $_POST['order_status'] ?? '';

$allowed = ['pending', 'processing', 'ready_for_pickup', 'completed'];

if (!in_array($order_status, $allowed)) {
    finishOrderStatusRequest(false, "Invalid print job status.", $orders_url, 400);
}

$get_sql = "SELECT o.customer_id, o.order_code, o.order_status, ps.shop_id
            FROM orders o
            JOIN print_shops ps ON o.shop_id = ps.shop_id
            WHERE o.order_id = ?
            AND ps.owner_id = ?
            LIMIT 1";

$get_stmt = mysqli_prepare($conn, $get_sql);
mysqli_stmt_bind_param($get_stmt, "ii", $order_id, $owner_id);
mysqli_stmt_execute($get_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($get_stmt));

if (!$order) {
    finishOrderStatusRequest(false, "Print job not found or unauthorized.", $orders_url, 404);
}

if ($order_status === 'processing') {
    $payment_sql = "SELECT payment_id
                    FROM payments
                    WHERE order_id = ?
                      AND payment_status = 'paid'
                      AND verification_status = 'verified'
                    ORDER BY verified_at DESC, payment_id DESC
                    LIMIT 1";
    $payment_stmt = mysqli_prepare($conn, $payment_sql);
    mysqli_stmt_bind_param($payment_stmt, "i", $order_id);
    mysqli_stmt_execute($payment_stmt);
    $paid_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_stmt));

    if (!$paid_payment) {
        finishOrderStatusRequest(false, "Verify the payment before accepting this print job.", $orders_url, 422);
    }
}

$current_order_status = $order['order_status'];
$update_sql = "UPDATE orders SET order_status = ? WHERE order_id = ? AND order_status = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "sis", $order_status, $order_id, $current_order_status);

if (mysqli_stmt_execute($update_stmt)) {
    if (mysqli_stmt_affected_rows($update_stmt) < 1) {
        finishOrderStatusRequest(false, "This print job was already updated elsewhere. Please refresh and try again.", $orders_url, 409);
    }

    $status_label = ucfirst(str_replace('_', ' ', $order_status));
    $order_code = $order['order_code'] ?: $order_id;

    sendNotification($conn, $order['customer_id'], "Your request #$order_code is now $status_label.", [
        'type' => 'order_status', 'title' => 'Request status updated',
        'target_url' => BASE_URL . "frontend/user/customer/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code, 'status' => $order_status],
    ]);

    cacheInvalidate("owner_order:{$order['shop_id']}");

    logActivity($conn, $owner_id, "Updated print job #$order_code status to $status_label", "Print Job Management");

    finishOrderStatusRequest(true, "Print job status updated successfully.", $orders_url, 200, [
        'order_id' => $order_id,
        'order_status' => $order_status,
    ]);
} else {
    finishOrderStatusRequest(false, "Failed to update print job status.", $orders_url, 500);
}
?>
