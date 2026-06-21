<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/toasts.php";
require_once __DIR__ . "/../../components/notifications.php";
require_once __DIR__ . "/includes/admin_layout.php";

function dashboardCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function dashboardRows($conn, $sql)
{
    $rows = [];
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function dashboardRelativeTime($datetime)
{
    if (empty($datetime)) {
        return 'Just now';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return e($datetime);
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = floor($diff / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function dashboardIcon($name)
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.4"></rect><rect x="14" y="3" width="7" height="7" rx="1.4"></rect><rect x="3" y="14" width="7" height="7" rx="1.4"></rect><rect x="14" y="14" width="7" height="7" rx="1.4"></rect>',
        'shop' => '<path d="M4 10h16l-1.2-5H5.2L4 10Z"></path><path d="M6 10v9h12v-9"></path><path d="M9 19v-5h6v5"></path><path d="M4 10c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>',
        'activity' => '<path d="M22 12h-4l-3 8L9 4l-3 8H2"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1.82V22a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.82-.33H2a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1.82V2a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 16 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.24.33.6.57 1 .6.45.04.91-.07 1.32-.33H22a2 2 0 1 1 0 4h-.09A1.65 1.65 0 0 0 19.4 15Z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
        'check' => '<path d="M20 6 9 17l-5-5"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'plus' => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        'chart' => '<path d="M3 3v18h18"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-3"></path>',
        'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"></path><path d="M5 3v4"></path><path d="M3 5h4"></path><path d="M19 17v4"></path><path d="M17 19h4"></path>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"></path><path d="M14 7h7v7"></path>',
        'bulb' => '<path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M8.5 14.5A6 6 0 1 1 15.5 14.5c-.7.47-1.5 1.35-1.5 2.5h-4c0-1.15-.8-2.03-1.5-2.5Z"></path>',
        'x' => '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>',
    ];

    $paths = $icons[$name] ?? $icons['dashboard'];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function dashboardLinePoints($series, $width = 640, $height = 210)
{
    $max = max(1, max($series));
    $count = count($series);
    $points = [];

    foreach ($series as $index => $value) {
        $x = $count > 1 ? ($index / ($count - 1)) * $width : $width / 2;
        $y = $height - (($value / $max) * ($height - 22)) - 10;
        $points[] = round($x, 2) . ',' . round($y, 2);
    }

    return implode(' ', $points);
}

$total_shops = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops");
$total_customers = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'");
$active_users = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND account_status = 'verified'");
$pending_users = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND account_status = 'pending'");
$pending_permits = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'pending'");
$pending_approvals = $pending_users + $pending_permits;
$verified_shops = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'verified'");
$new_shops_today = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE DATE(created_at) = CURDATE()");
$new_users_week = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$admin_id = (int) $_SESSION['user_id'];
$unread_notifications = getUnreadNotificationCount($conn, $admin_id);
$admin_notifications = getUserNotifications($conn, $admin_id, 5);

$pending_shops = dashboardRows($conn, "
    SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.business_permit_file, ps.created_at, u.full_name, u.email
    FROM print_shops ps
    JOIN users u ON ps.owner_id = u.user_id
    WHERE ps.permit_status = 'pending'
    ORDER BY ps.created_at DESC
    LIMIT 5
");

$pending_accounts = dashboardRows($conn, "
    SELECT u.user_id, u.full_name, u.email, u.role, u.created_at
    FROM users u
    WHERE u.role != 'super_admin' AND u.account_status = 'pending'
    ORDER BY u.created_at DESC
    LIMIT 5
");

$recent_activity = dashboardRows($conn, "
    SELECT al.action, al.module, al.created_at, u.full_name, u.role
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 5
");

$daily_users = [];
$daily_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $daily_users[$key] = 0;
    $daily_labels[$key] = date('D', strtotime($key));
}

$daily_result = mysqli_query($conn, "
    SELECT DATE(created_at) AS day_key, COUNT(*) AS total
    FROM users
    WHERE role != 'super_admin' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
if ($daily_result) {
    while ($row = mysqli_fetch_assoc($daily_result)) {
        if (isset($daily_users[$row['day_key']])) {
            $daily_users[$row['day_key']] = (int) $row['total'];
        }
    }
}

$weekly_shops = [];
$weekly_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $monday = date('Y-m-d', strtotime("monday this week -$i weeks"));
    $weekly_shops[$monday] = 0;
    $weekly_labels[$monday] = 'W' . date('W', strtotime($monday));
}

$weekly_result = mysqli_query($conn, "
    SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_key, COUNT(*) AS total
    FROM print_shops
    WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 WEEK)
    GROUP BY week_key
");
if ($weekly_result) {
    while ($row = mysqli_fetch_assoc($weekly_result)) {
        if (isset($weekly_shops[$row['week_key']])) {
            $weekly_shops[$row['week_key']] = (int) $row['total'];
        }
    }
}

$daily_values = array_values($daily_users);
$weekly_values = array_values($weekly_shops);
$max_weekly = max(1, max($weekly_values));
adminLayoutStart('dashboard', 'Dashboard', 'Monitor platform activity, user growth, and pending admin work.');
?>
            <div class="admin-dashboard-content">
                <section class="section-head">
                    <div>
                        <h2>System Overview</h2>
                        <p>Monitor platform activity and manage users</p>
                    </div>
                    <div class="actions">
                        <a href="reports.php" class="btn primary"><?php echo dashboardIcon('chart'); ?>View Reports</a>
                    </div>
                </section>

                <section class="metrics-grid" aria-label="System metrics">
                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon cyan"><?php echo dashboardIcon('shop'); ?></span>
                            <span class="metric-change">+ <?php echo (int) $new_shops_today; ?> today</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $total_shops; ?></div>
                            <p class="metric-label">Total Print Shops</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#02a9c7">
                            <?php foreach ([9, 13, 11, 18, 14, 21, 24, 20, 28, 36] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon blue"><?php echo dashboardIcon('users'); ?></span>
                            <span class="metric-change">+ <?php echo (int) $new_users_week; ?> week</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $total_customers; ?></div>
                            <p class="metric-label">Total Customers</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#057eb8">
                            <?php foreach ([12, 15, 13, 19, 23, 22, 27, 30, 28, 33] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon green"><?php echo dashboardIcon('check'); ?></span>
                            <span class="metric-change"><?php echo (int) $verified_shops; ?> verified shops</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $active_users; ?></div>
                            <p class="metric-label">Active Users</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#08ba4e">
                            <?php foreach ([18, 22, 21, 25, 27, 24, 28, 31, 29, 33] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon yellow"><?php echo dashboardIcon('clock'); ?></span>
                            <span class="metric-change warning"><?php echo dashboardIcon('clock'); ?> Needs Action</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $pending_approvals; ?></div>
                            <p class="metric-label">Pending Approvals</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#f4ad00">
                            <?php foreach ([16, 27, 20, 33, 28, 21, 17, 26, 32, 22] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="charts-grid">
                    <article class="panel chart-panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <h3>User Growth</h3>
                                <p>Track user registration trends</p>
                            </div>
                            <div class="tabs">
                                <span class="tab active">Daily</span>
                                <span class="tab">Weekly</span>
                                <span class="tab">Monthly</span>
                            </div>
                        </div>
                        <svg class="line-chart" viewBox="0 0 700 260" preserveAspectRatio="none" role="img" aria-label="Daily user growth chart">
                            <defs>
                                <pattern id="grid" width="110" height="52" patternUnits="userSpaceOnUse">
                                    <path d="M110 0H0V52" fill="none" stroke="#d9e2ec" stroke-width="1" stroke-dasharray="3 3"></path>
                                </pattern>
                            </defs>
                            <rect x="40" y="0" width="640" height="220" fill="url(#grid)"></rect>
                            <path d="M40 0V220H680" fill="none" stroke="#8d99a8" stroke-width="1.5"></path>
                            <polyline points="<?php echo dashboardLinePoints($daily_values); ?>" fill="none" stroke="#08b7d4" stroke-width="4" transform="translate(40 0)"></polyline>
                            <?php
                            $point_pairs = explode(' ', dashboardLinePoints($daily_values));
                            foreach ($point_pairs as $pair):
                                [$x, $y] = explode(',', $pair);
                            ?>
                                <circle cx="<?php echo 40 + (float) $x; ?>" cy="<?php echo (float) $y; ?>" r="5" fill="#08b7d4" stroke="#fff" stroke-width="2"></circle>
                            <?php endforeach; ?>
                        </svg>
                        <div class="axis-labels">
                            <?php foreach ($daily_labels as $label): ?>
                                <span><?php echo e($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="panel chart-panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <h3>Shop Registrations</h3>
                                <p>New shops per week</p>
                            </div>
                        </div>
                        <div class="bar-chart" role="img" aria-label="Weekly shop registration chart">
                            <?php foreach ($weekly_values as $value): ?>
                                <div class="bar-item">
                                    <span class="bar" style="height: <?php echo max(8, round(($value / $max_weekly) * 230)); ?>px" title="<?php echo (int) $value; ?> shops"></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="axis-labels">
                            <?php foreach ($weekly_labels as $label): ?>
                                <span><?php echo e($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section id="pending-approvals" class="panel approval-panel">
                    <div class="approval-head">
                        <div class="title-row">
                            <span class="title-icon"><?php echo dashboardIcon('clock'); ?></span>
                            <div class="panel-title">
                                <h3>Pending Approvals</h3>
                                <p>Use the dedicated management screens for secure approval actions.</p>
                            </div>
                        </div>
                        <span class="pill"><?php echo (int) $pending_approvals; ?> Pending</span>
                    </div>

                    <div class="approval-list">
                        <article class="approval-item">
                            <div class="approval-avatar"><?php echo dashboardIcon('shop'); ?></div>
                            <div class="approval-copy">
                                <strong><?php echo (int) $pending_permits; ?> print shop<?php echo $pending_permits === 1 ? '' : 's'; ?> need review</strong>
                                <span>Open the Manage Print Shop page to search, filter, approve, reject, or disable shops.</span>
                            </div>
                            <div class="approval-actions">
                                <a class="approval-btn approve" href="manage_print_shops.php?status=pending"><?php echo dashboardIcon('shop'); ?>Manage Print Shop</a>
                            </div>
                        </article>

                        <?php foreach ($pending_accounts as $account): ?>
                            <article class="approval-item">
                                <div class="approval-avatar"><?php echo e(strtoupper(substr($account['full_name'], 0, 1))); ?></div>
                                <div class="approval-copy">
                                    <strong><?php echo e($account['full_name']); ?></strong>
                                    <span><?php echo e(ucfirst(str_replace('_', ' ', $account['role']))); ?> account</span>
                                    <small>Registered: <?php echo e(date('Y-m-d', strtotime($account['created_at']))); ?></small>
                                </div>
                                <div class="approval-actions">
                                    <a class="btn" href="manage_users.php"><?php echo dashboardIcon('users'); ?>Manage User</a>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if (empty($pending_shops) && empty($pending_accounts)): ?>
                            <div class="empty">No pending approvals right now.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="insights">
                    <h3><?php echo dashboardIcon('bulb'); ?>Key Insights</h3>
                    <div class="insight-grid">
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('sparkle'); ?>New Shops</strong>
                            <p><?php echo (int) $new_shops_today; ?> new shop<?php echo $new_shops_today === 1 ? '' : 's'; ?> registered today</p>
                        </article>
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('trend'); ?>Growth Rate</strong>
                            <p><?php echo (int) array_sum($daily_values); ?> new user<?php echo array_sum($daily_values) === 1 ? '' : 's'; ?> in the last 7 days</p>
                        </article>
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('activity'); ?>Platform Activity</strong>
                            <p><?php echo count($recent_activity); ?> latest activity item<?php echo count($recent_activity) === 1 ? '' : 's'; ?> available</p>
                        </article>
                    </div>
                </section>

                <section class="panel activity-panel">
                    <h3>Recent Activity</h3>
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty">No recent activity yet.</div>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recent_activity as $index => $activity): ?>
                                <article class="activity-item">
                                    <span class="activity-icon"><?php echo dashboardIcon($index === 0 ? 'shop' : ($index === 1 ? 'clock' : 'activity')); ?></span>
                                    <div class="activity-copy">
                                        <strong><?php echo e($activity['action']); ?></strong>
                                        <small><?php echo e($activity['module']); ?> by <?php echo e($activity['full_name']); ?></small>
                                    </div>
                                    <div class="activity-time">
                                        <span><?php echo e(dashboardRelativeTime($activity['created_at'])); ?></span>
                                        <span class="dot"></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
<?php adminLayoutEnd(); ?>
