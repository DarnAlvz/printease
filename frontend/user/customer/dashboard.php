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

$total_orders = countCustomerOrders($conn, $customer_id);
$pending_orders = countCustomerOrders($conn, $customer_id, 'pending');
$ready_orders = countCustomerOrders($conn, $customer_id, 'ready_for_pickup');

$paid_orders_sql = "SELECT COUNT(DISTINCT o.order_id) AS total 
                    FROM orders o
                    JOIN payments p ON o.order_id = p.order_id
                    WHERE o.customer_id = ? AND p.payment_status = 'paid'";
$paid_orders_stmt = mysqli_prepare($conn, $paid_orders_sql);
mysqli_stmt_bind_param($paid_orders_stmt, "i", $customer_id);
mysqli_stmt_execute($paid_orders_stmt);
$paid_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($paid_orders_stmt))['total'] ?? 0;

$notif_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($notif_stmt, "i", $customer_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0;

$latest_order = null;
$latest_stmt = mysqli_prepare($conn, "SELECT order_id, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($latest_stmt, "i", $customer_id);
mysqli_stmt_execute($latest_stmt);
$latest_order = mysqli_fetch_assoc(mysqli_stmt_get_result($latest_stmt));
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
                    <a href="shops.php" class="bg-blue-600 text-white text-center py-3 rounded-xl font-semibold">
                        Find Shops
                    </a>
                    <a href="place_order.php" class="bg-green-600 text-white text-center py-3 rounded-xl font-semibold">
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
                <h2 class="font-bold text-gray-800">Latest Order</h2>

                <?php if ($latest_order): ?>
                    <p class="text-sm text-gray-500 mt-2">Order #<?php echo e($latest_order['order_id']); ?></p>
                    <p class="text-lg font-bold capitalize text-blue-700">
                        <?php echo e($latest_order['order_status']); ?>
                    </p>
                    <p class="text-xs text-gray-400"><?php echo e($latest_order['created_at']); ?></p>
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
        <a href="place_order.php" class="py-3 text-gray-600">Order</a>
        <a href="orders.php" class="py-3 text-gray-600">Track</a>
        <a href="profile.php" class="py-3 text-gray-600">Profile</a>
    </div>
</nav>

</body>
</html>