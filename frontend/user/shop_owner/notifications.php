<?php
include "../../../backend/includes/auth.php";
checkRole("shop_owner");

include "../../../backend/config/db.php";
include "../../../backend/config/app.php";
include "../../../backend/includes/functions.php";

$owner_id = $_SESSION['user_id'];

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Mark all as read
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $owner_id");
?>

<h2>Shop Notifications</h2>

<?php if(mysqli_num_rows($result) == 0): ?>
    <p>No notifications.</p>
<?php else: ?>
    <ul>
        <?php while($note = mysqli_fetch_assoc($result)): ?>
            <li>
                <?php echo e($note['message']); ?> 
                <small>(<?php echo $note['created_at']; ?>)</small>
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>

<a href="dashboard.php">Back to Dashboard</a>