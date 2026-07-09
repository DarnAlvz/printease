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

function activityAuditValueLabel($value)
{
    $value = trim((string) $value);
    if ($value === '') return 'N/A';

    $decoded = json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return $value;
    }

    $parts = [];
    foreach ($decoded as $key => $item) {
        $label = ucwords(str_replace('_', ' ', (string) $key));
        if (is_bool($item)) {
            $display = $item ? 'Yes' : 'No';
        } elseif ($item === null || $item === '') {
            $display = 'N/A';
        } elseif (is_array($item)) {
            $display = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $display = (string) $item;
        }
        $parts[] = $label . ': ' . $display;
    }

    return implode('; ', $parts);
}

function activityBrowserSummary($user_agent)
{
    $user_agent = trim((string) $user_agent);
    if ($user_agent === '') return 'N/A';

    $browser = 'Browser';
    if (stripos($user_agent, 'Edg/') !== false || stripos($user_agent, 'Edge/') !== false) {
        $browser = 'Microsoft Edge';
    } elseif (stripos($user_agent, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($user_agent, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($user_agent, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    $device = 'Unknown device';
    if (stripos($user_agent, 'Windows') !== false) {
        $device = 'Windows';
    } elseif (stripos($user_agent, 'Android') !== false) {
        $device = 'Android';
    } elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) {
        $device = 'iOS';
    } elseif (stripos($user_agent, 'Mac OS') !== false || stripos($user_agent, 'Macintosh') !== false) {
        $device = 'macOS';
    } elseif (stripos($user_agent, 'Linux') !== false) {
        $device = 'Linux';
    }

    return $browser . ' on ' . $device;
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
                    $target_type = trim((string) ($log['target_type'] ?? ''));
                    $target_id = trim((string) ($log['target_id'] ?? ''));
                    $target_label = $target_type !== ''
                        ? ucwords(str_replace('_', ' ', $target_type)) . ($target_id !== '' ? ' #' . $target_id : '')
                        : 'N/A';
                    $old_value = activityAuditValueLabel($log['old_value'] ?? '');
                    $new_value = activityAuditValueLabel($log['new_value'] ?? '');
                    $ip_address = trim((string) ($log['ip_address'] ?? '')) ?: 'N/A';
                    $browser_summary = activityBrowserSummary($log['user_agent'] ?? '');
                    $user_agent = trim((string) ($log['user_agent'] ?? '')) ?: 'N/A';
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
                    data-target="<?php echo e($target_label); ?>"
                    data-old-value="<?php echo e($old_value); ?>"
                    data-new-value="<?php echo e($new_value); ?>"
                    data-ip-address="<?php echo e($ip_address); ?>"
                    data-browser="<?php echo e($browser_summary); ?>"
                    data-user-agent="<?php echo e($user_agent); ?>"
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
        <section>
            <h3>Audit Details</h3>
            <p><strong>Target:</strong> <span data-activity-modal-target></span></p>
            <p><strong>Before:</strong> <span data-activity-modal-old-value></span></p>
            <p><strong>After:</strong> <span data-activity-modal-new-value></span></p>
        </section>
        <section>
            <h3>Request Context</h3>
            <p><strong>IP Address:</strong> <span data-activity-modal-ip-address></span></p>
            <p><strong>Browser:</strong> <span data-activity-modal-browser></span></p>
            <p><strong>User Agent:</strong> <span data-activity-modal-user-agent></span></p>
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
            action: modal.querySelector('[data-activity-modal-action]'),
            target: modal.querySelector('[data-activity-modal-target]'),
            oldValue: modal.querySelector('[data-activity-modal-old-value]'),
            newValue: modal.querySelector('[data-activity-modal-new-value]'),
            ipAddress: modal.querySelector('[data-activity-modal-ip-address]'),
            browser: modal.querySelector('[data-activity-modal-browser]'),
            userAgent: modal.querySelector('[data-activity-modal-user-agent]')
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
                fields.target.textContent = item.dataset.target || 'N/A';
                fields.oldValue.textContent = item.dataset.oldValue || 'N/A';
                fields.newValue.textContent = item.dataset.newValue || 'N/A';
                fields.ipAddress.textContent = item.dataset.ipAddress || 'N/A';
                fields.browser.textContent = item.dataset.browser || 'N/A';
                fields.userAgent.textContent = item.dataset.userAgent || 'N/A';
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
