<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/gcash_ocr.php";

header('Content-Type: application/json');

function paymentReferenceJson($success, $message, $reference_number = '')
{
    $payment_date = $GLOBALS['payment_date'] ?? '';
    echo json_encode([
        'success' => (bool) $success,
        'reference_number' => (string) $reference_number,
        'payment_date' => (string) $payment_date,
        'message' => (string) $message,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentReferenceJson(false, 'Invalid request.');
}

checkRole("customer");
requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);

if ($order_id <= 0) {
    paymentReferenceJson(false, 'Invalid order.');
}

$order_sql = "SELECT o.order_id
              FROM orders o
              WHERE o.order_id = ? AND o.customer_id = ?
              LIMIT 1";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) {
    paymentReferenceJson(false, 'Order not found.');
}

if (!isset($_FILES['proof_of_payment_file']) || !isAllowedPaymentProofUpload($_FILES['proof_of_payment_file'], $upload_error)) {
    paymentReferenceJson(false, $upload_error ?: 'Please upload a valid image file.');
}

$ocr_text = runReceiptOcr($_FILES['proof_of_payment_file']['tmp_name']);
$reference_number = detectGcashReferenceFromText($ocr_text);
$payment_date = detectGcashPaymentDateFromText($ocr_text);
$GLOBALS['payment_date'] = $payment_date ?? '';

if ($reference_number === null || $reference_number === '') {
    paymentReferenceJson(false, 'Could not detect the reference number.');
}

paymentReferenceJson(true, 'Reference number detected.', $reference_number);
?>
