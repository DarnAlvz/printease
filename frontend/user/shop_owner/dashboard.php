<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/actions/pickup_reminder_checker.php";

$owner_id = $_SESSION['user_id'];

$sql = "SELECT ps.*, u.account_status 
        FROM print_shops ps
        JOIN users u ON ps.owner_id = u.user_id
        WHERE ps.owner_id = ? 
        ORDER BY ps.shop_id DESC 
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$shop = mysqli_fetch_assoc($result);

$has_shop = !empty($shop);
$permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : 'pending';
$account_status = $shop ? ($shop['account_status'] ?? 'pending') : 'pending';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Shop Owner Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <h1 class="text-2xl font-bold mb-4">Shop Owner Dashboard</h1>
    <p>Welcome, <?php echo e($_SESSION['full_name']); ?>!</p>

    <?php if (isset($_GET['profile']) && $_GET['profile'] === 'success'): ?>
        <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded mb-4">
            <p class="text-green-800 font-semibold">Shop profile saved successfully!</p>
        </div>
    <?php endif; ?>

    <?php showMessage(); ?>

    <div class="bg-white p-5 rounded shadow mt-5">
        <?php if (!$has_shop): ?>
            <p class="mb-3">Your shop profile is not yet completed.</p>
            <a href="shop_profile.php" class="bg-blue-600 text-white px-4 py-2 rounded">
                Complete Shop Profile
            </a>

        <?php elseif ($permit_status === 'pending'): ?>
            <h2 class="text-xl font-bold"><?php echo e($shop['shop_name']); ?></h2>
            <p>Address: <?php echo e($shop['shop_address']); ?></p>
            <p>Contact: <?php echo e($shop['contact_number']); ?></p>
            <p>Permit Status: <span class="text-blue-600 font-semibold">Pending Verification</span></p>

            <?php if (!empty($shop['business_permit_file'])): ?>
                <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>"
                    class="w-64 h-auto border rounded shadow mt-3">
            <?php endif; ?>

            <div class="mt-5 bg-blue-100 border-l-4 border-blue-500 p-4 rounded">
                <p class="text-blue-800">Your business permit is pending verification by the Super Admin. Please wait for
                    approval to manage orders.</p>
            </div>

            <?php
            $notif_count = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $owner_id AND is_read = 0"
            ))['total'];
            ?>
            <div class="mt-4">
                <a href="notifications.php" class="bg-purple-600 text-white px-4 py-2 rounded relative">
                    Notifications
                    <?php if ($notif_count > 0): ?>
                        <span
                            style="background:red; color:white; padding:2px 5px; border-radius:50%; position:absolute; top:-5px; right:-10px;">
                            <?php echo $notif_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

        <?php elseif ($permit_status === 'rejected'): ?>
            <div class="bg-red-100 border-l-4 border-red-500 p-5 rounded">
                <h2 class="text-xl font-bold text-red-800">Permit Rejected</h2>
                <p class="text-red-700">Your business permit has been rejected. Please contact the administrator or update
                    your permit.</p>
                <a href="shop_profile.php" class="bg-blue-600 text-white px-4 py-2 rounded mt-3 inline-block">Update Shop
                    Profile</a>
            </div>

        <?php elseif ($permit_status === 'verified' && $account_status === 'verified'): ?>
            <h2 class="text-xl font-bold"><?php echo e($shop['shop_name']); ?></h2>
            <p>Address: <?php echo e($shop['shop_address']); ?></p>
            <p>Contact: <?php echo e($shop['contact_number']); ?></p>
            <p>Shop Status: <?php echo e($shop['shop_status']); ?></p>
            <p>Permit Status: <span class="text-green-600 font-semibold">Verified</span></p>

            <?php if (!empty($shop['business_permit_file'])): ?>
                <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>"
                    class="w-64 h-auto border rounded shadow mt-3">
            <?php endif; ?>

            <?php
            $notif_count = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $owner_id AND is_read = 0"
            ))['total'];
            ?>
            <div class="mt-5">
                <a href="orders.php" class="bg-blue-600 text-white px-4 py-2 rounded">Manage Orders</a>
                <a href="update_status.php" class="bg-green-600 text-white px-4 py-2 rounded ml-2">Update Shop Status</a>
                <a href="notifications.php" class="bg-purple-600 text-white px-4 py-2 rounded relative">
                    Notifications
                    <?php if ($notif_count > 0): ?>
                        <span
                            style="background:red; color:white; padding:2px 5px; border-radius:50%; position:absolute; top:-5px; right:-10px;">
                            <?php echo $notif_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <a href="<?php echo BASE_URL; ?>backend/actions/logout.php" class="text-red-600 block mt-5">Logout</a>

</body>

</html>
