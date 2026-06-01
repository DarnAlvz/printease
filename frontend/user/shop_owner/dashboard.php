<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/actions/pickup_reminder_checker.php";
require_once __DIR__ . "/includes/owner_layout.php";

function dashboardMoney($amount) {
    return '&#8369;' . number_format((float) $amount, 0);
}

function dashboardInitials($name) {
    $initials = '';
    foreach (explode(' ', trim($name ?? '')) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials ?: 'CU';
}

$owner_id = $_SESSION['user_id'];

$notif_sql = "SELECT COUNT(*) AS total
              FROM notifications
              WHERE user_id = ?
              AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_row = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt));
$notif_count = $notif_row['total'] ?? 0;

$sql = "SELECT ps.*, u.account_status
        FROM print_shops ps
        JOIN users u ON ps.owner_id = u.user_id
        WHERE ps.owner_id = ?
        ORDER BY ps.shop_id DESC
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$has_shop = !empty($shop);
$shop_id = $shop['shop_id'] ?? 0;
$permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : 'pending';
$account_status = $shop ? ($shop['account_status'] ?? 'pending') : 'pending';

$stats = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'ready_for_pickup' => 0,
    'completed' => 0,
    'revenue' => 0,
    'today_revenue' => 0,
    'active_customers' => 0,
];

if ($shop_id) {
    $stats_sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing,
                    SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_for_pickup,
                    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    COALESCE(SUM(total_amount), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END), 0) AS today_revenue,
                    COUNT(DISTINCT customer_id) AS active_customers
                  FROM orders
                  WHERE shop_id = ?";
    $stats_stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stats_stmt, "i", $shop_id);
    mysqli_stmt_execute($stats_stmt);
    $stats = array_merge($stats, mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt)) ?: []);
}

$recent_notes = [];
$recent_sql = "SELECT message, created_at, is_read
               FROM notifications
               WHERE user_id = ?
               ORDER BY created_at DESC
               LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $owner_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
while ($note = mysqli_fetch_assoc($recent_result)) {
    $recent_notes[] = $note;
}

$recent_orders = [];
if ($shop_id) {
    $orders_sql = "SELECT o.order_id, o.order_code, o.total_amount, o.order_status, o.created_at,
                          u.full_name,
                          (
                              SELECT uf.file_name
                              FROM uploaded_files uf
                              WHERE uf.order_id = o.order_id
                              ORDER BY uf.file_id ASC
                              LIMIT 1
                          ) AS file_name
                   FROM orders o
                   JOIN users u ON o.customer_id = u.user_id
                   WHERE o.shop_id = ?
                   ORDER BY o.created_at DESC
                   LIMIT 4";
    $orders_stmt = mysqli_prepare($conn, $orders_sql);
    mysqli_stmt_bind_param($orders_stmt, "i", $shop_id);
    mysqli_stmt_execute($orders_stmt);
    $orders_result = mysqli_stmt_get_result($orders_stmt);
    while ($order = mysqli_fetch_assoc($orders_result)) {
        $recent_orders[] = $order;
    }
}

$weekly_sales = array_fill(0, 7, 0);
if ($shop_id) {
    $weekly_sql = "SELECT WEEKDAY(created_at) AS day_index, COALESCE(SUM(total_amount), 0) AS total
                   FROM orders
                   WHERE shop_id = ?
                   AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
                   GROUP BY WEEKDAY(created_at)";
    $weekly_stmt = mysqli_prepare($conn, $weekly_sql);
    mysqli_stmt_bind_param($weekly_stmt, "i", $shop_id);
    mysqli_stmt_execute($weekly_stmt);
    $weekly_result = mysqli_stmt_get_result($weekly_stmt);
    while ($row = mysqli_fetch_assoc($weekly_result)) {
        $index = (int) $row['day_index'];
        if ($index >= 0 && $index <= 6) {
            $weekly_sales[$index] = (float) $row['total'];
        }
    }
}

