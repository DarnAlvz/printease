<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/actions/pickup_reminder_checker.php";
require_once __DIR__ . "/includes/owner_layout.php";

requireCompleteShopProfile($conn);
$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

function orderPageUrl($page, $search_code, $status_filter)
{
    $params = ['page' => max(1, (int) $page)];
    if ($search_code !== '') {
        $params['order_code'] = $search_code;
    }
    if ($status_filter !== 'all') {
        $params['status'] = $status_filter;
    }
    return 'orders.php?' . http_build_query($params);
}

$owner_id = $_SESSION['user_id'];

$notif_sql = "SELECT COUNT(*) AS total
              FROM notifications
              WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_row = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt));
$notif_count = $notif_row['total'] ?? 0;

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    die("Please complete your shop profile first.");
}

$shop_id = (int) $shop['shop_id'];
$search_code = trim($_GET['order_code'] ?? '');
$focus_order_id = max(0, (int) ($_GET['focus_order_id'] ?? 0));
$focus_order_code = trim($_GET['focus_order_code'] ?? '');
$allowed_filters = ['all', 'pending', 'processing', 'ready_for_pickup', 'completed'];
$status_filter = $_GET['status'] ?? 'all';
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

$per_page = 5;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = "WHERE o.shop_id = ?";
$types = "i";
$params = [$shop_id];

if ($search_code !== '') {
    $where .= " AND LOWER(o.order_code) LIKE ?";
    $types .= "s";
    $params[] = '%' . strtolower($search_code) . '%';
}

if ($status_filter !== 'all') {
    $where .= " AND o.order_status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

$count_filtered_sql = "SELECT COUNT(*) AS total
                       FROM orders o
                       JOIN users u ON o.customer_id = u.user_id
                       $where";
$count_filtered_stmt = mysqli_prepare($conn, $count_filtered_sql);
mysqli_stmt_bind_param($count_filtered_stmt, $types, ...$params);
mysqli_stmt_execute($count_filtered_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($count_filtered_stmt))['total'] ?? 0);

$total_pages = max(1, (int) ceil($filtered_total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$focus_sort = '';
$focus_sort_types = '';
$focus_sort_params = [];
if ($focus_order_id > 0 && $focus_order_code !== '') {
    $focus_sort = 'CASE WHEN o.order_id = ? OR o.order_code = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 'is';
    $focus_sort_params = [$focus_order_id, $focus_order_code];
} elseif ($focus_order_id > 0) {
    $focus_sort = 'CASE WHEN o.order_id = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 'i';
    $focus_sort_params = [$focus_order_id];
} elseif ($focus_order_code !== '') {
    $focus_sort = 'CASE WHEN o.order_code = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 's';
    $focus_sort_params = [$focus_order_code];
}

$orders_sql = "SELECT o.*, u.full_name, u.email, p.payment_id, p.payment_status, p.verification_status,
                       p.reference_number, p.ocr_reference_number, p.payment_reference_match,
                       p.ocr_payment_date, p.proof_of_payment_file, p.rejection_reason, p.created_at
                AS payment_submitted_at
               FROM orders o
               JOIN users u ON o.customer_id = u.user_id
               LEFT JOIN payments p ON o.order_id = p.order_id
               $where
               ORDER BY $focus_sort
                        CASE o.order_status
                            WHEN 'processing' THEN 1
                            WHEN 'pending' THEN 2
                            WHEN 'ready_for_pickup' THEN 3
                            WHEN 'completed' THEN 4
                            ELSE 5
                        END,
                        o.created_at DESC
               LIMIT ? OFFSET ?";
$orders_stmt = mysqli_prepare($conn, $orders_sql);
$orders_types = $types . $focus_sort_types . "ii";
$orders_params = array_merge($params, $focus_sort_params, [$per_page, $offset]);
mysqli_stmt_bind_param($orders_stmt, $orders_types, ...$orders_params);
mysqli_stmt_execute($orders_stmt);
$result = mysqli_stmt_get_result($orders_stmt);

$orders = [];
while ($order = mysqli_fetch_assoc($result)) {
    $orders[] = $order;
}

$counts = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'ready_for_pickup' => 0,
    'completed' => 0,
];
$count_sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_for_pickup,
                SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed
              FROM orders
              WHERE shop_id = ?";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $shop_id);
mysqli_stmt_execute($count_stmt);
$counts = array_merge($counts, mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt)) ?: []);

