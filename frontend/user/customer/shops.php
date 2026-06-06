<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$customer_id = $_SESSION['user_id'];

$user_stmt = mysqli_prepare($conn, "SELECT account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $customer_id);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

if (($user['account_status'] ?? '') !== 'verified') {
    redirect("dashboard.php");
}

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = "%$search%";
    $stmt = mysqli_prepare($conn, "SELECT * FROM print_shops WHERE shop_name LIKE ? OR shop_address LIKE ? ORDER BY shop_name ASC");
    mysqli_stmt_bind_param($stmt, "ss", $like, $like);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM print_shops ORDER BY shop_name ASC");
}

mysqli_stmt_execute($stmt);
$shops = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Print Shops</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">

        <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
            <h1 class="text-2xl font-bold">Find Print Shops</h1>
            <p class="text-sm opacity-90 mt-1">Choose your preferred shop for printing.</p>
        </header>

        <main class="p-4 md:p-6">

            <form method="GET" class="mb-4">
                <input type="text" name="search" value="<?php echo e($search); ?>"
                    placeholder="Search shop or address..."
                    class="w-full p-3 rounded-xl border focus:outline-none focus:ring-2 focus:ring-blue-500">
            </form>

            <?php if (mysqli_num_rows($shops) == 0): ?>
                <div class="bg-white p-5 rounded-2xl shadow text-center">
                    <p class="text-gray-500">No print shops found.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php while ($shop = mysqli_fetch_assoc($shops)): ?>
                        <?php
                        $status = $shop['shop_status'] ?? 'not_accepting';

                        if ($status === 'available') {
                            $statusClass = "bg-green-100 text-green-700";
                            $statusText = "Available";
                        } elseif ($status === 'busy') {
                            $statusClass = "bg-yellow-100 text-yellow-700";
                            $statusText = "Busy";
                        } else {
                            $statusClass = "bg-red-100 text-red-700";
                            $statusText = "Not Accepting";
                        }
                        ?>

                        <div class="bg-white p-4 rounded-2xl shadow">
                            <div class="flex justify-between items-start gap-3">
                                <div>
                                    <h2 class="font-bold text-lg text-gray-800">
                                        <?php echo e($shop['shop_name']); ?>
                                    </h2>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <?php echo e($shop['shop_address']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        Contact: <?php echo e($shop['contact_number'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <span class="text-xs px-3 py-1 rounded-full <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <?php if ($status === 'available' || $status === 'busy'): ?>
                                    <a href="<?php echo BASE_URL; ?>frontend/user/customer/place_order.php?shop_id=<?php echo e($shop['shop_id']); ?>"
                                        class="flex-1 bg-blue-600 text-white text-center py-3 rounded-xl font-semibold">
                                        Order Here
                                    </a>
                                <?php else: ?>
                                    <button disabled class="flex-1 bg-gray-300 text-gray-600 py-3 rounded-xl font-semibold">
                                        Unavailable
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="px-4 py-3 rounded-xl border text-gray-600">
                                    Map
                                </button>
                            </div>
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
            <a href="place_order.php" class="py-3 text-gray-600">Order</a>
            <a href="orders.php" class="py-3 text-gray-600">Track</a>
            <a href="profile.php" class="py-3 text-gray-600">Profile</a>
        </div>
    </nav>

</body>

</html>