<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/cache.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

validateCsrf();

$orders_url = BASE_URL . "frontend/user/shop_owner/orders.php";
$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function finishAdjustmentNoteRequest($success, $message, $redirect_url, $http_status = 200, array $payload = [])
{
    global $is_ajax;

    if ($is_ajax) {
        http_response_code($http_status);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => (bool) $success,
            'message' => $message,
        ], $payload));
        exit();
    }

    if ($success) {
        setMessage($message);
    } else {
        setError($message);
    }

    redirect($redirect_url);
}

$rate_guard = rateLimitGuardRequest($conn, 'send_order_adjustment_note', 60, 3600);
if (!$rate_guard['allowed']) {
    finishAdjustmentNoteRequest(false, "Too many adjustment notes. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".", $orders_url, 429);
}
rateLimitRecordRequest($conn, 'send_order_adjustment_note', $rate_guard['identifier'], $rate_guard['ip_address'], 60, 3600);

if (!isset($_POST['send_order_adjustment_note'])) {
    finishAdjustmentNoteRequest(false, "Invalid adjustment note request.", $orders_url, 400);
}

$owner_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);
$note_type = strtolower(trim((string) ($_POST['note_type'] ?? '')));
$note_text = trim((string) ($_POST['note_text'] ?? ''));
$note_text = preg_replace('/\s+/', ' ', $note_text);

if ($order_id <= 0) {
    finishAdjustmentNoteRequest(false, "Print job not found.", $orders_url, 404);
}

$redirect = $orders_url . "?focus_order_id=" . $order_id;

if (!in_array($note_type, ['additional', 'refund'], true)) {
    finishAdjustmentNoteRequest(false, "Please choose Request additional or Request refund.", $redirect, 422);
}

if ($note_text === '' || mb_strlen($note_text, 'UTF-8') > 500) {
    finishAdjustmentNoteRequest(false, "Note must be between 1 and 500 characters.", $redirect, 422);
}

if (stripos($note_text, '{X}') !== false) {
    finishAdjustmentNoteRequest(false, "Please replace {X} with the actual peso amount before sending.", $redirect, 422);
}

if (!preg_match('/\d/', $note_text)) {
    finishAdjustmentNoteRequest(false, "Note must include the peso amount.", $redirect, 422);
}

$order_sql = "SELECT o.order_id, o.order_code, o.customer_id, o.shop_id, o.order_status
              FROM orders o
              JOIN print_shops ps ON o.shop_id = ps.shop_id
              WHERE o.order_id = ?
              AND ps.owner_id = ?
              LIMIT 1";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $owner_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) {
    finishAdjustmentNoteRequest(false, "Print job not found or unauthorized.", $orders_url, 404);
}

if (($order['order_status'] ?? '') !== 'ready_for_pickup') {
    finishAdjustmentNoteRequest(false, "Pickup balance notes can only be sent when the request is ready for pickup.", $redirect, 422);
}

$file_sql = "SELECT file_type, file_name FROM uploaded_files WHERE order_id = ? LIMIT 1";
$file_stmt = mysqli_prepare($conn, $file_sql);
mysqli_stmt_bind_param($file_stmt, "i", $order_id);
mysqli_stmt_execute($file_stmt);
$file_row = mysqli_fetch_assoc(mysqli_stmt_get_result($file_stmt));
$file_type = strtolower((string) ($file_row['file_type'] ?? ''));
if ($file_type === '') {
    $file_type = strtolower(pathinfo((string) ($file_row['file_name'] ?? ''), PATHINFO_EXTENSION));
}

if (!in_array($file_type, ['doc', 'docx', 'wps'], true)) {
    finishAdjustmentNoteRequest(false, "Pickup balance notes are only available for Word / WPS files.", $redirect, 422);
}

$insert_sql = null;
$note_id = 0;

mysqli_begin_transaction($conn);
try {
    // Overwrite semantics: one active pickup note per order.
    $delete_sql = "DELETE FROM order_notes WHERE order_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    if (!$delete_stmt) {
        throw new Exception("Failed to save the note. Please try again.");
    }
    mysqli_stmt_bind_param($delete_stmt, "i", $order_id);
    if (!mysqli_stmt_execute($delete_stmt)) {
        mysqli_stmt_close($delete_stmt);
        throw new Exception("Failed to save the note. Please try again.");
    }
    mysqli_stmt_close($delete_stmt);

    $insert_sql = "INSERT INTO order_notes (order_id, shop_id, note_type, note_text, created_by)
                   VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    if (!$insert_stmt) {
        throw new Exception("Failed to save the note. Please try again.");
    }
    mysqli_stmt_bind_param($insert_stmt, "iissi", $order_id, $order['shop_id'], $note_type, $note_text, $owner_id);

    if (!mysqli_stmt_execute($insert_stmt)) {
        mysqli_stmt_close($insert_stmt);
        throw new Exception("Failed to save the note. Please try again.");
    }
    $note_id = mysqli_insert_id($conn);
    mysqli_stmt_close($insert_stmt);

    mysqli_commit($conn);
} catch (Exception $adjust_note_error) {
    mysqli_rollback($conn);
    finishAdjustmentNoteRequest(false, $adjust_note_error->getMessage(), $redirect, 500);
}
$order_code = $order['order_code'] ?: $order_id;
$title = $note_type === 'additional' ? 'Additional balance on pickup' : 'Refund on pickup';

sendNotification($conn, (int) $order['customer_id'], $note_text, [
    'type' => $note_type === 'additional' ? 'order_balance_additional' : 'order_balance_refund',
    'title' => $title,
    'target_url' => BASE_URL . 'frontend/user/customer/orders.php?focus_order_id=' . (int) $order_id,
    'metadata' => ['order_id' => (int) $order_id, 'order_code' => $order_code, 'note_id' => (int) $note_id, 'note_type' => $note_type],
]);

cacheInvalidate("owner_order:{$order['shop_id']}");

logActivity($conn, $owner_id, "Sent {$note_type} pickup note for print job #{$order_code}", "Print Job Management");

finishAdjustmentNoteRequest(true, "Note sent. The customer will see it on this request and as a notification.", $redirect, 200, [
    'order_id' => $order_id,
    'note_id' => (int) $note_id,
    'note_type' => $note_type,
]);
