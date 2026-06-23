<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../../frontend/components/notifications.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['customer', 'shop_owner', 'super_admin'], true)) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$notifications = getUserNotifications($conn, $user_id, 5);
$items = [];

foreach ($notifications as $notification) {
    $target = notificationSafeTarget($notification['target_url'] ?? '');
    $items[] = [
        'id' => (int) $notification['notification_id'],
        'type' => (string) ($notification['type'] ?? 'general'),
        'title' => (string) ($notification['title'] ?? 'Notification'),
        'message' => (string) ($notification['message'] ?? ''),
        'target_url' => $target,
        'is_read' => (int) ($notification['is_read'] ?? 0),
        'created_at' => (string) ($notification['created_at'] ?? ''),
        'relative_time' => notificationRelativeTime($notification['created_at'] ?? ''),
    ];
}

echo json_encode([
    'success' => true,
    'role' => (string) ($_SESSION['role'] ?? ''),
    'unread_count' => getUnreadNotificationCount($conn, $user_id),
    'items' => $items,
]);
?>