$chart_max = max(1, max($weekly_sales));
$chart_points = [];
$chart_circles = [];
foreach ($weekly_sales as $index => $value) {
    $x = 42 + ($index * 103);
    $y = 230 - (($value / $chart_max) * 160);
    $chart_points[] = round($x, 1) . ',' . round($y, 1);
    $chart_circles[] = ['x' => round($x, 1), 'y' => round($y, 1)];
}
$chart_polyline = implode(' ', $chart_points);
$max_status = max(1, (int) $stats['total']);

ownerLayoutStart('dashboard', 'Shop Owner Dashboard', 'Monitor orders, revenue, shop status, and recent activity.', $notif_count, $shop);
?>

<?php if (isset($_GET['profile']) && $_GET['profile'] === 'success'): ?>
    <div class="alert-card success">
        <strong>Shop profile saved successfully.</strong>
    </div>
<?php endif; ?>

<?php showMessage(); ?>

<?php if (!$has_shop): ?>
    <section class="hero-card">
        <div>
            <h2>Complete your shop profile</h2>
            <p>Add your shop details and permit to start accepting print orders.</p>
        </div>
        <a class="btn" href="shop_profile.php"><?php echo ownerIcon('store', 'icon'); ?>Complete Profile</a>
    </section>
<?php elseif ($permit_status === 'pending'): ?>
    <section class="hero-card">
        <div>
            <h2><?php echo e($shop['shop_name']); ?> is under review</h2>
            <p>Your business permit is pending Super Admin verification.</p>
        </div>
        <span class="status-badge status-warning">Pending Verification</span>
    </section>

    <div class="content-grid" style="margin-top:22px;">
        <section class="owner-card">
            <h2>Submitted Shop Details</h2>
            <p><strong>Address:</strong> <?php echo e($shop['shop_address']); ?></p>
            <p><strong>Contact:</strong> <?php echo e($shop['contact_number']); ?></p>
            <?php if (!empty($shop['business_permit_file'])): ?>
                <p class="card-note">Business permit preview</p>
                <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" class="permit-preview" alt="Business permit">
            <?php endif; ?>
        </section>

        <section class="owner-card">
            <h2>Recent Activity</h2>
            <div class="activity-list">
                <?php if (empty($recent_notes)): ?>
                    <p class="muted">No notifications yet.</p>
                <?php else: ?>
                    <?php foreach ($recent_notes as $note): ?>
                        <div class="activity-item">
                            <span class="activity-dot"><?php echo ownerIcon('bell-ring', 'icon'); ?></span>
                            <div>
                                <b><?php echo e($note['message']); ?></b>
                                <small class="muted"><?php echo e(date("M d, Y - g:i A", strtotime($note['created_at']))); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
<?php elseif ($permit_status === 'rejected'): ?>
    <section class="alert-card danger">
        <h2>Permit Rejected</h2>
        <p>Your business permit has been rejected. Please contact the administrator or upload a new permit.</p>
        <a href="shop_profile.php" class="btn btn-primary"><?php echo ownerIcon('upload', 'icon'); ?>Update Shop Profile</a>
    </section>
<?php elseif ($account_status !== 'verified'): ?>
    <section class="hero-card">
        <div>
            <h2><?php echo e($shop['shop_name']); ?> is almost ready</h2>
            <p>Your permit is verified. Your account is still waiting for final verification.</p>
        </div>
        <span class="status-badge status-warning"><?php echo e(ownerStatusLabel($account_status)); ?></span>
    </section>
