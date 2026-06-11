<?php
function ownerStatusLabel($status)
{
    return ucwords(str_replace('_', ' ', $status ?? 'pending'));
}

function ownerStatusClass($status)
{
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

function ownerMoney($amount)
{
    return '&#8369;' . number_format((float) $amount, 2);
}

function ownerIcon($name, $class = 'icon')
{
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '" aria-hidden="true"></i>';
}

function ownerNotificationUrl($notification, $owner_id)
{
    if (!$owner_id || !isset($GLOBALS['conn'])) {
        return '';
    }

    $message = $notification['message'] ?? '';

    if (preg_match('/\bPE-\d{8}-[A-Z0-9]+\b/i', $message, $matches)) {
        $order_code = strtoupper($matches[0]);
        $sql = "SELECT o.order_id
                FROM orders o
                JOIN print_shops ps ON o.shop_id = ps.shop_id
                WHERE ps.owner_id = ? AND o.order_code = ?
                LIMIT 1";
        $stmt = mysqli_prepare($GLOBALS['conn'], $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $owner_id, $order_code);
            mysqli_stmt_execute($stmt);
            $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($order) {
                return 'orders.php?focus_order_id=' . (int) $order['order_id'];
            }
        }
    }

    if (preg_match('/\bOrder\s*#(\d+)\b/i', $message, $matches)) {
        $order_id = (int) $matches[1];
        $sql = "SELECT o.order_id
                FROM orders o
                JOIN print_shops ps ON o.shop_id = ps.shop_id
                WHERE ps.owner_id = ? AND o.order_id = ?
                LIMIT 1";
        $stmt = mysqli_prepare($GLOBALS['conn'], $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $owner_id, $order_id);
            mysqli_stmt_execute($stmt);
            $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($order) {
                return 'orders.php?focus_order_id=' . (int) $order['order_id'];
            }
        }
    }

    return '';
}

