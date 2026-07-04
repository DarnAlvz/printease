<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/includes/owner_layout.php";

requireCompleteShopProfile($conn);
$owner_access = requireVerifiedStatus($conn, true);
$owner_toast = !empty($owner_access['allowed']) ? null : $owner_access;
$owner_id = (int) ($_SESSION['user_id'] ?? 0);

function reportDateValue($value)
{
    if (!is_string($value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}

function reportRangeUrl($range, $start_date = '', $end_date = '', $page = 1, $export = '')
{
    $params = ['range' => $range];
    if ($range === 'custom') {
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    if ($export !== '') {
        $params['export'] = $export;
    }
    return 'reports.php?' . http_build_query($params);
}

function reportCsvValue($value)
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

function reportPaymentLabel($paid_amount, $payment_status, $verification_status)
{
    if ((float) $paid_amount > 0 || $payment_status === 'paid') {
        return 'Paid';
    }
    if ($verification_status === 'pending') {
        return 'For Verification';
    }
    if ($verification_status === 'rejected') {
        return 'Rejected';
    }
    return 'Unpaid';
}

$notif_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0);

$shop_stmt = mysqli_prepare($conn, "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));
$shop_id = (int) ($shop['shop_id'] ?? 0);

$today = new DateTimeImmutable('today');
$range = is_string($_GET['range'] ?? null) ? $_GET['range'] : 'month';
$allowed_ranges = ['today', 'week', 'month', 'year', 'custom'];
$range_warning = '';

if (!in_array($range, $allowed_ranges, true)) {
    $range = 'month';
    $range_warning = 'The selected report range was invalid, so This Month is shown instead.';
}

switch ($range) {
    case 'today':
        $start = $today;
        $end = $today;
        $range_label = 'Today';
        break;
    case 'week':
        $start = $today->modify('monday this week');
        $end = $start->modify('+6 days');
        $range_label = 'This Week';
        break;
    case 'year':
        $start = $today->setDate((int) $today->format('Y'), 1, 1);
        $end = $start->modify('+1 year -1 day');
        $range_label = 'This Year';
        break;
    case 'custom':
        $custom_start = reportDateValue($_GET['start_date'] ?? '');
        $custom_end = reportDateValue($_GET['end_date'] ?? '');
        if (!$custom_start || !$custom_end || $custom_start > $custom_end) {
            $range = 'month';
            $start = $today->modify('first day of this month');
            $end = $today->modify('last day of this month');
            $range_label = 'This Month';
            $range_warning = 'Enter a valid custom date range. This Month is shown instead.';
        } else {
            $start = $custom_start;
            $end = $custom_end;
            $range_label = $start->format('M d, Y') . ' - ' . $end->format('M d, Y');
        }
        break;
    case 'month':
    default:
        $start = $today->modify('first day of this month');
        $end = $today->modify('last day of this month');
        $range_label = 'This Month';
        break;
}

$start_date = $start->format('Y-m-d');
$end_date = $end->format('Y-m-d');
$end_exclusive = $end->modify('+1 day')->format('Y-m-d');
$paid_join = "LEFT JOIN (
                SELECT order_id,
                       SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) AS paid_amount,
                       MAX(payment_status) AS payment_status,
                       MAX(verification_status) AS verification_status
                FROM payments
                GROUP BY order_id
              ) pay ON pay.order_id = o.order_id";

$summary_sql = "SELECT COUNT(*) AS total_orders,
                       SUM(CASE WHEN o.order_status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
                       COUNT(DISTINCT o.customer_id) AS unique_customers,
                       COALESCE(SUM(pay.paid_amount), 0) AS paid_revenue,
                       COALESCE(AVG(CASE WHEN pay.paid_amount > 0 THEN pay.paid_amount END), 0) AS average_paid
                FROM orders o
                $paid_join
                WHERE o.shop_id = ? AND o.created_at >= ? AND o.created_at < ?";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($summary_stmt, "iss", $shop_id, $start_date, $end_exclusive);
mysqli_stmt_execute($summary_stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summary_stmt)) ?: [];
$summary += ['total_orders' => 0, 'completed_orders' => 0, 'unique_customers' => 0, 'paid_revenue' => 0, 'average_paid' => 0];
$completion_rate = (int) $summary['total_orders'] > 0
    ? round(((int) $summary['completed_orders'] / (int) $summary['total_orders']) * 100, 1)
    : 0;

$status_counts = ['pending' => 0, 'processing' => 0, 'ready_for_pickup' => 0, 'completed' => 0];
$status_sql = "SELECT order_status, COUNT(*) AS total
               FROM orders
               WHERE shop_id = ? AND created_at >= ? AND created_at < ?
               GROUP BY order_status";
$status_stmt = mysqli_prepare($conn, $status_sql);
mysqli_stmt_bind_param($status_stmt, "iss", $shop_id, $start_date, $end_exclusive);
mysqli_stmt_execute($status_stmt);
$status_result = mysqli_stmt_get_result($status_stmt);
while ($row = mysqli_fetch_assoc($status_result)) {
    if (array_key_exists($row['order_status'], $status_counts)) {
        $status_counts[$row['order_status']] = (int) $row['total'];
    }
}

$days_spanned = (int) $start->diff($end)->format('%a') + 1;
$group_by_month = $range === 'year' || $days_spanned > 92;
$trend_format = $group_by_month ? '%Y-%m' : '%Y-%m-%d';
$trend_sql = "SELECT DATE_FORMAT(o.created_at, '$trend_format') AS bucket,
                     COALESCE(SUM(pay.paid_amount), 0) AS revenue
              FROM orders o
              $paid_join
              WHERE o.shop_id = ? AND o.created_at >= ? AND o.created_at < ?
              GROUP BY bucket
              ORDER BY bucket";
$trend_stmt = mysqli_prepare($conn, $trend_sql);
mysqli_stmt_bind_param($trend_stmt, "iss", $shop_id, $start_date, $end_exclusive);
mysqli_stmt_execute($trend_stmt);
$trend_result = mysqli_stmt_get_result($trend_stmt);
$trend_values = [];
while ($row = mysqli_fetch_assoc($trend_result)) {
    $trend_values[$row['bucket']] = (float) $row['revenue'];
}

$trend = [];
if ($group_by_month) {
    $cursor = $start->modify('first day of this month');
    $last = $end->modify('first day of this month');
    while ($cursor <= $last) {
        $key = $cursor->format('Y-m');
        $trend[] = ['label' => $cursor->format('M Y'), 'value' => $trend_values[$key] ?? 0];
        $cursor = $cursor->modify('+1 month');
    }
} else {
    $cursor = $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $trend[] = ['label' => $cursor->format($days_spanned <= 14 ? 'M d' : 'm/d'), 'value' => $trend_values[$key] ?? 0];
        $cursor = $cursor->modify('+1 day');
    }
}

