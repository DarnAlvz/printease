<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$customer_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Customer';

$stmt = mysqli_prepare($conn, "SELECT phone_number, address, valid_id_file, account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$account_status = $user['account_status'] ?? 'incomplete';
$profile_complete = !empty($user['phone_number']) && !empty($user['address']) && !empty($user['valid_id_file']);

function countCustomerOrders($conn, $customer_id, $status = null) {
    if ($status) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ? AND order_status = ?");
        mysqli_stmt_bind_param($stmt, "is", $customer_id, $status);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $customer_id);
    }

    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0;
}

function dashboardOrderStatusLabel($status) {
    return match ($status) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucwords(str_replace('_', ' ', $status ?? 'Pending'))
    };
}

function dashboardOrderBadge($status) {
    return match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-700',
        'accepted' => 'bg-blue-100 text-blue-700',
        'processing' => 'bg-purple-100 text-purple-700',
        'ready_for_pickup' => 'bg-green-100 text-green-700',
        'completed' => 'bg-gray-200 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700'
    };
}

function dashboardPaymentLabel($payment_status, $verification_status) {
    if ($payment_status === 'paid' && $verification_status === 'verified') {
        return 'Paid';
    }

    if ($verification_status === 'pending') {
        return 'Pending Verification';
    }

    if ($verification_status === 'rejected') {
        return 'Rejected';
    }

    return 'Unpaid';
}

function dashboardFormatDate($datetime) {
    if (empty($datetime)) {
        return 'Not set';
    }

    return date("M d, Y - g:i A", strtotime($datetime));
}

function dashboardMoney($amount) {
    return '₱' . number_format((float) $amount, 2);
}

$total_orders = countCustomerOrders($conn, $customer_id);
$pending_orders = countCustomerOrders($conn, $customer_id, 'pending');
$ready_orders = countCustomerOrders($conn, $customer_id, 'ready_for_pickup');

$paid_orders_sql = "SELECT COUNT(DISTINCT o.order_id) AS total 
                    FROM orders o
                    JOIN payments p ON o.order_id = p.order_id
                    WHERE o.customer_id = ? 
                    AND p.payment_status = 'paid'
                    AND p.verification_status = 'verified'";
$paid_orders_stmt = mysqli_prepare($conn, $paid_orders_sql);
mysqli_stmt_bind_param($paid_orders_stmt, "i", $customer_id);
mysqli_stmt_execute($paid_orders_stmt);
$paid_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($paid_orders_stmt))['total'] ?? 0;

