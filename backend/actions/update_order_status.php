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

if (isset($_POST['update_order'])) {
    $owner_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id']);
    $order_status = $_POST['order_status'];

    $allowed = ['pending', 'processing', 'ready_for_pickup', 'completed'];

    if (!in_array($order_status, $allowed)) {
        setMessage("Invalid order status.");
        redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
    }

    $sql = "UPDATE orders 
            SET order_status = ? 
            WHERE order_id = ? 
            AND shop_id = (
                SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1
            )";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $order_status, $order_id, $owner_id);

    if (mysqli_stmt_execute($stmt)) {
        logActivity(
            $conn,
            $_SESSION['user_id'],
            "Updated order #$order_id status to $order_status",
            "Order Management"
        );

        setMessage("Order status updated successfully.");
    } else {
        setMessage("Failed to update order status.");
    }

    $order_customer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_id FROM orders WHERE order_id = $order_id"));
    sendNotification($conn, $order_customer['customer_id'], "Your order #$order_id status updated to $order_status.");

    redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
}
?>
