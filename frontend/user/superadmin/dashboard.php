<?php
include "../../../backend/includes/auth.php";
checkRole("super_admin");

include "../../../backend/config/db.php";
include "../../../backend/config/app.php";
include "../../../backend/includes/functions.php";

$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin'"))['total'];
$incomplete_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE account_status = 'incomplete' OR account_status IS NULL"))['total'];
$pending_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE account_status = 'pending'"))['total'];
$verified_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE account_status = 'verified'"))['total'];
$rejected_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE account_status = 'rejected'"))['total'];
$pending_permits = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'pending'"))['total'];

$shops = mysqli_query($conn, "
    SELECT ps.*, u.full_name, u.email
    FROM print_shops ps
    JOIN users u ON ps.owner_id = u.user_id
    ORDER BY ps.created_at DESC
");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Super Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <h1 class="text-2xl font-bold mb-2">Super Admin Dashboard</h1>
    <p>Welcome, <?php echo e($_SESSION['full_name']); ?>!</p>

    <?php showMessage(); ?>

    <div class="grid grid-cols-5 gap-4 mt-6">
        <div class="bg-white p-5 rounded shadow">
            <h2>Total Users</h2>
            <p class="text-3xl font-bold"><?php echo $total_users; ?></p>
        </div>

        <div class="bg-white p-5 rounded shadow border-l-4 border-yellow-400">
            <h2>Incomplete</h2>
            <p class="text-3xl font-bold text-yellow-600"><?php echo $incomplete_users; ?></p>
        </div>

        <div class="bg-white p-5 rounded shadow border-l-4 border-blue-400">
            <h2>Pending</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo $pending_users; ?></p>
        </div>

        <div class="bg-white p-5 rounded shadow border-l-4 border-green-400">
            <h2>Verified</h2>
            <p class="text-3xl font-bold text-green-600"><?php echo $verified_users; ?></p>
        </div>

        <div class="bg-white p-5 rounded shadow border-l-4 border-red-400">
            <h2>Rejected</h2>
            <p class="text-3xl font-bold text-red-600"><?php echo $rejected_users; ?></p>
        </div>
    </div>

    <div class="mt-4">
        <div class="bg-white p-5 rounded shadow border-l-4 border-purple-400 inline-block">
            <h2>Pending Permits (Shops)</h2>
            <p class="text-3xl font-bold text-purple-600"><?php echo $pending_permits; ?></p>
        </div>
    </div>

    <div class="mt-6 space-x-3">
        <a href="manage_users.php" class="bg-blue-600 text-white px-4 py-2 rounded">Manage Users</a>
        <a href="activity_logs.php" class="bg-gray-700 text-white px-4 py-2 rounded">Activity Logs</a>
        <a href="reports.php" class="bg-purple-600 text-white px-4 py-2 rounded">Reports</a>
        <a href="<?php echo BASE_URL; ?>backend/actions/logout.php"
            class="bg-red-600 text-white px-4 py-2 rounded">Logout</a>
    </div>

    <div class="bg-white p-5 rounded shadow mt-6">
        <h2 class="text-xl font-bold mb-4">Print Shop Permit Verification</h2>

        <?php while ($shop = mysqli_fetch_assoc($shops)): ?>
            <div class="border p-4 rounded mb-4">
                <h3 class="font-bold text-lg"><?php echo e($shop['shop_name']); ?></h3>
                <p>Owner: <?php echo e($shop['full_name']); ?></p>
                <p>Email: <?php echo e($shop['email']); ?></p>
                <p>Address: <?php echo e($shop['shop_address']); ?></p>
                <p>Shop Status: <?php echo e($shop['shop_status']); ?></p>
                <p>Permit Status: <strong><?php echo e($shop['permit_status'] ?? 'pending'); ?></strong></p>

                <?php if (!empty($shop['business_permit_file'])): ?>
                    <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>"
                        class="w-64 h-auto border rounded mt-3" alt="Business Permit">
                <?php else: ?>
                    <p class="text-red-600 mt-2">No permit uploaded.</p>
                <?php endif; ?>

                <div class="mt-4 space-x-2">
                    <a href="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php?shop_id=<?php echo $shop['shop_id']; ?>&status=verified"
                        class="bg-green-600 text-white px-3 py-2 rounded">
                        Approve
                    </a>

                    <a href="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php?shop_id=<?php echo $shop['shop_id']; ?>&status=rejected"
                        class="bg-red-600 text-white px-3 py-2 rounded">
                        Reject
                    </a>


                </div>
            </div>
        <?php endwhile; ?>
    </div>

</body>

</html>