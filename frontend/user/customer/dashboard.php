<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";

$customer_id = (int) $_SESSION['user_id'];
$full_name = (string) ($_SESSION['full_name'] ?? 'Customer');
$first_name = trim(explode(' ', trim($full_name))[0] ?? 'Customer');

$stmt = mysqli_prepare($conn, "SELECT phone_number, address, valid_id_file, account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$account_status = $user['account_status'] ?? 'incomplete';
$profile_complete = !empty($user['phone_number']) && !empty($user['address']) && !empty($user['valid_id_file']);
$dashboard_status = $profile_complete ? $account_status : 'incomplete';

function dashboardOrderStatusLabel($status)
{
    return match ($status) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucwords(str_replace('_', ' ', (string) $status)),
    };
}

function dashboardPaymentLabel($payment_status, $verification_status)
{
    if ($payment_status === 'paid' && $verification_status === 'verified') return 'Paid';
    if ($verification_status === 'pending') return 'Payment Under Review';
    if ($verification_status === 'rejected') return 'Payment Rejected';
    return 'Unpaid';
}

function dashboardFormatDate($datetime, $fallback = 'Not scheduled')
{
    if (empty($datetime)) return $fallback;
    return date('M d, Y - g:i A', strtotime($datetime));
}

function dashboardMoney($amount)
{
    return number_format((float) $amount, 2);
}

function dashboardOrderLink(array $order)
{
    $tab = ($order['order_status'] ?? '') === 'completed' ? 'completed' : 'active';
    return 'orders.php?status=' . $tab . '&focus_order_code=' . urlencode((string) $order['order_code']);
}

$metrics = ['active_orders' => 0, 'ready_orders' => 0, 'completed_orders' => 0, 'total_spent' => 0.0];
$current_order = null;
$recent_orders = [];

if ($account_status === 'verified') {
    $metrics_stmt = mysqli_prepare($conn, "SELECT
            SUM(CASE WHEN order_status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_orders,
            SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_orders,
            SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed_orders
        FROM orders WHERE customer_id = ?");
    mysqli_stmt_bind_param($metrics_stmt, 'i', $customer_id);
    mysqli_stmt_execute($metrics_stmt);
    $order_metrics = mysqli_fetch_assoc(mysqli_stmt_get_result($metrics_stmt)) ?: [];
    $metrics['active_orders'] = (int) ($order_metrics['active_orders'] ?? 0);
    $metrics['ready_orders'] = (int) ($order_metrics['ready_orders'] ?? 0);
    $metrics['completed_orders'] = (int) ($order_metrics['completed_orders'] ?? 0);

    $spending_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(p.amount), 0) AS total_spent
        FROM payments p
        JOIN orders o ON o.order_id = p.order_id
        WHERE o.customer_id = ? AND p.payment_status = 'paid' AND p.verification_status = 'verified'");
    mysqli_stmt_bind_param($spending_stmt, 'i', $customer_id);
    mysqli_stmt_execute($spending_stmt);
    $metrics['total_spent'] = (float) (mysqli_fetch_assoc(mysqli_stmt_get_result($spending_stmt))['total_spent'] ?? 0);

    $order_select = "SELECT o.order_id, o.order_code, o.order_status, o.created_at, o.pickup_datetime,
                            o.total_amount, ps.shop_name, p.payment_status, p.verification_status
                     FROM orders o
                     JOIN print_shops ps ON ps.shop_id = o.shop_id
                     LEFT JOIN payments p ON p.payment_id = (
                         SELECT p2.payment_id FROM payments p2
                         WHERE p2.order_id = o.order_id
                         ORDER BY p2.created_at DESC, p2.payment_id DESC LIMIT 1
                     )";

    $current_stmt = mysqli_prepare($conn, $order_select . " WHERE o.customer_id = ?
        AND o.order_status NOT IN ('completed', 'cancelled') ORDER BY o.created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($current_stmt, 'i', $customer_id);
    mysqli_stmt_execute($current_stmt);
    $current_order = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));

    $recent_stmt = mysqli_prepare($conn, $order_select . " WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 3");
    mysqli_stmt_bind_param($recent_stmt, 'i', $customer_id);
    mysqli_stmt_execute($recent_stmt);
    $recent_orders = mysqli_fetch_all(mysqli_stmt_get_result($recent_stmt), MYSQLI_ASSOC);
}