$showing_start = $filtered_total > 0 ? $offset + 1 : 0;
$showing_end = min($offset + count($orders), $filtered_total);

// Helper function to determine payment status label
function paymentStatusLabel($payment_status, $verification_status)
{
    if ($payment_status === 'paid' && $verification_status === 'verified') {
        return 'Paid';
    } elseif ($verification_status === 'pending') {
        return 'Pending Verification';
    } elseif ($verification_status === 'rejected') {
        return 'Rejected';
    }
    return 'Unpaid';
}

ownerLayoutStart('orders', 'Order Management', '', $notif_count, $shop, $owner_toast);
?>

<nav class="orders-tabs" aria-label="Order status filters" data-live-region="owner-order-tabs">
    <?php
    $tabs = [
        'all' => ['label' => 'All Orders', 'count' => (int) $counts['total'], 'icon' => 'package'],
        'pending' => ['label' => 'Pending', 'count' => (int) $counts['pending'], 'icon' => 'clock'],
        'processing' => ['label' => 'Processing', 'count' => (int) $counts['processing'], 'icon' => 'trending-up'],
        'ready_for_pickup' => ['label' => 'Ready for Pickup', 'count' => (int) $counts['ready_for_pickup'], 'icon' => 'package-check'],
        'completed' => ['label' => 'Completed', 'count' => (int) $counts['completed'], 'icon' => 'circle-check'],
    ];
    foreach ($tabs as $key => $tab):
        $tab_url = orderPageUrl(1, $search_code, $key);
        ?>
        <a class="<?php echo $status_filter === $key ? 'active' : ''; ?>" href="<?php echo e($tab_url); ?>">
            <?php echo ownerIcon($tab['icon'], 'icon-sm'); ?>
            <?php echo e($tab['label']); ?>
            <span><?php echo (int) $tab['count']; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<section class="orders-summary-grid" data-live-region="owner-order-summary">
    <article class="orders-summary-card pending">
        <div class="orders-summary-icon"><?php echo ownerIcon('clock', 'icon'); ?></div>
        <strong><?php echo (int) $counts['pending']; ?></strong>
        <h2>Pending Orders</h2>
        <p>Awaiting acceptance</p>
    </article>
    <article class="orders-summary-card processing">
        <div class="orders-summary-icon"><?php echo ownerIcon('trending-up', 'icon'); ?></div>
        <strong><?php echo (int) $counts['processing']; ?></strong>
        <h2>Processing Orders</h2>
        <p>Currently printing</p>
    </article>
    <article class="orders-summary-card ready">
        <div class="orders-summary-icon"><?php echo ownerIcon('package', 'icon'); ?></div>
        <strong><?php echo (int) $counts['ready_for_pickup']; ?></strong>
        <h2>Ready Orders</h2>
        <p>Ready for pickup</p>
    </article>
    <article class="orders-summary-card completed">
        <div class="orders-summary-icon"><?php echo ownerIcon('circle-check', 'icon'); ?></div>
        <strong><?php echo (int) $counts['completed']; ?></strong>
        <h2>Completed Orders</h2>
        <p>Successfully picked up</p>
    </article>
</section>

