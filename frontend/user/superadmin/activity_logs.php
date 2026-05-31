<?php
include "../../../backend/includes/auth.php";
checkRole("super_admin");

include "../../../backend/config/db.php";
include "../../../backend/config/app.php";
include "../../../backend/includes/functions.php";

$sql = "SELECT al.*, u.full_name, u.role
        FROM activity_logs al
        JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC";

$result = mysqli_query($conn, $sql);
?>

<h1>Activity Logs</h1>

<table border="1" cellpadding="10">
    <tr>
        <th>User</th>
        <th>Role</th>
        <th>Action</th>
        <th>Module</th>
        <th>Date</th>
    </tr>

    <?php while ($log = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo e($log['full_name']); ?></td>
            <td><?php echo e($log['role']); ?></td>
            <td><?php echo e($log['action']); ?></td>
            <td><?php echo e($log['module']); ?></td>
            <td><?php echo e($log['created_at']); ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<br>
<a href="dashboard.php">Back to Dashboard</a>