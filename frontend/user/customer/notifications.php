<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$read_stmt = mysqli_prepare($conn, $read_sql);
mysqli_stmt_bind_param($read_stmt, "i", $customer_id);
mysqli_stmt_execute($read_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

<div class="max-w-md md:max-w-5xl mx-auto min-h-screen">
    <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
        <h1 class="text-2xl font-bold">Notifications</h1>
        <p class="text-sm opacity-90 mt-1">View your order updates.</p>
    </header>

    <main class="p-4 md:p-6">
        <?php if (mysqli_num_rows($result) == 0): ?>
            <div class="bg-white p-5 rounded-2xl shadow text-center">
                <p class="text-gray-500">No notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php while ($note = mysqli_fetch_assoc($result)): ?>
                    <div class="bg-white p-4 rounded-2xl shadow">
                        <p class="text-sm text-gray-800">
                            <?php echo e($note['message']); ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-2">
                            <?php echo e($note['created_at']); ?>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

 <nav class="fixed bottom-0 left-0 right-0 bg-white border-t shadow md:static md:shadow-none md:border md:rounded-2xl md:mt-6">
        <div class="max-w-md md:max-w-6xl mx-auto grid grid-cols-5 text-center text-xs">
            <a href="dashboard.php" class="py-3 text-gray-600">Home</a>
            <a href="shops.php" class="py-3 text-blue-700 font-bold">Shops</a>
            <a href="shopLocation.php" class="py-3 text-gray-600">Map</a>
            <a href="orders.php" class="py-3 text-gray-600">Track</a>
            <a href="profile.php" class="py-3 text-gray-600">Profile</a>
        </div>
    </nav>

</body>
</html>
