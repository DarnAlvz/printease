<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/functions.php";

// This script checks for orders that are scheduled for pickup within the next 30 minutes and sends a reminder notification to the shop owner if the order is not yet ready. It also updates the order to indicate that a reminder has been sent.
$owner_filter = "";

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'shop_owner') {
    $current_owner_id = intval($_SESSION['user_id']);
    $owner_filter = " AND ps.owner_id = $current_owner_id ";
}

$sql = "SELECT o.order_id, o.order_code, o.pickup_datetime, ps.owner_id
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        WHERE o.pickup_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 MINUTE)
        AND o.order_status IN ('pending', 'processing')
        AND o.pickup_reminder_sent = 0
        $owner_filter";
$result = mysqli_query($conn, $sql);

while ($order = mysqli_fetch_assoc($result)) {
    $order_id = $order['order_id'];
    $order_code = $order['order_code'] ?: $order_id;
    $owner_id = $order['owner_id'];
    $pickup_time = date("g:i A", strtotime($order['pickup_datetime']));

    $message = "Reminder: Order #$order_code is scheduled for pickup at $pickup_time and is not yet ready.";

    sendNotification($conn, $owner_id, $message);

    $update_sql = "UPDATE orders 
                   SET pickup_reminder_sent = 1 
                   WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
}

?>
