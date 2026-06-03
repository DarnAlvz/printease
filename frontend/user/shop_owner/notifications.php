<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/owner_layout.php";

$owner_id = $_SESSION['user_id'];

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

$count_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $owner_id);
mysqli_stmt_execute($count_stmt);
$notif_count = (mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($note = mysqli_fetch_assoc($result)) {
    $notifications[] = $note;
}

$read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$read_stmt = mysqli_prepare($conn, $read_sql);
mysqli_stmt_bind_param($read_stmt, "i", $owner_id);
mysqli_stmt_execute($read_stmt);

ownerLayoutStart('notifications', 'Notifications', 'Review recent shop alerts, order updates, and system messages.', $notif_count, $shop);
?>

<section class="owner-card">
    <div class="card-head">
        <h2>Recent Activity</h2>
        <span class="status-badge status-info"><?php echo (int) count($notifications); ?> total</span>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <h2>No notifications</h2>
            <p>Order and account updates will appear here.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $note): ?>
                <?php $notification_href = ownerNotificationUrl($note, $owner_id); ?>
                <?php if ($notification_href !== ''): ?>
                    <a class="notification-item notification-item-link" href="<?php echo e($notification_href); ?>">
                <?php else: ?>
                    <article class="notification-item">
                <?php endif; ?>
                    <span class="activity-dot"><?php echo ownerIcon($note['is_read'] == 0 ? 'bell-ring' : 'info', 'icon'); ?></span>
                    <div>
                        <div class="row-actions">
                            <?php if ($note['is_read'] == 0): ?>
                                <span class="status-badge status-info">New</span>
                            <?php endif; ?>
                            <b><?php echo e($note['message']); ?></b>
                        </div>
                        <small class="muted"><?php echo e(date("M d, Y - g:i A", strtotime($note['created_at']))); ?></small>
                    </div>
                <?php if ($notification_href !== ''): ?>
                    </a>
                <?php else: ?>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php ownerLayoutEnd(); ?>
