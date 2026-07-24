<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("customer");
validateCsrf();

$redirect = BASE_URL . "frontend/user/customer/orders.php?status=completed";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_completed_order'])) {
    redirect($redirect);
}

$customer_id = (int) ($_SESSION['user_id'] ?? 0);
$order_id = (int) ($_POST['order_id'] ?? 0);

if ($order_id <= 0 || $customer_id <= 0) {
    setError("Invalid completed request.");
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
    setError("Completed request not found.");
    redirect($redirect);
}

if (($order['order_status'] ?? '') !== 'completed') {
    setError("Only completed requests can be removed from your history.");
    redirect($redirect);
}

if (!empty($order['customer_deleted_at'])) {
    setToast("This completed request is already removed from your history.", "info");
    redirect($redirect);
}

$delete_sql = "UPDATE orders
               SET customer_deleted_at = NOW()
               WHERE order_id = ? AND customer_id = ? AND order_status = 'completed' AND customer_deleted_at IS NULL";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $order_id, $customer_id);

if (mysqli_stmt_execute($delete_stmt) && mysqli_stmt_affected_rows($delete_stmt) > 0) {
    logActivity($conn, $customer_id, "Removed completed request from customer history: {$order['order_code']}", "Customer Requests");
    setToast("Completed request removed from your history.", "success");
} else {
    setError("Failed to remove completed request. Please try again.");
}

redirect($redirect);
?>
