<?php
function ownerStatusLabel($status) {
    return ucwords(str_replace('_', ' ', $status ?? 'pending'));
}

function ownerStatusClass($status) {
    $status = strtolower($status ?? 'pending');

    if ($status === 'completed' || $status === 'verified' || $status === 'available' || $status === 'paid') {
        return 'status-success';
    }

    if ($status === 'processing' || $status === 'ready_for_pickup' || $status === 'busy') {
        return 'status-info';
    }

    if ($status === 'rejected' || $status === 'not_accepting') {
        return 'status-danger';
    }

    return 'status-warning';
}

function ownerMoney($amount) {
    return '&#8369;' . number_format((float) $amount, 2);
}

function ownerIcon($name, $class = 'icon') {
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '" aria-hidden="true"></i>';
}

function ownerLayoutStart($active, $title, $subtitle = '', $notif_count = 0, $shop = null) {
    $shop_name = $shop['shop_name'] ?? 'Print Shop';
    $shop_address = $shop['shop_address'] ?? 'Set up your shop profile';
    $shop_logo = $shop['shop_logo'] ?? '';
    $shop_logo_url = $shop_logo !== '' ? SHOP_LOGOS_URL . e($shop_logo) : '';
    $owner_css_version = filemtime(__DIR__ . "/../assets/owner.css");
    $owner_id = $_SESSION['user_id'] ?? 0;
    $recent_notifications = [];
    if ($owner_id && isset($GLOBALS['conn'])) {
        $notification_sql = "SELECT message, created_at, is_read
                             FROM notifications
                             WHERE user_id = ?
                             ORDER BY created_at DESC
                             LIMIT 5";
        $notification_stmt = mysqli_prepare($GLOBALS['conn'], $notification_sql);
        if ($notification_stmt) {
            mysqli_stmt_bind_param($notification_stmt, "i", $owner_id);
            mysqli_stmt_execute($notification_stmt);
            $notification_result = mysqli_stmt_get_result($notification_stmt);
            while ($notification = mysqli_fetch_assoc($notification_result)) {
                $recent_notifications[] = $notification;
            }
        }
    }
    $user_name = $_SESSION['full_name'] ?? 'Shop Owner';
    $initials = '';
    foreach (explode(' ', trim($user_name)) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    $initials = $initials ?: 'SO';

    $nav = [
        ['key' => 'dashboard', 'href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
        ['key' => 'orders', 'href' => 'orders.php', 'label' => 'Orders', 'icon' => 'shopping-cart'],
        ['key' => 'profile', 'href' => 'shop_profile.php', 'label' => 'Shop Management', 'icon' => 'store'],
        ['key' => 'services', 'href' => 'services.php', 'label' => 'Paper Pricing', 'icon' => 'file-text'],
        ['key' => 'transactions', 'href' => 'transactions.php', 'label' => 'Transactions', 'icon' => 'badge-dollar-sign'],
        ['key' => 'status', 'href' => 'update_status.php', 'label' => 'Shop Status', 'icon' => 'activity'],
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - Shop Owner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>frontend/user/shop_owner/assets/owner.css?v=<?php echo $owner_css_version; ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="owner-body">
    <aside class="owner-sidebar" aria-label="Shop owner sidebar">
        <div class="owner-brand">
            <?php if ($shop_logo_url !== ''): ?>
                <img src="<?php echo $shop_logo_url; ?>" class="owner-brand-logo" alt="<?php echo e($shop_name); ?> logo">
            <?php else: ?>
                <div class="owner-brand-mark">PE</div>
            <?php endif; ?>
            <div class="owner-brand-copy">
                <span>Print Shop</span>
                <strong><?php echo e($shop_name); ?></strong>
                <small><?php echo e($shop_address); ?></small>
            </div>
        </div>

        <nav class="owner-nav" aria-label="Shop owner navigation">
            <?php foreach ($nav as $item): ?>
                <a class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>" title="<?php echo e($item['label']); ?>" aria-label="<?php echo e($item['label']); ?>">
                    <?php echo ownerIcon($item['icon'], 'icon nav-icon'); ?>
                    <span class="owner-nav-label"><?php echo e($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <a class="owner-logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php" title="Logout" aria-label="Logout">
            <?php echo ownerIcon('log-out', 'icon nav-icon'); ?>
            <span class="owner-nav-label">Logout</span>
        </a>
    </aside>

    <div class="owner-shell">
        <header class="owner-topbar">
            <div class="topbar-title">
                <button type="button" class="sidebar-toggle" id="ownerSidebarToggle" aria-label="Toggle sidebar" aria-expanded="true">
                    <span class="menu-mark"></span>
                </button>
                <strong>Admin Panel</strong>
            </div>
            <div class="topbar-actions">
                <div class="notification-popover-wrap">
                    <button type="button" class="notification-link" id="ownerNotificationToggle" aria-label="Notifications" aria-expanded="false" aria-controls="ownerNotificationPopover">
                        <?php echo ownerIcon('bell', 'icon'); ?>
                        <?php if ($notif_count > 0): ?>
                            <span class="notification-badge"><?php echo (int) $notif_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <section class="notification-popover" id="ownerNotificationPopover" aria-hidden="true">
                        <header class="notification-popover-head">
                            <div>
                                <strong>Notifications</strong>
                                <span><?php echo (int) $notif_count; ?> unread</span>
                            </div>
                            <a href="notifications.php">View all</a>
                        </header>
                        <?php if (empty($recent_notifications)): ?>
                            <div class="notification-popover-empty">
                                <?php echo ownerIcon('bell', 'icon'); ?>
                                <p>No notifications yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="notification-popover-list">
                                <?php foreach ($recent_notifications as $notification): ?>
                                    <article class="notification-popover-item">
                                        <span class="notification-popover-icon"><?php echo ownerIcon($notification['is_read'] == 0 ? 'bell-ring' : 'info', 'icon-sm'); ?></span>
                                        <div>
                                            <p><?php echo e($notification['message']); ?></p>
                                            <time><?php echo e(date("M d, Y - g:i A", strtotime($notification['created_at']))); ?></time>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
                <div class="owner-user">
                    <div>
                        <strong><?php echo e($user_name); ?></strong>
                        <span>Shop Owner</span>
                    </div>
                    <?php if ($shop_logo_url !== ''): ?>
                        <img src="<?php echo $shop_logo_url; ?>" class="owner-user-logo" alt="<?php echo e($shop_name); ?> logo">
                    <?php else: ?>
                        <b><?php echo e($initials); ?></b>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="owner-main">
            <div class="page-heading">
                <div>
                    <h1><?php echo e($title); ?></h1>
                    <?php if ($subtitle !== ''): ?>
                        <p><?php echo e($subtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
<?php
}

function ownerLayoutEnd() {
?>
        </main>
    </div>
    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }

        (function () {
            const toggle = document.getElementById('ownerSidebarToggle');
            if (!toggle) {
                return;
            }

            toggle.addEventListener('click', function () {
                const collapsed = document.body.classList.toggle('owner-sidebar-collapsed');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        })();

        (function () {
            const toggle = document.getElementById('ownerNotificationToggle');
            const popover = document.getElementById('ownerNotificationPopover');
            if (!toggle || !popover) {
                return;
            }

            function closePopover() {
                popover.classList.remove('is-open');
                popover.setAttribute('aria-hidden', 'true');
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openPopover() {
                popover.classList.add('is-open');
                popover.setAttribute('aria-hidden', 'false');
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                if (popover.classList.contains('is-open')) {
                    closePopover();
                } else {
                    openPopover();
                }
            });

            popover.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            document.addEventListener('click', closePopover);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closePopover();
                }
            });
        })();
    </script>
</body>
</html>
<?php
}
?>
