<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];
$search = trim($_GET['order_code'] ?? '');

$sql = "SELECT o.*, ps.shop_name, 
               p.payment_status, 
               p.verification_status,
               p.proof_of_payment_file,
               p.rejection_reason
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        LEFT JOIN payments p ON p.payment_id = (
            SELECT p2.payment_id 
            FROM payments p2
            WHERE p2.order_id = o.order_id
            ORDER BY p2.created_at DESC, p2.payment_id DESC
            LIMIT 1
        )
        WHERE o.customer_id = ?";

if ($search !== '') {
    $sql .= " AND o.order_code LIKE ?";
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($search !== '') {
    $like = "%$search%";
    mysqli_stmt_bind_param($stmt, "is", $customer_id, $like);
} else {
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function orderBadge($status)
{
    return match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-700',
        'accepted' => 'bg-blue-100 text-blue-700',
        'processing' => 'bg-purple-100 text-purple-700',
        'ready_for_pickup' => 'bg-green-100 text-green-700',
        'completed' => 'bg-gray-200 text-gray-700',
        default => 'bg-gray-100 text-gray-700'
    };
}

function orderStatusLabel($status)
{
    return match ($status) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst(str_replace('_', ' ', $status))
    };
}
//12hrs format with month day, year
function formatDateTime12Hour($datetime)
{
    if (empty($datetime)) {
        return 'Not set';
    }

    return date("F d, Y h:i A", strtotime($datetime));
}
?>



<!DOCTYPE html>
<html>

<head>
    <title>My Orders</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">

        <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">
            <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
                <h1 class="text-2xl font-bold">My Orders</h1>
                <p class="text-sm opacity-90 mt-1">Track your print requests and payments.</p>
            </header>

            <main class="p-4 md:p-6">
                <?php showMessage(); ?>

                <form method="GET" class="flex gap-2 mb-4">
                    <input type="text" name="order_code" value="<?php echo e($search); ?>"
                        placeholder="Search order code" class="flex-1 border rounded-xl p-3">
                    <button class="bg-blue-600 text-white px-4 rounded-xl">Search</button>
                </form>

                <?php if (mysqli_num_rows($result) == 0): ?>
                    <div class="bg-white p-5 rounded-2xl shadow text-center">
                        <p class="text-gray-500">No orders found.</p>
                        <a href="shops.php" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-xl">Order
                            Now</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php while ($order = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $file_stmt = mysqli_prepare($conn, "SELECT * FROM uploaded_files WHERE order_id = ? LIMIT 1");
                            mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                            mysqli_stmt_execute($file_stmt);
                            $file = mysqli_fetch_assoc(mysqli_stmt_get_result($file_stmt));
                            ?>

                            <div class="bg-white p-5 rounded-2xl shadow">
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <h2 class="font-bold text-lg"><?php echo e($order['order_code']); ?></h2>
                                        <p class="text-sm text-gray-500"><?php echo e($order['shop_name']); ?></p>
                                    </div>

                                    <span
                                        class="text-xs px-3 py-1 rounded-full <?php echo orderBadge($order['order_status']); ?>">
                                        <?php echo e(orderStatusLabel($order['order_status'])); ?>
                                    </span>
                                </div>

                                <div class="mt-4 text-sm text-gray-700 space-y-1">
                                    <p><strong>Instruction:</strong>
                                        <?php if (!empty($order['customer_instruction'])): ?>
                                            <?php echo e($order['customer_instruction']); ?>
                                        <?php else: ?>
                                            No instruction provided
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Paper:</strong> <?php echo e($order['paper_size']); ?>,
                                        <?php echo e($order['paper_type']); ?>
                                    </p>
                                    <p><strong>Print:</strong> <?php echo e($order['print_type']); ?></p>
                                    <p><strong>Copies:</strong> <?php echo e($order['copies']); ?></p>
                                    <p><strong>Pickup:</strong> <?php echo e(formatDateTime12Hour($order['pickup_datetime'])); ?></p>
                                    <p><strong>Total:</strong> ₱<?php echo e(number_format($order['total_amount'], 2)); ?></p>
                                    <p><strong>Payment:</strong>
                                        <?php
                                        if (($order['payment_status'] ?? '') === 'paid') {
                                            echo "Paid";
                                        } elseif (($order['verification_status'] ?? '') === 'pending') {
                                            echo "Pending Verification";
                                        } elseif (($order['verification_status'] ?? '') === 'rejected') {
                                            echo "Rejected";
                                        } else {
                                            echo "Unpaid";
                                        }
                                        ?>
                                    </p>
                                </div>

                                <?php if (!empty($order['proof_of_payment_file'])): ?>
                                    <button type="button" class="text-blue-600 font-semibold proof-view-btn"
                                        data-proof="<?php echo BASE_URL . e($order['proof_of_payment_file']); ?>?v=<?php echo time(); ?>">
                                        View Uploaded Proof
                                    </button>
                                <?php endif; ?>

                                <?php if (empty($order['payment_status']) || $order['payment_status'] === 'unpaid'): ?>
                                    <a href="payment.php?order_id=<?php echo e($order['order_id']); ?>"
                                        class="block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold mt-4">
                                        Pay Now
                                    </a>
                                <?php elseif (($order['verification_status'] ?? '') === 'pending'): ?>
                                    <p class="mt-4 bg-yellow-100 text-yellow-700 p-3 rounded-xl text-sm">
                                        Payment proof submitted. Waiting for shop verification.
                                    </p>
                                <?php elseif ($order['payment_status'] === 'paid'): ?>
                                    <p class="mt-4 bg-green-100 text-green-700 p-3 rounded-xl text-sm">
                                        Payment verified.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <nav
            class="fixed bottom-0 left-0 right-0 bg-white border-t shadow md:static md:shadow-none md:border md:rounded-2xl md:mt-6">
            <div class="max-w-md md:max-w-6xl mx-auto grid grid-cols-5 text-center text-xs">
                <a href="dashboard.php" class="py-3 text-gray-600">Home</a>
                <a href="shops.php" class="py-3 text-gray-600">Shops</a>
                <a href="shops.php" class="py-3 text-gray-600">Order</a>
                <a href="orders.php" class="py-3 text-blue-700 font-bold">Track</a>
                <a href="profile.php" class="py-3 text-gray-600">Profile</a>
            </div>
        </nav>

        <div id="proofModal" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-3xl w-full p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-bold text-lg">Payment Proof</h2>
                    <button id="closeProofModal" class="text-red-600 font-bold">Close</button>
                </div>

                <img id="proofImage" src="" class="w-full max-h-[70vh] object-contain rounded-xl border">
            </div>
        </div>

        <script>
            document.querySelectorAll('.proof-view-btn').forEach(button => {
                button.addEventListener('click', () => {
                    document.getElementById('proofImage').src = button.dataset.proof;
                    document.getElementById('proofModal').classList.remove('hidden');
                    document.getElementById('proofModal').classList.add('flex');
                });
            });

            document.getElementById('closeProofModal').addEventListener('click', () => {
                document.getElementById('proofModal').classList.add('hidden');
                document.getElementById('proofModal').classList.remove('flex');
            });

            document.getElementById('proofModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        </script>

</body>

</html>