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
requireVerifiedStatus($conn);

function orderPageUrl($page, $search_code, $status_filter) {
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
    $where .= " AND RIGHT(o.order_code, 4) LIKE ?";
    $types .= "s";
    $params[] = "%$search_code%";
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

$orders_sql = "SELECT o.*, u.full_name, u.email
               FROM orders o
               JOIN users u ON o.customer_id = u.user_id
               $where
               ORDER BY CASE o.order_status
                            WHEN 'processing' THEN 1
                            WHEN 'pending' THEN 2
                            WHEN 'ready_for_pickup' THEN 3
                            WHEN 'completed' THEN 4
                            ELSE 5
                        END,
                        o.created_at DESC
               LIMIT ? OFFSET ?";
$orders_stmt = mysqli_prepare($conn, $orders_sql);
$orders_types = $types . "ii";
$orders_params = array_merge($params, [$per_page, $offset]);
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

ownerLayoutStart('orders', 'Order Management', '', $notif_count, $shop);
?>

<?php showMessage(); ?>

<nav class="orders-tabs" aria-label="Order status filters">
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

<section class="orders-summary-grid">
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
        <p>Successfully delivered</p>
    </article>
</section>

<section class="orders-search-card">
    <form method="GET">
        <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
        <div class="orders-search-box">
            <?php echo ownerIcon('search', 'icon'); ?>
            <input type="text" name="order_code" placeholder="Search by order ID..." value="<?php echo e($search_code); ?>">
        </div>
        <button type="submit" class="orders-submit-hidden">Search</button>
        <?php if ($search_code !== ''): ?>
            <a href="orders.php<?php echo $status_filter !== 'all' ? '?status=' . e($status_filter) : ''; ?>" class="orders-clear-search" aria-label="Clear search"><?php echo ownerIcon('x', 'icon-sm'); ?></a>
        <?php endif; ?>
    </form>
</section>

<?php if (empty($orders)): ?>
    <section class="owner-card empty-state">
        <h2>No orders found</h2>
        <p>New customer print orders will appear here.</p>
    </section>
<?php else: ?>
    <?php $order_files = []; ?>
    <section class="orders-table-card">
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
                        <tr data-order-row="<?php echo e($order['order_id']); ?>">
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
                                    <span>x<?php echo e($order['copies']); ?></span>
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
                                <span class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>" data-order-status-badge="<?php echo e($order['order_id']); ?>">
                                    <?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?>
                                    <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="orders-actions">
                                    <button type="button" class="btn order-btn-navy" data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View Details</button>

                                    <?php if ($order['order_status'] === 'processing'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form orders-status-action">
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="ready_for_pickup">
                                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>
                                        </form>
                                    <?php elseif ($order['order_status'] === 'ready_for_pickup'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form orders-status-action">
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="completed">
                                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as Completed</button>
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
            <?php $file_rows = $order_files[(int) $order['order_id']] ?? []; ?>
            <div class="order-modal" id="order-modal-<?php echo e($order['order_id']); ?>" aria-hidden="true">
                <div class="order-modal-backdrop" data-order-modal-close></div>
                <section class="order-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="order-modal-title-<?php echo e($order['order_id']); ?>">
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
                                        <a href="<?php echo BASE_URL . e($file['file_path']); ?>" target="_blank"><?php echo e($file['file_name']); ?></a>
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
                                    <span>Copies</span>
                                    <strong><?php echo e($order['copies'] ?: 'Not set'); ?></strong>
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
                                    <strong><span class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>" data-order-status-badge="<?php echo e($order['order_id']); ?>"><?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?><?php echo e(ownerStatusLabel($order['order_status'])); ?></span></strong>
                                </div>
                                <div>
                                    <span>Payment Status</span>
                                    <strong><span class="status-badge status-success">PAID</span></strong>
                                </div>
                                <div>
                                    <span>Total Amount</span>
                                    <strong><?php echo ownerMoney($order['total_amount']); ?></strong>
                                </div>
                            </div>
                        </section>
                    </div>

                    <footer class="order-modal-footer">
                        <button type="button" class="btn order-modal-secondary" data-order-modal-close>Close</button>
                        <?php if ($order['order_status'] === 'pending'): ?>
                            <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form orders-status-action" data-accept-download-form data-order-id="<?php echo e($order['order_id']); ?>">
                                <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                <input type="hidden" name="order_status" value="processing">
                                <?php foreach ($file_rows as $file): ?>
                                    <input type="hidden" data-download-url value="<?php echo BASE_URL . e($file['file_path']); ?>">
                                <?php endforeach; ?>
                                <button type="submit" name="update_order" class="btn order-modal-primary">Accept &amp; Download Order</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                </section>
            </div>
        <?php endforeach; ?>

        <div class="orders-mobile-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                $file_stmt = mysqli_prepare($conn, $file_sql);
                mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($file_stmt);
                $files = mysqli_stmt_get_result($file_stmt);
                ?>
                <article class="owner-card order-card-mobile" data-order-card="<?php echo e($order['order_id']); ?>">
                    <div class="card-head">
                        <h2><?php echo e($order['order_code']); ?></h2>
                        <span class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>" data-order-status-badge="<?php echo e($order['order_id']); ?>">
                            <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                        </span>
                    </div>
                    <p><strong>Customer:</strong> <?php echo e($order['full_name']); ?></p>
                    <p><strong>Details:</strong> <?php echo e($order['paper_size']); ?>, <?php echo e($order['paper_type']); ?>, <?php echo e($order['print_type']); ?>, x<?php echo e($order['copies']); ?></p>
                    <p><strong>Total:</strong> <?php echo ownerMoney($order['total_amount']); ?></p>
                    <p><strong>Instruction:</strong> <?php echo e($order['customer_instruction'] ?: 'No instruction'); ?></p>
                    <div class="row-actions">
                        <button type="button" class="btn order-btn-navy" data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View Details</button>
                    </div>
                    <?php if ($order['order_status'] === 'processing'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form orders-status-action mobile">
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="ready_for_pickup">
                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>
                        </form>
                    <?php elseif ($order['order_status'] === 'ready_for_pickup'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form orders-status-action mobile">
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="completed">
                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as Completed</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <footer class="orders-pagination">
            <p>Showing <strong><?php echo (int) $showing_start; ?>-<?php echo (int) $showing_end; ?></strong> of <?php echo (int) $filtered_total; ?> orders</p>
            <div>
                <?php if ($page > 1): ?>
                    <a class="pagination-btn" href="<?php echo e(orderPageUrl($page - 1, $search_code, $status_filter)); ?>"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</span>
                <?php endif; ?>
                <span class="pagination-current"><?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                    <a class="pagination-btn" href="<?php echo e(orderPageUrl($page + 1, $search_code, $status_filter)); ?>">Next<?php echo ownerIcon('chevron-right', 'icon-sm'); ?></a>
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

        function openModal(modal) {
            if (!modal) {
                return;
            }

            activeModal = modal;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('order-modal-open');
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
            document.querySelectorAll('[data-order-status-badge="' + orderId + '"]').forEach(function (badge) {
                badge.className = 'status-badge order-status-badge order-status-processing status-info';
                badge.innerHTML = '<i data-lucide="trending-up" class="icon-sm" aria-hidden="true"></i>Processing';
            });

            if (window.lucide) {
                window.lucide.createIcons();
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
                            alert('Failed to update order status. Please try again.');
                        });
                }, Math.max(450, urls.length * 180));
            });
        });
    })();
</script>

<?php ownerLayoutEnd(); ?>
