<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$owner_id = $_SESSION['user_id'];

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Mark all as read
$read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$read_stmt = mysqli_prepare($conn, $read_sql);
mysqli_stmt_bind_param($read_stmt, "i", $owner_id);
mysqli_stmt_execute($read_stmt);
?>

<h2>Shop Notifications</h2>

<?php if (mysqli_num_rows($result) == 0): ?>
    <p>No notifications.</p>
<?php else: ?>
    <ul>
        <?php while ($note = mysqli_fetch_assoc($result)): ?>
            <li style="border:1px solid #ddd; padding:10px; margin-bottom:8px;">
                <?php if ($note['is_read'] == 0): ?>
                    <span style="background:red; color:white; padding:2px 6px; border-radius:5px;">New</span>
                <?php endif; ?>

                <p><?php echo e($note['message']); ?></p>
                <small><?php echo e(date("M d, Y - g:i A", strtotime($note['created_at']))); ?></small>
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>

<a href="dashboard.php">Back to Dashboard</a>