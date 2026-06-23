<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/admin_layout.php";

function activityBindParams($stmt, $types, array $params)
{
    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function activityRows($conn, $sql, $types = '', array $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    if ($types !== '') {
        activityBindParams($stmt, $types, $params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function activityScalar($conn, $sql, $types = '', array $params = [], $field = 'total')
{
    $rows = activityRows($conn, $sql, $types, $params);
    return $rows[0][$field] ?? 0;
}

function activityStatus($action, $module)
{
    $text = strtolower((string) $action . ' ' . (string) $module);
    if (preg_match('/rejected|disabled|cancelled|failed|deleted|error/', $text)) {
        return 'critical';
    }
    if (preg_match('/pending|reminder|warning|needs action|unpaid/', $text)) {
        return 'warning';
    }
    if (preg_match('/approved|verified|completed|paid|saved|updated|created|submitted/', $text)) {
        return 'success';
    }
    return 'info';
}

function activityIconName($module, $action)
{
    $text = strtolower((string) $module . ' ' . (string) $action);
    if (str_contains($text, 'user') || str_contains($text, 'account')) return 'users';
    if (str_contains($text, 'permit') || str_contains($text, 'shop')) return 'shops';
    if (str_contains($text, 'order')) return 'file';
    if (str_contains($text, 'payment')) return 'clock';
    if (str_contains($text, 'profile')) return 'shield';
    return 'activity';
}

function activityStatusLabel($status)
{
    return match ($status) {
        'success' => 'Success',
        'warning' => 'Warning',
        'critical' => 'Critical',
        default => 'Info',
    };
}

function activityDateLabel($datetime)
{
    $timestamp = strtotime((string) $datetime);
    if (!$timestamp) return 'Unknown time';

    $date = date('Y-m-d', $timestamp);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) return date('g:i A', $timestamp);
    if ($date === $yesterday) return 'Yesterday, ' . date('g:i A', $timestamp);
    return date('M j, g:i A', $timestamp);
}

$range = strtolower(trim((string) ($_GET['range'] ?? 'today')));
$allowed_ranges = ['today', 'yesterday', 'week'];
if (!in_array($range, $allowed_ranges, true)) {
    $range = 'today';
}

$today = new DateTimeImmutable('today');
if ($range === 'yesterday') {
    $start = $today->modify('-1 day');
    $end = $today;
    $range_label = 'Yesterday';
} elseif ($range === 'week') {
    $start = $today->modify('monday this week');
    $end = $today->modify('+1 day');
    $range_label = 'This Week';
} else {
    $start = $today;
    $end = $today->modify('+1 day');
    $range_label = 'Today';
}

$start_date = $start->format('Y-m-d');
$end_date = $end->format('Y-m-d');
$search = trim((string) ($_GET['search'] ?? ''));
$module_filter = trim((string) ($_GET['module'] ?? 'all'));

$modules = activityRows(
    $conn,
    "SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL AND module <> '' ORDER BY module ASC"
);
$module_values = array_map(fn($row) => (string) $row['module'], $modules);
if ($module_filter !== 'all' && !in_array($module_filter, $module_values, true)) {
    $module_filter = 'all';
}

$where = ["al.created_at >= ?", "al.created_at < ?"];
$types = 'ss';
$params = [$start_date, $end_date];

if ($module_filter !== 'all') {
    $where[] = "al.module = ?";
    $types .= 's';
    $params[] = $module_filter;
}

if ($search !== '') {
    $where[] = "(LOWER(u.full_name) LIKE ? OR LOWER(u.role) LIKE ? OR LOWER(al.module) LIKE ? OR LOWER(al.action) LIKE ?)";
    $types .= 'ssss';
    $like = '%' . strtolower($search) . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = implode(' AND ', $where);
$logs = activityRows(
    $conn,
    "SELECT al.*, u.full_name, u.email, u.role
     FROM activity_logs al
     JOIN users u ON al.user_id = u.user_id
     WHERE $where_sql
     ORDER BY al.created_at DESC
     LIMIT 50",
    $types,
    $params
);

$summary_rows = activityRows(
    $conn,
    "SELECT al.action, al.module
     FROM activity_logs al
     JOIN users u ON al.user_id = u.user_id
     WHERE $where_sql",
    $types,
    $params
);

$summary = [
    'total' => count($summary_rows),
    'important' => 0,
    'users' => 0,
    'payments_orders' => 0,
];

foreach ($summary_rows as $row) {
    $status = activityStatus($row['action'] ?? '', $row['module'] ?? '');
    $module_text = strtolower((string) ($row['module'] ?? '') . ' ' . (string) ($row['action'] ?? ''));
    if (in_array($status, ['critical', 'warning'], true)) {
        $summary['important']++;
    }
    if (str_contains($module_text, 'user') || str_contains($module_text, 'account')) {
        $summary['users']++;
    }
    if (str_contains($module_text, 'payment') || str_contains($module_text, 'order')) {
        $summary['payments_orders']++;
    }
}

$ranges = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'week' => 'This Week',
];

adminLayoutStart('activity', 'System Activity Logs', 'Track system actions and monitor platform activity.');
?>
<section class="admin-activity-page">
    <form class="admin-activity-toolbar" method="GET" action="activity_logs.php" data-live-search-form data-live-target="admin_activity" data-live-min="1">
        <label class="admin-activity-search">
            <?php echo adminIcon('search'); ?>
            <input type="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by user, role, module, or action...">
        </label>
        <select name="range" onchange="this.form.submit()" aria-label="Date range">
            <?php foreach ($ranges as $key => $label): ?>
                <option value="<?php echo e($key); ?>" <?php echo $range === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="module" onchange="this.form.submit()" aria-label="Module filter">
            <option value="all">All Logs</option>
            <?php foreach ($module_values as $module): ?>
                <option value="<?php echo e($module); ?>" <?php echo $module_filter === $module ? 'selected' : ''; ?>><?php echo e($module); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
    </form>

    <nav class="admin-activity-ranges" aria-label="Activity date filters">
        <?php foreach ($ranges as $key => $label): ?>
            <?php
                $query = ['range' => $key];
                if ($search !== '') $query['search'] = $search;
                if ($module_filter !== 'all') $query['module'] = $module_filter;
            ?>
            <a class="<?php echo $range === $key ? 'is-active' : ''; ?>" href="activity_logs.php?<?php echo e(http_build_query($query)); ?>" <?php echo $range === $key ? 'aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="admin-activity-stats" aria-label="Activity summary" data-live-region="admin-activity-stats">
        <article class="total"><span><?php echo adminIcon('activity'); ?></span><strong><?php echo (int) $summary['total']; ?></strong><p>Total Logs <?php echo e($range_label); ?></p></article>
        <article class="critical"><span><?php echo adminIcon('x'); ?></span><strong><?php echo (int) $summary['important']; ?></strong><p>Important Events</p></article>
        <article class="users"><span><?php echo adminIcon('users'); ?></span><strong><?php echo (int) $summary['users']; ?></strong><p>User / Account Events</p></article>
        <article class="payments"><span><?php echo adminIcon('file'); ?></span><strong><?php echo (int) $summary['payments_orders']; ?></strong><p>Payments / Orders</p></article>
    </section>

    <section class="admin-activity-list-card" data-live-region="admin-activity-results">
        <header><h2><?php echo e($range_label); ?></h2><span>Latest <?php echo min(50, count($logs)); ?> shown</span></header>
        <div class="admin-activity-list">
            <?php if (empty($logs)): ?>
                <div class="admin-empty compact">No activity logs match this filter.</div>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <?php
                    $status = activityStatus($log['action'] ?? '', $log['module'] ?? '');
                    $icon = activityIconName($log['module'] ?? '', $log['action'] ?? '');
                    $role_label = ucfirst(str_replace('_', ' ', (string) ($log['role'] ?? 'User')));
                    $timestamp = !empty($log['created_at']) ? date('Y-m-d H:i:s', strtotime($log['created_at'])) : 'N/A';
                ?>
                <button
                    type="button"
                    class="admin-activity-item admin-activity-item-<?php echo e($status); ?>"
                    data-activity-view
                    data-actor="<?php echo e($log['full_name']); ?>"
                    data-email="<?php echo e($log['email'] ?? ''); ?>"
                    data-role="<?php echo e($role_label); ?>"
                    data-module="<?php echo e($log['module']); ?>"
                    data-action="<?php echo e($log['action']); ?>"
                    data-status="<?php echo e(activityStatusLabel($status)); ?>"
                    data-timestamp="<?php echo e($timestamp); ?>"
                >
                    <span class="admin-activity-item-icon"><?php echo adminIcon($icon); ?></span>
                    <span class="admin-activity-copy">
                        <strong><?php echo e($log['action']); ?></strong>
                        <small><?php echo e($log['module']); ?></small>
                        <em><?php echo e($role_label); ?> • <?php echo e($log['full_name']); ?> • <?php echo e(activityDateLabel($log['created_at'])); ?></em>
                    </span>
                    <span class="admin-activity-badge"><?php echo e(activityStatusLabel($status)); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<div class="admin-activity-modal" id="adminActivityModal" aria-hidden="true">
    <div class="admin-activity-modal__panel" role="dialog" aria-modal="true" aria-labelledby="adminActivityModalTitle">
        <button type="button" class="admin-activity-modal__close" data-activity-modal-close aria-label="Close activity details">&times;</button>
        <div class="admin-activity-modal__head">
            <h2 id="adminActivityModalTitle">Activity Log Details</h2>
        </div>
        <div class="admin-activity-modal__status">
            <span data-activity-modal-status></span>
        </div>
        <section>
            <h3>Actor Information</h3>
            <p><strong>Name:</strong> <span data-activity-modal-actor></span></p>
            <p><strong>Email:</strong> <span data-activity-modal-email></span></p>
            <p><strong>Role:</strong> <span data-activity-modal-role></span></p>
        </section>
        <section>
            <h3>Timestamp</h3>
            <p data-activity-modal-timestamp></p>
        </section>
        <section>
            <h3>Log Information</h3>
            <p><strong>Module:</strong> <span data-activity-modal-module></span></p>
            <p><strong>Action:</strong> <span data-activity-modal-action></span></p>
        </section>
        <button type="button" class="admin-activity-modal__button" data-activity-modal-close>Close</button>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('adminActivityModal');
        if (!modal) return;

        const fields = {
            title: modal.querySelector('#adminActivityModalTitle'),
            status: modal.querySelector('[data-activity-modal-status]'),
            actor: modal.querySelector('[data-activity-modal-actor]'),
            email: modal.querySelector('[data-activity-modal-email]'),
            role: modal.querySelector('[data-activity-modal-role]'),
            timestamp: modal.querySelector('[data-activity-modal-timestamp]'),
            module: modal.querySelector('[data-activity-modal-module]'),
            action: modal.querySelector('[data-activity-modal-action]')
        };

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('[data-activity-view]').forEach(function (item) {
            item.addEventListener('click', function () {
                fields.title.textContent = item.dataset.module || 'Activity Log Details';
                fields.status.textContent = item.dataset.status || 'Info';
                fields.actor.textContent = item.dataset.actor || 'N/A';
                fields.email.textContent = item.dataset.email || 'N/A';
                fields.role.textContent = item.dataset.role || 'N/A';
                fields.timestamp.textContent = item.dataset.timestamp || 'N/A';
                fields.module.textContent = item.dataset.module || 'N/A';
                fields.action.textContent = item.dataset.action || 'N/A';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.matches('[data-activity-modal-close]')) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeModal();
        });
    })();
</script>

<?php adminLayoutEnd(); ?>
