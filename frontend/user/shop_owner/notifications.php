<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/owner_layout.php";
require_once __DIR__ . "/../../components/notifications.php";

$owner_id = $_SESSION['user_id'];

// Fetch shop info
$shop_stmt = mysqli_prepare($conn, "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

$notifications = getUserNotifications($conn, $owner_id);
$unread_count = getUnreadNotificationCount($conn, $owner_id);

ownerLayoutStart('notifications', 'Notifications', 'Review recent shop alerts, order updates, and system messages.', $unread_count, $shop);
?>

<section class="owner-card">
    <?php renderNotificationCenter($notifications, ['role' => 'owner', 'unread_count' => $unread_count, 'empty_text' => 'Order, payment, and permit updates will appear here.']); ?>
</section>

<?php ownerLayoutEnd(); ?>
