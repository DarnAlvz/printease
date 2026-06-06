<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (!isset($_POST['submit_payment_proof'])) {
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$customer_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);

$order_sql = "SELECT o.order_id, o.order_code, o.total_amount, ps.owner_id
              FROM orders o
              JOIN print_shops ps ON o.shop_id = ps.shop_id
              WHERE o.order_id = ? AND o.customer_id = ?
              LIMIT 1";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) {
    setError("Order not found.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$check_sql = "SELECT payment_id FROM payments 
              WHERE order_id = ? 
              AND verification_status IN ('pending','verified') 
              LIMIT 1";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $order_id);
mysqli_stmt_execute($check_stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

if ($existing) {
    setError("Payment proof already submitted for this order.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

if (!isset($_FILES['proof_of_payment_file']) || $_FILES['proof_of_payment_file']['error'] !== UPLOAD_ERR_OK) {
    setError("Please upload payment proof.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

$upload_dir = "../../uploads/payments/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_name = time() . "_proof_" . basename($_FILES['proof_of_payment_file']['name']);
$target_path = $upload_dir . $file_name;
$db_path = "uploads/payments/" . $file_name;

move_uploaded_file($_FILES['proof_of_payment_file']['tmp_name'], $target_path);

$rejected_sql = "SELECT payment_id FROM payments 
                 WHERE order_id = ? 
                 AND customer_id = ?
                 AND verification_status = 'rejected'
                 LIMIT 1";

$rejected_stmt = mysqli_prepare($conn, $rejected_sql);
mysqli_stmt_bind_param($rejected_stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($rejected_stmt);
$rejected_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($rejected_stmt));

if ($rejected_payment) {
    $update_sql = "UPDATE payments 
                   SET proof_of_payment_file = ?,
                       payment_status = 'pending',
                       verification_status = 'pending',
                       rejection_reason = NULL,
                       created_at = NOW()
                   WHERE payment_id = ?";

    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "si", $db_path, $rejected_payment['payment_id']);
    mysqli_stmt_execute($stmt);

} else {
    $insert_sql = "INSERT INTO payments 
        (order_id, customer_id, amount, payment_method, proof_of_payment_file, payment_status, verification_status, created_at)
        VALUES (?, ?, ?, 'gcash', ?, 'pending', 'pending', NOW())";

    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "iids", $order_id, $customer_id, $order['total_amount'], $db_path);
    mysqli_stmt_execute($stmt);
}

sendNotification($conn, $order['owner_id'], "Payment proof submitted for order #" . $order['order_code'] . ".");
setMessage("Payment proof submitted. Waiting for shop verification.");

redirect(BASE_URL . "frontend/user/customer/orders.php");
?>