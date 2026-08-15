<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/admin_layout.php";

function manageShopStatusLabel($status)
{
    return match ((string) $status) {
        'verified' => 'Approved',
        'rejected' => 'Rejected',
        'disabled' => 'Disabled',
        default => 'Pending',
    };
}

function manageShopStatusClass($status)
{
    return match ((string) $status) {
        'verified' => 'admin-shop-status admin-shop-status-approved',
        'rejected' => 'admin-shop-status admin-shop-status-rejected',
        'disabled' => 'admin-shop-status admin-shop-status-disabled',
        default => 'admin-shop-status admin-shop-status-pending',
    };
}

function managePaymentStatusLabel($status)
{
    return match ((string) $status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

function managePaymentStatusClass($status)
{
    return match ((string) $status) {
        'approved' => 'admin-shop-status admin-shop-status-approved',
        'rejected' => 'admin-shop-status admin-shop-status-rejected',
        'partial' => 'admin-shop-status admin-shop-status-partial',
        default => 'admin-shop-status admin-shop-status-pending',
    };
}

function managePaymentChannelLabel($channel)
{
    return match ((string) $channel) {
        'gcash_merchant_link' => 'GCash Link',
        default => 'GCash QR',
    };
}

function managePaymentChannelFullLabel($channel)
{
    return match ((string) $channel) {
        'gcash_merchant_link' => 'GCash Merchant Link',
        default => 'GCash QR Code',
    };
}

function managePaymentAggregateStatus(array $payments)
{
    $statuses = array_map(static fn($payment) => (string) ($payment['status_key'] ?? $payment['approval_status'] ?? 'pending'), $payments);
    if (in_array('pending', $statuses, true)) return 'pending';
    if (!empty($statuses) && count(array_unique($statuses)) === 1 && $statuses[0] === 'approved') return 'approved';
    if (!empty($statuses) && count(array_unique($statuses)) === 1 && $statuses[0] === 'rejected') return 'rejected';
    return 'partial';
}

function managePaymentAggregateLabel($status)
{
    return match ((string) $status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'partial' => 'Partially approved',
        default => 'Pending',
    };
}

function manageShopInitial($name)
{
    $name = trim((string) $name);
    return strtoupper(substr($name !== '' ? $name : 'S', 0, 1));
}

function manageShopBindParams($stmt, $types, array $params)
{
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

$search = trim((string) ($_GET['search'] ?? ''));
$filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowed_filters = ['all', 'pending', 'verified', 'rejected', 'disabled'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$summary = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'rejected' => 0,
    'disabled' => 0,
];

$summary_result = mysqli_query($conn, "
    SELECT COALESCE(permit_status, 'pending') AS permit_status, COUNT(*) AS total
    FROM print_shops
    GROUP BY COALESCE(permit_status, 'pending')
");
if ($summary_result) {
    while ($row = mysqli_fetch_assoc($summary_result)) {
        $status = (string) ($row['permit_status'] ?? 'pending');
        $count = (int) ($row['total'] ?? 0);
        $summary['total'] += $count;
        if (array_key_exists($status, $summary)) {
            $summary[$status] = $count;
        }
    }
}

$where = [];
$types = '';
$params = [];

if ($filter !== 'all') {
    $where[] = "COALESCE(ps.permit_status, 'pending') = ?";
    $types .= 's';
    $params[] = $filter;
}

if ($search !== '') {
    $where[] = "(LOWER(ps.shop_name) LIKE ? OR LOWER(u.full_name) LIKE ?)";
    $types .= 'ss';
    $like = '%' . strtolower($search) . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "
    SELECT
        ps.shop_id,
        ps.shop_name,
        ps.shop_address,
        ps.business_permit_file,
        ps.shop_logo,
        ps.permit_status,
        ps.shop_status,
        ps.created_at,
        u.full_name AS owner_name,
        u.email AS owner_email
    FROM print_shops ps
    JOIN users u ON ps.owner_id = u.user_id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY ps.created_at DESC, ps.shop_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types !== '') {
    manageShopBindParams($stmt, $types, $params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $shops_result = mysqli_stmt_get_result($stmt);
} else {
    $shops_result = false;
}

$shops = [];
if ($shops_result) {
    while ($row = mysqli_fetch_assoc($shops_result)) {
        $shops[] = $row;
    }
}

$filters = [
    'all' => ['label' => 'All', 'count' => $summary['total'], 'icon' => 'shops'],
    'pending' => ['label' => 'Pending', 'count' => $summary['pending'], 'icon' => 'clock'],
    'verified' => ['label' => 'Approved', 'count' => $summary['verified'], 'icon' => 'check'],
    'rejected' => ['label' => 'Rejected', 'count' => $summary['rejected'], 'icon' => 'x'],
    'disabled' => ['label' => 'Disabled', 'count' => $summary['disabled'], 'icon' => 'shield'],
];

$payment_settings_sql = "
    SELECT
        c.*,
        ps.shop_name,
        ps.shop_logo,
        u.full_name AS owner_name,
        u.email AS owner_email
    FROM shop_payment_channels c
    JOIN print_shops ps ON c.shop_id = ps.shop_id
    JOIN users u ON ps.owner_id = u.user_id
    WHERE (
        (c.channel = 'gcash_qr' AND c.gcash_qr_code IS NOT NULL AND c.gcash_qr_code <> '')
        OR (c.channel = 'gcash_merchant_link' AND c.merchant_link IS NOT NULL AND c.merchant_link <> '')
    )
    ORDER BY
        CASE c.approval_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        c.updated_at DESC
";
$payment_settings_result = mysqli_query($conn, $payment_settings_sql);
$payment_settings = [];
if ($payment_settings_result) {
    while ($row = mysqli_fetch_assoc($payment_settings_result)) {
        $payment_settings[] = $row;
    }
}

$payment_groups = [];
foreach ($payment_settings as $setting) {
    $shop_id = (int) ($setting['shop_id'] ?? 0);
    if ($shop_id <= 0) continue;

    $submitted_source = $setting['created_at'] ?? $setting['updated_at'] ?? '';
    $updated_source = $setting['updated_at'] ?? $setting['created_at'] ?? '';
    $submitted_at = $submitted_source !== '' ? date('Y-m-d', strtotime($submitted_source)) : 'N/A';
    $updated_at = $updated_source !== '' ? date('Y-m-d', strtotime($updated_source)) : 'N/A';
    $latest_ts = $updated_source !== '' ? strtotime($updated_source) : 0;

    if (!isset($payment_groups[$shop_id])) {
        $payment_groups[$shop_id] = [
            'shop_id' => $shop_id,
            'shop_name' => (string) ($setting['shop_name'] ?? 'Unnamed Shop'),
            'shop_logo' => (string) ($setting['shop_logo'] ?? ''),
            'owner_name' => (string) ($setting['owner_name'] ?? 'N/A'),
            'owner_email' => (string) ($setting['owner_email'] ?? 'N/A'),
            'latest_ts' => $latest_ts,
            'submitted_date' => $submitted_at,
            'payments' => [],
        ];
    } elseif ($latest_ts > (int) $payment_groups[$shop_id]['latest_ts']) {
        $payment_groups[$shop_id]['latest_ts'] = $latest_ts;
        $payment_groups[$shop_id]['submitted_date'] = $submitted_at;
    }

    $channel = (string) ($setting['channel'] ?? 'gcash_qr');
    $payment_groups[$shop_id]['payments'][] = [
        'id' => (int) ($setting['id'] ?? 0),
        'channel' => $channel,
        'label' => managePaymentChannelFullLabel($channel),
        'badge' => managePaymentChannelLabel($channel),
        'status' => managePaymentStatusLabel($setting['approval_status'] ?? 'pending'),
        'status_key' => (string) ($setting['approval_status'] ?? 'pending'),
        'approval_status' => (string) ($setting['approval_status'] ?? 'pending'),
        'merchant_link' => (string) ($setting['merchant_link'] ?? ''),
        'qr_url' => !empty($setting['gcash_qr_code']) ? GCASH_QR_URL . $setting['gcash_qr_code'] : '',
        'gcash_account_name' => (string) ($setting['gcash_account_name'] ?? ''),
        'gcash_number' => (string) ($setting['gcash_number'] ?? ''),
        'instructions' => (string) ($setting['instructions'] ?? ''),
        'submitted_at' => $submitted_at,
        'updated_at' => $updated_at,
        'rejected_reason' => (string) ($setting['rejected_reason'] ?? ''),
    ];
}

foreach ($payment_groups as &$payment_group) {
    $payment_group['payment_count'] = count($payment_group['payments']);
    $payment_group['aggregate_status'] = managePaymentAggregateStatus($payment_group['payments']);
    $payment_group['aggregate_label'] = managePaymentAggregateLabel($payment_group['aggregate_status']);
    $payment_group['has_pending'] = in_array('pending', array_column($payment_group['payments'], 'status_key'), true);
}
unset($payment_group);

adminLayoutStart('shops', 'Manage Print Shop', 'Review shop permits, filter shop status, and control shop availability.');
?>
<section class="admin-shop-manager">
    <form class="admin-shop-toolbar" method="GET" action="manage_print_shops.php" data-live-search-form data-live-target="admin_shops" data-live-min="1">
        <label class="admin-shop-search" aria-label="Search shops">
            <?php echo adminIcon('search'); ?>
            <input type="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by shop name or owner name...">
        </label>
        <input type="hidden" name="status" value="<?php echo e($filter); ?>">
        <button class="admin-shop-search-button" type="submit">Search</button>
    </form>

    <nav class="admin-shop-filters" aria-label="Shop status filters" data-live-region="admin-shop-filters">
        <?php foreach ($filters as $key => $item): ?>
            <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($key !== 'all') $query['status'] = $key;
                $href = 'manage_print_shops.php' . (!empty($query) ? '?' . http_build_query($query) : '');
            ?>
            <a class="<?php echo $filter === $key ? 'is-active' : ''; ?>" href="<?php echo e($href); ?>" <?php echo $filter === $key ? 'aria-current="page"' : ''; ?>>
                <?php echo adminIcon($item['icon']); ?>
                <span><?php echo e($item['label']); ?></span>
                <strong><?php echo (int) $item['count']; ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="admin-shop-stats" aria-label="Shop management summary" data-live-region="admin-shop-stats">
        <article class="admin-shop-stat admin-shop-stat-total">
            <span><?php echo adminIcon('shops'); ?></span>
            <strong><?php echo (int) $summary['total']; ?></strong>
            <p>Total Shops</p>
        </article>
        <article class="admin-shop-stat admin-shop-stat-approved">
            <span><?php echo adminIcon('check'); ?></span>
            <strong><?php echo (int) $summary['verified']; ?></strong>
            <p>Approved Shops</p>
        </article>
        <article class="admin-shop-stat admin-shop-stat-pending">
            <span><?php echo adminIcon('clock'); ?></span>
            <strong><?php echo (int) $summary['pending']; ?></strong>
            <p>Pending Shops</p>
        </article>
        <article class="admin-shop-stat admin-shop-stat-rejected">
            <span><?php echo adminIcon('x'); ?></span>
            <strong><?php echo (int) $summary['rejected']; ?></strong>
            <p>Rejected Shops</p>
        </article>
        <article class="admin-shop-stat admin-shop-stat-disabled">
            <span><?php echo adminIcon('shield'); ?></span>
            <strong><?php echo (int) $summary['disabled']; ?></strong>
            <p>Disabled Shops</p>
        </article>
    </section>

    <section class="admin-shop-table-card" data-live-region="admin-shop-results">
        <div class="admin-shop-table-wrap">
            <table class="admin-shop-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Select all shops" data-admin-shop-select-all></th>
                        <th>Shop Name</th>
                        <th>Owner Name</th>
                        <th>Status</th>
                        <th>Date Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shops)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="admin-empty compact">No print shops match this view.</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($shops as $shop): ?>
                        <?php
                            $status = (string) ($shop['permit_status'] ?? 'pending');
                            $status = $status !== '' ? $status : 'pending';
                            $permit_url = !empty($shop['business_permit_file']) ? PERMITS_URL . $shop['business_permit_file'] : '';
                            $logo_url = !empty($shop['shop_logo']) ? SHOP_LOGOS_URL . $shop['shop_logo'] : '';
                            $created_at = !empty($shop['created_at']) ? date('Y-m-d', strtotime($shop['created_at'])) : 'N/A';
                        ?>
                        <tr>
                            <td><input type="checkbox" aria-label="Select <?php echo e($shop['shop_name']); ?>"></td>
                            <td>
                                <div class="admin-shop-name">
                                    <?php if ($logo_url !== ''): ?>
                                        <img src="<?php echo e($logo_url); ?>" alt="<?php echo e($shop['shop_name']); ?> logo" class="admin-shop-avatar" loading="lazy" onerror="this.remove()">
                                    <?php else: ?>
                                        <span><?php echo e(manageShopInitial($shop['shop_name'])); ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo e($shop['shop_name']); ?></strong>
                                        <small><?php echo e($shop['shop_address'] ?: 'No address provided'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="admin-shop-owner">
                                    <strong><?php echo e($shop['owner_name']); ?></strong>
                                    <small><?php echo e($shop['owner_email']); ?></small>
                                </div>
                            </td>
                            <td><span class="<?php echo e(manageShopStatusClass($status)); ?>"><?php echo e(manageShopStatusLabel($status)); ?></span></td>
                            <td><span class="admin-shop-date"><?php echo adminIcon('clock'); ?><?php echo e($created_at); ?></span></td>
                            <td>
                                <div class="admin-shop-actions">
                                    <button
                                        type="button"
                                        class="admin-shop-action admin-shop-action-view"
                                        data-shop-view
                                        data-shop-name="<?php echo e($shop['shop_name']); ?>"
                                        data-owner-name="<?php echo e($shop['owner_name']); ?>"
                                        data-owner-email="<?php echo e($shop['owner_email']); ?>"
                                        data-address="<?php echo e($shop['shop_address'] ?: 'No address provided'); ?>"
                                        data-status="<?php echo e(manageShopStatusLabel($status)); ?>"
                                        data-shop-status="<?php echo e($shop['shop_status'] ?: 'N/A'); ?>"
                                        data-created="<?php echo e($created_at); ?>"
                                        data-permit="<?php echo e($permit_url); ?>"
                                        data-logo="<?php echo e($logo_url); ?>"
                                    >
                                        <?php echo adminIcon('search'); ?>View
                                    </button>

                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="shop_id" value="<?php echo (int) $shop['shop_id']; ?>">
                                            <input type="hidden" name="status" value="verified">
                                            <input type="hidden" name="return_to" value="manage_print_shops.php">
                                            <button class="admin-shop-action admin-shop-action-approve" type="submit"><?php echo adminIcon('check'); ?>Approve</button>
                                        </form>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="shop_id" value="<?php echo (int) $shop['shop_id']; ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="return_to" value="manage_print_shops.php">
                                            <button class="admin-shop-action admin-shop-action-reject" type="submit"><?php echo adminIcon('x'); ?>Reject</button>
                                        </form>
                                    <?php elseif ($status === 'verified'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="shop_id" value="<?php echo (int) $shop['shop_id']; ?>">
                                            <input type="hidden" name="status" value="disabled">
                                            <input type="hidden" name="return_to" value="manage_print_shops.php">
                                            <button class="admin-shop-action admin-shop-action-disable" type="submit"><?php echo adminIcon('shield'); ?>Disable</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="shop_id" value="<?php echo (int) $shop['shop_id']; ?>">
                                            <input type="hidden" name="status" value="verified">
                                            <input type="hidden" name="return_to" value="manage_print_shops.php">
                                            <button class="admin-shop-action admin-shop-action-approve" type="submit"><?php echo adminIcon('check'); ?>Re-approve</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-shop-table-card" id="payment-settings-review" data-live-region="admin-payment-settings-results" style="margin-top:24px;">
        <div class="admin-payment-review-head">
            <div>
                <h2>Payment Settings Review</h2>
                <p>Approve shop owner-provided GCash details before customers can use them.</p>
            </div>
            <span><?php echo count($payment_groups); ?> <?php echo count($payment_groups) === 1 ? 'shop' : 'shops'; ?></span>
        </div>
        <div class="admin-shop-table-wrap admin-payment-review-wrap">
            <table class="admin-shop-table admin-payment-review-table">
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>Payment Methods</th>
                        <th>Submitted Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payment_groups)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="admin-empty compact">No payment settings submitted yet.</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payment_groups as $group): ?>
                        <?php $details_json = json_encode($group, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>
                        <tr class="admin-payment-group-row">
                            <td data-label="Shop">
                                <?php
                                    $group_logo_url = !empty($group['shop_logo']) ? SHOP_LOGOS_URL . $group['shop_logo'] : '';
                                ?>
                                <div class="admin-shop-name admin-payment-shop">
                                    <?php if ($group_logo_url !== ''): ?>
                                        <img src="<?php echo e($group_logo_url); ?>" alt="<?php echo e($group['shop_name']); ?> logo" class="admin-shop-avatar" loading="lazy" onerror="this.remove()">
                                    <?php else: ?>
                                        <span><?php echo e(manageShopInitial($group['shop_name'])); ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo e($group['shop_name']); ?></strong>
                                        <small><?php echo e($group['owner_name']); ?></small>
                                        <small><?php echo e($group['owner_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <?php
                                $pending_payments = array_values(array_filter($group['payments'], static fn($p) => ($p['status_key'] ?? '') === 'pending'));
                                $pending_count = count($pending_payments);
                                $single_pending_label = $pending_count === 1 ? ($pending_payments[0]['channel'] === 'gcash_merchant_link' ? 'GCash Link' : 'GCash QR') : '';
                            ?>
                            <td data-label="Payment Methods">
                                <div class="admin-payment-methods">
                                    <strong>
                                        <?php echo (int) $group['payment_count']; ?> Payment <?php echo (int) $group['payment_count'] === 1 ? 'Method' : 'Methods'; ?>
                                        <?php if ($pending_count > 0): ?>
                                            <small style="color:#b45309;font-weight:800;margin-left:4px;">(<?php echo (int) $pending_count; ?> Needs Review)</small>
                                        <?php endif; ?>
                                    </strong>
                                    <div class="admin-payment-badges">
                                        <?php foreach ($group['payments'] as $payment): ?>
                                            <?php
                                                $status_key = (string) ($payment['status_key'] ?? 'pending');
                                                $badge_class = 'admin-payment-method-badge admin-payment-status-badge-' . e($status_key);
                                            ?>
                                            <span class="<?php echo $badge_class; ?>">
                                                <?php echo adminIcon($payment['channel'] === 'gcash_merchant_link' ? 'search' : 'file'); ?>
                                                <?php echo e($payment['badge']); ?>
                                                <small class="admin-payment-badge-status status-<?php echo e($status_key); ?>"><?php echo e($payment['status']); ?></small>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Submitted Date"><span class="admin-shop-date"><?php echo adminIcon('clock'); ?><?php echo e($group['submitted_date']); ?></span></td>
                            <td data-label="Status"><span class="<?php echo e(managePaymentStatusClass($group['aggregate_status'])); ?>"><?php echo e($group['aggregate_label']); ?></span></td>
                            <td data-label="Actions">
                                <div class="admin-shop-actions admin-payment-group-actions">
                                    <button
                                        type="button"
                                        class="admin-shop-action admin-shop-action-view"
                                        data-payment-view
                                        data-payment-details="<?php echo e($details_json ?: '{}'); ?>"
                                    >
                                        <?php echo adminIcon('search'); ?>View
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (false): ?>
    <section class="admin-shop-table-card" data-live-region="admin-payment-settings-results" style="margin-top:24px;">
        <div style="padding:18px 20px 4px;">
            <h2 style="margin:0;font-size:20px;">Payment Settings Review</h2>
            <p style="margin:6px 0 0;color:#667085;">Approve shop owner-provided GCash details before customers can use them.</p>
        </div>
        <div class="admin-shop-table-wrap">
            <table class="admin-shop-table">
                <thead>
                    <tr>
                        <th>Shop</th>
                        <th>GCash Details</th>
                        <th>QR / Link</th>
                        <th>Instructions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payment_settings)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="admin-empty compact">No payment settings submitted yet.</div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payment_settings as $setting): ?>
                        <?php
                            $payment_status = (string) ($setting['approval_status'] ?? 'pending');
                            $channel = (string) ($setting['channel'] ?? 'gcash_qr');
                            $channel_label = $channel === 'gcash_merchant_link' ? 'GCash Merchant Link' : 'GCash QR Code';
                            $qr_url = !empty($setting['gcash_qr_code']) ? GCASH_QR_URL . $setting['gcash_qr_code'] : '';
                        ?>
                        <tr>
                            <td>
                                <div class="admin-shop-owner">
                                    <strong><?php echo e($setting['shop_name']); ?></strong>
                                    <small><?php echo e($setting['owner_name']); ?> · <?php echo e($setting['owner_email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo e($channel_label); ?></strong>
                                <?php if ($channel === 'gcash_merchant_link'): ?>
                                    <small style="display:block;word-break:break-all;"><?php echo e($setting['merchant_link'] ?? 'No link provided'); ?></small>
                                <?php else: ?>
                                    <small style="display:block;"><?php echo e($setting['gcash_account_name'] ?? 'N/A'); ?> · <?php echo e($setting['gcash_number'] ?? 'N/A'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-payment-link-actions">
                                    <?php if ($channel === 'gcash_qr' && $qr_url !== ''): ?>
                                        <a class="admin-payment-link-btn primary" href="<?php echo e($qr_url); ?>" target="_blank" rel="noopener">
                                            <?php echo adminIcon('file'); ?>View QR
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($channel === 'gcash_merchant_link' && !empty($setting['merchant_link'])): ?>
                                        <a class="admin-payment-link-btn" href="<?php echo e($setting['merchant_link']); ?>" target="_blank" rel="noopener">
                                            <?php echo adminIcon('search'); ?>GCash Merchant Link
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php
                                $short_instructions = (string) ($setting['instructions'] ?? '');
                                if (strlen($short_instructions) > 120) {
                                    $short_instructions = substr($short_instructions, 0, 117) . '...';
                                }
                            ?>
                            <td><?php echo e($short_instructions); ?></td>
                            <td><span class="<?php echo e(managePaymentStatusClass($payment_status)); ?>"><?php echo e(managePaymentStatusLabel($payment_status)); ?></span></td>
                            <td>
                                <div class="admin-shop-actions">
                                    <?php if ($payment_status !== 'approved'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_payment_settings_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="settings_id" value="<?php echo (int) $setting['id']; ?>">
                                            <input type="hidden" name="channel" value="<?php echo e($channel); ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button class="admin-shop-action admin-shop-action-approve" type="submit"><?php echo adminIcon('check'); ?>Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($payment_status !== 'rejected'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_payment_settings_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="settings_id" value="<?php echo (int) $setting['id']; ?>">
                                            <input type="hidden" name="channel" value="<?php echo e($channel); ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="admin-shop-action admin-shop-action-reject" type="submit"><?php echo adminIcon('x'); ?>Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</section>

<div class="admin-shop-modal" id="adminShopModal" aria-hidden="true">
    <div class="admin-shop-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminShopModalTitle">
        <button class="admin-shop-modal__close" type="button" data-shop-modal-close aria-label="Close shop details">&times;</button>
        <span class="admin-shop-modal__eyebrow">Print Shop Details</span>
        <h2 id="adminShopModalTitle">Shop Details</h2>
        <div class="admin-shop-modal__logo-wrap" data-shop-modal-logo-wrap hidden>
            <img data-shop-modal-logo alt="Shop logo">
        </div>
        <dl>
            <div><dt>Owner</dt><dd data-shop-modal-owner></dd></div>
            <div><dt>Email</dt><dd data-shop-modal-email></dd></div>
            <div><dt>Address</dt><dd data-shop-modal-address></dd></div>
            <div><dt>Permit Status</dt><dd data-shop-modal-status></dd></div>
            <div><dt>Shop Status</dt><dd data-shop-modal-shop-status></dd></div>
            <div><dt>Date Registered</dt><dd data-shop-modal-created></dd></div>
        </dl>
        <a class="admin-shop-modal__permit" href="#" target="_blank" rel="noopener" data-shop-modal-permit>View Business Permit</a>
    </div>
</div>

<div class="admin-shop-modal admin-payment-modal" id="adminPaymentModal" aria-hidden="true">
    <div class="admin-shop-modal__panel admin-payment-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminPaymentModalTitle">
        <button class="admin-shop-modal__close" type="button" data-payment-modal-close aria-label="Close payment details">&times;</button>
        <span class="admin-shop-modal__eyebrow">Payment Settings</span>
        <h2 id="adminPaymentModalTitle">Payment Details</h2>
        <div class="admin-payment-modal-owner">
            <strong data-payment-modal-owner></strong>
            <span data-payment-modal-email></span>
        </div>
        <div class="admin-payment-detail-list" data-payment-modal-list></div>
    </div>
</div>

<div class="admin-shop-modal admin-payment-modal" id="adminPaymentRejectModal" aria-hidden="true">
    <div class="admin-shop-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminPaymentRejectTitle">
        <button class="admin-shop-modal__close" type="button" data-payment-reject-close aria-label="Close rejection confirmation">&times;</button>
        <span class="admin-shop-modal__eyebrow">Confirm Rejection</span>
        <h2 id="adminPaymentRejectTitle">Reject Payment Methods?</h2>
        <p class="admin-payment-confirm-copy">Reject all pending payment methods submitted by this shop?</p>
        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_payment_settings_status.php" class="admin-payment-reject-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="scope" value="shop" data-payment-reject-scope>
            <input type="hidden" name="shop_id" value="" data-payment-reject-shop-id>
            <input type="hidden" name="settings_id" value="" data-payment-reject-settings-id>
            <input type="hidden" name="status" value="rejected">
            <label>
                Rejection Reason <span>(optional)</span>
                <textarea name="rejection_reason" maxlength="500" rows="4" placeholder="Add context for the shop owner..." data-payment-reject-reason></textarea>
            </label>
            <div class="admin-payment-confirm-actions">
                <button class="admin-payment-link-btn" type="button" data-payment-reject-close>Cancel</button>
                <button class="admin-shop-action admin-shop-action-reject" type="submit" data-payment-reject-submit><?php echo adminIcon('x'); ?>Reject All Pending</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-shop-modal admin-payment-modal" id="adminImageLightboxModal" aria-hidden="true" style="z-index: 100000;">
    <div class="admin-shop-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminLightboxTitle" style="max-width: 540px; text-align: center;">
        <button class="admin-shop-modal__close" type="button" data-lightbox-close aria-label="Close image preview">&times;</button>
        <span class="admin-shop-modal__eyebrow" id="adminLightboxTitle">GCash QR Code</span>
        <div style="margin-top: 14px; background: #fff; padding: 12px; border-radius: 16px; border: 1px solid #b7efff;">
            <img src="" id="adminLightboxImg" alt="Full image preview" style="max-width: 100%; max-height: 70vh; border-radius: 12px; object-fit: contain; display: block; margin: 0 auto;">
        </div>
    </div>
</div>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function () {
        const selectAll = document.querySelector('[data-admin-shop-select-all]');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.admin-shop-table tbody input[type="checkbox"]').forEach(function (box) {
                    box.checked = selectAll.checked;
                });
            });
        }

        const modal = document.getElementById('adminShopModal');
        if (!modal) return;

        const fields = {
            title: modal.querySelector('#adminShopModalTitle'),
            owner: modal.querySelector('[data-shop-modal-owner]'),
            email: modal.querySelector('[data-shop-modal-email]'),
            address: modal.querySelector('[data-shop-modal-address]'),
            status: modal.querySelector('[data-shop-modal-status]'),
            shopStatus: modal.querySelector('[data-shop-modal-shop-status]'),
            created: modal.querySelector('[data-shop-modal-created]'),
            permit: modal.querySelector('[data-shop-modal-permit]'),
            logoWrap: modal.querySelector('[data-shop-modal-logo-wrap]'),
            logoImg: modal.querySelector('[data-shop-modal-logo]')
        };

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (fields.logoWrap) fields.logoWrap.hidden = true;
            if (fields.logoImg) fields.logoImg.src = '';
        }

        document.querySelectorAll('[data-shop-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                fields.title.textContent = button.dataset.shopName || 'Shop Details';
                fields.owner.textContent = button.dataset.ownerName || 'N/A';
                fields.email.textContent = button.dataset.ownerEmail || 'N/A';
                fields.address.textContent = button.dataset.address || 'N/A';
                fields.status.textContent = button.dataset.status || 'Pending';
                fields.shopStatus.textContent = button.dataset.shopStatus || 'N/A';
                fields.created.textContent = button.dataset.created || 'N/A';

                if (button.dataset.logo) {
                    fields.logoWrap.hidden = false;
                    fields.logoImg.src = button.dataset.logo;
                } else {
                    fields.logoWrap.hidden = true;
                    fields.logoImg.src = '';
                }

                if (button.dataset.permit) {
                    fields.permit.href = button.dataset.permit;
                    fields.permit.classList.remove('is-disabled');
                    fields.permit.textContent = 'View Business Permit';
                } else {
                    fields.permit.href = '#';
                    fields.permit.classList.add('is-disabled');
                    fields.permit.textContent = 'No permit uploaded';
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.matches('[data-shop-modal-close]')) closeModal();
        });

        const paymentModal = document.getElementById('adminPaymentModal');
        const paymentRejectModal = document.getElementById('adminPaymentRejectModal');
        const paymentStatusActionUrl = <?php echo json_encode(BASE_URL . 'backend/actions/update_payment_settings_status.php'); ?>;
        const csrfFieldHtml = <?php echo json_encode(csrfField()); ?>;

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function paymentStatusClass(status) {
            if (status === 'approved') return 'admin-shop-status admin-shop-status-approved';
            if (status === 'rejected') return 'admin-shop-status admin-shop-status-rejected';
            return 'admin-shop-status admin-shop-status-pending';
        }

        function detailRow(label, value) {
            if (!value) return '';
            return '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
        }

        function paymentShortLabel(payment) {
            return payment.channel === 'gcash_merchant_link' ? 'Link' : 'QR';
        }

        function paymentActions(payment) {
            if ((payment.status_key || 'pending') !== 'pending') return '';

            const shortLabel = paymentShortLabel(payment);
            return '<div class="admin-payment-method-actions">'
                + '<form method="POST" action="' + escapeHtml(paymentStatusActionUrl) + '">'
                + csrfFieldHtml
                + '<input type="hidden" name="settings_id" value="' + escapeHtml(payment.id) + '">'
                + '<input type="hidden" name="status" value="approved">'
                + '<button class="admin-shop-action admin-shop-action-approve" type="submit"><?php echo str_replace(["\r", "\n"], '', adminIcon('check')); ?>Approve ' + shortLabel + '</button>'
                + '</form>'
                + '<button class="admin-shop-action admin-shop-action-reject" type="button" data-payment-reject data-reject-scope="channel" data-settings-id="' + escapeHtml(payment.id) + '" data-method-label="' + escapeHtml(payment.label || 'Payment Method') + '"><?php echo str_replace(["\r", "\n"], '', adminIcon('x')); ?>Reject ' + shortLabel + '</button>'
                + '</div>';
        }

        function closePaymentModal() {
            if (!paymentModal) return;
            paymentModal.classList.remove('is-open');
            paymentModal.setAttribute('aria-hidden', 'true');
        }

        function closeRejectModal() {
            if (!paymentRejectModal) return;
            paymentRejectModal.classList.remove('is-open');
            paymentRejectModal.setAttribute('aria-hidden', 'true');
        }

        if (paymentModal) {
            const paymentTitle = paymentModal.querySelector('#adminPaymentModalTitle');
            const paymentOwner = paymentModal.querySelector('[data-payment-modal-owner]');
            const paymentEmail = paymentModal.querySelector('[data-payment-modal-email]');
            const paymentList = paymentModal.querySelector('[data-payment-modal-list]');

            document.querySelectorAll('[data-payment-view]').forEach(function (button) {
                button.addEventListener('click', function () {
                    let group = {};
                    try {
                        group = JSON.parse(button.dataset.paymentDetails || '{}');
                    } catch (error) {
                        group = {};
                    }

                    paymentTitle.textContent = group.shop_name || 'Payment Details';
                    paymentOwner.textContent = group.owner_name || 'N/A';
                    paymentEmail.textContent = group.owner_email || 'N/A';
                    paymentList.innerHTML = (group.payments || []).map(function (payment, index) {
                        const status = payment.status_key || 'pending';
                        const link = payment.merchant_link
                            ? '<a class="admin-payment-link-btn" href="' + escapeHtml(payment.merchant_link) + '" target="_blank" rel="noopener"><?php echo str_replace(["\r", "\n"], '', adminIcon('search')); ?> Open Merchant Link</a>'
                            : '';
                        const qr = payment.qr_url
                            ? '<a class="admin-payment-qr-preview" href="' + escapeHtml(payment.qr_url) + '" target="_blank" rel="noopener" title="Click to view full size QR code"><img src="' + escapeHtml(payment.qr_url) + '" alt="GCash QR preview"></a>'
                            : '';

                        let bannerHtml = '';
                        let cardClass = 'admin-payment-detail-card';
                        if (status === 'pending') {
                            cardClass += ' is-pending';
                            bannerHtml = '<div class="admin-payment-card-banner is-pending"><?php echo str_replace(["\r", "\n"], '', adminIcon('clock')); ?> Needs Approval / Updated</div>';
                        } else if (status === 'approved') {
                            bannerHtml = '<div class="admin-payment-card-banner is-approved"><?php echo str_replace(["\r", "\n"], '', adminIcon('check')); ?> Active / Approved</div>';
                        }

                        return '<article class="' + cardClass + '">'
                            + bannerHtml
                            + '<header><div><span>Payment Method ' + (index + 1) + '</span><strong>' + escapeHtml(payment.label || 'Payment Method') + '</strong></div>'
                            + '<span class="' + paymentStatusClass(status) + '">' + escapeHtml(payment.status || 'Pending') + '</span></header>'
                            + '<dl>'
                            + detailRow('Type', payment.label)
                            + detailRow('Account Name', payment.gcash_account_name)
                            + detailRow('GCash Number', payment.gcash_number)
                            + detailRow('Date Submitted', payment.submitted_at)
                            + detailRow('Last Updated', payment.updated_at)
                            + detailRow('Rejection Reason', payment.rejected_reason)
                            + detailRow('Instructions', payment.instructions)
                            + '</dl>'
                            + (link || qr ? '<div class="admin-payment-detail-media">' + link + qr + '</div>' : '')
                            + paymentActions(payment)
                            + '</article>';
                    }).join('');

                    paymentModal.classList.add('is-open');
                    paymentModal.setAttribute('aria-hidden', 'false');
                });
            });

            paymentModal.addEventListener('click', function (event) {
                if (event.target === paymentModal || event.target.matches('[data-payment-modal-close]')) closePaymentModal();
            });
        }

        if (paymentRejectModal) {
            const rejectScope = paymentRejectModal.querySelector('[data-payment-reject-scope]');
            const rejectShopId = paymentRejectModal.querySelector('[data-payment-reject-shop-id]');
            const rejectSettingsId = paymentRejectModal.querySelector('[data-payment-reject-settings-id]');
            const rejectReason = paymentRejectModal.querySelector('[data-payment-reject-reason]');
            const rejectTitle = paymentRejectModal.querySelector('#adminPaymentRejectTitle');
            const rejectCopy = paymentRejectModal.querySelector('.admin-payment-confirm-copy');
            const rejectSubmit = paymentRejectModal.querySelector('[data-payment-reject-submit]');

            function openRejectModal(button) {
                const scope = button.dataset.rejectScope || 'shop';
                rejectScope.value = scope;
                rejectShopId.value = scope === 'shop' ? (button.dataset.shopId || '') : '';
                rejectSettingsId.value = scope === 'channel' ? (button.dataset.settingsId || '') : '';
                rejectReason.value = '';

                if (scope === 'channel') {
                    const methodLabel = button.dataset.methodLabel || 'Payment Method';
                    rejectTitle.textContent = 'Reject ' + methodLabel + '?';
                    rejectCopy.textContent = 'Reject this payment method submitted by this shop?';
                    rejectSubmit.innerHTML = '<?php echo str_replace(["\r", "\n"], '', adminIcon('x')); ?>Reject Method';
                } else {
                    rejectTitle.textContent = 'Reject ' + (button.dataset.shopName || 'Payment Methods') + '?';
                    rejectCopy.textContent = 'Reject all pending payment methods submitted by this shop?';
                    rejectSubmit.innerHTML = '<?php echo str_replace(["\r", "\n"], '', adminIcon('x')); ?>Reject All Pending';
                }

                paymentRejectModal.classList.add('is-open');
                paymentRejectModal.setAttribute('aria-hidden', 'false');
                rejectReason.focus();
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-payment-reject]');
                if (button) openRejectModal(button);
            });

            paymentRejectModal.addEventListener('click', function (event) {
                if (event.target === paymentRejectModal || event.target.matches('[data-payment-reject-close]')) closeRejectModal();
            });
        }

        const imageLightboxModal = document.getElementById('adminImageLightboxModal');
        if (imageLightboxModal) {
            const lightboxImg = document.getElementById('adminLightboxImg');
            const lightboxOpenNew = document.getElementById('adminLightboxOpenNew');
            const lightboxTitle = document.getElementById('adminLightboxTitle');

            function openLightbox(src, title) {
                if (!src) return;
                lightboxImg.src = src;
                if (lightboxOpenNew) lightboxOpenNew.href = src;
                if (lightboxTitle) lightboxTitle.textContent = title || 'GCash QR Code';
                imageLightboxModal.classList.add('is-open');
                imageLightboxModal.setAttribute('aria-hidden', 'false');
            }

            function closeLightbox() {
                imageLightboxModal.classList.remove('is-open');
                imageLightboxModal.setAttribute('aria-hidden', 'true');
                lightboxImg.src = '';
            }

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-lightbox-src], .admin-payment-qr-preview');
                if (trigger) {
                    event.preventDefault();
                    const src = trigger.dataset.lightboxSrc || trigger.getAttribute('href') || trigger.querySelector('img')?.src;
                    const title = trigger.dataset.lightboxTitle || 'GCash QR Code';
                    openLightbox(src, title);
                    return;
                }
                if (event.target === imageLightboxModal || event.target.matches('[data-lightbox-close]')) {
                    closeLightbox();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
                closePaymentModal();
                closeRejectModal();
                if (imageLightboxModal) {
                    imageLightboxModal.classList.remove('is-open');
                    imageLightboxModal.setAttribute('aria-hidden', 'true');
                }
            }
        });
    })();
</script>
<?php adminLayoutEnd(); ?>
