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
        default => 'admin-shop-status admin-shop-status-pending',
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
        sps.*,
        ps.shop_name,
        u.full_name AS owner_name,
        u.email AS owner_email
    FROM shop_payment_settings sps
    JOIN print_shops ps ON sps.shop_id = ps.shop_id
    JOIN users u ON ps.owner_id = u.user_id
    ORDER BY
        CASE sps.approval_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            ELSE 3
        END,
        sps.updated_at DESC
";
$payment_settings_result = mysqli_query($conn, $payment_settings_sql);
$payment_settings = [];
if ($payment_settings_result) {
    while ($row = mysqli_fetch_assoc($payment_settings_result)) {
        $payment_settings[] = $row;
    }
}

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
                            $created_at = !empty($shop['created_at']) ? date('Y-m-d', strtotime($shop['created_at'])) : 'N/A';
                        ?>
                        <tr>
                            <td><input type="checkbox" aria-label="Select <?php echo e($shop['shop_name']); ?>"></td>
                            <td>
                                <div class="admin-shop-name">
                                    <span><?php echo e(manageShopInitial($shop['shop_name'])); ?></span>
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
                                <strong><?php echo e($setting['gcash_account_name']); ?></strong>
                                <small style="display:block;"><?php echo e($setting['gcash_number']); ?></small>
                            </td>
                            <td>
                                <div class="admin-payment-link-actions">
                                    <?php if ($qr_url !== ''): ?>
                                        <a class="admin-payment-link-btn primary" href="<?php echo e($qr_url); ?>" target="_blank" rel="noopener">
                                            <?php echo adminIcon('file'); ?>View QR
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($setting['merchant_link'])): ?>
                                        <a class="admin-payment-link-btn" href="<?php echo e($setting['merchant_link']); ?>" target="_blank" rel="noopener">
                                            <?php echo adminIcon('search'); ?>Payment Link
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php
                                $short_instructions = (string) $setting['instructions'];
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
                                            <input type="hidden" name="status" value="approved">
                                            <button class="admin-shop-action admin-shop-action-approve" type="submit"><?php echo adminIcon('check'); ?>Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($payment_status !== 'rejected'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_payment_settings_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="settings_id" value="<?php echo (int) $setting['id']; ?>">
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
</section>

<div class="admin-shop-modal" id="adminShopModal" aria-hidden="true">
    <div class="admin-shop-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminShopModalTitle">
        <button class="admin-shop-modal__close" type="button" data-shop-modal-close aria-label="Close shop details">&times;</button>
        <span class="admin-shop-modal__eyebrow">Print Shop Details</span>
        <h2 id="adminShopModalTitle">Shop Details</h2>
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

<script>
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
            permit: modal.querySelector('[data-shop-modal-permit]')
        };

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
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
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeModal();
        });
    })();
</script>
<?php adminLayoutEnd(); ?>