<section class="orders-search-card">
    <form method="GET" data-live-search-form data-live-target="owner_orders" data-live-min="1">
        <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
        <div class="orders-search-box">
            <?php echo ownerIcon('search', 'icon'); ?>
            <input type="text" name="order_code" placeholder="Search by order code..."
                value="<?php echo e($search_code); ?>">
        </div>
        <button type="submit" class="orders-submit-hidden">Search</button>
        <?php if ($search_code !== ''): ?>
            <a href="orders.php<?php echo $status_filter !== 'all' ? '?status=' . e($status_filter) : ''; ?>"
                class="orders-clear-search" aria-label="Clear search"><?php echo ownerIcon('x', 'icon-sm'); ?></a>
        <?php endif; ?>
    </form>
</section>

<?php if (empty($orders)): ?>
    <section class="owner-card empty-state" data-live-region="owner-order-results">
        <h2>No orders found</h2>
        <p>New customer print orders will appear here.</p>
    </section>
<?php else: ?>
    <?php $order_files = []; ?>
    <section class="orders-table-card" data-live-region="owner-order-results">
        <div class="owner-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer Name</th>
                        <th>File Name</th>
                        <th>Print Details</th>
                        <th>Pickup Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                        $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                        $file_stmt = mysqli_prepare($conn, $file_sql);
                        mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                        mysqli_stmt_execute($file_stmt);
                        $files = mysqli_stmt_get_result($file_stmt);
                        $file_rows = [];
                        while ($file = mysqli_fetch_assoc($files)) {
                            $file_rows[] = $file;
                        }
                        $first_file = $file_rows[0]['file_name'] ?? 'No uploaded file';
                        $order_files[(int) $order['order_id']] = $file_rows;
                        ?>
                        <?php $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0) || ($search_code !== '' && strcasecmp($search_code, $order['order_code']) === 0); ?>
                        <tr class="<?php echo $is_focused_order ? 'order-focused' : ''; ?>"
                            data-order-row="<?php echo e($order['order_id']); ?>">
                            <td><strong><?php echo e($order['order_code']); ?></strong></td>
                            <td>
                                <strong><?php echo e($order['full_name']); ?></strong>
                                <small><?php echo e($order['email']); ?></small>
                            </td>
                            <td><?php echo e($first_file); ?></td>
                            <td>
                                <div class="print-detail-chips">
                                    <span><?php echo ownerIcon('file-text', 'icon-sm'); ?><?php echo e($order['paper_size']); ?></span>
                                    <span><?php echo ownerIcon('printer', 'icon-sm'); ?><?php echo e($order['print_type']); ?></span>
                                    <span><?php echo e($order_page_count); ?>p x<?php echo e($order['copies']); ?></span>
                                </div>
                                <small class="muted"><?php echo e($order['paper_type']); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($order['pickup_datetime'])): ?>
                                    <strong><?php echo ownerIcon('clock', 'icon-sm'); ?><?php echo e(date("g:i A", strtotime($order['pickup_datetime']))); ?></strong>
                                <?php else: ?>
                                    <span class="muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span
                                    class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                                    data-order-status-badge="<?php echo e($order['order_id']); ?>">
                                    <?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?>
                                    <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="orders-actions">
                                    <button type="button" class="btn order-btn-navy"
                                        data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View
                                        Details</button>

                                    <?php if ($owner_is_verified && $order['order_status'] === 'processing'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                                            class="orders-update-form orders-status-action">
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="ready_for_pickup">
                                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as
                                                Ready</button>
                                        </form>
                                    <?php elseif ($owner_is_verified && $order['order_status'] === 'ready_for_pickup'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                                            class="orders-update-form orders-status-action">
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="completed">
                                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as
                                                Completed</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($orders as $order): ?>
            <?php
            $file_rows = $order_files[(int) $order['order_id']] ?? [];
            $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
            ?>
            <div class="order-modal" id="order-modal-<?php echo e($order['order_id']); ?>" aria-hidden="true">
                <div class="order-modal-backdrop" data-order-modal-close></div>
                <section class="order-modal-dialog" role="dialog" aria-modal="true"
                    aria-labelledby="order-modal-title-<?php echo e($order['order_id']); ?>">
                    <header class="order-modal-header">
                        <h2 id="order-modal-title-<?php echo e($order['order_id']); ?>">Order Details</h2>
                        <button type="button" class="order-modal-close" data-order-modal-close aria-label="Close order details">
                            <?php echo ownerIcon('x', 'icon'); ?>
                        </button>
                    </header>

                    <div class="order-modal-body">
                        <section class="order-modal-note">
                            <?php echo ownerIcon('info', 'icon-sm'); ?>
                            <div>
                                <h3>Customer Instructions</h3>
                                <p><?php echo e($order['customer_instruction'] ?: 'No instruction'); ?></p>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>File Preview</h3>
                            <div class="order-file-preview">
                                <?php echo ownerIcon('file-text', 'icon-xl'); ?>
                                <?php if (empty($file_rows)): ?>
                                    <strong>No uploaded file</strong>
                                    <span>No file is attached to this order.</span>
                                <?php else: ?>
                                    <strong><?php echo e(count($file_rows) > 1 ? count($file_rows) . ' Uploaded Files' : 'PDF Document'); ?></strong>
                                    <?php foreach ($file_rows as $file): ?>
                                        <a href="<?php echo e(printEaseFileUrl($file['file_path'])); ?>"
                                            target="_blank"><?php echo e($file['file_name']); ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>Print Settings</h3>
                            <div class="order-settings-grid">
                                <div class="order-setting-card">
                                    <span>Size</span>
                                    <strong><?php echo e($order['paper_size'] ?: 'Not set'); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Color</span>
                                    <strong><?php echo e($order['print_type'] ?: 'Not set'); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Pages</span>
                                    <strong><?php echo e($order_page_count); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Copies</span>
                                    <strong><?php echo e($order['copies'] ?: 'Not set'); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Print Volume</span>
                                    <strong><?php echo e($order_page_count); ?> x <?php echo e($order['copies'] ?: 1); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Services</span>
                                    <strong><?php echo e(trim(($order['paper_type'] ?: '') . ($order['print_type'] ? ', ' . $order['print_type'] : '')) ?: 'Not set'); ?></strong>
                                </div>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>Order Information</h3>
                            <div class="order-info-list">
                                <div>
                                    <span>Order ID</span>
                                    <strong><?php echo e($order['order_code']); ?></strong>
                                </div>
                                <div>
                                    <span>Customer</span>
                                    <strong><?php echo e($order['full_name']); ?></strong>
                                </div>
                                <div>
                                    <span>Date</span>
                                    <strong><?php echo e(!empty($order['created_at']) ? date('Y-m-d', strtotime($order['created_at'])) : 'Not set'); ?></strong>
                                </div>
                                <div>
                                    <span>Preferred Pickup Time</span>
                                    <strong><?php echo e(!empty($order['pickup_datetime']) ? date('g:i A', strtotime($order['pickup_datetime'])) : 'Not set'); ?></strong>
                                </div>
                                <div>
                                    <span>Status</span>
                                    <strong><span
                                            class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                                            data-order-status-badge="<?php echo e($order['order_id']); ?>"><?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?><?php echo e(ownerStatusLabel($order['order_status'])); ?></span></strong>
                                </div>
                                <div>
                                    <span>Payment Status</span>
                                    <strong>
                                        <span class="status-badge">
                                            <?php echo e(paymentStatusLabel($order['payment_status'] ?? '', $order['verification_status'] ?? '')); ?>
                                        </span>
                                    </strong>
                                </div>

                                <div>
                                    <span>Verification Status</span>
                                    <strong>
                                        <?php
                                        if (empty($order['payment_id'])) {
                                            echo "No Payment Submitted";
                                        } elseif (($order['verification_status'] ?? '') === 'verified') {
                                            echo "Verified";
                                        } elseif (($order['verification_status'] ?? '') === 'pending') {
                                            echo "Pending Verification";
                                        } elseif (($order['verification_status'] ?? '') === 'rejected') {
                                            echo "Rejected";
                                        } else {
                                            echo "Unverified";
                                        }
                                        ?>
                                    </strong>
                                </div>

                                <?php if (!empty($order['reference_number']) && empty($order['ocr_reference_number'])): ?>
                                    <div>
                                        <span>Reference No.</span>
                                        <strong><?php echo e($order['reference_number']); ?></strong>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($order['payment_id'])): ?>
                                    <div>
                                        <span>Detected Reference No.</span>
                                        <strong><?php echo e($order['ocr_reference_number'] ?: $order['reference_number'] ?: 'Not detected'); ?></strong>
                                    </div>
                                    <div>
                                        <span>Detected Payment Date</span>
                                        <strong><?php echo !empty($order['ocr_payment_date']) ? e(date('M d, Y', strtotime($order['ocr_payment_date']))) : 'Not detected'; ?></strong>
                                    </div>
                                    <div>
                                        <span>Proof Submitted</span>
                                        <strong><?php echo !empty($order['payment_submitted_at']) ? e(date('M d, Y - g:i A', strtotime($order['payment_submitted_at']))) : 'Not available'; ?></strong>
                                    </div>
                                    <div>
                                        <span>OCR Status</span>
                                        <strong>
                                            <?php
                                            $match_status = $order['payment_reference_match'] ?: 'not_detected';
                                            $match_class = match ($match_status) {
                                                'detected' => 'status-success',
                                                'partial' => 'status-warning',
                                                'not_detected' => 'status-warning',
                                                default => 'status-info',
                                            };
                                            ?>
                                            <span class="status-badge <?php echo e($match_class); ?>">
                                                <?php echo e(ucwords(str_replace('_', ' ', $match_status))); ?>
                                            </span>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                                <div class="order-payment-proof-card">
                                    <span>Proof of Payment</span>
                                    <strong>
                                        <?php if (!empty($order['proof_of_payment_file'])): ?>
                                            <?php
                                            $proof = BASE_URL . e($order['proof_of_payment_file']);
                                            $ext = strtolower(pathinfo($order['proof_of_payment_file'], PATHINFO_EXTENSION));
                                            ?>
                                            <button type="button" class="text-blue-700 font-semibold hover:underline proof-toggle"
                                                data-proof-url="<?php echo $proof; ?>"
                                                data-proof-type="<?php echo e($ext); ?>">
                                                View Proof
                                            </button>
                                        <?php else: ?>
                                            No proof uploaded
                                        <?php endif; ?>
                                    </strong>
                                </div>

                                <?php if (!empty($order['payment_id']) && ($order['verification_status'] ?? '') === 'pending'): ?>
                                    <div class="order-payment-action-card">
                                        <span>Payment Action</span>
                                        <strong class="payment-action-group">
                                            <form action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST"
                                                class="orders-update-form">
                                                <input type="hidden" name="payment_id"
                                                    value="<?php echo e($order['payment_id']); ?>">
                                                <button type="submit" name="verify_payment" class="btn order-btn-ready">
                                                    Mark as Paid
                                                </button>
                                            </form>

                                            <button type="button" class="btn order-btn-danger reject-toggle"
                                                data-target="reject-box-<?php echo e($order['payment_id']); ?>">
                                                Reject Payment
                                            </button>

                                            <form id="reject-box-<?php echo e($order['payment_id']); ?>"
                                                action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST"
                                                class="orders-update-form" hidden>
                                                <input type="hidden" name="payment_id"
                                                    value="<?php echo e($order['payment_id']); ?>">
                                                <textarea name="rejection_reason" placeholder="Enter rejection reason"
                                                    class="payment-reject-textarea" required></textarea>
                                                <button type="submit" name="reject_payment" class="btn order-btn-danger">
                                                    Confirm Reject
                                                </button>
                                            </form>
                                        </strong>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span>Total Amount</span>
                                    <strong><?php echo ownerMoney($order['total_amount']); ?></strong>
                                </div>
                            </div>
                        </section>
                    </div>

                    <footer class="order-modal-footer">
                        <button type="button" class="btn order-modal-secondary" data-order-modal-close>Close</button>
                        <?php if ($owner_is_verified && $order['order_status'] === 'pending'): ?>
                            <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                                class="orders-update-form orders-status-action" data-accept-download-form
                                data-order-id="<?php echo e($order['order_id']); ?>">
                                <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                <input type="hidden" name="order_status" value="processing">
                                <?php foreach ($file_rows as $file): ?>
                                    <input type="hidden" data-download-url value="<?php echo e(printEaseFileUrl($file['file_path'])); ?>">
                                <?php endforeach; ?>
                                <button type="submit" name="update_order" class="btn order-modal-primary">Accept &amp; Download
                                    Order</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                </section>
            </div>
        <?php endforeach; ?>

        <div class="orders-mobile-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                $file_stmt = mysqli_prepare($conn, $file_sql);
                mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($file_stmt);
                $files = mysqli_stmt_get_result($file_stmt);
                ?>
                <?php $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0) || ($search_code !== '' && strcasecmp($search_code, $order['order_code']) === 0); ?>
                <article class="owner-card order-card-mobile <?php echo $is_focused_order ? 'order-focused' : ''; ?>"
                    data-order-card="<?php echo e($order['order_id']); ?>">
                    <div class="card-head">
                        <h2><?php echo e($order['order_code']); ?></h2>
                        <span
                            class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                            data-order-status-badge="<?php echo e($order['order_id']); ?>">
                            <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                        </span>
                    </div>
                    <p><strong>Customer:</strong> <?php echo e($order['full_name']); ?></p>
                    <p><strong>Details:</strong> <?php echo e($order['paper_size']); ?>, <?php echo e($order['paper_type']); ?>,
                        <?php echo e($order['print_type']); ?>, <?php echo e($order_page_count); ?> pages x<?php echo e($order['copies']); ?>
                    </p>
                    <p><strong>Total:</strong> <?php echo ownerMoney($order['total_amount']); ?></p>
                    <p><strong>Instruction:</strong> <?php echo e($order['customer_instruction'] ?: 'No instruction'); ?></p>
                    <div class="row-actions">
                        <button type="button" class="btn order-btn-navy"
                            data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View Details</button>
                    </div>
                    <?php if ($owner_is_verified && $order['order_status'] === 'processing'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                            class="orders-update-form orders-status-action mobile">
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="ready_for_pickup">
                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>
                        </form>
                    <?php elseif ($owner_is_verified && $order['order_status'] === 'ready_for_pickup'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                            class="orders-update-form orders-status-action mobile">
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="completed">
                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as Completed</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <footer class="orders-pagination">
            <p>Showing <strong><?php echo (int) $showing_start; ?>-<?php echo (int) $showing_end; ?></strong> of
                <?php echo (int) $filtered_total; ?> orders
            </p>
            <div>
                <?php if ($page > 1): ?>
                    <a class="pagination-btn"
                        href="<?php echo e(orderPageUrl($page - 1, $search_code, $status_filter)); ?>"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</span>
                <?php endif; ?>
                <span class="pagination-current"><?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                    <a class="pagination-btn"
                        href="<?php echo e(orderPageUrl($page + 1, $search_code, $status_filter)); ?>">Next<?php echo ownerIcon('chevron-right', 'icon-sm'); ?></a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Next<?php echo ownerIcon('chevron-right', 'icon-sm'); ?></span>
                <?php endif; ?>
            </div>
        </footer>
    </section>
<?php endif; ?>

<script>
    (function () {
        const openButtons = document.querySelectorAll('[data-order-modal-target]');
        const closeSelector = '[data-order-modal-close]';
        let activeModal = null;

        const focusedOrder = document.querySelector('.order-focused');

        function openModal(modal) {
            if (!modal) {
                return;
            }

            activeModal = modal;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('order-modal-open');
        }

        if (focusedOrder) {
            window.setTimeout(function () {
                focusedOrder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 120);
        }

        function closeModal(modal) {
            if (!modal) {
                return;
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (activeModal === modal) {
                activeModal = null;
            }
            if (!document.querySelector('.order-modal.is-open')) {
                document.body.classList.remove('order-modal-open');
            }
        }

        openButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(document.getElementById(button.dataset.orderModalTarget));
            });
        });

        document.addEventListener('click', function (event) {
            const closeTarget = event.target.closest(closeSelector);
            if (closeTarget) {
                closeModal(closeTarget.closest('.order-modal'));
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });

        function setOrderProcessing(orderId) {
            const processingIcon = <?php echo json_encode(ownerIcon('trending-up', 'icon-sm')); ?>;
            document.querySelectorAll('[data-order-status-badge="' + orderId + '"]').forEach(function (badge) {
                badge.className = 'status-badge order-status-badge order-status-processing status-info';
                badge.innerHTML = processingIcon + 'Processing';
            });
        }

        function createReadyForm(orderId, isMobile) {
            const readyForm = document.createElement('form');
            readyForm.action = '<?php echo BASE_URL; ?>backend/actions/update_order_status.php';
            readyForm.method = 'POST';
            readyForm.className = 'orders-update-form orders-status-action' + (isMobile ? ' mobile' : '');

            readyForm.innerHTML =
                '<input type="hidden" name="order_id" value="' + orderId + '">' +
                '<input type="hidden" name="order_status" value="ready_for_pickup">' +
                '<button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>';

            return readyForm;
        }

        function showReadyAction(orderId) {
            const row = document.querySelector('[data-order-row="' + orderId + '"]');
            const rowActions = row ? row.querySelector('.orders-actions') : null;
            if (rowActions && !rowActions.querySelector('.order-btn-ready')) {
                rowActions.appendChild(createReadyForm(orderId, false));
            }

            const mobileCard = document.querySelector('[data-order-card="' + orderId + '"]');
            if (mobileCard && !mobileCard.querySelector('.order-btn-ready')) {
                mobileCard.appendChild(createReadyForm(orderId, true));
            }
        }

        document.querySelectorAll('[data-accept-download-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.downloadStarted === 'true') {
                    return;
                }

                event.preventDefault();

                const urls = Array.from(form.querySelectorAll('[data-download-url]'))
                    .map(function (input) {
                        return input.value;
                    })
                    .filter(Boolean);

                form.dataset.downloadStarted = 'true';
                form.classList.add('is-loading');
                const submitButton = form.querySelector('[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Accepting...';
                }

                urls.forEach(function (url, index) {
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = '';
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.style.display = 'none';
                    document.body.appendChild(link);

                    window.setTimeout(function () {
                        link.click();
                        link.remove();
                    }, index * 150);
                });

                window.setTimeout(function () {
                    const formData = new FormData(form);
                    if (!formData.has('update_order')) {
                        formData.append('update_order', '1');
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Order update failed.');
                            }

                            const orderId = form.dataset.orderId;
                            setOrderProcessing(orderId);
                            showReadyAction(orderId);
                            form.remove();
                            window.setTimeout(function () {
                                closeModal(document.getElementById('order-modal-' + orderId));
                            }, 180);
                        })
                        .catch(function () {
                            form.dataset.downloadStarted = 'false';
                            form.classList.remove('is-loading');
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.textContent = 'Accept & Download Order';
                            }
                            window.ownerShowToast('Failed to update order status. Please try again.', 'error');
                        });
                }, Math.max(450, urls.length * 180));
            });
        });

        document.querySelectorAll('.reject-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = document.getElementById(button.dataset.target);
                if (target) target.hidden = !target.hidden;
            });
        });
    })();

</script>

<?php ownerLayoutEnd(); ?>