<?php else: ?>
    <div class="dashboard-grid">
        <section class="dashboard-main">
            <section class="hero-card dashboard-hero">
                <div>
                    <h2>Welcome back, <?php echo e($shop['shop_name']); ?>!</h2>
                    <p>Here is what is happening with your print shop today.</p>
                </div>
                <div class="hero-icon"><?php echo ownerIcon('printer', 'icon-xl'); ?></div>
            </section>

            <section class="dashboard-stat-grid">
                <article class="metric-card dashboard-stat-card">
                    <div class="metric-head">
                        <span class="metric-icon"><?php echo ownerIcon('package', 'icon'); ?></span>
                        <span class="status-badge status-success"><?php echo (int) $stats['total']; ?></span>
                    </div>
                    <strong><?php echo (int) $stats['total']; ?></strong>
                    <p>Total Orders</p>
                </article>
                <article class="metric-card dashboard-stat-card">
                    <div class="metric-head">
                        <span class="metric-icon"><?php echo ownerIcon('clock', 'icon'); ?></span>
                        <span class="status-badge status-warning">Active</span>
                    </div>
                    <strong><?php echo (int) $stats['processing']; ?></strong>
                    <p>Processing Orders</p>
                </article>
                <article class="metric-card dashboard-stat-card">
                    <div class="metric-head">
                        <span class="metric-icon warning-icon"><?php echo ownerIcon('circle-alert', 'icon'); ?></span>
                        <span class="status-badge status-warning"><?php echo (int) $stats['pending']; ?></span>
                    </div>
                    <strong><?php echo (int) $stats['pending']; ?></strong>
                    <p>Pending Orders</p>
                </article>
                <article class="metric-card dashboard-sales-card">
                    <div class="metric-head">
                        <span class="metric-icon"><?php echo ownerIcon('badge-dollar-sign', 'icon'); ?></span>
                        <span class="status-badge status-info">Sales</span>
                    </div>
                    <strong><?php echo dashboardMoney($stats['today_revenue'] > 0 ? $stats['today_revenue'] : $stats['revenue']); ?></strong>
                    <p>Today's Sales</p>
                    <svg viewBox="0 0 220 45" class="mini-sparkline" aria-hidden="true">
                        <polyline points="4,34 55,32 102,25 150,30 216,16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </article>
            </section>

            <section class="dashboard-middle-grid">
                <article class="owner-card sales-chart-card">
                    <div class="card-head">
                        <h2>Sales This Week</h2>
                        <span class="muted">Last 7 days</span>
                    </div>
                    <div class="chart-wrap">
                        <svg viewBox="0 0 710 270" preserveAspectRatio="none" class="sales-chart-svg" aria-label="Sales this week chart">
                            <?php foreach ([40, 90, 140, 190, 240] as $line_y): ?>
                                <line x1="42" y1="<?php echo $line_y; ?>" x2="690" y2="<?php echo $line_y; ?>" class="chart-grid-line"/>
                            <?php endforeach; ?>
                            <polyline points="<?php echo e($chart_polyline); ?>" class="chart-line" fill="none"/>
                            <?php foreach ($chart_circles as $point): ?>
                                <circle cx="<?php echo $point['x']; ?>" cy="<?php echo $point['y']; ?>" r="6" class="chart-point"/>
                            <?php endforeach; ?>
                            <?php foreach (['0', '1500', '3000', '4500', '6000'] as $i => $label): ?>
                                <text x="8" y="<?php echo 238 - ($i * 50); ?>" class="chart-label"><?php echo e($label); ?></text>
                            <?php endforeach; ?>
                            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day): ?>
                                <text x="<?php echo 34 + ($i * 103); ?>" y="260" class="chart-label"><?php echo e($day); ?></text>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </article>

                <article class="owner-card dashboard-status-card">
                    <h2>Orders Status</h2>
                    <div class="status-list">
                        <?php
                        $status_rows = [
                            ['label' => 'Processing', 'count' => (int) $stats['processing'], 'color' => '#05b7d3'],
                            ['label' => 'Pending', 'count' => (int) $stats['pending'], 'color' => '#f4b000'],
                            ['label' => 'Completed', 'count' => (int) $stats['completed'], 'color' => '#10b981'],
                        ];
                        foreach ($status_rows as $row):
                            $width = min(100, round(($row['count'] / $max_status) * 100));
                        ?>
                            <div>
                                <div class="status-row">
                                    <span><?php echo e($row['label']); ?></span>
                                    <strong><?php echo (int) $row['count']; ?></strong>
                                </div>
                                <div class="progress"><b style="width:<?php echo max(25, $width); ?>%; background:<?php echo e($row['color']); ?>"></b></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="status-total">
                        <span>Total Orders</span>
                        <strong><?php echo (int) $stats['total']; ?></strong>
                    </div>
                </article>
            </section>

            <section class="owner-card recent-orders-card">
                <div class="card-head recent-orders-head">
                    <h2>Recent Orders</h2>
                    <a href="orders.php">View All</a>
                </div>
                <?php if (empty($recent_orders)): ?>
                    <div class="empty-state">
                        <h2>No recent orders</h2>
                        <p>New print orders will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="recent-orders-table-wrap">
                        <table class="recent-orders-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Order ID</th>
                                    <th>File Name</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $avatar_classes = ['avatar-blue', 'avatar-purple', 'avatar-pink', 'avatar-green'];
                                foreach ($recent_orders as $index => $order):
                                    $avatar_class = $avatar_classes[$index % count($avatar_classes)];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="recent-customer">
                                                <span class="customer-avatar <?php echo e($avatar_class); ?>"><?php echo e(dashboardInitials($order['full_name'])); ?></span>
                                                <strong><?php echo e($order['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><a href="orders.php?order_code=<?php echo e(substr($order['order_code'], -4)); ?>"><?php echo e($order['order_code']); ?></a></td>
                                        <td><?php echo e($order['file_name'] ?: 'No uploaded file'); ?></td>
                                        <td><strong><?php echo dashboardMoney($order['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>">
                                                <?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : 'clock'), 'icon-sm'); ?>
                                                <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="order-mobile-list recent-order-mobile-list">
                        <?php foreach ($recent_orders as $index => $order): ?>
                            <article class="owner-card order-card-mobile">
                                <div class="card-head">
                                    <h2><?php echo e($order['order_code']); ?></h2>
                                    <span class="status-badge <?php echo ownerStatusClass($order['order_status']); ?>"><?php echo e(ownerStatusLabel($order['order_status'])); ?></span>
                                </div>
                                <p><strong>Customer:</strong> <?php echo e($order['full_name']); ?></p>
                                <p><strong>File:</strong> <?php echo e($order['file_name'] ?: 'No uploaded file'); ?></p>
                                <p><strong>Amount:</strong> <?php echo dashboardMoney($order['total_amount']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>

        <aside class="dashboard-aside">
            <section class="owner-card dashboard-activity-card">
                <div class="card-head">
                    <h2>Recent Activity</h2>
                    <?php echo ownerIcon('bell', 'icon muted'); ?>
                </div>
                <div class="dashboard-activity-list">
                    <?php if (empty($recent_notes)): ?>
                        <p class="muted">No notifications yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_notes as $index => $note): ?>
                            <div class="dashboard-activity-item">
                                <span class="activity-square activity-square-<?php echo ($index % 5) + 1; ?>">
                                    <?php echo ownerIcon($index === 0 ? 'shopping-cart' : ($index === 1 ? 'clock' : ($index === 2 ? 'badge-dollar-sign' : ($index === 3 ? 'circle-check' : 'users'))), 'icon-sm'); ?>
                                </span>
                                <div>
                                    <b><?php echo e($note['message']); ?></b>
                                    <small><?php echo e(date("g:i A", strtotime($note['created_at']))); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="owner-card quick-stats-card">
                <h2>Quick Stats</h2>
                <p>Today's Revenue <strong><?php echo dashboardMoney($stats['today_revenue'] > 0 ? $stats['today_revenue'] : $stats['revenue']); ?></strong></p>
                <p>Active Customers <strong><?php echo (int) $stats['active_customers']; ?></strong></p>
                <p>Ready Orders <strong><?php echo (int) $stats['ready_for_pickup']; ?></strong></p>
            </section>
        </aside>
    </div>
<?php endif; ?>

<?php ownerLayoutEnd(); ?>
