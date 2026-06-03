<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (isset($_POST['pay_order'])) {
    $order_id = intval($_POST['order_id']);
    $customer_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $payment_method = 'gcash';

    $order_sql = "SELECT o.order_code, ps.owner_id
                  FROM orders o
                  JOIN print_shops ps ON o.shop_id = ps.shop_id
                  WHERE o.order_id = ? AND o.customer_id = ?
                  LIMIT 1";
    $order_stmt = mysqli_prepare($conn, $order_sql);
    mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $customer_id);
    mysqli_stmt_execute($order_stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));
    $order_code = $order['order_code'] ?? $order_id;

    // Insert payment
    $sql = "INSERT INTO payments (order_id, customer_id, amount, payment_method, payment_status) 
            VALUES (?, ?, ?, ?, 'paid')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iids", $order_id, $customer_id, $amount, $payment_method);
    $execute = mysqli_stmt_execute($stmt);

    if ($execute) {
        // Log activity
        logActivity($conn, $customer_id, "Paid order #$order_code via GCash", "Payment");


        sendNotification($conn, $customer_id, "Your payment for order #$order_code was successful via GCash.");

        if ($order && !empty($order['owner_id'])) {
            sendNotification($conn, $order['owner_id'], "Order #$order_code has been paid by the customer via GCash.");
        }

        // Set success message
        setMessage("Payment successful via GCash.");
    } else {
        setMessage("Payment failed. Please try again.");
    }

    // Redirect to dashboard to display message
    redirect(BASE_URL . "frontend/user/customer/dashboard.php");
}
