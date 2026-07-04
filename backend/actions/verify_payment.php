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

$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function paymentJsonResponse($success, $message, $extra = [], $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit();
}

$owner_id = $_SESSION['user_id'];
$payment_id = intval($_POST['payment_id'] ?? 0);
$default_redirect = BASE_URL . "frontend/user/shop_owner/orders.php";
$referer_path = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '';
$payment_redirect = str_ends_with($referer_path, '/payments.php')
    ? BASE_URL . "frontend/user/shop_owner/payments.php"
    : $default_redirect;

$sql = "SELECT p.*, o.order_code, o.order_status, ps.owner_id
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
    if ($is_ajax) {
        paymentJsonResponse(false, "Payment not found or unauthorized.", [], 404);
    }
    setError("Payment not found or unauthorized.");
    redirect(BASE_URL . "frontend/user/shop_owner/payments.php");
}

if (isset($_POST['verify_payment'])) {
    mysqli_begin_transaction($conn);

    $update = "UPDATE payments 
               SET payment_status='paid',
                   verification_status='verified',
                   active_lock_order_id=order_id,
                   verified_by=?,
                   verified_at=NOW(),
                   paid_at=NOW()
               WHERE payment_id=?
               AND verification_status='pending'";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "ii", $owner_id, $payment_id);
    $updated = mysqli_stmt_execute($stmt);

    if (!$updated) {
        mysqli_rollback($conn);
        if ($is_ajax) {
            paymentJsonResponse(false, "Failed to verify payment. Please try again.", [], 500);
        }
        setError("Failed to verify payment. Please try again.");
        redirect($payment_redirect);
    }

    if (mysqli_stmt_affected_rows($stmt) < 1) {
        mysqli_rollback($conn);
        if ($is_ajax) {
            paymentJsonResponse(false, "This payment was already updated elsewhere. Please refresh and try again.", [], 409);
        }
        setError("This payment was already updated elsewhere. Please refresh and try again.");
        redirect($payment_redirect);
    }

    $order_status_after = $payment['order_status'] ?? 'pending';

    mysqli_commit($conn);

    $verified_message = "Your payment for order #" . $payment['order_code'] . " has been verified.";

    sendNotification($conn, $payment['customer_id'], $verified_message, [
        'type' => 'payment_verified', 'title' => 'Payment verified',
        'target_url' => BASE_URL . 'frontend/user/customer/orders.php?focus_order_id=' . (int) $payment['order_id'],
        'metadata' => ['order_id' => (int) $payment['order_id'], 'order_code' => $payment['order_code'], 'status' => $order_status_after],
    ]);
    if ($is_ajax) {
        paymentJsonResponse(true, "Payment verified successfully.", [
            'payment_id' => (int) $payment_id,
            'order_id' => (int) $payment['order_id'],
            'payment_status' => 'paid',
            'verification_status' => 'verified',
            'order_status' => $order_status_after,
        ]);
    }
    setMessage("Payment verified successfully.");
}

if (isset($_POST['reject_payment'])) {
    $reason = trim($_POST['rejection_reason'] ?? '');
    $reason = preg_replace('/\s+/', ' ', $reason);

    if ($reason === '') {
        if ($is_ajax) {
            paymentJsonResponse(false, "Please enter a reason before rejecting this payment proof.", [], 422);
        }
        setError("Please enter a reason before rejecting this payment proof.");
        redirect($payment_redirect);
    }

    if (strlen($reason) > 500) {
        $reason = substr($reason, 0, 500);
    }

    $update = "UPDATE payments 
           SET payment_status='unpaid',
               verification_status='rejected',
               active_lock_order_id=NULL,
               rejection_reason=?
           WHERE payment_id=?
           AND verification_status='pending'";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "si", $reason, $payment_id);
    $updated = mysqli_stmt_execute($stmt);

    if (!$updated) {
        if ($is_ajax) {
            paymentJsonResponse(false, "Failed to reject payment. Please try again.", [], 500);
        }
        setError("Failed to reject payment. Please try again.");
        redirect($payment_redirect);
    }

    if (mysqli_stmt_affected_rows($stmt) < 1) {
        if ($is_ajax) {
            paymentJsonResponse(false, "This payment was already updated elsewhere. Please refresh and try again.", [], 409);
        }
        setError("This payment was already updated elsewhere. Please refresh and try again.");
        redirect($payment_redirect);
    }

    sendNotification($conn, $payment['customer_id'], "Your payment proof for order #" . $payment['order_code'] . " was rejected. Reason: " . $reason, [
        'type' => 'payment_rejected', 'title' => 'Payment proof rejected',
        'target_url' => BASE_URL . 'frontend/user/customer/orders.php?focus_order_id=' . (int) $payment['order_id'],
        'metadata' => ['order_id' => (int) $payment['order_id'], 'order_code' => $payment['order_code'], 'reason' => $reason],
    ]);
    if ($is_ajax) {
        paymentJsonResponse(true, "Payment proof rejected.", [
            'payment_id' => (int) $payment_id,
            'order_id' => (int) $payment['order_id'],
            'payment_status' => 'unpaid',
            'verification_status' => 'rejected',
        ]);
    }
    setToast("Payment proof rejected.", "warning");
}

if ($is_ajax) {
    paymentJsonResponse(false, "No payment action was requested.", [], 400);
}

redirect($payment_redirect);
?>
