<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/gcash_ocr.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (!isset($_POST['submit_payment_proof'])) {
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$customer_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$reference_number = trim($_POST['reference_number'] ?? '');
$ip = rateLimitClientIp();
$customer_key = rateLimitCurrentUserKey();

$submit_limit = rateLimitCheck($conn, 'payment_submit_customer_hour', $customer_key, 'all', 10, 60 * 60);
if (!$submit_limit['allowed']) {
    setError("Too many payment submission attempts. Please wait " . rateLimitFormatSeconds($submit_limit['retry_after']) . " before trying again.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}
rateLimitRecord($conn, 'payment_submit_customer_hour', $customer_key, 'all', 10, 60 * 60, 60 * 60);

if (strlen($reference_number) > 100) {
    setError("GCash reference number must be 100 characters or fewer.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

function isDuplicateKeyError($conn)
{
    return (int) mysqli_errno($conn) === 1062;
}

function cleanupPaymentProofUpload($target_path)
{
    if ($target_path && is_file($target_path)) {
        unlink($target_path);
    }
}

$order_sql = "SELECT o.order_id, o.order_code, o.total_amount, ps.owner_id,
                     sps.gcash_account_name, sps.gcash_number, sps.gcash_qr_code
              FROM orders o
              JOIN print_shops ps ON o.shop_id = ps.shop_id
              LEFT JOIN shop_payment_settings sps ON sps.shop_id = ps.shop_id
                  AND sps.approval_status = 'approved'
                  AND sps.is_active = 1
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

if (empty($order['gcash_account_name']) || empty($order['gcash_number']) || empty($order['gcash_qr_code'])) {
    setError("This shop's GCash payment details are not approved yet.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

if (!isset($_FILES['proof_of_payment_file']) || !isAllowedPaymentProofUpload($_FILES['proof_of_payment_file'], $upload_error)) {
    setError($upload_error ?: "Please upload a valid image file.");
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

$proof_extension = strtolower(pathinfo($_FILES['proof_of_payment_file']['name'], PATHINFO_EXTENSION));
$upload_dir = ocrUploadsPath('payment_proofs') . DIRECTORY_SEPARATOR;
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

$ocr_customer_limit = rateLimitCheck($conn, 'ocr_customer_minute', $customer_key, 'all', 3, 60);
$ocr_ip_limit = rateLimitCheck($conn, 'ocr_ip_hour', 'all', $ip, 30, 60 * 60);
$ocr_text = '';

if (!$ocr_customer_limit['allowed'] || !$ocr_ip_limit['allowed']) {
    $retry_after = max((int) $ocr_customer_limit['retry_after'], (int) $ocr_ip_limit['retry_after']);
    setToast("OCR was skipped because it is temporarily rate limited. Your payment proof was still submitted for review.", "warning");
} else {
    rateLimitRecord($conn, 'ocr_customer_minute', $customer_key, 'all', 3, 60, 60);
    rateLimitRecord($conn, 'ocr_ip_hour', 'all', $ip, 30, 60 * 60, 60 * 60);
    $ocr_text = runReceiptOcr($target_path);
}
$ocr_reference_number = detectGcashReferenceFromText($ocr_text);
$ocr_payment_date = detectGcashPaymentDateFromText($ocr_text);

if ($reference_number === '' && $ocr_reference_number !== null) {
    $reference_number = $ocr_reference_number;
}

$payment_reference_match = gcashOcrStatus($ocr_reference_number, $ocr_payment_date);

mysqli_begin_transaction($conn);
$transaction_started = true;

try {
    $rejected_sql = "SELECT payment_id FROM payments
                     WHERE order_id = ?
                     AND customer_id = ?
                     AND verification_status = 'rejected'
                     ORDER BY payment_id DESC
                     LIMIT 1";

    $rejected_stmt = mysqli_prepare($conn, $rejected_sql);
    mysqli_stmt_bind_param($rejected_stmt, "ii", $order_id, $customer_id);
    mysqli_stmt_execute($rejected_stmt);
    $rejected_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($rejected_stmt));

    if ($rejected_payment) {
        $update_sql = "UPDATE payments
                       SET active_lock_order_id = ?,
                           proof_of_payment_file = ?,
                           reference_number = ?,
                           ocr_reference_number = ?,
                           ocr_payment_date = ?,
                           payment_reference_match = ?,
                           payment_method = 'gcash_direct',
                           payment_status = 'pending',
                           verification_status = 'pending',
                           rejection_reason = NULL,
                           created_at = NOW()
                       WHERE payment_id = ?
                       AND verification_status = 'rejected'";

        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "isssssi", $order_id, $db_path, $reference_number, $ocr_reference_number, $ocr_payment_date, $payment_reference_match, $rejected_payment['payment_id']);
        $saved = mysqli_stmt_execute($stmt);

        if (!$saved) {
            if (isDuplicateKeyError($conn)) {
                throw new RuntimeException("Payment proof already submitted for this order.", 1062);
            }

            throw new Exception("Failed to update payment proof: " . mysqli_stmt_error($stmt));
        }

        if (mysqli_stmt_affected_rows($stmt) < 1) {
            throw new Exception("Payment proof already submitted for this order.");
        }

    } else {
        $insert_sql = "INSERT INTO payments
            (order_id, active_lock_order_id, customer_id, amount, payment_method, reference_number, ocr_reference_number, ocr_payment_date, payment_reference_match, proof_of_payment_file, payment_status, verification_status, created_at)
            VALUES (?, ?, ?, ?, 'gcash_direct', ?, ?, ?, ?, ?, 'pending', 'pending', NOW())";

        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "iiidsssss", $order_id, $order_id, $customer_id, $order['total_amount'], $reference_number, $ocr_reference_number, $ocr_payment_date, $payment_reference_match, $db_path);
        $saved = mysqli_stmt_execute($stmt);

        if (!$saved) {
            if (isDuplicateKeyError($conn)) {
                throw new RuntimeException("Payment proof already submitted for this order.", 1062);
            }

            throw new Exception("Failed to save payment proof: " . mysqli_stmt_error($stmt));
        }
    }

    sendNotification($conn, $order['owner_id'], "Payment proof submitted for order #" . $order['order_code'] . ".", [
        'type' => 'payment_submitted', 'title' => 'Payment proof submitted',
        'target_url' => BASE_URL . 'frontend/user/shop_owner/orders.php?focus_order_id=' . (int) $order_id,
        'metadata' => ['order_id' => (int) $order_id, 'order_code' => $order['order_code']],
    ]);

    mysqli_commit($conn);
    $transaction_started = false;

} catch (Throwable $e) {
    if ($transaction_started) {
        mysqli_rollback($conn);
    }

    cleanupPaymentProofUpload($target_path);

    if ((int) $e->getCode() === 1062 || $e->getMessage() === "Payment proof already submitted for this order.") {
        setError("Payment proof already submitted for this order.");
        redirect(BASE_URL . "frontend/user/customer/orders.php");
    }

    setError($e->getMessage());
    redirect(BASE_URL . "frontend/user/customer/payment.php?order_id=" . $order_id);
}

setToast("Payment submitted for verification.", "info");

redirect(BASE_URL . "frontend/user/customer/orders.php");
?>
