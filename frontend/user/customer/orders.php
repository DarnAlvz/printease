<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];

// Fetch customer orders
$sql = "SELECT o.*, p.payment_status
        FROM orders o
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Order History</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">My Order</h1>
            <p>Track your print orders and payments.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="dashboard.php" class="bg-gray-700 text-white px-4 py-2 rounded">Back to Dashboard</a>
            <a href="place_order.php" class="bg-blue-600 text-white px-4 py-2 rounded">Place Order</a>
        </div>
    </div>

    <?php showMessage(); ?>

    <?php if (mysqli_num_rows($result) == 0): ?>
        <div class="bg-white p-5 rounded shadow">
            <p>You have no orders yet.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php while ($order = mysqli_fetch_assoc($result)): ?>
                <div class="bg-white border p-5 rounded shadow">
                    <p><strong>Order ID:</strong> <?php echo e($order['order_id']); ?></p>
                    <p><strong>Paper Size:</strong> <?php echo e($order['paper_size']); ?></p>
                    <p><strong>Print Type:</strong> <?php echo e($order['print_type']); ?></p>
                    <p><strong>Copies:</strong> <?php echo e($order['copies']); ?></p>
                    <p><strong>Total:</strong> &#8369;<?php echo e($order['total_amount']); ?></p>
                    <p><strong>Status:</strong>
                        <?php
                        $status = str_replace('_', ' ', $order['order_status']);
                        echo e(ucfirst(strtolower($status)));
                        ?>
                    </p>
                    <p><strong>Payment Status:</strong> <?php echo e($order['payment_status'] ?? 'pending'); ?></p>

                    <?php if (empty($order['payment_status'])): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/make_payment.php" method="POST" class="mt-3">
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="amount" value="<?php echo e($order['total_amount']); ?>">
                            <input type="hidden" name="payment_method" value="gcash">
                            <button type="submit" name="pay_order" class="bg-green-600 text-white px-4 py-2 rounded">Pay Now via GCash</button>
                        </form>
                    <?php else: ?>
                        <p class="text-green-600 font-semibold mt-2">Paid</p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

</body>

</html>
