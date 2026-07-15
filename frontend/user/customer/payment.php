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

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id'] ?? 0);

$sql = "SELECT o.*, ps.shop_name, sps.gcash_account_name, sps.gcash_number, sps.gcash_qr_code,
               sps.merchant_link, sps.instructions
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        LEFT JOIN shop_payment_settings sps ON sps.shop_id = ps.shop_id
            AND sps.approval_status = 'approved'
            AND sps.is_active = 1
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

$gcash_ready = !empty($order['gcash_account_name']) && !empty($order['gcash_number']) && !empty($order['gcash_qr_code']);

function customerPaymentPrintType($print_type)
{
    $print_type = trim((string) $print_type);
    return $print_type === '' || $print_type === '0' ? 'Not specified' : $print_type;
}

$order_page_count = max(1, (int) ($order['page_count'] ?? 1));
$order_copies = max(1, (int) ($order['copies'] ?? 1));
$order_unit_price = (float) ($order['total_amount'] ?? 0) / $order_page_count / $order_copies;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Payment</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>
    <div class="max-w-md md:max-w-3xl mx-auto min-h-screen">
        <?php renderCustomerLayout(['title' => 'Payment', 'subtitle' => $order['order_code']]); ?>

        <main class="p-4 md:p-6">
            <div class="bg-white p-5 rounded-2xl shadow space-y-5">
                <div class="space-y-1">
                    <p class="text-sm text-gray-500">Online GCash Payment</p>
                    <h2 class="text-xl font-bold text-gray-900"><?php echo e($order['shop_name']); ?></h2>
                    <p><strong>Total Amount:</strong> &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?></p>
                </div>

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-gray-700 space-y-1">
                    <strong class="block text-gray-900 mb-1">Order Price Breakdown</strong>
                    <p><strong>Paper:</strong> <?php echo e($order['paper_size']); ?>, <?php echo e($order['paper_type']); ?></p>
                    <p><strong>Print:</strong> <?php echo e(customerPaymentPrintType($order['print_type'])); ?></p>
                    <p><strong>Paper Price:</strong> &#8369;<?php echo e(number_format($order_unit_price, 2)); ?>/page</p>
                    <p><strong>Computation:</strong> &#8369;<?php echo e(number_format($order_unit_price, 2)); ?> x <?php echo e($order_page_count); ?> page<?php echo $order_page_count === 1 ? '' : 's'; ?> x <?php echo e($order_copies); ?> cop<?php echo $order_copies === 1 ? 'y' : 'ies'; ?> = &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?></p>
                </div>

                <?php if (!$gcash_ready): ?>
                    <div class="bg-yellow-100 text-yellow-800 p-4 rounded-xl text-sm">
                        This shop's GCash payment details are not approved yet. Please contact the shop or try again later.
                    </div>
                <?php else: ?>
                    <div class="grid md:grid-cols-[220px_1fr] gap-5 items-start">
                        <div class="bg-gray-50 border rounded-2xl p-3">
                            <img src="<?php echo GCASH_QR_URL . e($order['gcash_qr_code']); ?>"
                                alt="GCash QR code for <?php echo e($order['shop_name']); ?>"
                                class="w-full rounded-xl object-contain">
                        </div>
                        <div class="space-y-3">
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                <p class="text-sm text-gray-500">GCash Account Name</p>
                                <strong class="text-lg"><?php echo e($order['gcash_account_name']); ?></strong>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                <p class="text-sm text-gray-500">GCash Number</p>
                                <strong class="text-lg"><?php echo e($order['gcash_number']); ?></strong>
                            </div>
                            <?php if (!empty($order['merchant_link'])): ?>
                                <a href="<?php echo e($order['merchant_link']); ?>" target="_blank" rel="noopener"
                                    class="block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold">
                                    Open GCash Payment Link
                                </a>
                            <?php endif; ?>
                            <div class="bg-gray-50 border rounded-xl p-4 text-sm text-gray-700">
                                <strong class="block text-gray-900 mb-1">Payment Instructions</strong>
                                <?php echo nl2br(e($order['instructions'])); ?>
                            </div>
                            <ol class="list-decimal pl-5 text-sm text-gray-600 space-y-1">
                                <li>Open your GCash app and pay the exact total amount.</li>
                                <li>Use the QR code or GCash number shown here.</li>
                                <li>Take a screenshot of the successful payment.</li>
                                <li>Upload the receipt for verification. The system will try to detect the reference number automatically.</li>
                            </ol>
                        </div>
                    </div>

                    <form action="<?php echo BASE_URL; ?>backend/actions/submit_payment_proof.php" method="POST"
                        enctype="multipart/form-data" class="space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo e($order_id); ?>">

                        <div>
                            <label class="block text-sm font-semibold mb-1">GCash Reference Number <span class="font-normal text-gray-500">(Optional)</span></label>
                            <input type="text" name="reference_number" maxlength="100"
                                placeholder="Optional if clear in screenshot"
                                class="w-full border rounded-xl p-3">
                            <p class="mt-2 text-sm text-gray-500">
                                OCR will try to detect it automatically. Enter it manually only if the receipt is unclear or OCR cannot read it.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Upload Payment Screenshot</label>
                            <input type="file" name="proof_of_payment_file"
                                accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp"
                                required class="w-full border rounded-xl p-3">
                            <p class="mt-2 text-sm text-gray-500">
                                The shop owner will review your uploaded proof. Detected receipt details will appear on their side when available.
                            </p>
                        </div>

                        <button type="submit" name="submit_payment_proof"
                            class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
                            Submit for Verification
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php renderCustomerLayoutEnd('orders'); ?>
</body>

</html>