$notif_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($notif_stmt, "i", $customer_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0;

$latest_order = null;
$latest_sql = "SELECT o.order_id, o.order_code, o.order_status, o.created_at,
                      o.paper_size, o.paper_type, o.print_type, o.copies,
                      o.pickup_datetime, o.total_amount, ps.shop_name,
                      p.payment_status, p.verification_status
               FROM orders o
               JOIN print_shops ps ON o.shop_id = ps.shop_id
               LEFT JOIN payments p ON p.payment_id = (
                   SELECT p2.payment_id
                   FROM payments p2
                   WHERE p2.order_id = o.order_id
                   ORDER BY p2.created_at DESC, p2.payment_id DESC
                   LIMIT 1
               )
               WHERE o.customer_id = ?
               ORDER BY o.created_at DESC
               LIMIT 1";
$latest_stmt = mysqli_prepare($conn, $latest_sql);
mysqli_stmt_bind_param($latest_stmt, "i", $customer_id);
mysqli_stmt_execute($latest_stmt);
$latest_order = mysqli_fetch_assoc(mysqli_stmt_get_result($latest_stmt));
$latest_order_tab = ($latest_order && ($latest_order['order_status'] ?? '') === 'completed') ? 'completed' : 'active';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

<div class="max-w-md md:max-w-6xl mx-auto bg-gray-100 min-h-screen">

    <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm opacity-90">Welcome back,</p>
                <h1 class="text-2xl font-bold"><?php echo e($full_name); ?></h1>
            </div>

            <a href="notifications.php" class="relative bg-white/20 px-3 py-2 rounded-full text-sm">
                🔔
                <?php if ($notif_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                        <?php echo e($notif_count); ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>

        <p class="mt-4 text-sm">
            Account Status:
            <span class="font-bold uppercase"><?php echo e($account_status); ?></span>
        </p>
    </header>

    <main class="p-4 md:p-6">

        <?php if ($account_status === 'incomplete' || !$profile_complete): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-xl shadow">
                <h2 class="font-bold text-yellow-800">Complete Your Profile</h2>
                <p class="text-sm text-yellow-700 mt-1">Update your details and upload a valid ID to access all features.</p>
                <a href="profile.php" class="inline-block mt-3 bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm">
                    Complete Profile
                </a>
            </div>

        <?php elseif ($account_status === 'pending'): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 p-4 rounded-xl shadow">
                <h2 class="font-bold text-blue-800">Pending Verification</h2>
                <p class="text-sm text-blue-700 mt-1">Your profile is complete. Please wait for Super Admin approval.</p>
                <a href="profile.php" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                    View Profile
                </a>
            </div>

        <?php elseif ($account_status === 'rejected'): ?>
            <div class="bg-red-100 border-l-4 border-red-500 p-4 rounded-xl shadow">
                <h2 class="font-bold text-red-800">Account Rejected</h2>
                <p class="text-sm text-red-700 mt-1">Please contact the administrator for assistance.</p>
            </div>

        <?php elseif ($account_status === 'verified'): ?>

            <section class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-2">
                <div class="bg-white p-4 rounded-2xl shadow">
                    <p class="text-sm text-gray-500">Total Orders</p>
                    <h2 class="text-3xl font-bold text-blue-700"><?php echo e($total_orders); ?></h2>
                </div>

                <div class="bg-white p-4 rounded-2xl shadow">
                    <p class="text-sm text-gray-500">Pending</p>
                    <h2 class="text-3xl font-bold text-orange-500"><?php echo e($pending_orders); ?></h2>
                </div>

                <div class="bg-white p-4 rounded-2xl shadow">
                    <p class="text-sm text-gray-500">Ready</p>
                    <h2 class="text-3xl font-bold text-green-600"><?php echo e($ready_orders); ?></h2>
                </div>

                <div class="bg-white p-4 rounded-2xl shadow">
                    <p class="text-sm text-gray-500">Paid</p>
                    <h2 class="text-3xl font-bold text-purple-600"><?php echo e($paid_orders); ?></h2>
                </div>
            </section>

            <section class="bg-white mt-5 p-4 rounded-2xl shadow">
                <h2 class="font-bold text-gray-800 mb-3">Quick Actions</h2>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <a href="shopLocation.php" class="bg-blue-600 text-white text-center py-3 rounded-xl font-semibold">
                        Find Shops
                    </a>
                    <a href="order.php" class="bg-green-600 text-white text-center py-3 rounded-xl font-semibold">
                        Place Order
                    </a>
                    <a href="orders.php" class="bg-gray-800 text-white text-center py-3 rounded-xl font-semibold">
                        My Orders
                    </a>
                    <a href="profile.php" class="bg-white border text-gray-700 text-center py-3 rounded-xl font-semibold">
                        Profile
                    </a>
                </div>
            </section>

            <section class="bg-white mt-5 p-4 rounded-2xl shadow">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-bold text-gray-800">Latest Order</h2>
                    <?php if ($latest_order): ?>
                        <span class="text-xs text-gray-400">Tap to view</span>
                    <?php endif; ?>
                </div>

                <?php if ($latest_order): ?>
                    <a href="orders.php?status=<?php echo e($latest_order_tab); ?>&focus_order_code=<?php echo urlencode($latest_order['order_code']); ?>"
                        class="block border rounded-2xl p-4 hover:border-blue-300 hover:bg-blue-50/40 transition">
                        <div class="flex justify-between items-start gap-3">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-wide text-gray-400">Order Code</p>
                                <h3 class="font-bold text-lg text-blue-700 break-words">
                                    #<?php echo e($latest_order['order_code']); ?>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1"><?php echo e($latest_order['shop_name']); ?></p>
                            </div>

                            <span class="shrink-0 text-xs px-3 py-1 rounded-full <?php echo dashboardOrderBadge($latest_order['order_status']); ?>">
                                <?php echo e(dashboardOrderStatusLabel($latest_order['order_status'])); ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4 text-sm text-gray-700">
                            <p><strong>Paper:</strong> <?php echo e($latest_order['paper_size']); ?>, <?php echo e($latest_order['paper_type']); ?></p>
                            <p><strong>Print:</strong> <?php echo e($latest_order['print_type']); ?></p>
                            <p><strong>Copies:</strong> <?php echo e($latest_order['copies']); ?></p>
                            <p><strong>Total:</strong> <?php echo e(dashboardMoney($latest_order['total_amount'])); ?></p>
                            <p><strong>Pickup:</strong> <?php echo e(dashboardFormatDate($latest_order['pickup_datetime'])); ?></p>
                            <p><strong>Payment:</strong> <?php echo e(dashboardPaymentLabel($latest_order['payment_status'] ?? '', $latest_order['verification_status'] ?? '')); ?></p>
                        </div>

                        <p class="text-xs text-gray-400 mt-4">
                            Submitted <?php echo e(dashboardFormatDate($latest_order['created_at'])); ?>
                        </p>
                    </a>
                <?php else: ?>
                    <p class="text-sm text-gray-500 mt-2">No orders yet. Start by choosing a print shop.</p>
                <?php endif; ?>
            </section>

        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>backend/actions/logout.php" class="block text-center text-red-600 mt-6">
            Logout
        </a>

    </main>
</div>

<nav class="fixed bottom-0 left-0 right-0 bg-white border-t shadow md:static md:shadow-none md:border md:rounded-2xl md:mt-6">
    <div class="max-w-md md:max-w-6xl mx-auto grid grid-cols-5 text-center text-xs">
        <a href="dashboard.php" class="py-3 text-blue-700 font-bold">Home</a>
        <a href="shops.php" class="py-3 text-gray-600">Shops</a>
        <a href="shopLocation.php" class="py-3 text-gray-600">Map</a>
        <a href="orders.php" class="py-3 text-gray-600">Track</a>
        <a href="profile.php" class="py-3 text-gray-600">Profile</a>
    </div>
</nav>

</body>
</html>
