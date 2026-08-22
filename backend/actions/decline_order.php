<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/cache.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);

validateCsrf();

$orders_url = BASE_URL . "frontend/user/shop_owner/orders.php";
$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function finishDeclineRequest($success, $message, $redirect_url, $http_status = 200, array $payload = [])
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

$rate_guard = rateLimitGuardRequest($conn, 'decline_order', 60, 3600);
if (!$rate_guard['allowed']) {
    finishDeclineRequest(false, "Too many decline attempts. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".", $orders_url, 429);
}
rateLimitRecordRequest($conn, 'decline_order', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

if (!isset($_POST['decline_order'])) {
    finishDeclineRequest(false, "Invalid decline request.", $orders_url, 400);
}

$owner_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);

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
    finishDeclineRequest(false, "Print job not found or unauthorized.", $orders_url, 404);
}

if ($order['order_status'] !== 'pending') {
    finishDeclineRequest(false, "Only pending requests can be declined.", $orders_url, 422);
}

$proof_sql = "SELECT payment_id
              FROM payments
              WHERE order_id = ?
                AND NOT (payment_status = 'unpaid' AND verification_status = 'rejected')
              LIMIT 1";
$proof_stmt = mysqli_prepare($conn, $proof_sql);
mysqli_stmt_bind_param($proof_stmt, "i", $order_id);
mysqli_stmt_execute($proof_stmt);
$active_proof = mysqli_fetch_assoc(mysqli_stmt_get_result($proof_stmt));

if ($active_proof) {
    finishDeclineRequest(false, "Cannot decline this request because the customer already submitted a payment proof. Reject the payment proof instead if it is invalid.", $orders_url, 422);
}

$update_sql = "UPDATE orders SET order_status = 'cancelled' WHERE order_id = ? AND order_status = 'pending'";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $order_id);

if (mysqli_stmt_execute($update_stmt)) {
    if (mysqli_stmt_affected_rows($update_stmt) < 1) {
        finishDeclineRequest(false, "This print job was already updated elsewhere. Please refresh and try again.", $orders_url, 409);
    }

    $order_code = $order['order_code'] ?: $order_id;

    sendNotification($conn, $order['customer_id'], "Your request #$order_code was declined by the shop.", [
        'type' => 'order_status',
        'title' => 'Request declined',
        'target_url' => BASE_URL . "frontend/user/customer/orders.php?status=cancelled&focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code, 'status' => 'cancelled'],
    ]);

    cacheInvalidate("owner_order:{$order['shop_id']}");

    logActivity($conn, $owner_id, "Declined print job #$order_code", "Print Job Management");

    finishDeclineRequest(true, "Print request declined. The customer has been notified.", $orders_url, 200, [
        'order_id' => $order_id,
        'order_status' => 'cancelled',
    ]);
}

finishDeclineRequest(false, "Failed to decline the print request. Please try again.", $orders_url, 500);
?>
