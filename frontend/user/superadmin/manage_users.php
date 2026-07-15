<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/admin_layout.php";

function manageUserStatusLabel($status)
{
    return match ((string) $status) {
        'verified' => 'Active',
        'rejected' => 'Rejected',
        'inactive' => 'Inactive',
        'incomplete' => 'Pending',
        default => 'Pending',
    };
}

function manageUserStatusClass($status)
{
    return match ((string) $status) {
        'verified' => 'admin-user-status admin-user-status-active',
        'rejected' => 'admin-user-status admin-user-status-rejected',
        'inactive' => 'admin-user-status admin-user-status-inactive',
        default => 'admin-user-status admin-user-status-pending',
    };
}

function manageUserInitial($name)
{
    $name = trim((string) $name);
    return strtoupper(substr($name !== '' ? $name : 'U', 0, 1));
}

function manageUserRelativeTime($datetime)
{
    if (empty($datetime)) {
        return 'N/A';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return 'N/A';
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($diff / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function manageUserBindParams($stmt, $types, array $params)
{
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

$search = trim((string) ($_GET['search'] ?? ''));
$filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowed_filters = ['all', 'verified', 'pending', 'rejected', 'inactive'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$summary = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'rejected' => 0,
    'inactive' => 0,
];

$summary_result = mysqli_query($conn, "
    SELECT COALESCE(account_status, 'pending') AS account_status, COUNT(*) AS total
    FROM users
    WHERE role != 'super_admin'
    GROUP BY COALESCE(account_status, 'pending')
");
if ($summary_result) {
    while ($row = mysqli_fetch_assoc($summary_result)) {
        $status = (string) ($row['account_status'] ?? 'pending');
        $count = (int) ($row['total'] ?? 0);
        $summary['total'] += $count;

        if ($status === 'incomplete') {
            $summary['pending'] += $count;
        } elseif (array_key_exists($status, $summary)) {
            $summary[$status] += $count;
        }
    }
}

$where = ["u.role != 'super_admin'"];
$types = '';
$params = [];

if ($filter === 'pending') {
    $where[] = "COALESCE(u.account_status, 'pending') IN ('pending', 'incomplete')";
} elseif ($filter !== 'all') {
    $where[] = "COALESCE(u.account_status, 'pending') = ?";
    $types .= 's';
    $params[] = $filter;
}

if ($search !== '') {
    $where[] = "(LOWER(u.full_name) LIKE ? OR LOWER(u.email) LIKE ?)";
    $types .= 'ss';
    $like = '%' . strtolower($search) . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.role,
        u.account_status,
        u.valid_id_file,
        u.created_at,
        ps.shop_id,
        ps.shop_name,
        ps.business_permit_file,
        ps.permit_status,
        (
            SELECT COUNT(*)
            FROM orders o
            WHERE o.customer_id = u.user_id
        ) AS customer_order_count,
        (
            SELECT COUNT(*)
            FROM orders o
            JOIN print_shops ops ON ops.shop_id = o.shop_id
            WHERE ops.owner_id = u.user_id
        ) AS owner_order_count,
        (
            SELECT MAX(al.created_at)
            FROM activity_logs al
            WHERE al.user_id = u.user_id
        ) AS last_activity_at
    FROM users u
    LEFT JOIN print_shops ps ON ps.owner_id = u.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.created_at DESC, u.full_name ASC
";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && $types !== '') {
    manageUserBindParams($stmt, $types, $params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = false;
}

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

$filters = [
    'all' => ['label' => 'All', 'count' => $summary['total'], 'icon' => 'users'],
    'verified' => ['label' => 'Active', 'count' => $summary['verified'], 'icon' => 'check'],
    'pending' => ['label' => 'Pending', 'count' => $summary['pending'], 'icon' => 'clock'],
    'rejected' => ['label' => 'Rejected', 'count' => $summary['rejected'], 'icon' => 'x'],
    'inactive' => ['label' => 'Inactive', 'count' => $summary['inactive'], 'icon' => 'shield'],
];

adminLayoutStart('users', 'User Management', 'Review, approve, and manage customer and shop owner accounts.');
?>
<section class="admin-user-manager">
    <form class="admin-user-toolbar" method="GET" action="manage_users.php" data-live-search-form data-live-target="admin_users" data-live-min="1">
        <label class="admin-user-search" aria-label="Search users">
            <?php echo adminIcon('search'); ?>
            <input type="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by name or email...">
        </label>
        <input type="hidden" name="status" value="<?php echo e($filter); ?>">
        <button class="admin-user-search-button" type="submit">Search</button>
    </form>

    <nav class="admin-user-filters" aria-label="User status filters" data-live-region="admin-user-filters">
        <?php foreach ($filters as $key => $item): ?>
            <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($key !== 'all') $query['status'] = $key;
                $href = 'manage_users.php' . (!empty($query) ? '?' . http_build_query($query) : '');
            ?>
            <a class="<?php echo $filter === $key ? 'is-active' : ''; ?>" href="<?php echo e($href); ?>" <?php echo $filter === $key ? 'aria-current="page"' : ''; ?>>
                <?php echo adminIcon($item['icon']); ?>
                <span><?php echo e($item['label']); ?></span>
                <strong><?php echo (int) $item['count']; ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="admin-user-stats" aria-label="User management summary" data-live-region="admin-user-stats">
        <article class="admin-user-stat admin-user-stat-total">
            <span><?php echo adminIcon('users'); ?></span>
            <strong><?php echo (int) $summary['total']; ?></strong>
            <p>Total Users</p>
        </article>
        <article class="admin-user-stat admin-user-stat-active">
            <span><?php echo adminIcon('check'); ?></span>
            <strong><?php echo (int) $summary['verified']; ?></strong>
            <p>Active Users</p>
        </article>
        <article class="admin-user-stat admin-user-stat-pending">
            <span><?php echo adminIcon('clock'); ?></span>
            <strong><?php echo (int) $summary['pending']; ?></strong>
            <p>Pending Users</p>
        </article>
        <article class="admin-user-stat admin-user-stat-rejected">
            <span><?php echo adminIcon('x'); ?></span>
            <strong><?php echo (int) $summary['rejected']; ?></strong>
            <p>Rejected Users</p>
        </article>
        <article class="admin-user-stat admin-user-stat-inactive">
            <span><?php echo adminIcon('shield'); ?></span>
            <strong><?php echo (int) $summary['inactive']; ?></strong>
            <p>Inactive Users</p>
        </article>
    </section>

    <section class="admin-user-table-card" data-live-region="admin-user-results">
        <div class="admin-user-table-wrap">
            <table class="admin-user-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Select all users" data-admin-user-select-all></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th>Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7"><div class="admin-empty compact">No users match this view.</div></td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user): ?>
                        <?php
                            $status = (string) ($user['account_status'] ?? 'pending');
                            $status = $status !== '' ? $status : 'pending';
                            $role_label = ucfirst(str_replace('_', ' ', (string) $user['role']));
                            $orders_count = $user['role'] === 'shop_owner'
                                ? (int) ($user['owner_order_count'] ?? 0)
                                : (int) ($user['customer_order_count'] ?? 0);
                            $last_activity_source = $user['last_activity_at'] ?: ($user['created_at'] ?? '');
                            $last_activity = manageUserRelativeTime($last_activity_source);
                            $created_at = !empty($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : 'N/A';
                            $document_url = '';
                            $document_type = '';
                            if ($user['role'] === 'customer' && !empty($user['valid_id_file'])) {
                                $document_url = BASE_URL . $user['valid_id_file'];
                                $document_ext = strtolower(pathinfo($user['valid_id_file'], PATHINFO_EXTENSION));
                                $document_type = in_array($document_ext, ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true) ? 'image'
                                    : ($document_ext === 'pdf' ? 'pdf' : '');
                            } elseif ($user['role'] === 'shop_owner' && !empty($user['business_permit_file'])) {
                                $document_url = PERMITS_URL . $user['business_permit_file'];
                                $document_ext = strtolower(pathinfo($user['business_permit_file'], PATHINFO_EXTENSION));
                                $document_type = in_array($document_ext, ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true) ? 'image'
                                    : ($document_ext === 'pdf' ? 'pdf' : '');
                            }
                        ?>
                        <tr>
                            <td><input type="checkbox" aria-label="Select <?php echo e($user['full_name']); ?>"></td>
                            <td>
                                <div class="admin-user-name">
                                    <span><?php echo e(manageUserInitial($user['full_name'])); ?></span>
                                    <div>
                                        <strong><?php echo e($user['full_name']); ?></strong>
                                        <small><?php echo e($role_label); ?><?php echo !empty($user['shop_name']) ? ' - ' . e($user['shop_name']) : ''; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo e($user['email']); ?></td>
                            <td><span class="<?php echo e(manageUserStatusClass($status)); ?>"><?php echo e(manageUserStatusLabel($status)); ?></span></td>
                            <td><?php echo e($last_activity); ?></td>
                            <td><strong><?php echo (int) $orders_count; ?></strong></td>
                            <td>
                                <div class="admin-user-actions">
                                    <button
                                        type="button"
                                        class="admin-user-action admin-user-action-view"
                                        data-user-view
                                        data-name="<?php echo e($user['full_name']); ?>"
                                        data-email="<?php echo e($user['email']); ?>"
                                        data-role="<?php echo e($role_label); ?>"
                                        data-status="<?php echo e(manageUserStatusLabel($status)); ?>"
                                        data-orders="<?php echo (int) $orders_count; ?>"
                                        data-last-activity="<?php echo e($last_activity); ?>"
                                        data-created="<?php echo e($created_at); ?>"
                                        data-document-url="<?php echo e($document_url); ?>"
                                        data-document-type="<?php echo e($document_type); ?>"
                                    >
                                        <?php echo adminIcon('search'); ?>View
                                    </button>

                                    <?php if ($status === 'verified'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php" data-confirm-action="Deactivate this user? They will no longer be able to access their account.">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <input type="hidden" name="account_status" value="inactive">
                                            <button class="admin-user-action admin-user-action-disable" type="submit" name="update_user_status"><?php echo adminIcon('shield'); ?>Deactivate</button>
                                        </form>
                                    <?php elseif ($status === 'inactive'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <input type="hidden" name="account_status" value="verified">
                                            <button class="admin-user-action admin-user-action-activate" type="submit" name="update_user_status"><?php echo adminIcon('check'); ?>Activate</button>
                                        </form>
                                    <?php elseif ($status === 'pending' || $status === 'incomplete'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <input type="hidden" name="account_status" value="verified">
                                            <button class="admin-user-action admin-user-action-activate" type="submit" name="update_user_status"><?php echo adminIcon('check'); ?>Activate</button>
                                        </form>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php" data-confirm-action="Reject this user account?">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <input type="hidden" name="account_status" value="rejected">
                                            <button class="admin-user-action admin-user-action-reject" type="submit" name="update_user_status"><?php echo adminIcon('x'); ?>Reject</button>
                                        </form>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php" data-confirm-action="Activate this rejected user?">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <input type="hidden" name="account_status" value="verified">
                                            <button class="admin-user-action admin-user-action-activate" type="submit" name="update_user_status"><?php echo adminIcon('check'); ?>Activate</button>
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

<div class="admin-user-modal" id="adminUserModal" aria-hidden="true">
    <div class="admin-user-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminUserModalTitle">
        <button class="admin-user-modal__close" type="button" data-user-modal-close aria-label="Close user details">&times;</button>
        <span class="admin-user-modal__eyebrow">Account Details</span>
        <h2 id="adminUserModalTitle">User Details</h2>
        <dl>
            <div><dt>Email</dt><dd data-user-modal-email></dd></div>
            <div><dt>Role</dt><dd data-user-modal-role></dd></div>
            <div><dt>Status</dt><dd data-user-modal-status></dd></div>
            <div><dt>Orders</dt><dd data-user-modal-orders></dd></div>
            <div><dt>Last Activity</dt><dd data-user-modal-last-activity></dd></div>
            <div><dt>Date Registered</dt><dd data-user-modal-created></dd></div>
        </dl>
        <div class="admin-user-modal__preview" data-user-modal-preview hidden>
            <img data-user-modal-preview-img hidden alt="Document preview">
            <iframe data-user-modal-preview-pdf hidden title="Document PDF preview"></iframe>
            <p data-user-modal-preview-fallback hidden class="admin-user-modal__preview-fallback">Preview is not available for this file type.</p>
        </div>
    </div>
</div>

<script>
    (function () {
        const selectAll = document.querySelector('[data-admin-user-select-all]');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.admin-user-table tbody input[type="checkbox"]').forEach(function (box) {
                    box.checked = selectAll.checked;
                });
            });
        }

        document.querySelectorAll('[data-confirm-action]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirmAction)) {
                    event.preventDefault();
                }
            });
        });

        const modal = document.getElementById('adminUserModal');
        if (!modal) return;

        const fields = {
            title: modal.querySelector('#adminUserModalTitle'),
            email: modal.querySelector('[data-user-modal-email]'),
            role: modal.querySelector('[data-user-modal-role]'),
            status: modal.querySelector('[data-user-modal-status]'),
            orders: modal.querySelector('[data-user-modal-orders]'),
            lastActivity: modal.querySelector('[data-user-modal-last-activity]'),
            created: modal.querySelector('[data-user-modal-created]'),
            preview: modal.querySelector('[data-user-modal-preview]'),
            previewImg: modal.querySelector('[data-user-modal-preview-img]'),
            previewPdf: modal.querySelector('[data-user-modal-preview-pdf]'),
            previewFallback: modal.querySelector('[data-user-modal-preview-fallback]')
        };

        function resetPreview() {
            fields.preview.hidden = true;
            fields.previewImg.hidden = true;
            fields.previewImg.src = '';
            fields.previewPdf.hidden = true;
            fields.previewPdf.src = '';
            fields.previewFallback.hidden = true;
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            resetPreview();
        }

        document.querySelectorAll('[data-user-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                fields.title.textContent = button.dataset.name || 'User Details';
                fields.email.textContent = button.dataset.email || 'N/A';
                fields.role.textContent = button.dataset.role || 'N/A';
                fields.status.textContent = button.dataset.status || 'Pending';
                fields.orders.textContent = button.dataset.orders || '0';
                fields.lastActivity.textContent = button.dataset.lastActivity || 'N/A';
                fields.created.textContent = button.dataset.created || 'N/A';

                resetPreview();

                if (button.dataset.documentUrl) {
                    var docUrl = button.dataset.documentUrl;
                    var docType = button.dataset.documentType || '';

                    if (docType === 'image') {
                        fields.preview.hidden = false;
                        fields.previewImg.hidden = false;
                        fields.previewImg.src = docUrl;
                    } else if (docType === 'pdf') {
                        fields.preview.hidden = false;
                        fields.previewPdf.hidden = false;
                        fields.previewPdf.src = docUrl + '#toolbar=0';
                    } else if (docUrl) {
                        fields.preview.hidden = false;
                        fields.previewFallback.hidden = false;
                    }
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.matches('[data-user-modal-close]')) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeModal();
        });
    })();
</script>

<?php adminLayoutEnd(); ?>
