<?php
require_once __DIR__ . '/../../../components/head.php';
require_once __DIR__ . '/../../../components/toasts.php';
require_once __DIR__ . '/../../../../backend/includes/functions.php';
require_once __DIR__ . '/../../../../backend/config/app.php';
require_once __DIR__ . '/../../../../backend/config/db.php';
require_once __DIR__ . '/../../../components/notifications.php';

function adminStatusClass($status)
{
    $status = strtolower((string) ($status ?? 'pending'));
    return match ($status) {
        'verified', 'completed', 'paid', 'active' => 'admin-status admin-status-success',
        'rejected', 'cancelled', 'failed' => 'admin-status admin-status-danger',
        'pending', 'incomplete' => 'admin-status admin-status-warning',
        'disabled' => 'admin-status admin-status-info',
        default => 'admin-status admin-status-info',
    };
}

function adminIcon($name, $class = 'admin-icon')
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.4"/><rect x="14" y="3" width="7" height="7" rx="1.4"/><rect x="3" y="14" width="7" height="7" rx="1.4"/><rect x="14" y="14" width="7" height="7" rx="1.4"/>',
        'shops' => '<path d="M3 9h18l-2-5H5L3 9Z"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'reports' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        'activity' => '<path d="M22 12h-4l-3 8L9 4l-3 8H2"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'settings' => '<path d="M12.2 2h-.4a2 2 0 0 0-2 1.8l-.1 1a7.8 7.8 0 0 0-1.4.8l-.9-.4a2 2 0 0 0-2.5.8l-.2.4a2 2 0 0 0 .4 2.6l.8.6a7.8 7.8 0 0 0 0 1.6l-.8.6a2 2 0 0 0-.4 2.6l.2.4a2 2 0 0 0 2.5.8l.9-.4a7.8 7.8 0 0 0 1.4.8l.1 1a2 2 0 0 0 2 1.8h.4a2 2 0 0 0 2-1.8l.1-1a7.8 7.8 0 0 0 1.4-.8l.9.4a2 2 0 0 0 2.5-.8l.2-.4a2 2 0 0 0-.4-2.6l-.8-.6a7.8 7.8 0 0 0 0-1.6l.8-.6a2 2 0 0 0 .4-2.6l-.2-.4a2 2 0 0 0-2.5-.8l-.9.4a7.8 7.8 0 0 0-1.4-.8l-.1-1a2 2 0 0 0-2-1.8Z"/><circle cx="12" cy="12" r="3"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'x' => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    ];
    $paths = $icons[$name] ?? $icons['dashboard'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function adminNavItems()
{
    return [
        ['key' => 'dashboard', 'href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['key' => 'shops', 'href' => 'manage_print_shops.php', 'label' => 'Manage Print Shop', 'icon' => 'shops'],
        ['key' => 'users', 'href' => 'manage_users.php', 'label' => 'User Management', 'icon' => 'users'],
        ['key' => 'reports', 'href' => 'reports.php', 'label' => 'Reports', 'icon' => 'reports'],
        ['key' => 'activity', 'href' => 'activity_logs.php', 'label' => 'Activity Logs', 'icon' => 'activity'],
    ];
}

function adminInitials($name)
{
    $initials = '';
    foreach (preg_split('/\s+/', trim((string) $name)) as $part) {
        if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'SA';
}

function adminLayoutStart($active, $title, $subtitle = '')
{
    $admin_name = (string) ($_SESSION['full_name'] ?? 'Super Admin');
    $admin_email = (string) ($_SESSION['email'] ?? 'admin@printease.local');
    $admin_id = (int) ($_SESSION['user_id'] ?? 0);
    $conn = $GLOBALS['conn'] ?? null;
    $unread_count = $conn instanceof mysqli && $admin_id > 0 ? getUnreadNotificationCount($conn, $admin_id) : 0;
    $recent_notifications = $conn instanceof mysqli && $admin_id > 0 ? getUserNotifications($conn, $admin_id, 5) : [];
    $css_path = __DIR__ . '/../assets/admin.css';
    $css_version = is_file($css_path) ? filemtime($css_path) : time();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo e($title); ?> | PrintEase</title>
        <?php renderPrintEaseIcons(); ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>frontend/user/superadmin/assets/admin.css?v=<?php echo $css_version; ?>">
    </head>
    <body class="admin-body">
        <?php renderAppToasts('admin'); ?>
        <aside class="admin-sidebar" aria-label="Super admin sidebar">
            <div class="admin-brand">
                <?php renderPrintEaseLogo(['class' => 'admin-brand-logo', 'alt' => 'PrintEase']); ?>
                <div class="admin-brand-copy">
                    <span>PrintEase</span>
                    <strong>Super Admin</strong>
                    <small>Platform command center</small>
                </div>
            </div>
            <nav class="admin-nav" aria-label="Super admin navigation">
                <?php foreach (adminNavItems() as $item): ?>
                    <a class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>" title="<?php echo e($item['label']); ?>" aria-label="<?php echo e($item['label']); ?>" <?php echo $active === $item['key'] ? 'aria-current="page"' : ''; ?>>
                        <?php echo adminIcon($item['icon']); ?>
                        <span class="admin-nav-label"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="admin-sidebar-actions">
                <a class="admin-settings <?php echo $active === 'settings' ? 'active' : ''; ?>" href="settings.php" title="Settings" aria-label="Settings" <?php echo $active === 'settings' ? 'aria-current="page"' : ''; ?>>
                    <?php echo adminIcon('settings'); ?>
                    <span class="admin-nav-label">Settings</span>
                </a>
                <a class="admin-logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php" title="Logout" aria-label="Logout">
                    <?php echo adminIcon('logout'); ?>
                    <span class="admin-nav-label">Logout</span>
                </a>
            </div>
        </aside>
        <div class="admin-shell">
            <header class="admin-topbar">
                <div class="admin-topbar-title"><strong>Admin Panel</strong></div>
                <div class="admin-topbar-actions">
                    <div class="admin-notification-wrap">
                        <button type="button" class="admin-notification-button" id="adminNotificationToggle" aria-label="Notifications" aria-expanded="false" aria-controls="adminNotificationPopover">
                            <?php echo adminIcon('bell'); ?>
                            <?php if ($unread_count > 0): ?><span><?php echo (int) $unread_count; ?></span><?php endif; ?>
                        </button>
                        <section class="admin-notification-popover" id="adminNotificationPopover" aria-hidden="true">
                            <header>
                                <div><strong>Notifications</strong><span><?php echo (int) $unread_count; ?> unread</span></div>
                                <a href="notifications.php">View all</a>
                            </header>
                            <?php if (empty($recent_notifications)): ?>
                                <div class="admin-empty compact">No notifications yet.</div>
                            <?php else: ?>
                                <div class="admin-notification-list">
                                    <?php foreach ($recent_notifications as $notification): ?>
                                        <?php $target = notificationSafeTarget($notification['target_url'] ?? '') ?: 'notifications.php'; ?>
                                        <a href="<?php echo e($target); ?>" data-notification-id="<?php echo (int) $notification['notification_id']; ?>" data-is-read="<?php echo (int) $notification['is_read']; ?>">
                                            <strong><?php echo e($notification['title'] ?? 'Notification'); ?></strong>
                                            <span><?php echo e($notification['message'] ?? ''); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                    <div class="admin-user">
                        <div><strong><?php echo e($admin_name); ?></strong><span><?php echo e($admin_email); ?></span></div>
                        <b><?php echo e(adminInitials($admin_name)); ?></b>
                    </div>
                </div>
            </header>
            <main class="admin-main">
                <section class="admin-page-heading">
                    <div>
                        
                        <h1><?php echo e($title); ?></h1>
                        <?php if ($subtitle !== ''): ?><p><?php echo e($subtitle); ?></p><?php endif; ?>
                    </div>
                </section>
    <?php
}

function adminLayoutEnd()
{
    ?>
            </main>
        </div>
        <script>
            (function () {
                const toggle = document.getElementById('adminNotificationToggle');
                const popover = document.getElementById('adminNotificationPopover');
                if (!toggle || !popover) return;
                function close() {
                    popover.classList.remove('is-open');
                    popover.setAttribute('aria-hidden', 'true');
                    toggle.setAttribute('aria-expanded', 'false');
                }
                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const open = popover.classList.toggle('is-open');
                    popover.setAttribute('aria-hidden', open ? 'false' : 'true');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                popover.addEventListener('click', function (event) { event.stopPropagation(); });
                document.addEventListener('click', close);
                document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
            })();
        </script>
    </body>
    </html>
    <?php
}
?>
