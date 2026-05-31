<?php
session_start();
include "../config/db.php";
include "../config/app.php";
include "../includes/auth.php";
include "../includes/functions.php";
include "../includes/status_guard.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (isset($_POST['pay_order'])) {
    $order_id = intval($_POST['order_id']);
    $customer_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $payment_method = 'gcash';

    // Insert payment
    $sql = "INSERT INTO payments (order_id, customer_id, amount, payment_method, payment_status) 
            VALUES (?, ?, ?, ?, 'paid')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iids", $order_id, $customer_id, $amount, $payment_method);
    $execute = mysqli_stmt_execute($stmt);

    if ($execute) {
        // Log activity
        logActivity($conn, $customer_id, "Paid order #$order_id via GCash", "Payment");


        sendNotification($conn, $customer_id, "Your payment for order #$order_id was successful via GCash.");

        // Set success message
        setMessage("Payment successful via GCash.");
    } else {
        setMessage("Payment failed. Please try again.");
    }
    
    // Notify the shop owner
    $shop_sql = "SELECT owner_id FROM print_shops WHERE shop_id = (SELECT shop_id FROM orders WHERE order_id = ?)";
    $shop_stmt = mysqli_prepare($conn, $shop_sql);
    mysqli_stmt_bind_param($shop_stmt, "i", $order_id);
    mysqli_stmt_execute($shop_stmt);
    $shop_result = mysqli_stmt_get_result($shop_stmt);
    $shop = mysqli_fetch_assoc($shop_result);

    if ($shop && !empty($shop['owner_id'])) {
        sendNotification($conn, $shop['owner_id'], "Order #$order_id has been paid by the customer via GCash.");
    }

    // Redirect to dashboard to display message
    redirect(BASE_URL . "frontend/user/customer/dashboard.php");
}