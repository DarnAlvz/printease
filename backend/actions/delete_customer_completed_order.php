<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("customer");
validateCsrf();

$allowed_redirect_tabs = ['active', 'completed', 'cancelled'];
$redirect_tab = 'completed';
if (isset($_POST['redirect_tab']) && in_array((string) $_POST['redirect_tab'], $allowed_redirect_tabs, true)) {
    $redirect_tab = (string) $_POST['redirect_tab'];
}
$redirect = BASE_URL . "frontend/user/customer/orders.php?status=" . urlencode($redirect_tab);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_completed_order'])) {
    redirect($redirect);
}

$rate_guard = rateLimitGuardRequest($conn, 'delete_customer_completed_order', 60, 3600);
if (!$rate_guard['allowed']) {
    setError("Too many request removals. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    redirect($redirect);
}
rateLimitRecordRequest($conn, 'delete_customer_completed_order', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

$customer_id = (int) ($_SESSION['user_id'] ?? 0);
$order_id = (int) ($_POST['order_id'] ?? 0);

if ($order_id <= 0 || $customer_id <= 0) {
    setError("Invalid request.");
    redirect($redirect);
}

if (!customerOrderPrivacyColumnExists($conn)) {
    setError("Request privacy delete is not ready yet. Please run the latest database migration.");
    redirect($redirect);
}

$order_sql = "SELECT order_id, order_code, order_status, customer_deleted_at
              FROM orders
              WHERE order_id = ? AND customer_id = ?
              LIMIT 1";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) {
    setError("Request not found.");
    redirect($redirect);
}

if (!in_array(($order['order_status'] ?? ''), ['completed', 'cancelled'], true)) {
    setError("Only completed or declined requests can be removed from your history.");
    redirect($redirect);
}

if (!empty($order['customer_deleted_at'])) {
    setToast("This request is already removed from your history.", "info");
    redirect($redirect);
}

$delete_sql = "UPDATE orders
               SET customer_deleted_at = NOW()
               WHERE order_id = ? AND customer_id = ? AND order_status IN ('completed', 'cancelled') AND customer_deleted_at IS NULL";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $order_id, $customer_id);

if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
    logActivity($conn, $customer_id, "Removed {$order['order_status']} request from customer history: {$order['order_code']}", "Customer Requests");
    setToast("Request removed from your history.", "success");
} else {
    setError("Failed to remove request. Please try again.");
}

redirect($redirect);
?>
