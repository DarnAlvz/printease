<?php
require_once __DIR__ . '/../../../backend/includes/auth.php';
checkRole('super_admin');

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/config/app.php';
require_once __DIR__ . '/../../../backend/includes/functions.php';
require_once __DIR__ . '/../../components/notifications.php';
require_once __DIR__ . '/includes/admin_layout.php';

$admin_id = (int) $_SESSION['user_id'];
$notifications = getUserNotifications($conn, $admin_id);
$unread_count = getUnreadNotificationCount($conn, $admin_id);

adminLayoutStart('notifications', 'Notifications', 'Approval and system activity for your admin account.');
?>

<section class="admin-card">
    <?php renderNotificationCenter($notifications, ['role' => 'admin', 'unread_count' => $unread_count, 'empty_text' => 'Verification submissions and system updates will appear here.']); ?>
</section>

<?php adminLayoutEnd(); ?>
