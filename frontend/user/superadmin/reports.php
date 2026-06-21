<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/admin_layout.php";

function reportScalar($conn, $sql, $types = '', array $params = [], $field = 'total')
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return 0;
    if ($types !== '') {
        reportBindParams($stmt, $types, $params);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row[$field] ?? 0;
}

function reportRows($conn, $sql, $types = '', array $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    if ($types !== '') {
        reportBindParams($stmt, $types, $params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function reportBindParams($stmt, $types, array $params)
{
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function reportCurrency($value)
{
    return '₱' . number_format((float) $value, 0);
}

function reportPercent($part, $total)
{
    $total = (float) $total;
    if ($total <= 0) return 0;
    return round(((float) $part / $total) * 100, 1);
}

$range = (string) ($_GET['range'] ?? '30d');
$allowed_ranges = ['7d', '30d', '90d', 'year'];
if (!in_array($range, $allowed_ranges, true)) {
    $range = '30d';
}

$today = new DateTimeImmutable('today');
switch ($range) {
    case '7d':
        $start = $today->modify('-6 days');
        $range_label = 'Last 7 Days';
        break;
    case '90d':
        $start = $today->modify('-89 days');
        $range_label = 'Last 90 Days';
        break;
    case 'year':
        $start = $today->setDate((int) $today->format('Y'), 1, 1);
        $range_label = 'This Year';
        break;
    case '30d':
    default:
        $start = $today->modify('-29 days');
        $range_label = 'Last 30 Days';
        break;
}

$end = $today;
$start_date = $start->format('Y-m-d');
$end_exclusive = $end->modify('+1 day')->format('Y-m-d');
$range_types = 'ss';
$range_params = [$start_date, $end_exclusive];

$paid_join = "LEFT JOIN (
    SELECT order_id, SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) AS paid_amount
    FROM payments
    GROUP BY order_id
) pay ON pay.order_id = o.order_id";

$total_revenue = (float) reportScalar(
    $conn,
    "SELECT COALESCE(SUM(pay.paid_amount), 0) AS total
     FROM orders o
     $paid_join
     WHERE o.created_at >= ? AND o.created_at < ?",
    $range_types,
    $range_params
);

$total_orders = (int) reportScalar(
    $conn,
    "SELECT COUNT(*) AS total FROM orders WHERE created_at >= ? AND created_at < ?",
    $range_types,
    $range_params
);

$total_users = (int) reportScalar($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin'");
$total_shops = (int) reportScalar($conn, "SELECT COUNT(*) AS total FROM print_shops");
$range_new_shops = (int) reportScalar(
    $conn,
    "SELECT COUNT(*) AS total FROM print_shops WHERE created_at >= ? AND created_at < ?",
    $range_types,
    $range_params
);
$pending_users = (int) reportScalar($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND account_status IN ('pending', 'incomplete')");
$pending_shops = (int) reportScalar($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'pending'");
$pending_approvals = $pending_users + $pending_shops;

$order_status_rows = reportRows(
    $conn,
    "SELECT order_status, COUNT(*) AS total
     FROM orders
     WHERE created_at >= ? AND created_at < ?
     GROUP BY order_status",
    $range_types,
    $range_params
);
$order_status_counts = ['completed' => 0, 'pending' => 0, 'cancelled' => 0];
foreach ($order_status_rows as $row) {
    $status = (string) ($row['order_status'] ?? 'pending');
    $count = (int) ($row['total'] ?? 0);
    if ($status === 'completed') {
        $order_status_counts['completed'] += $count;
    } elseif ($status === 'cancelled') {
        $order_status_counts['cancelled'] += $count;
    } else {
        $order_status_counts['pending'] += $count;
    }
}

$shop_status_rows = reportRows($conn, "SELECT COALESCE(permit_status, 'pending') AS permit_status, COUNT(*) AS total FROM print_shops GROUP BY COALESCE(permit_status, 'pending')");
$shop_status_counts = ['verified' => 0, 'pending' => 0, 'rejected' => 0, 'disabled' => 0];
foreach ($shop_status_rows as $row) {
    $status = (string) ($row['permit_status'] ?? 'pending');
    if (array_key_exists($status, $shop_status_counts)) {
        $shop_status_counts[$status] = (int) ($row['total'] ?? 0);
    }
}

$trend_group_month = $range === 'year';
$trend_format = $trend_group_month ? '%Y-%m' : '%Y-%m-%d';
$trend_rows = reportRows(
    $conn,
    "SELECT DATE_FORMAT(o.created_at, '$trend_format') AS bucket, COALESCE(SUM(pay.paid_amount), 0) AS revenue
     FROM orders o
     $paid_join
     WHERE o.created_at >= ? AND o.created_at < ?
     GROUP BY bucket
     ORDER BY bucket",
    $range_types,
    $range_params
);
$trend_map = [];
foreach ($trend_rows as $row) {
    $trend_map[(string) $row['bucket']] = (float) ($row['revenue'] ?? 0);
}

$trend = [];
if ($trend_group_month) {
    $cursor = $start->modify('first day of this month');
    $last = $end->modify('first day of this month');
    while ($cursor <= $last) {
        $key = $cursor->format('Y-m');
        $trend[] = ['label' => $cursor->format('M'), 'value' => $trend_map[$key] ?? 0];
        $cursor = $cursor->modify('+1 month');
    }
} else {
    $cursor = $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $trend[] = ['label' => $cursor->format($range === '7d' ? 'D' : 'M j'), 'value' => $trend_map[$key] ?? 0];
        $cursor = $cursor->modify('+1 day');
    }
}

$trend_max = max(1, ...array_column($trend, 'value'));
$trend_points = [];
$trend_count = count($trend);
foreach ($trend as $index => $point) {
    $x = $trend_count > 1 ? 44 + ($index * (636 / ($trend_count - 1))) : 362;
    $y = 230 - (((float) $point['value'] / $trend_max) * 170);
    $trend_points[] = ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $point['label'], 'value' => $point['value']];
}
$trend_polyline = implode(' ', array_map(fn($point) => $point['x'] . ',' . $point['y'], $trend_points));

$top_shops = reportRows(
    $conn,
    "SELECT ps.shop_name, COALESCE(stats.order_count, 0) AS order_count, COALESCE(stats.revenue, 0) AS revenue
     FROM print_shops ps
     LEFT JOIN (
        SELECT o.shop_id, COUNT(o.order_id) AS order_count, COALESCE(SUM(pay.paid_amount), 0) AS revenue
        FROM orders o
        $paid_join
        WHERE o.created_at >= ? AND o.created_at < ?
        GROUP BY o.shop_id
     ) stats ON stats.shop_id = ps.shop_id
     ORDER BY stats.revenue DESC, stats.order_count DESC, ps.shop_name ASC
     LIMIT 5",
    $range_types,
    $range_params
);
$top_revenue = max(1, ...array_map(fn($shop) => (float) ($shop['revenue'] ?? 0), $top_shops ?: [['revenue' => 0]]));

$growth_rows = reportRows(
    $conn,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket, role, COUNT(*) AS total
     FROM users
     WHERE role IN ('customer', 'shop_owner')
       AND created_at >= ? AND created_at < ?
     GROUP BY bucket, role
     ORDER BY bucket",
    $range_types,
    $range_params
);
$growth_map = [];
foreach ($growth_rows as $row) {
    $growth_map[(string) $row['bucket']][(string) $row['role']] = (int) ($row['total'] ?? 0);
}

$growth_months = [];
$cursor = $start->modify('first day of this month');
$last = $end->modify('first day of this month');
while ($cursor <= $last) {
    $key = $cursor->format('Y-m');
    $growth_months[] = [
        'label' => $cursor->format('M'),
        'customers' => $growth_map[$key]['customer'] ?? 0,
        'owners' => $growth_map[$key]['shop_owner'] ?? 0,
    ];
    $cursor = $cursor->modify('+1 month');
}
$growth_max = max(1, ...array_map(fn($row) => max($row['customers'], $row['owners']), $growth_months));

$completed_percent = reportPercent($order_status_counts['completed'], $total_orders);
$pending_percent = reportPercent($order_status_counts['pending'], $total_orders);
$cancelled_percent = reportPercent($order_status_counts['cancelled'], $total_orders);
$approved_shops = $shop_status_counts['verified'];
$approved_percent = reportPercent($approved_shops, max(1, $total_shops));
$top_shop_name = $top_shops[0]['shop_name'] ?? 'No shop data yet';
$top_shop_revenue = (float) ($top_shops[0]['revenue'] ?? 0);

adminLayoutStart('reports', 'Business Reports', 'Comprehensive analytics and insights across PrintEase.');
?>
<section class="admin-report-dashboard">
    <form class="admin-report-toolbar" method="GET" action="reports.php">
        <label>
            <?php echo adminIcon('clock'); ?>
            <select name="range" onchange="this.form.submit()">
                <?php foreach (['7d' => 'Last 7 Days', '30d' => 'Last 30 Days', '90d' => 'Last 90 Days', 'year' => 'This Year'] as $key => $label): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $range === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <section class="admin-report-metrics" aria-label="Business report metrics">
        <article class="admin-report-metric">
            <span class="admin-report-icon cyan">₱</span>
            <small><?php echo e($range_label); ?></small>
            <p>Revenue</p>
            <strong><?php echo e(reportCurrency($total_revenue)); ?></strong>
            <em>Paid payments only</em>
        </article>
        <article class="admin-report-metric">
            <span class="admin-report-icon blue"><?php echo adminIcon('file'); ?></span>
            <small><?php echo (int) $total_orders; ?> in range</small>
            <p>Total Orders</p>
            <strong><?php echo number_format($total_orders); ?></strong>
            <em>All shops</em>
        </article>
        <article class="admin-report-metric">
            <span class="admin-report-icon cyan"><?php echo adminIcon('users'); ?></span>
            <small><?php echo e($range_label); ?></small>
            <p>Total Users</p>
            <strong><?php echo number_format($total_users); ?></strong>
            <em>Platform total</em>
        </article>
        <article class="admin-report-metric">
            <span class="admin-report-icon navy"><?php echo adminIcon('shops'); ?></span>
            <small>+<?php echo (int) $range_new_shops; ?> new</small>
            <p>Total Shops</p>
            <strong><?php echo number_format($total_shops); ?></strong>
            <em>Platform total</em>
        </article>
        <article class="admin-report-metric warning">
            <span class="admin-report-icon orange"><?php echo adminIcon('clock'); ?></span>
            <small><?php echo (int) $pending_shops; ?> shops, <?php echo (int) $pending_users; ?> users</small>
            <p>Pending Approvals</p>
            <strong><?php echo number_format($pending_approvals); ?></strong>
            <em>Action needed</em>
        </article>
    </section>

    <section class="admin-report-grid">
        <article class="admin-report-card admin-report-chart-card">
            <header>
                <div><h2>Revenue Trend</h2><p><?php echo e($range_label); ?> paid revenue</p></div>
                <span>Revenue</span>
            </header>
            <svg class="admin-report-line-chart" viewBox="0 0 720 280" preserveAspectRatio="none" role="img" aria-label="Revenue trend chart">
                <line x1="44" y1="230" x2="690" y2="230"></line>
                <line x1="44" y1="60" x2="44" y2="230"></line>
                <?php foreach ([0, .25, .5, .75, 1] as $step): ?>
                    <?php $y = 230 - ($step * 170); ?>
                    <line class="grid" x1="44" y1="<?php echo $y; ?>" x2="690" y2="<?php echo $y; ?>"></line>
                    <text x="4" y="<?php echo $y + 4; ?>">₱<?php echo number_format(($trend_max * $step) / 1000, 0); ?>k</text>
                <?php endforeach; ?>
                <polyline points="<?php echo e($trend_polyline); ?>"></polyline>
                <?php foreach ($trend_points as $point): ?>
                    <circle cx="<?php echo $point['x']; ?>" cy="<?php echo $point['y']; ?>" r="4"><title><?php echo e($point['label'] . ': ' . reportCurrency($point['value'])); ?></title></circle>
                <?php endforeach; ?>
            </svg>
            <div class="admin-report-axis">
                <?php foreach ($trend as $index => $point): ?>
                    <?php if ($index === 0 || $index === count($trend) - 1 || $index % max(1, (int) ceil(count($trend) / 6)) === 0): ?>
                        <span><?php echo e($point['label']); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-report-card admin-report-donut-card">
            <header><div><h2>Orders Overview</h2><p>Order status distribution</p></div></header>
            <div class="admin-report-donut" style="--completed: <?php echo $completed_percent; ?>; --pending: <?php echo $pending_percent; ?>; --cancelled: <?php echo $cancelled_percent; ?>;">
                <div><strong><?php echo number_format($total_orders); ?></strong><span>Total Orders</span></div>
            </div>
            <div class="admin-report-donut-legend">
                <article class="completed"><span></span><p>Completed</p><strong><?php echo (int) $order_status_counts['completed']; ?></strong><small><?php echo $completed_percent; ?>%</small></article>
                <article class="pending"><span></span><p>Pending</p><strong><?php echo (int) $order_status_counts['pending']; ?></strong><small><?php echo $pending_percent; ?>%</small></article>
                <article class="cancelled"><span></span><p>Cancelled</p><strong><?php echo (int) $order_status_counts['cancelled']; ?></strong><small><?php echo $cancelled_percent; ?>%</small></article>
            </div>
        </article>
    </section>

    <section class="admin-report-grid">
        <article class="admin-report-card">
            <header><div><h2>Top Performing Shops</h2><p>Based on paid revenue</p></div><span><?php echo adminIcon('reports'); ?></span></header>
            <div class="admin-report-top-shops">
                <?php if (empty($top_shops)): ?>
                    <div class="admin-empty compact">No shop revenue in this range.</div>
                <?php endif; ?>
                <?php foreach ($top_shops as $index => $shop): ?>
                    <?php $share = reportPercent($shop['revenue'], $top_revenue); ?>
                    <article>
                        <b><?php echo $index + 1; ?></b>
                        <div><strong><?php echo e($shop['shop_name']); ?></strong><small><?php echo (int) $shop['order_count']; ?> orders</small><span style="--share: <?php echo $share; ?>%"></span></div>
                        <aside><strong><?php echo e(reportCurrency($shop['revenue'])); ?></strong><small><?php echo $share; ?>% share</small></aside>
                    </article>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-report-card">
            <header><div><h2>User Growth</h2><p>Monthly user registrations</p></div></header>
            <div class="admin-report-bars">
                <?php foreach ($growth_months as $month): ?>
                    <div>
                        <span class="customers" style="height: <?php echo max(4, round(($month['customers'] / $growth_max) * 220)); ?>px" title="<?php echo (int) $month['customers']; ?> customers"></span>
                        <span class="owners" style="height: <?php echo max(4, round(($month['owners'] / $growth_max) * 220)); ?>px" title="<?php echo (int) $month['owners']; ?> shop owners"></span>
                        <small><?php echo e($month['label']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="admin-report-bar-legend"><span class="customers"></span>Customers <span class="owners"></span>Shop Owners</div>
        </article>
    </section>

    <section class="admin-report-card admin-report-approval-card">
        <header><div><h2>Shop Approval Status</h2><p>Current status distribution</p></div></header>
        <div class="admin-report-approval-grid">
            <article class="approved"><span><?php echo adminIcon('check'); ?></span><div><strong>Approved / Active</strong> <small> <?php echo $approved_percent; ?>% of total</small></div><b><?php echo (int) $approved_shops; ?></b></article>
            <article class="pending"><span><?php echo adminIcon('clock'); ?></span><div><strong>Pending</strong> <small><?php echo (int) $pending_shops; ?> awaiting review</small></div><b><?php echo (int) $pending_shops; ?></b></article>
            <article class="rejected"><span><?php echo adminIcon('x'); ?></span><div><strong>Rejected</strong> <small><?php echo (int) $shop_status_counts['disabled']; ?> disabled</small></div><b><?php echo (int) $shop_status_counts['rejected']; ?></b></article>
        </div>
    </section>

    <section class="admin-report-grid">
        <article class="admin-report-card admin-report-insights">
            <header><span><?php echo adminIcon('check'); ?></span><div><h2>Key Insights</h2><p>Data-driven observations</p></div></header>
            <p><b></b> <?php echo e($top_shop_name); ?> is the top performer with <?php echo e(reportCurrency($top_shop_revenue)); ?> paid revenue.</p>
            <p><b></b> <?php echo $completed_percent; ?>% of orders are successfully completed in this range.</p>
            <p><b></b> <?php echo (int) $range_new_shops; ?> new print shop<?php echo $range_new_shops === 1 ? '' : 's'; ?> registered during <?php echo e($range_label); ?>.</p>
            <p><b></b> <?php echo (int) $pending_approvals; ?> total approval item<?php echo $pending_approvals === 1 ? '' : 's'; ?> need admin review.</p>
        </article>

        <article class="admin-report-card admin-report-alerts">
            <header><span><?php echo adminIcon('clock'); ?></span><div><h2>Alerts & Warnings</h2><p>Items requiring attention</p></div></header>
            <a href="manage_print_shops.php?status=pending"><span><?php echo adminIcon('clock'); ?></span><div><strong>Pending Shop Approvals</strong> <small><?php echo (int) $pending_shops; ?> print shops are waiting for approval</small></div><b>View</b></a>
            <a href="manage_users.php?status=pending"><span><?php echo adminIcon('users'); ?></span><div><strong>User Verification</strong> <small><?php echo (int) $pending_users; ?> user accounts are pending verification</small></div><b>Review</b></a>
            <a href="activity_logs.php"><span><?php echo adminIcon('check'); ?></span><div><strong>System Health</strong> <small>All report data loaded from current platform tables</small></div><b>Logs</b></a>
        </article>
    </section>
</section>

<?php adminLayoutEnd(); ?>
