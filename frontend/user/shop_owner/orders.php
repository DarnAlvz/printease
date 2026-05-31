<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/shop_guard.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

requireVerifiedShop($conn);

$owner_id = $_SESSION['user_id'];

$shop_sql = "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop_result = mysqli_stmt_get_result($shop_stmt);
$shop = mysqli_fetch_assoc($shop_result);

if (!$shop) {
    die("Please complete your shop profile first.");
}

$shop_id = $shop['shop_id'];

$sql = "SELECT o.*, u.full_name, u.email
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.shop_id = ?
        ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $shop_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<h2>Manage Orders</h2>
<?php showMessage(); ?>

<?php if (mysqli_num_rows($result) == 0): ?>
    <p>No orders yet.</p>
<?php else: ?>
    <?php while ($order = mysqli_fetch_assoc($result)): ?>
        <div style="border:1px solid #ccc; padding:15px; margin-bottom:10px;">
            <p><strong>Paper Size:</strong> <?php echo e($order['paper_size']); ?></p>
            <p><strong>Paper Type:</strong> <?php echo e($order['paper_type']); ?></p>
            <p><strong>Print Type:</strong> <?php echo e($order['print_type']); ?></p>
            <p><strong>Copies:</strong> <?php echo e($order['copies']); ?></p>
            <p><strong>Instruction:</strong> <?php echo e($order['customer_instruction'] ?: 'No instruction'); ?></p>
            <p>
                <strong>Pickup Time:</strong>
                <?php echo e(date("g:i A", strtotime($order['pickup_datetime']))); ?>
            </p>
            <p><strong>Total Amount:</strong> ₱<?php echo e($order['total_amount']); ?></p>
            <p><strong>Payment Status:</strong>
                <?php
                $payment_sql = "SELECT payment_status FROM payments WHERE order_id = ? LIMIT 1";
                $payment_stmt = mysqli_prepare($conn, $payment_sql);
                mysqli_stmt_bind_param($payment_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($payment_stmt);
                $payment_result = mysqli_stmt_get_result($payment_stmt);
                $payment = mysqli_fetch_assoc($payment_result);
                echo e($payment['payment_status'] ?? 'pending');
                ?>
            </p>
            <p><strong>Status:</strong>
                <?php
                $status = str_replace('_', ' ', $order['order_status']);
                echo ucfirst(strtolower($status));
                ?>
            </p>

            <p><strong>Uploaded Files:</strong></p>
            <?php
            $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
            $file_stmt = mysqli_prepare($conn, $file_sql);
            mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
            mysqli_stmt_execute($file_stmt);
            $files = mysqli_stmt_get_result($file_stmt);
            ?>

            <?php if (mysqli_num_rows($files) == 0): ?>
                <p>No uploaded file.</p>
            <?php else: ?>
                <?php while ($file = mysqli_fetch_assoc($files)): ?>
                    <a href="<?php echo BASE_URL . e($file['file_path']); ?>" target="_blank">
                        View Uploaded File
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>

            <br>

            <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">

                <select name="order_status" required>
                    <?php
                    $statuses = ['pending', 'processing', 'ready_for_pickup', 'completed'];
                    foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php if ($order['order_status'] == $status)
                               echo 'selected'; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="update_order">Update Status</button>
            </form>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

<a href="dashboard.php">Back to Dashboard</a>
