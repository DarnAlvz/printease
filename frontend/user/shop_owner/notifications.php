<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/owner_layout.php";

$owner_id = $_SESSION['user_id'];

// Fetch shop info
$shop_stmt = mysqli_prepare($conn, "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
$unread_count = 0;
while ($note = mysqli_fetch_assoc($result)) {
    $notifications[] = $note;
    if ($note['is_read'] == 0)
        $unread_count++;
}

ownerLayoutStart('notifications', 'Notifications', 'Review recent shop alerts, order updates, and system messages.', $unread_count, $shop);
?>

<section class="owner-card">
    <div class="card-head flex justify-between items-center">
        <h2>Recent Activity</h2>
        <span class="status-badge status-info" id="unread-badge"><?php echo $unread_count; ?> unread</span>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state mt-4">
            <h2>No notifications</h2>
            <p>Order and account updates will appear here.</p>
        </div>
    <?php else: ?>
        <div class="notification-list mt-4 space-y-3">
            <?php foreach ($notifications as $note): ?>
                <?php $href = ownerNotificationUrl($note, $owner_id); ?>
                <?php if ($href !== ''): ?>
                    <a class="notification-item notification-item-link flex items-start gap-3 p-3 rounded-xl border hover:bg-gray-50"
                        href="<?php echo e($href); ?>" data-notification-id="<?php echo (int) $note['notification_id']; ?>"
                        data-is-read="<?php echo (int) $note['is_read']; ?>">
                    <?php else: ?>
                        <article class="notification-item flex items-start gap-3 p-3 rounded-xl border bg-white"
                            data-notification-id="<?php echo (int) $note['notification_id']; ?>"
                            data-is-read="<?php echo (int) $note['is_read']; ?>">
                        <?php endif; ?>
                        <span
                            class="activity-dot"><?php echo ownerIcon($note['is_read'] == 0 ? 'bell-ring' : 'info', 'icon'); ?></span>
                        <div>
                            <div class="row-actions flex items-center gap-2">
                                <?php if ($note['is_read'] == 0): ?>
                                    <span class="status-badge status-info text-xs">New</span>
                                <?php endif; ?>
                                <b><?php echo e($note['message']); ?></b>
                            </div>
                            <small
                                class="muted"><?php echo e(date("M d, Y - g:i A", strtotime($note['created_at']))); ?></small>
                        </div>
                        <?php if ($href !== ''): ?>
                    </a>
                <?php else: ?>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php ownerLayoutEnd(); ?>
