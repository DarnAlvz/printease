<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    if (authIsAjaxRequest()) {
        authJsonResponse(false, 'Authentication required.', 401);
    }
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit();
}

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['customer', 'shop_owner'], true)) {
    authJsonResponse(false, 'Access denied.', 403);
}

$rows = [];
$allowed_service_types = ['Document Printing', 'Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing', 'Photocopy', 'Binding', 'Scanning'];

if ($role === 'shop_owner') {
    $owner_id = (int) ($_SESSION['user_id'] ?? 0);
    $sql = "SELECT ps.shop_id, sst.service_type, sst.online_available, sst.customer_note
            FROM print_shops ps
            LEFT JOIN shop_service_types sst ON sst.shop_id = ps.shop_id AND sst.service_offered = 1
            WHERE ps.owner_id = ?
            ORDER BY sst.service_type ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT ps.shop_id, sst.service_type, sst.online_available, sst.customer_note
            FROM print_shops ps
            LEFT JOIN shop_service_types sst ON sst.shop_id = ps.shop_id
            WHERE ps.permit_status = 'verified'
              AND ps.shop_status IN ('available', 'busy')
              AND ps.latitude IS NOT NULL
              AND ps.longitude IS NOT NULL
              AND sst.service_offered = 1
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
    if ($service_type !== '' && in_array($service_type, $allowed_service_types, true)) {
        $exists = false;
        foreach ($rows[$shop_id]['service_types'] as $existing) {
            if (($existing['service_type'] ?? '') === $service_type) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $rows[$shop_id]['service_types'][] = [
                'service_type' => $service_type,
                'online_available' => (int) ($row['online_available'] ?? 1) === 1,
                'customer_note' => (string) ($row['customer_note'] ?? ''),
            ];
        }
    }
}

$shops = array_values($rows);

echo json_encode([
    'success' => true,
    'signature' => md5(json_encode($shops)),
    'shops' => $shops,
]);
?>
