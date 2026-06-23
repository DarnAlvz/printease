<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/config/cloudinary.php";

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];
$search = trim($_GET['order_code'] ?? '');
$focus_order_id = max(0, (int) ($_GET['focus_order_id'] ?? 0));
$focus_order_code = trim($_GET['focus_order_code'] ?? '');
$allowed_tabs = ['active', 'completed'];
$status_tab = $_GET['status'] ?? 'active';
if (!in_array($status_tab, $allowed_tabs, true)) {
    $status_tab = 'active';
}

function customerOrdersUrl($status_tab, $search = '')
{
    $params = [];
    $params['status'] = $status_tab;
    if ($search !== '') {
        $params['order_code'] = $search;
    }

    return 'orders.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

function countCustomerOrdersByTab($conn, $customer_id, $status_tab)
{
    $sql = "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?";

    if ($status_tab === 'active') {
        $sql .= " AND COALESCE(NULLIF(order_status, ''), 'pending') IN ('pending', 'processing', 'ready_for_pickup')";
    } else {
        $sql .= " AND order_status = 'completed'";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return (int) ($row['total'] ?? 0);
}

$tab_counts = [
    'active' => countCustomerOrdersByTab($conn, $customer_id, 'active'),
    'completed' => countCustomerOrdersByTab($conn, $customer_id, 'completed'),
];

$sql = "SELECT o.*, ps.shop_name, 
               p.payment_status, 
               p.verification_status,
               p.reference_number,
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

if ($status_tab === 'active') {
    $sql .= " AND COALESCE(NULLIF(o.order_status, ''), 'pending') IN ('pending', 'processing', 'ready_for_pickup')";
} else {
    $sql .= " AND o.order_status = 'completed'";
}

if ($search !== '') {
    $sql .= " AND LOWER(o.order_code) LIKE ?";
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($search !== '') {
    $like = '%' . strtolower($search) . '%';
    mysqli_stmt_bind_param($stmt, "is", $customer_id, $like);
} else {
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function normalizeOrderStatus($status)
{
    $status = trim((string) $status);
    return $status === '' ? 'pending' : $status;
}

function displayPrintType($print_type)
{
    $print_type = trim((string) $print_type);
    return $print_type === '' || $print_type === '0' ? 'Not specified' : $print_type;
}

function orderBadge($status)
{
    return match (normalizeOrderStatus($status)) {
        'pending' => 'bg-yellow-100 text-yellow-700',
        'accepted' => 'bg-blue-100 text-blue-700',
        'processing' => 'bg-purple-100 text-purple-700',
        'ready_for_pickup' => 'bg-green-100 text-green-700',
        'completed' => 'bg-gray-200 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700'
    };
}

function orderStatusLabel($status)
{
    return match (normalizeOrderStatus($status)) {
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        
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
    <?php renderCustomerHead(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">

        <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">
            <?php renderCustomerLayout(['title' => 'My Orders', 'subtitle' => 'Track your print requests and payments.']); ?>

            <main class="p-4 md:p-6">
                <form method="GET" class="flex gap-2 mb-4" data-live-search-form data-live-target="customer_orders" data-live-min="1">
                    <input type="hidden" name="status" value="<?php echo e($status_tab); ?>">
                    <input type="text" name="order_code" value="<?php echo e($search); ?>"
                        placeholder="Search order code" class="flex-1 border rounded-xl p-3">
                    <button class="bg-blue-600 text-white px-4 rounded-xl">Search</button>
                </form>

                <nav class="grid grid-cols-2 gap-2 mb-4" aria-label="Order filters" data-live-region="customer-order-tabs">
                    <?php
                    $order_tabs = [
                        'active' => 'Active',
                        'completed' => 'Completed',
                    ];
                    foreach ($order_tabs as $tab_key => $tab_label):
                        $is_active_tab = $status_tab === $tab_key;
                    ?>
                        <a href="<?php echo e(customerOrdersUrl($tab_key, $search)); ?>"
                            class="rounded-xl border px-3 py-3 text-center text-sm font-semibold <?php echo $is_active_tab ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-200'; ?>">
                            <?php echo e($tab_label); ?>
                            <span class="<?php echo $is_active_tab ? 'text-blue-100' : 'text-gray-400'; ?>">
                                (<?php echo (int) $tab_counts[$tab_key]; ?>)
                            </span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <?php if (mysqli_num_rows($result) == 0): ?>
                    <div class="bg-white p-5 rounded-2xl shadow text-center" data-live-region="customer-order-results">
                        <p class="text-gray-500">No orders found.</p>
                        <a href="explore.php?view=all" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-xl">Order
                            Now</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" data-live-region="customer-order-results">
                        <?php while ($order = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0);
                            $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                            $file_stmt = mysqli_prepare($conn, "SELECT * FROM uploaded_files WHERE order_id = ? LIMIT 1");
                            mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                            mysqli_stmt_execute($file_stmt);
                            $file = mysqli_fetch_assoc(mysqli_stmt_get_result($file_stmt));
                            ?>

                            <div <?php echo $is_focused_order ? 'id="focused-order"' : ''; ?>
                                class="bg-white p-5 rounded-2xl shadow <?php echo $is_focused_order ? 'ring-2 ring-blue-500 border border-blue-300' : ''; ?>">
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="font-bold text-lg"><?php echo e($order['order_code']); ?></h2>
                                            <?php if ($is_focused_order): ?>
                                                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700">Selected</span>
                                            <?php endif; ?>
                                        </div>
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
                                    <p><strong>Print:</strong> <?php echo e(displayPrintType($order['print_type'])); ?></p>
                                    <p><strong>Pages:</strong> <?php echo e($order_page_count); ?></p>
                                    <p><strong>Copies:</strong> <?php echo e($order['copies']); ?></p>
                                    <p><strong>Volume:</strong> <?php echo e($order_page_count); ?> x <?php echo e($order['copies']); ?></p>
                                    <p><strong>Pickup:</strong>
                                        <?php echo e(formatDateTime12Hour($order['pickup_datetime'])); ?></p>
                                    <p><strong>Total:</strong> ₱<?php echo e(number_format($order['total_amount'], 2)); ?></p>
                                    <p><strong>Payment:</strong>
                                        <?php
                                        if (($order['payment_status'] ?? '') === 'paid' && ($order['verification_status'] ?? '') === 'verified') {
                                            echo "Paid";
                                        } elseif (($order['verification_status'] ?? '') === 'pending') {
                                            echo "Pending Verification";
                                        } elseif (($order['verification_status'] ?? '') === 'rejected') {
                                            echo "Rejected";
                                        } else {
                                            echo "Waiting for Payment";
                                        }
                                        ?>
                                    </p>
                                    <?php if (!empty($order['reference_number'])): ?>
                                        <p><strong>GCash Ref:</strong> <?php echo e($order['reference_number']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($order['proof_of_payment_file'])): ?>
                                    <?php $proof_ext = strtolower(pathinfo($order['proof_of_payment_file'], PATHINFO_EXTENSION)); ?>
                                    <button type="button" class="text-blue-600 font-semibold proof-view-btn"
                                        data-proof-url="<?php echo BASE_URL . e($order['proof_of_payment_file']); ?>?v=<?php echo time(); ?>"
                                        data-proof-type="<?php echo e($proof_ext); ?>">
                                        View Uploaded Proof
                                    </button>
                                <?php endif; ?>

                                <?php if (($order['verification_status'] ?? '') === 'rejected'): ?>
                                    <div class="mt-4 bg-red-100 text-red-700 p-3 rounded-xl text-sm">
                                        <p class="font-semibold">Payment proof rejected.</p>

                                        <?php if (!empty($order['rejection_reason'])): ?>
                                            <p class="mt-1">
                                                <strong>Reason:</strong> <?php echo e($order['rejection_reason']); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="mt-1">
                                                <strong>Reason:</strong> No reason provided.
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <a href="payment.php?order_id=<?php echo e($order['order_id']); ?>"
                                        class="block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold mt-4">
                                        Submit New Proof
                                    </a>

                                <?php elseif (($order['verification_status'] ?? '') === 'pending'): ?>
                                    <p class="mt-4 bg-yellow-100 text-yellow-700 p-3 rounded-xl text-sm">
                                        Payment proof submitted. Waiting for shop verification.
                                    </p>

                                <?php elseif (($order['payment_status'] ?? '') === 'paid'): ?>
                                    <p class="mt-4 bg-green-100 text-green-700 p-3 rounded-xl text-sm">
                                        Payment verified.
                                    </p>

                                <?php else: ?>
                                    <a href="payment.php?order_id=<?php echo e($order['order_id']); ?>"
                                        class="block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold mt-4">
                                        Pay Now
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <?php renderCustomerLayoutEnd('orders'); ?>

        <script>
            const focusedOrder = document.getElementById('focused-order');
            if (focusedOrder) {
                focusedOrder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        </script>

</body>

</html>
