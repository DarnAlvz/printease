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

$per_page = 10;
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
               ORDER BY o.created_at DESC
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

ownerLayoutStart('orders', 'Order Management', 'Manage and track all paid orders', $notif_count, $shop);
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
            <input type="text" name="order_code" placeholder="Search by last 4 digits of order ID..." value="<?php echo e($search_code); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo ownerIcon('search', 'icon'); ?>Search</button>
        <a href="orders.php<?php echo $status_filter !== 'all' ? '?status=' . e($status_filter) : ''; ?>" class="btn btn-soft"><?php echo ownerIcon('x', 'icon'); ?>Clear</a>
    </form>
</section>

<?php if (empty($orders)): ?>
    <section class="owner-card empty-state">
        <h2>No orders found</h2>
        <p>New customer print orders will appear here.</p>
    </section>
<?php else: ?>
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
                        ?>
                        <tr>
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
                                <span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>">
                                    <?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?>
                                    <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="orders-actions">
                                    <?php if (empty($file_rows)): ?>
                                        <span class="muted">No file</span>
                                    <?php else: ?>
                                        <?php foreach ($file_rows as $file): ?>
                                            <a class="btn order-btn-navy" href="<?php echo BASE_URL . e($file['file_path']); ?>" target="_blank"><?php echo ownerIcon('eye', 'icon'); ?>View File</a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form">
                                        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                        <select name="order_status" required>
                                            <?php foreach (['pending', 'processing', 'ready_for_pickup', 'completed'] as $status): ?>
                                                <option value="<?php echo e($status); ?>" <?php if ($order['order_status'] == $status) echo 'selected'; ?>>
                                                    <?php echo e(ownerStatusLabel($status)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_order" class="btn btn-success"><?php echo ownerIcon('refresh-cw', 'icon'); ?>Update</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="orders-mobile-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                $file_stmt = mysqli_prepare($conn, $file_sql);
                mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($file_stmt);
                $files = mysqli_stmt_get_result($file_stmt);
                ?>
                <article class="owner-card order-card-mobile">
                    <div class="card-head">
                        <h2><?php echo e($order['order_code']); ?></h2>
                        <span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>">
                            <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                        </span>
                    </div>
                    <p><strong>Customer:</strong> <?php echo e($order['full_name']); ?></p>
                    <p><strong>Details:</strong> <?php echo e($order['paper_size']); ?>, <?php echo e($order['paper_type']); ?>, <?php echo e($order['print_type']); ?>, x<?php echo e($order['copies']); ?></p>
                    <p><strong>Total:</strong> <?php echo ownerMoney($order['total_amount']); ?></p>
                    <p><strong>Instruction:</strong> <?php echo e($order['customer_instruction'] ?: 'No instruction'); ?></p>
                    <div class="row-actions">
                        <?php if (mysqli_num_rows($files) == 0): ?>
                            <span class="muted">No uploaded file</span>
                        <?php else: ?>
                            <?php while ($file = mysqli_fetch_assoc($files)): ?>
                                <a class="btn order-btn-navy" href="<?php echo BASE_URL . e($file['file_path']); ?>" target="_blank"><?php echo ownerIcon('eye', 'icon'); ?>View File</a>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                    <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST" class="orders-update-form mobile">
                        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                        <select name="order_status" required>
                            <?php foreach (['pending', 'processing', 'ready_for_pickup', 'completed'] as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php if ($order['order_status'] == $status) echo 'selected'; ?>>
                                    <?php echo e(ownerStatusLabel($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_order" class="btn btn-success"><?php echo ownerIcon('refresh-cw', 'icon'); ?>Update</button>
                    </form>
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

<?php ownerLayoutEnd(); ?>
