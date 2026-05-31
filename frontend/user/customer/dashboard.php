<?php
include "../../../backend/includes/auth.php";
checkRole("customer");

include "../../../backend/config/db.php";
include "../../../backend/config/app.php";
include "../../../backend/includes/functions.php";

$customer_id = $_SESSION['user_id'];

// Fetch customer info including account_status
$stmt = mysqli_prepare($conn, "SELECT phone_number, address, valid_id_file, account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$account_status = $user['account_status'] ?? 'incomplete';

// Check if profile is complete
$profile_complete = !empty($user['phone_number']) && !empty($user['address']) && !empty($user['valid_id_file']);

// Count unread notifications
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $customer_id AND is_read = 0"
))['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<header class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Customer Dashboard</h1>
    <div>
        <a href="notifications.php" class="relative">
            Notifications
            <?php if($notif_count > 0): ?>
                <span style="background:red; color:white; padding:2px 5px; border-radius:50%; position:absolute; top:-5px; right:-10px;">
                    <?php echo $notif_count; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</header>

<?php if ($account_status === 'incomplete' || !$profile_complete): ?>
    <div class="bg-yellow-100 p-5 rounded shadow mb-6">
        <p>Your profile is incomplete. You must update your profile and upload your valid ID to access all features.</p>
        <a href="profile.php" class="bg-green-600 text-white px-4 py-2 rounded mt-2 inline-block">Complete Profile</a>
    </div>

<?php elseif ($account_status === 'pending'): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 p-5 rounded shadow mb-6">
        <h2 class="text-xl font-bold text-blue-800">Account Pending Verification</h2>
        <p class="text-blue-700">Your profile is complete. Your account is now pending verification by the Super Admin. Please wait for approval to access all features.</p>
        <div class="mt-4">
            <a href="profile.php" class="bg-gray-600 text-white px-4 py-2 rounded">View Profile</a>
        </div>
    </div>

<?php elseif ($account_status === 'rejected'): ?>
    <div class="bg-red-100 border-l-4 border-red-500 p-5 rounded shadow mb-6">
        <h2 class="text-xl font-bold text-red-800">Account Rejected</h2>
        <p class="text-red-700">Your account has been rejected. Please contact the administrator for more information.</p>
    </div>

<?php elseif ($account_status === 'verified'): ?>
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded shadow">
            <h2>Total Orders</h2>
            <p class="text-3xl font-bold">
                <?php
                $total_orders_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?");
                mysqli_stmt_bind_param($total_orders_stmt, "i", $customer_id);
                mysqli_stmt_execute($total_orders_stmt);
                $total_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($total_orders_stmt))['total'];
                echo $total_orders;
                ?>
            </p>
        </div>
        <div class="bg-white p-5 rounded shadow">
            <h2>Pending Orders</h2>
            <p class="text-3xl font-bold">
                <?php
                $pending_orders_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ? AND order_status='pending'");
                mysqli_stmt_bind_param($pending_orders_stmt, "i", $customer_id);
                mysqli_stmt_execute($pending_orders_stmt);
                $pending_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($pending_orders_stmt))['total'];
                echo $pending_orders;
                ?>
            </p>
        </div>
        <div class="bg-white p-5 rounded shadow">
            <h2>Paid Orders</h2>
            <p class="text-3xl font-bold">
                <?php
                $paid_orders_sql = "SELECT COUNT(DISTINCT o.order_id) AS total 
                                    FROM orders o
                                    JOIN payments p ON o.order_id = p.order_id
                                    WHERE o.customer_id = ? AND p.payment_status='paid'";
                $paid_orders_stmt = mysqli_prepare($conn, $paid_orders_sql);
                mysqli_stmt_bind_param($paid_orders_stmt, "i", $customer_id);
                mysqli_stmt_execute($paid_orders_stmt);
                $paid_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($paid_orders_stmt))['total'];
                echo $paid_orders;
                ?>
            </p>
        </div>
    </div>

    <a href="orders.php" class="bg-blue-600 text-white px-4 py-2 rounded">My Orders</a>
<?php endif; ?>

<a href="<?php echo BASE_URL; ?>backend/actions/logout.php" class="text-red-600 block mt-5">Logout</a>

</body>
</html>