function ownerLayoutStart($active, $title, $subtitle = '', $notif_count = 0, $shop = null, $floating_toast = null)
{
    $shop_name = $shop['shop_name'] ?? 'Print Shop';
    $shop_address = $shop['shop_address'] ?? 'Set up your shop profile';
    $shop_logo = $shop['shop_logo'] ?? '';
    $shop_logo_url = $shop_logo !== '' ? SHOP_LOGOS_URL . e($shop_logo) : '';
    $owner_css_version = filemtime(__DIR__ . "/../assets/owner.css");
    $owner_id = $_SESSION['user_id'] ?? 0;
    $recent_notifications = [];
    if ($owner_id && isset($GLOBALS['conn'])) {
        $notification_sql = "SELECT notification_id, message, created_at, is_read
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
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script>
            try {
                if (sessionStorage.getItem('ownerSidebarHold') === '1') {
                    document.documentElement.classList.add('owner-sidebar-hovered');
                }
            } catch (error) { }
        </script>
        <link rel="stylesheet"
            href="<?php echo BASE_URL; ?>frontend/user/shop_owner/assets/owner.css?v=<?php echo $owner_css_version; ?>">
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
                    <small title="<?php echo e($shop_address); ?>"><?php echo e($shop_address); ?></small>
                </div>
            </div>

            <nav class="owner-nav" aria-label="Shop owner navigation">
                <?php foreach ($nav as $item): ?>
                    <a class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>"
                        title="<?php echo e($item['label']); ?>" aria-label="<?php echo e($item['label']); ?>">
                        <?php echo ownerIcon($item['icon'], 'icon nav-icon'); ?>
                        <span class="owner-nav-label"><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <a class="owner-logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php" title="Logout"
                aria-label="Logout">
                <?php echo ownerIcon('log-out', 'icon nav-icon'); ?>
                <span class="owner-nav-label">Logout</span>
            </a>
        </aside>

        <div class="owner-shell">
            <header class="owner-topbar">
                <div class="topbar-title">
                    <strong>Admin Panel</strong>
                </div>
                <div class="topbar-actions">
                    <div class="notification-popover-wrap">
                        <button type="button" class="notification-link" id="ownerNotificationToggle"
                            aria-label="Notifications" aria-expanded="false" aria-controls="ownerNotificationPopover">
                            <?php echo ownerIcon('bell', 'icon'); ?>
                            <?php if ($notif_count > 0): ?>
                                <span class="notification-badge" id="ownerNotificationBadge"><?php echo (int) $notif_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <section class="notification-popover" id="ownerNotificationPopover" aria-hidden="true">
                            <header class="notification-popover-head">
                                <div>
                                    <strong>Notifications</strong>
                                    <span id="ownerNotificationUnreadText"><?php echo (int) $notif_count; ?> unread</span>
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
                                        <?php $notification_href = ownerNotificationUrl($notification, $owner_id); ?>
                                        <?php if ($notification_href !== ''): ?>
                                            <a class="notification-popover-item notification-popover-link"
                                                href="<?php echo e($notification_href); ?>"
                                                data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                                                data-is-read="<?php echo (int) $notification['is_read']; ?>">
                                            <?php else: ?>
                                                <article class="notification-popover-item"
                                                    data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                                                    data-is-read="<?php echo (int) $notification['is_read']; ?>">
                                                <?php endif; ?>
                                                <span
                                                    class="notification-popover-icon"><?php echo ownerIcon($notification['is_read'] == 0 ? 'bell-ring' : 'info', 'icon-sm'); ?></span>
                                                <div>
                                                    <p><?php echo e($notification['message']); ?></p>
                                                    <time><?php echo e(date("M d, Y - g:i A", strtotime($notification['created_at']))); ?></time>
                                                </div>
                                                <?php if ($notification_href !== ''): ?>
                                            </a>
                                        <?php else: ?>
                                            </article>
                                        <?php endif; ?>
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
                            <img src="<?php echo $shop_logo_url; ?>" class="owner-user-logo"
                                alt="<?php echo e($shop_name); ?> logo">
                        <?php else: ?>
                            <b><?php echo e($initials); ?></b>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if (!empty($floating_toast['message'])): ?>
                <div id="ownerFloatingToast"
                    class="owner-floating-toast <?php echo e($floating_toast['status'] ?? 'pending'); ?>"
                    role="status" aria-live="polite">
                    <?php echo ownerIcon(($floating_toast['status'] ?? 'pending') === 'rejected' ? 'triangle-alert' : 'clock', 'icon'); ?>
                    <span><?php echo e($floating_toast['message']); ?></span>
                    <button type="button" aria-label="Dismiss notification" data-owner-toast-close>
                        <?php echo ownerIcon('x', 'icon-sm'); ?>
                    </button>
                </div>
            <?php endif; ?>

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

function ownerLayoutEnd()
{
    ?>
            </main>
        </div>
        <script>
            if (window.lucide) {
                window.lucide.createIcons();
            }


            (function () {
                const sidebar = document.querySelector('.owner-sidebar');
                const expandedClass = 'owner-sidebar-hovered';
                const storageKey = 'ownerSidebarHold';

                if (!sidebar) return;

                function setStoredHoverState(isHovered) {
                    try {
                        if (isHovered) {
                            sessionStorage.setItem(storageKey, '1');
                        } else {
                            sessionStorage.removeItem(storageKey);
                        }
                    } catch (error) { }
                }

                function openSidebar() {
                    document.documentElement.classList.add(expandedClass);
                    setStoredHoverState(true);
                }

                function closeSidebar() {
                    document.documentElement.classList.remove(expandedClass);
                    setStoredHoverState(false);
                }

                try {
                    if (sessionStorage.getItem(storageKey) === '1') {
                        document.documentElement.classList.add(expandedClass);
                    }
                } catch (error) { }

                sidebar.addEventListener('pointerenter', openSidebar);
                sidebar.addEventListener('pointerleave', closeSidebar);

                sidebar.addEventListener('click', function (event) {
                    if (event.target.closest('a')) {
                        openSidebar();
                    }
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

            (function () {
                const toast = document.getElementById('ownerFloatingToast');
                if (!toast) {
                    return;
                }

                requestAnimationFrame(function () {
                    toast.classList.add('is-visible');
                });

                const closeButton = toast.querySelector('[data-owner-toast-close]');
                function closeToast() {
                    toast.classList.remove('is-visible');
                    window.setTimeout(function () {
                        toast.remove();
                    }, 250);
                }

                if (closeButton) {
                    closeButton.addEventListener('click', closeToast);
                }

                window.setTimeout(closeToast, 5000);
            })();

            (function () {
                const markReadUrl = '<?php echo BASE_URL; ?>backend/actions/mark_notification_read.php';
                const badge = document.getElementById('ownerNotificationBadge');
                const unreadText = document.getElementById('ownerNotificationUnreadText');
                const pageUnreadBadge = document.getElementById('unread-badge');

                function setUnreadCount(count) {
                    const nextCount = Math.max(0, count);
                    if (badge) {
                        badge.textContent = nextCount;
                        badge.hidden = nextCount === 0;
                    }
                    if (unreadText) {
                        unreadText.textContent = nextCount + ' unread';
                    }
                    if (pageUnreadBadge) {
                        pageUnreadBadge.textContent = nextCount + ' unread';
                    }
                }

                function markItemVisualRead(item) {
                    const selector = '[data-notification-id="' + item.dataset.notificationId + '"]';
                    document.querySelectorAll(selector).forEach(function (matchingItem) {
                        matchingItem.dataset.isRead = '1';

                        const newBadge = matchingItem.querySelector('.status-badge');
                        if (newBadge && newBadge.textContent.trim().toLowerCase() === 'new') {
                            newBadge.remove();
                        }

                        const icon = matchingItem.querySelector('[data-lucide="bell-ring"]');
                        if (icon) {
                            icon.setAttribute('data-lucide', 'info');
                        }
                    });

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }

                function markNotificationRead(item, callback) {
                    const notificationId = item.dataset.notificationId;
                    if (!notificationId) {
                        callback(false, null);
                        return;
                    }

                    fetch(markReadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'notification_id=' + encodeURIComponent(notificationId)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.success) {
                                setUnreadCount(data.unread_count || 0);
                                if (data.updated || item.dataset.isRead === '0') {
                                    markItemVisualRead(item);
                                }
                                callback(true, data);
                                return;
                            }

                            callback(false, data || null);
                        })
                        .catch(() => callback(false, null));
                }

                document.querySelectorAll('[data-notification-id]').forEach(function (item) {
                    item.addEventListener('click', function (event) {
                        const href = item.getAttribute('href');
                        const isUnread = item.dataset.isRead === '0';

                        if (!isUnread) {
                            return;
                        }

                        if (href) {
                            event.preventDefault();
                        }

                        markNotificationRead(item, function () {
                            if (href) {
                                window.location.href = href;
                            }
                        });
                    });
                });
            })();
        </script>
    </body>

    </html>
    <?php
}
?>