$manila_now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour = (int) $manila_now->format('G');
$greeting = $hour >= 5 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 18 ? 'Good afternoon' : 'Good evening');
$progress_statuses = ['pending', 'accepted', 'processing', 'ready_for_pickup', 'completed'];
$current_progress = $current_order ? array_search($current_order['order_status'], $progress_statuses, true) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Customer Dashboard</title>
    <?php renderCustomerHead(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="customer-body customer-dashboard-page bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="customer-page-frame min-h-screen">
        <?php renderCustomerLayout([
            'title' => 'Dashboard Overview',
            'subtitle' => 'Track your print orders and quickly access what you need.'
        ]); ?>

        <main class="customer-dashboard-main">
            <section class="customer-dashboard-hero">
                <div class="customer-dashboard-hero-copy">
                    <span><?php echo e($greeting); ?>,</span>
                    <h1><?php echo e($first_name); ?></h1>
                    <p>Your PrintEase activity, orders, and account updates are all in one place.</p>
                    <div class="customer-dashboard-account-status status-<?php echo e($dashboard_status); ?>">
                        <i></i><strong><?php echo e(ucfirst($dashboard_status)); ?> account</strong>
                    </div>
                </div>
                <?php if ($account_status !== 'verified'): ?>
                    <a href="profile.php" class="customer-dashboard-primary-action">
                        <?php echo customerIcon('profile'); ?><span><small>Account setup</small>Open Profile</span><?php echo customerIcon('arrow'); ?>
                    </a>
                <?php endif; ?>
            </section>

            <?php if ($account_status !== 'verified' || !$profile_complete): ?>
                <?php
                $status_title = 'Complete Your Profile';
                $status_message = 'Add your phone number, complete address, and valid ID to unlock shop browsing and ordering.';
                $status_action = 'Complete Profile';
                $status_tone = 'incomplete';
                if ($profile_complete && $account_status === 'pending') {
                    $status_title = 'Verification in Progress';
                    $status_message = 'Your profile has been submitted. You will get access after Super Admin approval.';
                    $status_action = 'Review Profile';
                    $status_tone = 'pending';
                } elseif ($account_status === 'rejected') {
                    $status_title = 'Account Needs Attention';
                    $status_message = 'Your verification was not approved. Review your profile details or contact the administrator.';
                    $status_action = 'Review Profile';
                    $status_tone = 'rejected';
                }
                ?>
                <section class="customer-dashboard-verification <?php echo e($status_tone); ?>">
                    <div class="customer-dashboard-verification-icon"><?php echo customerIcon($status_tone === 'pending' ? 'clock' : 'profile'); ?></div>
                    <div>
                        <span>Account access</span>
                        <h2><?php echo e($status_title); ?></h2>
                        <p><?php echo e($status_message); ?></p>
                    </div>
                    <a href="profile.php"><?php echo e($status_action); ?><?php echo customerIcon('arrow'); ?></a>
                </section>

                <section class="customer-dashboard-locked-overview" aria-label="Features available after verification">
                    <h2>What you can do after verification</h2>
                    <div>
                        <article><?php echo customerIcon('map'); ?><strong>Explore nearby shops</strong><span>Compare verified print shops on the map.</span></article>
                        <article><?php echo customerIcon('printer'); ?><strong>Place print orders</strong><span>Select services and submit files online.</span></article>
                        <article><?php echo customerIcon('orders'); ?><strong>Track every order</strong><span>Follow processing, payment, and pickup updates.</span></article>
                    </div>
                </section>
            <?php else: ?>
                <section class="customer-dashboard-kpis" aria-label="Order overview">
                    <article class="tone-blue"><span><?php echo customerIcon('package'); ?></span><div><small>Active Orders</small><strong><?php echo $metrics['active_orders']; ?></strong><p>Currently in progress</p></div></article>
                    <article class="tone-green"><span><?php echo customerIcon('check'); ?></span><div><small>Ready for Pickup</small><strong><?php echo $metrics['ready_orders']; ?></strong><p>Waiting for collection</p></div></article>
                    <article class="tone-cyan"><span><?php echo customerIcon('orders'); ?></span><div><small>Completed</small><strong><?php echo $metrics['completed_orders']; ?></strong><p>Finished print orders</p></div></article>
                    <article class="tone-navy"><span><?php echo customerIcon('wallet'); ?></span><div><small>Verified Spending</small><strong>&#8369;<?php echo e(dashboardMoney($metrics['total_spent'])); ?></strong><p>Lifetime paid total</p></div></article>
                </section>

                <section class="customer-dashboard-current-order" aria-labelledby="currentOrderTitle">
                    <div class="customer-dashboard-section-head">
                        <div><span>Order tracker</span><h2 id="currentOrderTitle">Current Order</h2></div>
                        <?php if ($current_order): ?><a href="<?php echo e(dashboardOrderLink($current_order)); ?>">Track Order<?php echo customerIcon('arrow'); ?></a><?php endif; ?>
                    </div>

                    <?php if ($current_order): ?>
                        <div class="customer-current-order-summary">
                            <div><small>Order code</small><strong>#<?php echo e($current_order['order_code']); ?></strong><span><?php echo e($current_order['shop_name']); ?></span></div>
                            <div><small>Order total</small><strong>&#8369;<?php echo e(dashboardMoney($current_order['total_amount'])); ?></strong><span><?php echo e(dashboardPaymentLabel($current_order['payment_status'] ?? '', $current_order['verification_status'] ?? '')); ?></span></div>
                            <div><small>Pickup schedule</small><strong><?php echo e(dashboardFormatDate($current_order['pickup_datetime'])); ?></strong><span><?php echo e(dashboardOrderStatusLabel($current_order['order_status'])); ?></span></div>
                        </div>
                        <ol class="customer-order-progress" aria-label="Order progress">
                            <?php foreach ($progress_statuses as $index => $status): ?>
                                <?php $state = $index < $current_progress ? 'complete' : ($index === $current_progress ? 'current' : 'upcoming'); ?>
                                <li class="<?php echo $state; ?>" <?php echo $state === 'current' ? 'aria-current="step"' : ''; ?>><span><?php echo $index < $current_progress ? customerIcon('check') : $index + 1; ?></span><strong><?php echo e(dashboardOrderStatusLabel($status)); ?></strong></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <div class="customer-dashboard-empty">
                            <span><?php echo customerIcon('package'); ?></span><div><strong>No active order</strong><p>Your next print order will appear here with live status and pickup details.</p></div><a href="explore.php?view=all">Browse Shops</a>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="customer-dashboard-lower-grid">
                    <section class="customer-dashboard-quick-actions">
                        <div class="customer-dashboard-section-head"><div><span>Shortcuts</span><h2>Quick Actions</h2></div></div>
                        <div>
                            <a href="explore.php?view=nearby"><span><?php echo customerIcon('map'); ?></span><strong>Nearby Shops</strong><small>Find shops on map</small><?php echo customerIcon('arrow'); ?></a>
                            <a href="explore.php?view=all"><span><?php echo customerIcon('shops'); ?></span><strong>All Shops</strong><small>Start a new order</small><?php echo customerIcon('arrow'); ?></a>
                            <a href="orders.php"><span><?php echo customerIcon('orders'); ?></span><strong>Track Orders</strong><small>View order history</small><?php echo customerIcon('arrow'); ?></a>
                            <a href="profile.php"><span><?php echo customerIcon('profile'); ?></span><strong>My Profile</strong><small>Manage your account</small><?php echo customerIcon('arrow'); ?></a>
                        </div>
                    </section>

                    <section class="customer-dashboard-recent-orders">
                        <div class="customer-dashboard-section-head"><div><span>Recent activity</span><h2>Latest Orders</h2></div><a href="orders.php">View All<?php echo customerIcon('arrow'); ?></a></div>
                        <?php if ($recent_orders): ?>
                            <div class="customer-recent-order-list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <a href="<?php echo e(dashboardOrderLink($order)); ?>">
                                        <span class="customer-recent-order-icon"><?php echo customerIcon('package'); ?></span>
                                        <div><strong>#<?php echo e($order['order_code']); ?></strong><span><?php echo e($order['shop_name']); ?></span><small><?php echo e(dashboardFormatDate($order['created_at'], '')); ?></small></div>
                                        <div><strong>&#8369;<?php echo e(dashboardMoney($order['total_amount'])); ?></strong><span class="status-<?php echo e($order['order_status']); ?>"><?php echo e(dashboardOrderStatusLabel($order['order_status'])); ?></span></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="customer-dashboard-empty compact"><span><?php echo customerIcon('orders'); ?></span><div><strong>No orders yet</strong><p>Your recent orders will appear here.</p></div></div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php renderCustomerLayoutEnd('home'); ?>
</body>
</html>
