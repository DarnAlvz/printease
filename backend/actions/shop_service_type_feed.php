<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['customer', 'shop_owner'], true)) {
    echo json_encode(['success' => false, 'message' => 'Service updates are not available.']);
    exit();
}

$rows = [];

if ($role === 'shop_owner') {
    $owner_id = (int) ($_SESSION['user_id'] ?? 0);
    $sql = "SELECT ps.shop_id, sst.service_type
            FROM print_shops ps
            LEFT JOIN shop_service_types sst ON sst.shop_id = ps.shop_id
            WHERE ps.owner_id = ?
            ORDER BY sst.service_type ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT ps.shop_id, sst.service_type
            FROM print_shops ps
            LEFT JOIN shop_service_types sst ON sst.shop_id = ps.shop_id
            WHERE ps.permit_status = 'verified'
              AND ps.shop_status IN ('available', 'busy')
              AND ps.latitude IS NOT NULL
              AND ps.longitude IS NOT NULL
            ORDER BY ps.shop_id ASC, sst.service_type ASC";
    $result = mysqli_query($conn, $sql);
}

while ($row = mysqli_fetch_assoc($result)) {
    $shop_id = (int) $row['shop_id'];
    if (!isset($rows[$shop_id])) {
        $rows[$shop_id] = [
            'shop_id' => $shop_id,
            'service_types' => [],
        ];
    }

    $service_type = trim((string) ($row['service_type'] ?? ''));
    if ($service_type !== '' && !in_array($service_type, $rows[$shop_id]['service_types'], true)) {
        $rows[$shop_id]['service_types'][] = $service_type;
    }
}

$shops = array_values($rows);

echo json_encode([
    'success' => true,
    'signature' => md5(json_encode($shops)),
    'shops' => $shops,
]);
?>
