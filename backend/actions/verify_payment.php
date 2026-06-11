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

$owner_id = $_SESSION['user_id'];
$payment_id = intval($_POST['payment_id'] ?? 0);

$sql = "SELECT p.*, o.order_code, ps.owner_id
        FROM payments p
        JOIN orders o ON p.order_id = o.order_id
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        WHERE p.payment_id = ? AND ps.owner_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $payment_id, $owner_id);
mysqli_stmt_execute($stmt);
$payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$payment) {
    setError("Payment not found or unauthorized.");
    redirect(BASE_URL . "frontend/user/shop_owner/payments.php");
}

if (isset($_POST['verify_payment'])) {
    $update = "UPDATE payments 
               SET payment_status='paid', verification_status='verified', verified_by=?, verified_at=NOW(), paid_at=NOW()
               WHERE payment_id=?";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "ii", $owner_id, $payment_id);
    mysqli_stmt_execute($stmt);

    sendNotification($conn, $payment['customer_id'], "Your payment for order #" . $payment['order_code'] . " has been verified.");
    setMessage("Payment verified successfully.");
}

if (isset($_POST['reject_payment'])) {
    $reason = trim($_POST['rejection_reason'] ?? '');

    $update = "UPDATE payments 
           SET payment_status='unpaid',
               verification_status='rejected',
               rejection_reason=?
           WHERE payment_id=?";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "si", $reason, $payment_id);
    mysqli_stmt_execute($stmt);

    sendNotification($conn, $payment['customer_id'], "Your payment proof for order #" . $payment['order_code'] . " was rejected.");
    setMessage("Payment proof rejected.");
}

redirect(BASE_URL . "frontend/user/shop_owner/orders.php");
?>
