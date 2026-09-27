<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/config/cloudinary.php";

requireCustomerFeatureAccess($conn);

$customer_id = $_SESSION['user_id'];
$search = trim($_GET['order_code'] ?? '');
$focus_order_id = max(0, (int) ($_GET['focus_order_id'] ?? 0));
$focus_order_code = trim($_GET['focus_order_code'] ?? '');
$allowed_tabs = ['active', 'completed', 'cancelled'];
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
    $sql = "SELECT COUNT(*) AS total FROM orders WHERE customer_id = ?" . customerOrderPrivacySql($conn);

    if ($status_tab === 'active') {
        $sql .= " AND COALESCE(NULLIF(order_status, ''), 'pending') IN ('pending', 'accepted', 'processing', 'ready_for_pickup')";
    } elseif ($status_tab === 'cancelled') {
        $sql .= " AND order_status = 'cancelled'";
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
    'cancelled' => countCustomerOrdersByTab($conn, $customer_id, 'cancelled'),
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
        WHERE o.customer_id = ?" . customerOrderPrivacySql($conn, 'o');

if ($status_tab === 'active') {
    $sql .= " AND COALESCE(NULLIF(o.order_status, ''), 'pending') IN ('pending', 'accepted', 'processing', 'ready_for_pickup')";
} elseif ($status_tab === 'cancelled') {
    $sql .= " AND o.order_status = 'cancelled'";
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

function customerOrderServiceName(array $order)
{
    $instruction = trim((string) ($order['customer_instruction'] ?? ''));
    if (preg_match('/Service request:\s*([^-\.]+?)\s*-/i', $instruction, $matches)) {
        return trim($matches[1]);
    }

    $paper_type = trim((string) ($order['paper_type'] ?? ''));
    $document_paper_types = [
        'standard',
        'bond',
        'bond paper',
        'glossy',
        'matte',
        'plain',
        'regular',
        'specialty',
    ];

    if ($paper_type !== '' && !in_array(strtolower($paper_type), $document_paper_types, true)) {
        return $paper_type;
    }

    return 'Document Printing';
}

function customerOrderPaymentLabel(array $order)
{
    if (($order['payment_status'] ?? '') === 'paid' && ($order['verification_status'] ?? '') === 'verified') {
        return 'Paid';
    }

    if (($order['verification_status'] ?? '') === 'pending') {
        return 'For Verification';
    }

    if (($order['verification_status'] ?? '') === 'rejected') {
        return 'Rejected';
    }

    return 'Waiting for Payment';
}

function customerOrderPaymentClass(string $label)
{
    return match ($label) {
        'Paid' => 'customer-payment-paid',
        'For Verification' => 'customer-payment-pending',
        'Waiting for Payment' => 'customer-payment-pending',
        'Rejected' => 'customer-payment-failed',
        default => 'customer-payment-pending',
    };
}

function customerOrderUnitPrice(array $order)
{
    $page_count = max(1, (int) ($order['page_count'] ?? 1));
    $copies = max(1, (int) ($order['copies'] ?? 1));

    return (float) ($order['total_amount'] ?? 0) / $page_count / $copies;
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
        'accepted' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        
        default => ucfirst(str_replace('_', ' ', $status))
    };
}

function customerOrderProgressStatuses()
{
    return ['pending', 'accepted', 'processing', 'ready_for_pickup', 'completed'];
}

function customerOrderProgressIndex($status)
{
    $progress_statuses = customerOrderProgressStatuses();
    $current_progress = array_search(normalizeOrderStatus($status), $progress_statuses, true);

    return $current_progress === false ? null : $current_progress;
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
    <title>My Requests</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">

        <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">
            <?php renderCustomerLayout(['title' => 'My Requests', 'subtitle' => 'Track your print requests and payments.']); ?>

            <main class="p-4 md:p-6 customer-requests-main">
                <form method="GET" class="customer-request-search-panel" data-live-search-form data-live-target="customer_orders" data-live-min="1">
                    <input type="hidden" name="status" value="<?php echo e($status_tab); ?>">
                    <label class="customer-request-search-field">
                        <?php echo customerIcon('search'); ?>
                        <input type="text" name="order_code" value="<?php echo e($search); ?>"
                            placeholder="Search request code">
                    </label>
                    <button class="customer-request-search-button">Search</button>
                </form>

                <nav class="customer-request-tabs" aria-label="Request filters" data-live-region="customer-order-tabs">
                    <?php
                    $order_tabs = [
                        'active' => ['label' => 'Active', 'icon' => 'wallet'],
                        'completed' => ['label' => 'Completed', 'icon' => 'check'],
                        'cancelled' => ['label' => 'Declined', 'icon' => 'x-circle'],
                    ];
                    foreach ($order_tabs as $tab_key => $tab):
                        $is_active_tab = $status_tab === $tab_key;
                    ?>
                        <a href="<?php echo e(customerOrdersUrl($tab_key, $search)); ?>"
                            class="customer-request-tab customer-request-tab--<?php echo e($tab_key); ?> <?php echo $is_active_tab ? 'is-active' : ''; ?>">
                            <?php echo customerIcon($tab['icon']); ?>
                            <span><?php echo e($tab['label']); ?></span>
                            <b>
                                (<?php echo (int) $tab_counts[$tab_key]; ?>)
                            </b>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <?php if (mysqli_num_rows($result) == 0): ?>
                    <div class="customer-request-empty bg-white p-5 rounded-2xl shadow text-center" data-live-region="customer-order-results">
                        <p class="text-gray-500">No requests found.</p>
                        <a href="explore.php?view=all" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-xl">Request
                            Print</a>
                    </div>
                <?php else: ?>
                    <div class="customer-request-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" data-live-region="customer-order-results">
                        <?php while ($order = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0);
                            $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                            $order_copies = max(1, (int) ($order['copies'] ?? 1));
                            $order_unit_price = customerOrderUnitPrice($order);
                            $order_service_name = customerOrderServiceName($order);
                            $is_document_request = $order_service_name === 'Document Printing';
                            $is_photo_request = $order_service_name === 'Photo Printing';
                            $is_tarpaulin_request = $order_service_name === 'Tarpaulin Printing';
                            $is_id_request = $order_service_name === 'ID Printing';
                            $is_invitation_card_request = $order_service_name === 'Invitation / Card Printing';
                            $is_detailed_service_request = $is_photo_request || $is_tarpaulin_request || $is_id_request || $is_invitation_card_request;
                            $order_unit_label = ($is_tarpaulin_request || $is_id_request || $is_invitation_card_request) ? 'piece' : 'item';
                            $order_instruction = trim((string) ($order['customer_instruction'] ?? ''));
                            $order_instruction = trim((string) preg_replace('/^Service request:.*?\.\s*/is', '', $order_instruction));
                            if ($order_instruction === '') {
                                $order_instruction = 'No instruction provided';
                            }
                            $order_payment_label = customerOrderPaymentLabel($order);
                            $progress_statuses = customerOrderProgressStatuses();
                            $current_progress = customerOrderProgressIndex($order['order_status']);
                            $file_stmt = mysqli_prepare($conn, "SELECT * FROM uploaded_files WHERE order_id = ? LIMIT 1");
                            mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                            mysqli_stmt_execute($file_stmt);
                            $file = mysqli_fetch_assoc(mysqli_stmt_get_result($file_stmt));
                            static $order_notes_supported = null;
                            if ($order_notes_supported === null) {
                                $notes_check = mysqli_query($conn, "SHOW TABLES LIKE 'order_notes'");
                                $order_notes_supported = ($notes_check && mysqli_num_rows($notes_check) > 0);
                            }
                            $customer_notes = [];
                            if ($order_notes_supported) {
                                $notes_stmt = mysqli_prepare($conn, "SELECT note_type, note_text, created_at FROM order_notes WHERE order_id = ? ORDER BY created_at DESC, note_id DESC LIMIT 1");
                                if ($notes_stmt) {
                                    mysqli_stmt_bind_param($notes_stmt, "i", $order['order_id']);
                                    mysqli_stmt_execute($notes_stmt);
                                    $notes_result = mysqli_stmt_get_result($notes_stmt);
                                    while ($note_row = mysqli_fetch_assoc($notes_result)) {
                                        $customer_notes[] = $note_row;
                                    }
                                }
                            }
                            ?>

                            <div <?php echo $is_focused_order ? 'id="focused-order"' : ''; ?>
                                data-request-details
                                class="customer-request-card bg-white p-5 rounded-2xl shadow <?php echo $is_focused_order ? 'ring-2 ring-blue-500 border border-blue-300' : ''; ?>">
                                <div class="flex justify-between items-start gap-3">
                                    <div class="customer-request-card-header">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="customer-request-code font-bold text-lg"><?php echo e($order['order_code']); ?></h2>
                                            <?php if ($is_focused_order): ?>
                                                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700">Selected</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="customer-request-shop text-sm text-gray-500"><?php echo e($order['shop_name']); ?></p>
                                    </div>

                                    <span
                                        class="customer-request-status-pill text-xs px-3 py-1 rounded-full <?php echo orderBadge($order['order_status']); ?>">
                                        <?php echo e(orderStatusLabel($order['order_status'])); ?>
                                    </span>
                                </div>

                                <?php if ($current_progress !== null): ?>
                                    <ol class="customer-order-progress customer-request-progress" aria-label="Request progress">
                                        <?php foreach ($progress_statuses as $index => $progress_status): ?>
                                            <?php $state = $index < $current_progress ? 'complete' : ($index === $current_progress ? 'current' : 'upcoming'); ?>
                                            <li class="<?php echo e($state); ?>" title="<?php echo e(orderStatusLabel($progress_status)); ?>" <?php echo $state === 'current' ? 'aria-current="step"' : ''; ?>>
                                                <span><?php echo $index < $current_progress ? customerIcon('check') : $index + 1; ?></span>
                                                <strong><?php echo e(orderStatusLabel($progress_status)); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php endif; ?>

                                <div class="customer-request-details mt-4 text-sm text-gray-700">
                                    <div class="customer-request-section">
                                        <div class="customer-request-section-title">Request Information</div>
                                        <div class="customer-request-detail-row">
                                            <strong>File:</strong>
                                            <?php if (!empty($file) && !empty($file['file_name'])): ?>
                                                <span>
                                                    <a href="<?php echo e($file['file_path']); ?>"
                                                        target="_blank" rel="noopener"
                                                        class="customer-request-file-link"
                                                        title="<?php echo e($file['file_name']); ?>">
                                                        <?php echo e($file['file_name']); ?>
                                                    </a>
                                                </span>
                                            <?php else: ?>
                                                <span>No file attached</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="customer-request-detail-row">
                                            <strong>Service:</strong>
                                            <span><?php echo e($order_service_name); ?></span>
                                        </div>
                                        <div class="customer-request-detail-row">
                                            <strong>Instruction:</strong>
                                            <span class="customer-request-instruction"><?php echo e($order_instruction); ?></span>
                                        </div>
                                        <div class="customer-request-detail-row">
                                            <strong>Pickup:</strong>
                                            <span><?php echo e(formatDateTime12Hour($order['pickup_datetime'])); ?></span>
                                        </div>
                                        <?php if (normalizeOrderStatus($order['order_status']) === 'ready_for_pickup'): ?>
                                            <div class="customer-request-alert customer-request-alert--pending mt-2 p-3 rounded-xl text-sm">
                                                <span>Show this request code when claiming your order at the shop.</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="customer-request-section">
                                        <div class="customer-request-section-title">Payment Information</div>
                                        <div class="customer-request-detail-row">
                                            <strong>Total:</strong>
                                            <span class="customer-request-total">&#8369;<?php echo e(number_format($order['total_amount'], 2)); ?></span>
                                        </div>
                                        <div class="customer-request-detail-row">
                                            <strong>Status:</strong>
                                            <span class="<?php echo e(customerOrderPaymentClass($order_payment_label)); ?>"><?php echo e($order_payment_label); ?></span>
                                        </div>
                                        <?php if (!empty($order['reference_number'])): ?>
                                            <div class="customer-request-detail-row">
                                                <strong>GCash Ref:</strong>
                                                <span><?php echo e($order['reference_number']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($customer_notes)): ?>
                                        <div class="customer-request-section">
                                            <div class="customer-request-section-title">Shop Pickup Notes</div>
                                            <?php foreach ($customer_notes as $customer_note): ?>
                                                <?php $is_refund_note = ($customer_note['note_type'] ?? '') === 'refund'; ?>
                                                <div class="customer-request-alert mt-2 p-3 rounded-xl text-sm <?php echo $is_refund_note ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                                    <p class="font-semibold"><?php echo $is_refund_note ? 'Refund on pickup' : 'Additional balance on pickup'; ?></p>
                                                    <p class="mt-1"><?php echo e($customer_note['note_text']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="customer-request-more" data-request-more hidden>
                                        <div class="customer-request-section">
                                            <div class="customer-request-section-title">Printing Details</div>
                                            <?php if ($is_document_request): ?>
                                                <div class="customer-request-detail-row"><strong>Paper:</strong> <span><?php echo e($order['paper_size']); ?><?php if (!empty($order['paper_type'])): ?>, <?php echo e($order['paper_type']); ?><?php endif; ?></span></div>
                                            <?php else: ?>
                                                <div class="customer-request-detail-row"><strong>Size:</strong> <span><?php echo e($order['paper_size']); ?></span></div>
                                                <?php if ($is_detailed_service_request && !empty($order['paper_type'])): ?>
                                                    <div class="customer-request-detail-row"><strong><?php echo ($is_tarpaulin_request || $is_id_request) ? 'Material:' : 'Paper Type:'; ?></strong> <span><?php echo e($order['paper_type']); ?></span></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($is_document_request || $is_detailed_service_request): ?>
                                                <div class="customer-request-detail-row"><strong><?php echo $is_document_request ? 'Print:' : 'Print Type:'; ?></strong> <span><?php echo e(displayPrintType($order['print_type'])); ?></span></div>
                                            <?php endif; ?>
                                            <?php if ($is_document_request): ?>
                                                <div class="customer-request-detail-row"><strong>Pages:</strong> <span><?php echo e($order_page_count); ?></span></div>
                                                <div class="customer-request-detail-row"><strong>Copies:</strong> <span><?php echo e($order_copies); ?></span></div>
                                            <?php else: ?>
                                                <div class="customer-request-detail-row"><strong>Quantity:</strong> <span><?php echo e($order_copies); ?></span></div>
                                            <?php endif; ?>
                                            <div class="customer-request-detail-row"><strong><?php echo $is_document_request ? 'Paper Price:' : 'Price:'; ?></strong> <span>&#8369;<?php echo e(number_format($order_unit_price, 2)); ?><?php echo $is_document_request ? '/page' : ' per ' . $order_unit_label; ?></span></div>
                                            <div class="customer-request-detail-row">
                                                <strong>Computation:</strong>
                                                <span>
                                                    <?php if ($is_document_request): ?>
                                                        &#8369;<?php echo e(number_format($order_unit_price, 2)); ?> x <?php echo e($order_page_count); ?> page<?php echo $order_page_count === 1 ? '' : 's'; ?> x <?php echo e($order_copies); ?> cop<?php echo $order_copies === 1 ? 'y' : 'ies'; ?> = &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?>
                                                    <?php else: ?>
                                                        &#8369;<?php echo e(number_format($order_unit_price, 2)); ?> x <?php echo e($order_copies); ?> <?php echo e($order_unit_label); ?><?php echo $order_copies === 1 ? '' : 's'; ?> = &#8369;<?php echo e(number_format($order['total_amount'], 2)); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <?php if ($order['order_status'] !== 'cancelled'): ?>
                                <?php if (!empty($order['proof_of_payment_file'])): ?>
                                    <?php $proof_ext = strtolower(pathinfo($order['proof_of_payment_file'], PATHINFO_EXTENSION)); ?>
                                    <div class="customer-request-action-row">
                                        <button type="button" class="proof-view-btn customer-request-proof-button"
                                            data-proof-url="<?php echo BASE_URL . e($order['proof_of_payment_file']); ?>?v=<?php echo time(); ?>"
                                            data-proof-type="<?php echo e($proof_ext); ?>">
                                            <?php echo customerIcon('orders'); ?>
                                            <span>View Uploaded Proof</span>
                                        </button>
                                        <button type="button" class="customer-request-more-toggle" data-request-toggle aria-expanded="false">
                                            Show more
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="customer-request-action-row customer-request-action-row--single">
                                        <button type="button" class="customer-request-more-toggle" data-request-toggle aria-expanded="false">
                                            Show more
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <?php if (($order['verification_status'] ?? '') === 'rejected'): ?>
                                    <div class="customer-request-alert customer-request-alert--failed mt-4 bg-red-100 text-red-700 p-3 rounded-xl text-sm">
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
                                        class="customer-request-pay-link block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold mt-4">
                                        Submit New Proof
                                    </a>

                                <?php elseif (($order['verification_status'] ?? '') === 'pending'): ?>
                                    <p class="customer-request-alert customer-request-alert--pending mt-4 bg-yellow-100 text-yellow-700 p-3 rounded-xl text-sm">
                                        Payment submitted for verification.
                                    </p>

                                <?php elseif (($order['payment_status'] ?? '') === 'paid'): ?>
                                    <p class="customer-request-alert customer-request-alert--success mt-4 bg-green-100 text-green-700 p-3 rounded-xl text-sm">
                                        <?php echo customerIcon('check'); ?>
                                        <span>
                                        Payment verified.
                                        </span>
                                    </p>

                                <?php else: ?>
                                    <a href="payment.php?order_id=<?php echo e($order['order_id']); ?>"
                                        class="customer-request-pay-link block text-center w-full bg-green-600 text-white py-3 rounded-xl font-semibold mt-4">
                                        Pay Now
                                    </a>
                                <?php endif; ?>
                                <?php else: ?>
                                    <div class="customer-request-action-row customer-request-action-row--single">
                                        <button type="button" class="customer-request-more-toggle" data-request-toggle aria-expanded="false">
                                            Show more
                                        </button>
                                    </div>
                                    <div class="customer-request-alert customer-request-alert--declined mt-4 bg-red-50 text-red-700 p-3 rounded-xl text-sm">
                                        This request was declined by the shop. You may safely remove it from your list.
                                    </div>
                                <?php endif; ?>

                                <?php if (in_array($status_tab, ['completed', 'cancelled'], true)): ?>
                                    <form action="<?php echo BASE_URL; ?>backend/actions/delete_customer_completed_order.php"
                                        method="POST"
                                        class="mt-3"
                                        data-delete-request-form>
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                        <input type="hidden" name="redirect_tab" value="<?php echo e($status_tab); ?>">
                                        <input type="hidden" name="delete_completed_order" value="1">
                                        <button type="button"
                                            data-open-delete-request
                                            class="w-full border border-red-200 text-red-600 bg-red-50 py-3 rounded-xl font-semibold hover:bg-red-100 transition">
                                            Remove
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <?php renderCustomerLayoutEnd('orders'); ?>

        <div class="customer-request-delete-modal" data-delete-request-modal hidden>
            <div class="customer-request-delete-backdrop" data-close-delete-request></div>
            <section class="customer-request-delete-panel" role="dialog" aria-modal="true" aria-labelledby="deleteRequestTitle" tabindex="-1">
                <div class="customer-request-delete-icon" aria-hidden="true">!</div>
                <div class="customer-request-delete-copy">
                    <h2 id="deleteRequestTitle">Remove this request?</h2>
                    <p>This only hides the request from your history. The print shop keeps the record for payment and service tracking.</p>
                </div>
                <div class="customer-request-delete-actions">
                    <button type="button" class="customer-request-delete-cancel" data-close-delete-request>Cancel</button>
                    <button type="button" class="customer-request-delete-confirm" data-confirm-delete-request>Remove Request</button>
                </div>
            </section>
        </div>

        <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
            const focusedOrder = document.getElementById('focused-order');
            if (focusedOrder) {
                focusedOrder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            document.addEventListener('click', function (event) {
                const toggle = event.target.closest('[data-request-toggle]');
                if (!toggle) return;

                const details = toggle.closest('[data-request-details]');
                const more = details ? details.querySelector('[data-request-more]') : null;
                if (!more) return;

                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                more.hidden = isExpanded;
                toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                toggle.textContent = isExpanded ? 'Show more' : 'Show less';
            });

            (function () {
                const modal = document.querySelector('[data-delete-request-modal]');
                const panel = modal ? modal.querySelector('.customer-request-delete-panel') : null;
                const confirmButton = modal ? modal.querySelector('[data-confirm-delete-request]') : null;
                let pendingForm = null;

                function openDeleteModal(form) {
                    pendingForm = form;
                    modal.hidden = false;
                    document.body.classList.add('customer-request-delete-open');
                    window.setTimeout(function () {
                        modal.classList.add('is-visible');
                        if (panel) panel.focus();
                    }, 10);
                }

                function closeDeleteModal() {
                    modal.classList.remove('is-visible');
                    document.body.classList.remove('customer-request-delete-open');
                    pendingForm = null;
                    window.setTimeout(function () {
                        modal.hidden = true;
                    }, 160);
                }

                if (modal) {
                    document.addEventListener('click', function (event) {
                        const openButton = event.target.closest('[data-open-delete-request]');
                        if (openButton) {
                            event.preventDefault();
                            openDeleteModal(openButton.closest('[data-delete-request-form]'));
                            return;
                        }

                        if (event.target.closest('[data-close-delete-request]')) {
                            event.preventDefault();
                            closeDeleteModal();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !modal.hidden) {
                            closeDeleteModal();
                        }
                    });

                    if (confirmButton) {
                        confirmButton.addEventListener('click', function () {
                            if (!pendingForm) return;
                            confirmButton.disabled = true;
                            confirmButton.textContent = 'Removing...';
                            pendingForm.submit();
                        });
                    }
                }
            })();
        </script>

</body>

</html>