$trend_max = max(1, ...array_column($trend, 'value'));
$trend_points = [];
$trend_count = count($trend);
foreach ($trend as $index => $point) {
    $x = $trend_count > 1 ? 48 + ($index * (632 / ($trend_count - 1))) : 364;
    $y = 228 - (($point['value'] / $trend_max) * 170);
    $trend_points[] = ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $point['label'], 'value' => $point['value']];
}
$trend_polyline = implode(' ', array_map(fn($point) => $point['x'] . ',' . $point['y'], $trend_points));

function reportTopValues($conn, $shop_id, $start_date, $end_exclusive, $column)
{
    $allowed = ['paper_size', 'paper_type', 'print_type'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }
    $sql = "SELECT COALESCE(NULLIF(TRIM($column), ''), 'Not specified') AS label, COUNT(*) AS total
            FROM orders
            WHERE shop_id = ? AND created_at >= ? AND created_at < ?
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT 5";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $shop_id, $start_date, $end_exclusive);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

$top_print_data = [
    'Paper Sizes' => reportTopValues($conn, $shop_id, $start_date, $end_exclusive, 'paper_size'),
    'Paper Types' => reportTopValues($conn, $shop_id, $start_date, $end_exclusive, 'paper_type'),
    'Print Types' => reportTopValues($conn, $shop_id, $start_date, $end_exclusive, 'print_type'),
];

$customer_sql = "SELECT u.full_name, u.email, COUNT(*) AS order_count,
                        COALESCE(SUM(pay.paid_amount), 0) AS spending
                 FROM orders o
                 JOIN users u ON u.user_id = o.customer_id
                 $paid_join
                 WHERE o.shop_id = ? AND o.created_at >= ? AND o.created_at < ?
                 GROUP BY o.customer_id, u.full_name, u.email
                 ORDER BY spending DESC, order_count DESC
                 LIMIT 5";
$customer_stmt = mysqli_prepare($conn, $customer_sql);
mysqli_stmt_bind_param($customer_stmt, "iss", $shop_id, $start_date, $end_exclusive);
mysqli_stmt_execute($customer_stmt);
$top_customers = mysqli_fetch_all(mysqli_stmt_get_result($customer_stmt), MYSQLI_ASSOC);

$order_select = "SELECT o.order_code, o.paper_size, o.paper_type, o.print_type, o.copies,
                        o.order_status, o.total_amount, o.created_at, u.full_name, u.email,
                        COALESCE(pay.paid_amount, 0) AS paid_amount,
                        pay.payment_status, pay.verification_status
                 FROM orders o
                 JOIN users u ON u.user_id = o.customer_id
                 $paid_join
                 WHERE o.shop_id = ? AND o.created_at >= ? AND o.created_at < ?";

if (($_GET['export'] ?? '') === 'csv') {
    $export_stmt = mysqli_prepare($conn, $order_select . " ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($export_stmt, "iss", $shop_id, $start_date, $end_exclusive);
    mysqli_stmt_execute($export_stmt);
    $export_result = mysqli_stmt_get_result($export_stmt);

    $filename = 'shop-report-' . $start_date . '-to-' . $end_date . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order Code', 'Customer', 'Email', 'Paper Size', 'Paper Type', 'Print Type', 'Copies', 'Order Status', 'Payment Status', 'Order Amount', 'Paid Amount', 'Order Date']);
    while ($order = mysqli_fetch_assoc($export_result)) {
        fputcsv($output, array_map('reportCsvValue', [
            $order['order_code'], $order['full_name'], $order['email'], $order['paper_size'],
            $order['paper_type'], $order['print_type'], $order['copies'], ownerStatusLabel($order['order_status']),
            reportPaymentLabel($order['paid_amount'], $order['payment_status'], $order['verification_status']),
            number_format((float) $order['total_amount'], 2, '.', ''),
            number_format((float) $order['paid_amount'], 2, '.', ''),
            date('Y-m-d H:i:s', strtotime($order['created_at'])),
        ]));
    }
    fclose($output);
    exit();
}

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM orders WHERE shop_id = ? AND created_at >= ? AND created_at < ?");
mysqli_stmt_bind_param($count_stmt, "iss", $shop_id, $start_date, $end_exclusive);
mysqli_stmt_execute($count_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);
$per_page = 10;
$page_value = $_GET['page'] ?? 1;
$page = max(1, is_scalar($page_value) ? (int) $page_value : 1);
$total_pages = max(1, (int) ceil($filtered_total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$orders_stmt = mysqli_prepare($conn, $order_select . " ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($orders_stmt, "issii", $shop_id, $start_date, $end_exclusive, $per_page, $offset);
mysqli_stmt_execute($orders_stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($orders_stmt), MYSQLI_ASSOC);
$showing_start = $filtered_total > 0 ? $offset + 1 : 0;
$showing_end = min($offset + count($orders), $filtered_total);

ownerLayoutStart('reports', 'Reports', 'Review shop performance, sales, customers, and print demand.', $notif_count, $shop, $owner_toast);
?>

<?php if ($range_warning !== ''): ?>
    <div class="alert-card warning report-range-warning" role="alert"><?php echo e($range_warning); ?></div>
<?php endif; ?>

<section class="report-toolbar owner-card">
    <div>
        <span class="report-eyebrow">Reporting Period</span>
        <strong><?php echo e($range_label); ?></strong>
        <small><?php echo e($start->format('M d, Y')); ?> to <?php echo e($end->format('M d, Y')); ?></small>
    </div>
    <nav class="report-range-tabs" aria-label="Report date ranges">
        <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label): ?>
            <a class="<?php echo $range === $key ? 'active' : ''; ?>" href="<?php echo e(reportRangeUrl($key)); ?>"><?php echo e($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="GET" class="report-custom-range">
        <input type="hidden" name="range" value="custom">
        <label>From <input type="date" name="start_date" value="<?php echo e($start_date); ?>" required></label>
        <label>To <input type="date" name="end_date" value="<?php echo e($end_date); ?>" required></label>
        <button type="submit" class="btn btn-soft">Apply</button>
    </form>
    <a class="btn btn-primary report-export" href="<?php echo e(reportRangeUrl($range, $start_date, $end_date, 1, 'csv')); ?>">
        <?php echo ownerIcon('download', 'icon'); ?> Export CSV
    </a>
</section>

<section class="report-summary-grid" aria-label="Report summary">
    <?php
    $summary_cards = [
        ['label' => 'Total Orders', 'value' => number_format((int) $summary['total_orders']), 'icon' => 'package', 'class' => 'orders'],
        ['label' => 'Completed', 'value' => number_format((int) $summary['completed_orders']) . ' (' . $completion_rate . '%)', 'icon' => 'circle-check', 'class' => 'completed'],
        ['label' => 'Unique Customers', 'value' => number_format((int) $summary['unique_customers']), 'icon' => 'users', 'class' => 'customers'],
        ['label' => 'Average Paid Order', 'value' => ownerMoney($summary['average_paid']), 'icon' => 'calculator', 'class' => 'average'],
    ];
    foreach ($summary_cards as $card): ?>
        <article class="report-summary-card <?php echo e($card['class']); ?>">
            <span><?php echo ownerIcon($card['icon'], 'icon'); ?></span>
            <div><p><?php echo e($card['label']); ?></p><strong><?php echo $card['value']; ?></strong></div>
        </article>
    <?php endforeach; ?>
</section>

<section class="report-analytics-grid">
    <article class="owner-card report-revenue-card">
        <div class="card-head">
            <div><h2>Paid Revenue Trend</h2><p class="card-note">Revenue from paid orders created within the selected period.</p></div>
            <strong><?php echo ownerMoney($summary['paid_revenue']); ?></strong>
        </div>
        <div class="report-chart-wrap">
            <svg viewBox="0 0 720 270" preserveAspectRatio="none" class="report-chart" role="img" aria-label="Paid revenue trend">
                <?php foreach ([48, 93, 138, 183, 228] as $line_y): ?>
                    <line x1="48" y1="<?php echo $line_y; ?>" x2="680" y2="<?php echo $line_y; ?>" class="chart-grid-line" />
                <?php endforeach; ?>
                <?php if ($trend_polyline !== ''): ?><polyline points="<?php echo e($trend_polyline); ?>" class="chart-line" fill="none" /><?php endif; ?>
                <?php foreach ($trend_points as $point): ?>
                    <circle cx="<?php echo $point['x']; ?>" cy="<?php echo $point['y']; ?>" r="5" class="chart-point"><title><?php echo e($point['label'] . ': ' . strip_tags(ownerMoney($point['value']))); ?></title></circle>
                <?php endforeach; ?>
                <?php
                $label_step = max(1, (int) ceil(max(1, count($trend_points)) / 7));
                foreach ($trend_points as $index => $point):
                    if ($index % $label_step !== 0 && $index !== count($trend_points) - 1) continue;
                    ?>
                    <text x="<?php echo $point['x']; ?>" y="260" text-anchor="middle" class="chart-label"><?php echo e($point['label']); ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
    </article>

    <article class="owner-card report-status-card">
        <h2>Order Status</h2>
        <p class="card-note">Distribution for <?php echo e($range_label); ?>.</p>
        <div class="report-status-list">
            <?php
            $status_meta = [
                'pending' => ['Pending', '#f4b000'], 'processing' => ['Processing', '#05b7d3'],
                'ready_for_pickup' => ['Ready for Pickup', '#9333ea'], 'completed' => ['Completed', '#10b981'],
            ];
            foreach ($status_meta as $key => [$label, $color]):
                $percent = (int) $summary['total_orders'] > 0 ? round(($status_counts[$key] / (int) $summary['total_orders']) * 100) : 0;
                ?>
                <div><div class="status-row"><span><?php echo e($label); ?></span><strong><?php echo $status_counts[$key]; ?> <small><?php echo $percent; ?>%</small></strong></div><div class="progress"><b style="width:<?php echo $percent; ?>%;background:<?php echo e($color); ?>"></b></div></div>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="report-demand-grid">
    <?php foreach ($top_print_data as $title => $rows): ?>
        <article class="owner-card report-ranking-card">
            <h2><?php echo e($title); ?></h2>
            <?php if (empty($rows)): ?><p class="muted">No order data for this period.</p><?php else: ?>
                <ol><?php $top_total = max(1, (int) $rows[0]['total']); foreach ($rows as $row): ?>
                    <li><div><span><?php echo e($row['label']); ?></span><strong><?php echo (int) $row['total']; ?></strong></div><div class="progress"><b style="width:<?php echo round(((int) $row['total'] / $top_total) * 100); ?>%"></b></div></li>
                <?php endforeach; ?></ol>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="owner-card report-customers-card">
    <div class="card-head"><div><h2>Top Customers</h2><p class="card-note">Ranked by paid spending, then order count.</p></div></div>
    <?php if (empty($top_customers)): ?><div class="empty-state"><p>No customer activity for this period.</p></div><?php else: ?>
        <div class="report-customer-list"><?php foreach ($top_customers as $index => $customer): ?>
            <div><span class="report-rank"><?php echo $index + 1; ?></span><div><strong><?php echo e($customer['full_name']); ?></strong><small><?php echo e($customer['email']); ?></small></div><span><?php echo (int) $customer['order_count']; ?> orders</span><strong><?php echo ownerMoney($customer['spending']); ?></strong></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
</section>

<section class="report-orders-card">
    <div class="report-table-head"><div><h2>Order Report</h2><p>Showing <?php echo $showing_start; ?>-<?php echo $showing_end; ?> of <?php echo $filtered_total; ?> orders</p></div></div>
    <?php if (empty($orders)): ?>
        <div class="empty-state"><h2>No orders found</h2><p>Try another date range or wait for new orders.</p></div>
    <?php else: ?>
        <div class="owner-table-wrap"><table class="report-table"><thead><tr><th>Order</th><th>Customer</th><th>Print Details</th><th>Copies</th><th>Order Status</th><th>Payment</th><th>Amount</th><th>Date</th></tr></thead><tbody>
            <?php foreach ($orders as $order): $payment_label = reportPaymentLabel($order['paid_amount'], $order['payment_status'], $order['verification_status']); ?>
                <tr><td><strong><?php echo e($order['order_code']); ?></strong></td><td><strong><?php echo e($order['full_name']); ?></strong><small><?php echo e($order['email']); ?></small></td><td><span><?php echo e($order['paper_size'] ?: 'Not set'); ?></span><small><?php echo e(trim(($order['paper_type'] ?: '') . ' ' . ($order['print_type'] ?: '')) ?: 'Not set'); ?></small></td><td><?php echo (int) $order['copies']; ?></td><td><span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>"><?php echo e(ownerStatusLabel($order['order_status'])); ?></span></td><td><span class="status-badge <?php echo $payment_label === 'Paid' ? 'status-success' : ($payment_label === 'Rejected' ? 'status-danger' : 'status-warning'); ?>"><?php echo e($payment_label); ?></span></td><td><strong><?php echo ownerMoney($order['total_amount']); ?></strong></td><td><?php echo e(date('M d, Y', strtotime($order['created_at']))); ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <div class="order-mobile-list report-mobile-list"><?php foreach ($orders as $order): $payment_label = reportPaymentLabel($order['paid_amount'], $order['payment_status'], $order['verification_status']); ?>
            <article class="owner-card order-card-mobile"><div class="card-head"><h2><?php echo e($order['order_code']); ?></h2><span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>"><?php echo e(ownerStatusLabel($order['order_status'])); ?></span></div><p><strong>Customer:</strong> <?php echo e($order['full_name']); ?></p><p><strong>Print:</strong> <?php echo e($order['paper_size'] . ', ' . $order['paper_type'] . ', ' . $order['print_type']); ?> x<?php echo (int) $order['copies']; ?></p><p><strong>Payment:</strong> <?php echo e($payment_label); ?></p><p><strong>Amount:</strong> <?php echo ownerMoney($order['total_amount']); ?></p><p><strong>Date:</strong> <?php echo e(date('M d, Y', strtotime($order['created_at']))); ?></p></article>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($total_pages > 1): ?>
        <div class="orders-pagination"><span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span><div><?php if ($page > 1): ?><a class="pagination-btn" href="<?php echo e(reportRangeUrl($range, $start_date, $end_date, $page - 1)); ?>">Previous</a><?php endif; ?><span class="pagination-current"><?php echo $page; ?></span><?php if ($page < $total_pages): ?><a class="pagination-btn" href="<?php echo e(reportRangeUrl($range, $start_date, $end_date, $page + 1)); ?>">Next</a><?php endif; ?></div></div>
    <?php endif; ?>
</section>

<?php ownerLayoutEnd(); ?>
