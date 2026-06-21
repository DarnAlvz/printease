<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/gcash_ocr.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (!isset($_POST['submit_payment_proof'])) {
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$customer_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$reference_number = trim($_POST['reference_number'] ?? '');

if (strlen($reference_number) > 100) {
    setError("Please enter a valid GCash reference number.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

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

if (!isset($_FILES['proof_of_payment_file']) || !isAllowedPaymentProofUpload($_FILES['proof_of_payment_file'], $upload_error)) {
    setError($upload_error ?: "Please upload a valid image file.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

$proof_extension = strtolower(pathinfo($_FILES['proof_of_payment_file']['name'], PATHINFO_EXTENSION));
$upload_dir = "../../uploads/payment_proofs/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true);
}

$file_name = time() . "_proof_" . bin2hex(random_bytes(4)) . "." . $proof_extension;
$target_path = $upload_dir . $file_name;
$db_path = "uploads/payment_proofs/" . $file_name;

if (!move_uploaded_file($_FILES['proof_of_payment_file']['tmp_name'], $target_path)) {
    setError("Failed to upload payment proof.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

$ocr_text = runReceiptOcr($target_path);
$ocr_reference_number = detectGcashReferenceFromText($ocr_text);
$ocr_payment_date = detectGcashPaymentDateFromText($ocr_text);

if ($reference_number === '' && $ocr_reference_number !== null) {
    $reference_number = $ocr_reference_number;
}

$payment_reference_match = gcashOcrStatus($ocr_reference_number, $ocr_payment_date);

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
                       reference_number = ?,
                       ocr_reference_number = ?,
                       ocr_payment_date = ?,
                       payment_reference_match = ?,
                       payment_method = 'gcash_direct',
                       payment_status = 'pending',
                       verification_status = 'pending',
                       rejection_reason = NULL,
                       created_at = NOW()
                   WHERE payment_id = ?";

    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "sssssi", $db_path, $reference_number, $ocr_reference_number, $ocr_payment_date, $payment_reference_match, $rejected_payment['payment_id']);
    mysqli_stmt_execute($stmt);

} else {
    $insert_sql = "INSERT INTO payments 
        (order_id, customer_id, amount, payment_method, reference_number, ocr_reference_number, ocr_payment_date, payment_reference_match, proof_of_payment_file, payment_status, verification_status, created_at)
        VALUES (?, ?, ?, 'gcash_direct', ?, ?, ?, ?, ?, 'pending', 'pending', NOW())";

    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "iidsssss", $order_id, $customer_id, $order['total_amount'], $reference_number, $ocr_reference_number, $ocr_payment_date, $payment_reference_match, $db_path);
    mysqli_stmt_execute($stmt);
}

sendNotification($conn, $order['owner_id'], "Payment proof submitted for order #" . $order['order_code'] . ".", [
    'type' => 'payment_submitted', 'title' => 'Payment proof submitted',
    'target_url' => BASE_URL . 'frontend/user/shop_owner/orders.php?focus_order_id=' . (int) $order_id,
    'metadata' => ['order_id' => (int) $order_id, 'order_code' => $order['order_code']],
]);
setToast("Payment proof submitted. Waiting for shop verification.", "info");

redirect(BASE_URL . "frontend/user/customer/orders.php");
?>
