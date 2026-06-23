<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'shop_owner') {
    echo json_encode(['success' => false]);
    exit();
}

$owner_id = (int) $_SESSION['user_id'];
$shop_stmt = mysqli_prepare($conn, "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    echo json_encode(['success' => false]);
    exit();
}

$shop_id = (int) $shop['shop_id'];
$count_sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_for_pickup,
                SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed,
                MAX(created_at) AS latest_created
              FROM orders
              WHERE shop_id = ?";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $shop_id);
mysqli_stmt_execute($count_stmt);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt)) ?: [];

$signature_sql = "SELECT o.order_id, o.order_code, o.order_status,
                         COALESCE(p.payment_status, '') AS payment_status,
                         COALESCE(p.verification_status, '') AS verification_status
                  FROM orders o
                  LEFT JOIN payments p ON o.order_id = p.order_id
                  WHERE o.shop_id = ?
                  ORDER BY o.created_at DESC, o.order_id DESC
                  LIMIT 25";
$signature_stmt = mysqli_prepare($conn, $signature_sql);
mysqli_stmt_bind_param($signature_stmt, "i", $shop_id);
mysqli_stmt_execute($signature_stmt);
$signature_result = mysqli_stmt_get_result($signature_stmt);
$signature_parts = [];
while ($row = mysqli_fetch_assoc($signature_result)) {
    $signature_parts[] = implode(':', [
        $row['order_id'],
        $row['order_code'],
        $row['order_status'],
        $row['payment_status'],
        $row['verification_status'],
    ]);
}

echo json_encode([
    'success' => true,
    'counts' => [
        'total' => (int) ($counts['total'] ?? 0),
        'pending' => (int) ($counts['pending'] ?? 0),
        'processing' => (int) ($counts['processing'] ?? 0),
        'ready_for_pickup' => (int) ($counts['ready_for_pickup'] ?? 0),
        'completed' => (int) ($counts['completed'] ?? 0),
    ],
    'signature' => hash('sha256', implode('|', $signature_parts) . '|' . ($counts['latest_created'] ?? '')),
]);
?>
