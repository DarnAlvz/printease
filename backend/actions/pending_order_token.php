<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("customer");

header("Content-Type: application/json");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$draft_id = trim((string) ($_GET['draft_id'] ?? ''));
if ($draft_id !== '' && preg_match('/^[a-f0-9]{32}$/i', $draft_id)) {
    $_SESSION['order_submit_token'] = strtolower($draft_id);
}

if (empty($_SESSION['order_submit_token'])) {
    $_SESSION['order_submit_token'] = bin2hex(random_bytes(16));
}

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token'],
    'order_submit_token' => $_SESSION['order_submit_token'],
]);
exit;
