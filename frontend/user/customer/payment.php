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

$sql = "SELECT o.*, ps.shop_name,
               qr.gcash_account_name, qr.gcash_number, qr.gcash_qr_code, qr.instructions AS qr_instructions,
               lnk.merchant_link, lnk.instructions AS link_instructions
        FROM orders o
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        LEFT JOIN shop_payment_channels qr ON qr.shop_id = ps.shop_id
            AND qr.channel = 'gcash_qr'
            AND qr.approval_status = 'approved'
            AND qr.is_active = 1
        LEFT JOIN shop_payment_channels lnk ON lnk.shop_id = ps.shop_id
            AND lnk.channel = 'gcash_merchant_link'
            AND lnk.approval_status = 'approved'
            AND lnk.is_active = 1
        WHERE o.order_id = ? AND o.customer_id = ?" . customerOrderPrivacySql($conn, 'o') . "
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $customer_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    setError("Request not found.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$has_merchant_link = !empty($order['merchant_link']);
$has_qr_code = !empty($order['gcash_qr_code']);
$gcash_ready = $has_qr_code || $has_merchant_link;
$order['instructions'] = $order['qr_instructions'] ?? ($order['link_instructions'] ?? '');
$payment_mode_label = $has_merchant_link && $has_qr_code
    ? 'Pay using GCash Link or QR Code'
    : ($has_merchant_link ? 'Pay using GCash Link' : 'Pay using QR Code');

function customerPaymentPrintType($print_type)
{
    $print_type = trim((string) $print_type);
    return $print_type === '' || $print_type === '0' ? 'Not specified' : $print_type;
}

function customerPaymentServiceName(array $order)
{
    $instruction = trim((string) ($order['customer_instruction'] ?? ''));
    if (preg_match('/Service request:\s*([^-\.]+?)\s*-/i', $instruction, $matches)) {
        return trim($matches[1]);
    }

    return 'Document Printing';
}

$order_page_count = max(1, (int) ($order['page_count'] ?? 1));
$order_copies = max(1, (int) ($order['copies'] ?? 1));
$order_service_name = customerPaymentServiceName($order);
$is_document_payment = $order_service_name === 'Document Printing';
$payment_unit_label = in_array($order_service_name, ['Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true) ? 'piece' : 'item';
$order_unit_price = $is_document_payment
    ? (float) ($order['total_amount'] ?? 0) / $order_page_count / $order_copies
    : (float) ($order['total_amount'] ?? 0) / $order_copies;
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
                    <?php if ($gcash_ready): ?>
                        <p class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 border border-green-100">
                            <?php echo $payment_mode_label; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-gray-700 space-y-1">
                    <strong class="block text-gray-900 mb-1">Request Price Breakdown</strong>
                    <p><strong>Service:</strong> <?php echo e($order_service_name); ?></p>
                    <p><strong><?php echo $is_document_payment ? 'Paper' : 'Size'; ?>:</strong> <?php echo e($order['paper_size']); ?><?php if (!empty($order['paper_type'])): ?>, <?php echo e($order['paper_type']); ?><?php endif; ?></p>
                    <?php if ($is_document_payment || !empty($order['print_type'])): ?>
                        <p><strong><?php echo $is_document_payment ? 'Print' : 'Print Type'; ?>:</strong> <?php echo e(customerPaymentPrintType($order['print_type'])); ?></p>
                    <?php endif; ?>
                    <p><strong><?php echo $is_document_payment ? 'Paper Price' : 'Price'; ?>:</strong> &#8369;<?php echo e(number_format($order_unit_price, 2)); ?><?php echo $is_document_payment ? '/page' : ' per ' . $payment_unit_label; ?></p>
                    <p><strong>Computation:</strong>
                        <?php if ($is_document_payment): ?>
                            &#8369;<?php echo e(number_format($order_unit_price, 2)); ?> x <?php echo e($order_page_count); ?> page<?php echo $order_page_count === 1 ? '' : 's'; ?> x <?php echo e($order_copies); ?> cop<?php echo $order_copies === 1 ? 'y' : 'ies'; ?> = &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?>
                        <?php else: ?>
                            &#8369;<?php echo e(number_format($order_unit_price, 2)); ?> x <?php echo e($order_copies); ?> <?php echo e($payment_unit_label); ?><?php echo $order_copies === 1 ? '' : 's'; ?> = &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (!$gcash_ready): ?>
                    <div class="bg-yellow-100 text-yellow-800 p-4 rounded-xl text-sm">
                        This shop's GCash payment details are not approved yet. Please contact the shop or try again later.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php if ($has_merchant_link): ?>
                            <a href="<?php echo e($order['merchant_link']); ?>" target="_blank" rel="noopener"
                                class="block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold">
                                Pay via GCash
                            </a>
                        <?php endif; ?>

                        <?php if ($has_merchant_link && $has_qr_code): ?>
                            <div class="flex items-center gap-3 text-xs font-semibold text-gray-400">
                                <span class="h-px flex-1 bg-gray-200"></span>
                                <span>OR</span>
                                <span class="h-px flex-1 bg-gray-200"></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="<?php echo $has_qr_code ? 'grid md:grid-cols-[220px_1fr] gap-5 items-start' : 'space-y-3'; ?>">
                        <?php if ($has_qr_code): ?>
                            <div class="bg-gray-50 border rounded-2xl p-3">
                                <p class="text-sm font-semibold text-gray-700 mb-2 text-center">Scan QR Code</p>
                                <a href="<?php echo GCASH_QR_URL . e($order['gcash_qr_code']); ?>" target="_blank" rel="noopener" title="Click to view full size QR code" class="block">
                                    <img src="<?php echo GCASH_QR_URL . e($order['gcash_qr_code']); ?>"
                                        alt="GCash QR code for <?php echo e($order['shop_name']); ?>"
                                        class="w-full rounded-xl object-contain hover:opacity-90 transition-opacity">
                                </a>
                                <div class="mt-3 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-gray-700">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white text-blue-600">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="7" y="2" width="10" height="20" rx="2"></rect>
                                                <path d="M11 18h2"></path>
                                                <path d="M9 6h6"></path>
                                            </svg>
                                        </span>
                                        <strong class="text-gray-900">How to pay using one device?</strong>
                                    </div>
                                    <ol class="list-decimal space-y-1 pl-5 text-xs leading-5 text-gray-600">
                                        <li>Save or download the QR code image.</li>
                                        <li>Open your GCash app.</li>
                                        <li>Tap "Pay QR".</li>
                                        <li>Choose the Gallery/Image option.</li>
                                        <li>Select the saved QR code image.</li>
                                        <li>Confirm your payment.</li>
                                    </ol>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="space-y-3">
                            <?php if (!empty($order['gcash_account_name'])): ?>
                                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                    <p class="text-sm text-gray-500">GCash Account Name</p>
                                    <strong class="text-lg"><?php echo e($order['gcash_account_name']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($order['gcash_number'])): ?>
                                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                                    <p class="text-sm text-gray-500">GCash Number</p>
                                    <strong class="text-lg"><?php echo e($order['gcash_number']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="bg-gray-50 border rounded-xl p-4 text-sm text-gray-700">
                                <strong class="block text-gray-900 mb-1">Payment Instructions</strong>
                                <?php echo nl2br(e($order['instructions'])); ?>
                            </div>
                        </div>
                    </div>

                    <form action="<?php echo BASE_URL; ?>backend/actions/submit_payment_proof.php" method="POST"
                        enctype="multipart/form-data" class="space-y-3">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="order_id" value="<?php echo e($order_id); ?>">

                        <div>
                            <label class="block text-sm font-semibold mb-1">GCash Reference Number <span class="font-normal text-gray-500">(Optional)</span></label>
                            <input type="text" name="reference_number" maxlength="100"
                                placeholder="Optional if clear in screenshot"
                                class="w-full border rounded-xl p-3">
                            <p class="mt-2 text-sm text-gray-500">
                                Optional if your screenshot clearly shows the reference number.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-1">Upload Payment Screenshot</label>
                            <input type="file" name="proof_of_payment_file"
                                accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp"
                                required class="w-full border rounded-xl p-3">
                            <p class="mt-2 text-sm text-gray-500">
                                Upload a clear screenshot of your successful GCash payment.
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
