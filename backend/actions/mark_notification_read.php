<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header('Content-Type: application/json');

function notificationReadResponse($success, $updated = false, $unread_count = 0)
{
    echo json_encode([
        'success' => $success,
        'updated' => $updated,
        'unread_count' => (int) $unread_count,
    ]);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'shop_owner') {
    notificationReadResponse(false);
}

$notification_id = intval($_POST['notification_id'] ?? 0);
$owner_id = $_SESSION['user_id'];

function getUnreadNotificationCount($conn, $owner_id)
{
    $count_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, "i", $owner_id);
    mysqli_stmt_execute($count_stmt);
    $count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
    return (int) ($count_row['total'] ?? 0);
}

if ($notification_id <= 0) {
    notificationReadResponse(false, false, getUnreadNotificationCount($conn, $owner_id));
}

$sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ? AND is_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $notification_id, $owner_id);
mysqli_stmt_execute($stmt);
$updated = mysqli_stmt_affected_rows($stmt) > 0;

$exists_sql = "SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ? LIMIT 1";
$exists_stmt = mysqli_prepare($conn, $exists_sql);
mysqli_stmt_bind_param($exists_stmt, "ii", $notification_id, $owner_id);
mysqli_stmt_execute($exists_stmt);
$exists = mysqli_fetch_assoc(mysqli_stmt_get_result($exists_stmt));

if (!$exists) {
    notificationReadResponse(false, false, getUnreadNotificationCount($conn, $owner_id));
}

notificationReadResponse(true, $updated, getUnreadNotificationCount($conn, $owner_id));
?>
