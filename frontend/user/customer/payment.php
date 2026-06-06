<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);

$sql = "SELECT o.*, ps.shop_name
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        WHERE o.order_id = ? AND o.customer_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    setError("Order not found.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

/* Replace this with your real GCash/Maya merchant link */
$merchant_link = "https://gcash.com";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">
<div class="max-w-md md:max-w-3xl mx-auto min-h-screen">
    <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
        <h1 class="text-2xl font-bold">Payment</h1>
        <p class="text-sm opacity-90 mt-1"><?php echo e($order['order_code']); ?></p>
    </header>

    <main class="p-4 md:p-6">
        <?php showMessage(); ?>

        <div class="bg-white p-5 rounded-2xl shadow space-y-4">
            <p><strong>Shop:</strong> <?php echo e($order['shop_name']); ?></p>
            <p><strong>Total Amount:</strong> ₱<?php echo e(number_format($order['total_amount'], 2)); ?></p>

            <a href="<?php echo e($merchant_link); ?>" target="_blank"
               class="block text-center bg-green-600 text-white py-3 rounded-xl font-semibold">
                Open Merchant Payment Link
            </a>

            <form action="<?php echo BASE_URL; ?>backend/actions/submit_payment_proof.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="order_id" value="<?php echo e($order_id); ?>">

                <div>
                    <label class="block text-sm font-semibold mb-1">Upload Payment Screenshot</label>
                    <input type="file" name="proof_of_payment_file" required class="w-full border rounded-xl p-3">
                </div>

                <button type="submit" name="submit_payment_proof"
                        class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
                    Submit Payment Proof
                </button>
            </form>
        </div>
    </main>
</div>
</body>
</html>