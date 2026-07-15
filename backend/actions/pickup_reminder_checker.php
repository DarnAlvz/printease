<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

// This script checks for orders that are scheduled for pickup within the next 30 minutes and sends a reminder notification to the shop owner if the order is not yet ready. It also updates the order to indicate that a reminder has been sent.
$owner_id = null;

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'shop_owner') {
    $owner_id = (int) $_SESSION['user_id'];
}

$base_sql = "SELECT o.order_id, o.order_code, o.pickup_datetime, ps.owner_id
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        WHERE o.pickup_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)
        AND o.order_status IN ('pending', 'processing')
        AND o.pickup_reminder_sent = 0";

if ($owner_id !== null) {
    $sql = $base_sql . " AND ps.owner_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
} else {
    $sql = $base_sql;
    $stmt = mysqli_prepare($conn, $sql);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($order = mysqli_fetch_assoc($result)) {
    $order_id = $order['order_id'];
    $order_code = $order['order_code'] ?: $order_id;
    $owner_id = $order['owner_id'];
    $pickup_time = date("g:i A", strtotime($order['pickup_datetime']));

    $message = "Reminder: Order #$order_code is scheduled for pickup at $pickup_time and is not yet ready.";

    sendNotification($conn, $owner_id, $message, [
        'type' => 'pickup_reminder', 'title' => 'Pickup reminder',
        'target_url' => BASE_URL . "frontend/user/shop_owner/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code],
    ]);

    $update_sql = "UPDATE orders 
                   SET pickup_reminder_sent = 1 
                   WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
}

?>
