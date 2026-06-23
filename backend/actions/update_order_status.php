<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

if (!isset($_POST['update_order'])) {
    redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
}

$owner_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$order_status = $_POST['order_status'] ?? '';

$allowed = ['pending', 'processing', 'ready_for_pickup', 'completed'];

if (!in_array($order_status, $allowed)) {
    setError("Invalid order status.");
    redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
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
    setError("Order not found or unauthorized.");
    redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
}

$update_sql = "UPDATE orders SET order_status = ? WHERE order_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "si", $order_status, $order_id);

if (mysqli_stmt_execute($update_stmt)) {
    $status_label = ucfirst(str_replace('_', ' ', $order_status));
    $order_code = $order['order_code'] ?: $order_id;

    sendNotification($conn, $order['customer_id'], "Your order #$order_code is now $status_label.", [
        'type' => 'order_status', 'title' => 'Order status updated',
        'target_url' => BASE_URL . "frontend/user/customer/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code, 'status' => $order_status],
    ]);

    logActivity($conn, $owner_id, "Updated order #$order_code status to $status_label", "Order Management");

    setMessage("Order status updated successfully.");
} else {
    setError("Failed to update order status.");
}

redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
?>
