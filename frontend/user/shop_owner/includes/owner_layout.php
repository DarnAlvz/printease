<?php
require_once __DIR__ . '/../../../components/head.php';
require_once __DIR__ . '/../../../components/toasts.php';

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
    $icons = [
        'badge-dollar-sign' => '<path d="M12 3h4a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-4"/><path d="M8 7h4.5a2.5 2.5 0 0 1 0 5H8h5a2.5 2.5 0 0 1 0 5H8"/><path d="M8 4v16"/>',
        'bell' => '<path d="M10 21h4"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/>',
        'bell-ring' => '<path d="M10 21h4"/><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M4 2 2 4"/><path d="m22 4-2-2"/>',
        'chart-no-axes-combined' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="m7 14 4-4 3 3 5-7"/><path d="M7 19v-5"/><path d="M11 19v-9"/><path d="M15 19v-6"/><path d="M19 19V6"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'circle-alert' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
        'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'edit-3' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h8"/><path d="M8 9h2"/>',
        'folder' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7l-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'key-round' => '<path d="M2 18a6 6 0 1 0 10.6-3.8L22 4.8 19.2 2 17 4.2 15.8 3 13.7 5.1 15 6.3 10.8 10.5A6 6 0 0 0 2 18Z"/><path d="M7 17h.01"/>',
        'layers' => '<path d="m12 2 10 5-10 5L2 7Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'lock' => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'package' => '<path d="m7.5 4.3 9 5.2"/><path d="M21 8v8a2 2 0 0 1-1 1.7l-7 4a2 2 0 0 1-2 0l-7-4A2 2 0 0 1 3 16V8a2 2 0 0 1 1-1.7l7-4a2 2 0 0 1 2 0l7 4A2 2 0 0 1 21 8Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'philippine-peso' => '<path d="M20 6H4"/><path d="M20 10H4"/><path d="M7 21V3h7a5 5 0 0 1 0 10H7"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6Z"/>',
        'refresh-cw' => '<path d="M21 12a9 9 0 0 1-15.4 6.4L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.4-6.4L21 8"/><path d="M21 3v5h-5"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'settings' => '<path d="M12.2 2h-.4a2 2 0 0 0-2 1.8l-.1 1a7.8 7.8 0 0 0-1.4.8l-.9-.4a2 2 0 0 0-2.5.8l-.2.4a2 2 0 0 0 .4 2.6l.8.6a7.8 7.8 0 0 0 0 1.6l-.8.6a2 2 0 0 0-.4 2.6l.2.4a2 2 0 0 0 2.5.8l.9-.4a7.8 7.8 0 0 0 1.4.8l.1 1a2 2 0 0 0 2 1.8h.4a2 2 0 0 0 2-1.8l.1-1a7.8 7.8 0 0 0 1.4-.8l.9.4a2 2 0 0 0 2.5-.8l.2-.4a2 2 0 0 0-.4-2.6l-.8-.6a7.8 7.8 0 0 0 0-1.6l.8-.6a2 2 0 0 0 .4-2.6l-.2-.4a2 2 0 0 0-2.5-.8l-.9.4a7.8 7.8 0 0 0-1.4-.8l-.1-1a2 2 0 0 0-2-1.8Z"/><circle cx="12" cy="12" r="3"/>',
        'shopping-cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 2h3l3.6 12.6a2 2 0 0 0 2 1.4H18a2 2 0 0 0 2-1.5L22 7H6"/>',
        'volume-2' => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/>',
        'store' => '<path d="M3 9h18l-2-5H5Z"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'trending-up' => '<path d="M3 17 9 11l4 4 8-8"/><path d="M14 7h7v7"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    ];

    $name = (string) $name;
    $paths = $icons[$name] ?? $icons['circle-alert'];

    return '<svg viewBox="0 0 24 24" class="' . e($class) . '" data-owner-icon="' . e($name) . '" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

function ownerShopLocation($shop)
{
    $shop = is_array($shop) ? $shop : [];
    $full_address = trim((string) ($shop['shop_address'] ?? ''));
    $primary = trim((string) ($shop['display_address'] ?? ''));

    if ($primary === '' && $full_address !== '') {
        $primary = $full_address;
    }

    return [
        'primary' => $primary !== '' ? $primary : 'Set up your shop location',
        'landmark' => trim((string) ($shop['landmark'] ?? '')),
    ];
}

function ownerNotificationUrl($notification, $owner_id)
{
    return isInternalAppUrl($notification['target_url'] ?? '') ? (string) $notification['target_url'] : '';
}

function ownerNotificationTone($notification)
{
    $type = strtolower((string) ($notification['type'] ?? ''));
    $text = strtolower(trim((string) (($notification['title'] ?? '') . ' ' . ($notification['message'] ?? ''))));

    if (str_contains($type, 'rejected') || str_contains($text, 'rejected')) {
        return 'danger';
    }

    if ($type === 'pickup_reminder' || str_contains($text, 'pickup reminder') || str_contains($text, 'pickup time')) {
        return 'warning';
    }

    if (str_contains($type, 'verified') || str_contains($text, 'verified') || str_contains($text, 'paid') || str_contains($text, 'completed') || str_contains($text, 'approved')) {
        return 'success';
    }

    if ($type === 'payment_submitted' || str_contains($text, 'submitted') || str_contains($text, 'pending') || str_contains($text, 'for verification')) {
        return 'info';
    }

    if (str_starts_with($type, 'order_') || str_contains($type, 'order')) {
        return 'info';
    }

    return 'neutral';
}

function ownerLayoutStart($active, $title, $subtitle = '', $notif_count = 0, $shop = null, $floating_toast = null)
{
    if (!empty($_SESSION['owner_toast']) && is_array($_SESSION['owner_toast'])) {
        setToast($_SESSION['owner_toast']['message'] ?? '', $_SESSION['owner_toast']['status'] ?? 'info');
        unset($_SESSION['owner_toast']);
    }

    if (!empty($floating_toast['message'])) {
        setToast($floating_toast['message'], $floating_toast['status'] ?? 'info');
    }

    $reopen_password_modal = !empty($_SESSION['reopen_change_password_modal']);
    unset($_SESSION['reopen_change_password_modal']);
    $uses_google_session = ($_SESSION['auth_provider'] ?? 'password') === 'google';
    $return_path = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
    if (!empty($_SERVER['QUERY_STRING'])) {
        $return_path .= '?' . $_SERVER['QUERY_STRING'];
    }

    $shop_name = $shop['shop_name'] ?? 'Print Shop';
    $shop_location = ownerShopLocation($shop);
    $shop_logo = $shop['shop_logo'] ?? '';
    $shop_logo_url = $shop_logo !== '' ? SHOP_LOGOS_URL . e($shop_logo) : '';
    $owner_css_version = filemtime(__DIR__ . "/../assets/owner.css");
    $owner_id = $_SESSION['user_id'] ?? 0;
    $recent_notifications = [];
    if ($owner_id && isset($GLOBALS['conn'])) {
        $notification_sql = "SELECT notification_id, type, title, message, target_url, created_at, is_read
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
        ['key' => 'reports', 'href' => 'reports.php', 'label' => 'Reports', 'icon' => 'chart-no-axes-combined'],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo e($title); ?> | PrintEase</title>
        <?php renderPrintEaseIcons(); ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
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
    </head>

    <body class="owner-body">
        <aside class="owner-sidebar" aria-label="Shop owner sidebar">
            <div class="owner-brand">
                <?php if ($shop_logo_url !== ''): ?>
                    <img src="<?php echo $shop_logo_url; ?>" class="owner-brand-logo" alt="<?php echo e($shop_name); ?> logo"
                        data-shop-logo-preview="sidebar">
                <?php else: ?>
                    <div class="owner-brand-mark" data-shop-logo-preview="sidebar">PE</div>
                <?php endif; ?>
                <div class="owner-brand-copy">
                    <span>Print Shop</span>
                    <strong><?php echo e($shop_name); ?></strong>
                    <div class="owner-brand-location" title="<?php echo e($shop_location['primary']); ?>">
                        <?php echo ownerIcon('map-pin', 'icon-sm'); ?>
                        <div>
                            <small><?php echo e($shop_location['primary']); ?></small>
                            <?php if ($shop_location['landmark'] !== ''): ?>
                                <small class="owner-brand-landmark"><?php echo e($shop_location['landmark']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
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

            <div class="owner-sidebar-actions">
                <button type="button" class="owner-security" id="ownerChangePasswordTrigger"
                    title="Change Password" aria-label="Change Password" aria-haspopup="dialog"
                    aria-controls="ownerChangePasswordModal">
                    <?php echo ownerIcon('key-round', 'icon nav-icon'); ?>
                    <span class="owner-nav-label">Change Password</span>
                </button>
                <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/logout.php" style="display:inline;">
                    <?php echo csrfField(); ?>
                    <button class="owner-logout" type="submit" title="Logout" aria-label="Logout">
                        <?php echo ownerIcon('log-out', 'icon nav-icon'); ?>
                        <span class="owner-nav-label">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="owner-shell">
            <header class="owner-topbar">
                <div class="topbar-title">
                    <strong>Admin Panel</strong>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="owner-sound-toggle" id="ownerSoundToggle"
                        aria-label="Mute new order sound alerts" aria-pressed="true"
                        title="New order sound alerts on">
                        <?php echo ownerIcon('volume-2', 'icon'); ?>
                    </button>
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
                                        <?php $notification_tone = ownerNotificationTone($notification); ?>
                                        <?php if ($notification_href !== ''): ?>
                                            <a class="notification-popover-item notification-popover-link notification-tone-<?php echo e($notification_tone); ?>"
                                                href="<?php echo e($notification_href); ?>"
                                                data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                                                data-is-read="<?php echo (int) $notification['is_read']; ?>"
                                                data-notification-tone="<?php echo e($notification_tone); ?>">
                                            <?php else: ?>
                                                <article class="notification-popover-item notification-tone-<?php echo e($notification_tone); ?>"
                                                    data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                                                    data-is-read="<?php echo (int) $notification['is_read']; ?>"
                                                    data-notification-tone="<?php echo e($notification_tone); ?>">
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
                                alt="<?php echo e($shop_name); ?> logo" data-shop-logo-preview="topbar">
                        <?php else: ?>
                            <b data-shop-logo-preview="topbar"><?php echo e($initials); ?></b>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="owner-modal-backdrop" id="ownerChangePasswordModal" role="dialog" aria-modal="true"
                aria-labelledby="ownerChangePasswordTitle" data-reopen="<?php echo $reopen_password_modal ? 'true' : 'false'; ?>" hidden>
                <section class="owner-modal-panel" tabindex="-1">
                    <header class="owner-modal-head">
                        <div>
                            <h2 id="ownerChangePasswordTitle">Change Password</h2>
                            <p>Update the password used for standard email sign-in.</p>
                        </div>
                        <button type="button" class="owner-modal-close" data-password-modal-close
                            aria-label="Close change password dialog">
                            <?php echo ownerIcon('x', 'icon'); ?>
                        </button>
                    </header>

                    <form action="<?php echo BASE_URL; ?>backend/actions/change_owner_password.php" method="POST"
                        class="form-grid" id="ownerChangePasswordForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="return_path" value="<?php echo e($return_path); ?>">

                        <?php if (!$uses_google_session): ?>
                            <div class="field full">
                                <label for="owner_current_password">Current Password</label>
                                <div class="password-field-wrap">
                                    <input id="owner_current_password" type="password" name="current_password"
                                        autocomplete="current-password" required>
                                    <button type="button" class="password-toggle" data-password-toggle="owner_current_password"
                                        aria-label="Show current password" aria-pressed="false">
                                        <?php echo ownerIcon('eye', 'icon-sm'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="field full">
                                <p class="password-help">You signed in with Google, so your active Google session verifies this password change.</p>
                            </div>
                        <?php endif; ?>

                        <div class="field full">
                            <label for="owner_new_password">New Password</label>
                            <div class="password-field-wrap">
                                <input id="owner_new_password" type="password" name="new_password" minlength="8"
                                    autocomplete="new-password" required aria-describedby="ownerNewPasswordHelp">
                                <button type="button" class="password-toggle" data-password-toggle="owner_new_password"
                                    aria-label="Show new password" aria-pressed="false">
                                    <?php echo ownerIcon('eye', 'icon-sm'); ?>
                                </button>
                            </div>
                            <p class="password-help" id="ownerNewPasswordHelp">Minimum of 8 characters.</p>
                        </div>

                        <div class="field full">
                            <label for="owner_confirm_password">Confirm New Password</label>
                            <div class="password-field-wrap">
                                <input id="owner_confirm_password" type="password" name="confirm_password" minlength="8"
                                    autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle="owner_confirm_password"
                                    aria-label="Show password confirmation" aria-pressed="false">
                                    <?php echo ownerIcon('eye', 'icon-sm'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="field full password-actions">
                            <button type="button" class="btn btn-soft" data-password-modal-close>Cancel</button>
                            <button class="btn btn-primary" type="submit" name="change_password">
                                <?php echo ownerIcon('key-round', 'icon'); ?>
                                Update Password
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <?php renderAppToasts('owner'); ?>

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
            (function () {
                const sidebar = document.querySelector('.owner-sidebar');
                const expandedClass = 'owner-sidebar-hovered';
                const storageKey = 'ownerSidebarHold';
                const pointerXKey = 'ownerSidebarPointerX';
                const pointerYKey = 'ownerSidebarPointerY';
                const navigationKey = 'ownerSidebarNavigationAt';
                const navigationWindow = 10000;
                let navigating = false;
                let lastPointer = null;
                let reconcileTimer = null;

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

                function storePointer(event) {
                    if (!event || !Number.isFinite(event.clientX) || !Number.isFinite(event.clientY)) {
                        return;
                    }

                    lastPointer = { x: event.clientX, y: event.clientY };
                    try {
                        sessionStorage.setItem(pointerXKey, String(event.clientX));
                        sessionStorage.setItem(pointerYKey, String(event.clientY));
                    } catch (error) { }
                }

                function storedPointerIsInside() {
                    try {
                        const navigationAt = Number(sessionStorage.getItem(navigationKey) || 0);
                        const x = lastPointer ? lastPointer.x : Number(sessionStorage.getItem(pointerXKey));
                        const y = lastPointer ? lastPointer.y : Number(sessionStorage.getItem(pointerYKey));
                        if (!navigationAt || Date.now() - navigationAt > navigationWindow || !Number.isFinite(x) || !Number.isFinite(y)) {
                            return false;
                        }

                        const rect = sidebar.getBoundingClientRect();
                        return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
                    } catch (error) {
                        return false;
                    }
                }

                function clearNavigationState() {
                    navigating = false;
                    try {
                        sessionStorage.removeItem(navigationKey);
                    } catch (error) { }
                }

                function openSidebar() {
                    document.documentElement.classList.add(expandedClass);
                    setStoredHoverState(true);
                }

                function closeSidebar(force = false) {
                    if (navigating && !force) {
                        return;
                    }
                    document.documentElement.classList.remove(expandedClass);
                    setStoredHoverState(false);
                }

                function reconcileSidebar() {
                    if (window.matchMedia('(max-width: 820px)').matches) {
                        closeSidebar(true);
                        clearNavigationState();
                        return;
                    }

                    const pointerInside = sidebar.matches(':hover') || storedPointerIsInside();
                    if (pointerInside) {
                        openSidebar();
                    } else {
                        closeSidebar(true);
                    }
                    clearNavigationState();
                }

                function scheduleReconcile() {
                    if (reconcileTimer !== null) {
                        window.clearTimeout(reconcileTimer);
                    }
                    reconcileTimer = window.setTimeout(function () {
                        reconcileTimer = null;
                        reconcileSidebar();
                    }, 30);
                }

                sidebar.addEventListener('pointerenter', function (event) {
                    storePointer(event);
                    openSidebar();
                });
                sidebar.addEventListener('pointermove', storePointer);
                sidebar.addEventListener('pointerleave', function () {
                    closeSidebar();
                });

                document.addEventListener('pointermove', function (event) {
                    lastPointer = { x: event.clientX, y: event.clientY };
                    if (!navigating && !sidebar.contains(event.target) && !sidebar.matches(':hover')) {
                        closeSidebar(true);
                    }
                });

                sidebar.addEventListener('click', function (event) {
                    const link = event.target.closest('a');
                    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

                    navigating = true;
                    if (event.detail > 0) {
                        storePointer(event);
                    } else {
                        try {
                            sessionStorage.removeItem(pointerXKey);
                            sessionStorage.removeItem(pointerYKey);
                        } catch (error) { }
                    }
                    openSidebar();
                    try {
                        sessionStorage.setItem(navigationKey, String(Date.now()));
                    } catch (error) { }
                });

                window.addEventListener('pageshow', function () {
                    scheduleReconcile();
                });

                scheduleReconcile();
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
                const markReadUrl = '<?php echo BASE_URL; ?>backend/actions/mark_notification_read.php';
                const badge = document.getElementById('ownerNotificationBadge');
                const unreadText = document.getElementById('ownerNotificationUnreadText');
                const pageUnreadBadge = document.getElementById('unread-badge');
                const readNotificationIcon = <?php echo json_encode(ownerIcon('info', 'icon-sm')); ?>;

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

                        const iconWrap = matchingItem.querySelector('.notification-popover-icon');
                        if (iconWrap) {
                            iconWrap.innerHTML = readNotificationIcon;
                        }
                    });
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

            (function () {
                const trigger = document.getElementById('ownerChangePasswordTrigger');
                const modal = document.getElementById('ownerChangePasswordModal');
                const panel = modal ? modal.querySelector('.owner-modal-panel') : null;
                const form = document.getElementById('ownerChangePasswordForm');
                const newPassword = document.getElementById('owner_new_password');
                const confirmation = document.getElementById('owner_confirm_password');
                let previousFocus = null;

                if (!trigger || !modal || !panel || !form || !newPassword || !confirmation) {
                    return;
                }

                function focusableElements() {
                    return Array.from(modal.querySelectorAll(
                        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'
                    )).filter(function (element) {
                        return !element.hidden;
                    });
                }

                function openModal() {
                    previousFocus = document.activeElement;
                    modal.hidden = false;
                    document.body.classList.add('owner-modal-open');
                    requestAnimationFrame(function () {
                        modal.classList.add('is-visible');
                        const firstInput = modal.querySelector('input[type="password"]');
                        (firstInput || panel).focus();
                    });
                }

                function closeModal() {
                    modal.classList.remove('is-visible');
                    document.body.classList.remove('owner-modal-open');
                    window.setTimeout(function () {
                        modal.hidden = true;
                        form.reset();
                        confirmation.setCustomValidity('');
                        if (previousFocus && typeof previousFocus.focus === 'function') {
                            previousFocus.focus();
                        } else {
                            trigger.focus();
                        }
                    }, 200);
                }

                function validateConfirmation() {
                    confirmation.setCustomValidity(
                        confirmation.value !== newPassword.value ? 'The new passwords do not match.' : ''
                    );
                }

                trigger.addEventListener('click', openModal);
                modal.querySelectorAll('[data-password-modal-close]').forEach(function (button) {
                    button.addEventListener('click', closeModal);
                });

                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                modal.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeModal();
                        return;
                    }

                    if (event.key !== 'Tab') return;
                    const focusable = focusableElements();
                    if (focusable.length === 0) {
                        event.preventDefault();
                        panel.focus();
                        return;
                    }

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });

                modal.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const input = document.getElementById(button.dataset.passwordToggle);
                        if (!input) return;
                        const showing = input.type === 'text';
                        input.type = showing ? 'password' : 'text';
                        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    });
                });

                newPassword.addEventListener('input', validateConfirmation);
                confirmation.addEventListener('input', validateConfirmation);
                form.addEventListener('submit', validateConfirmation);

                if (modal.dataset.reopen === 'true') {
                    openModal();
                }
            })();
        </script>
        <script src="<?php echo BASE_URL; ?>frontend/assets/js/live-updates.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/live-updates.js'); ?>" data-printease-live data-base-url="<?php echo e(BASE_URL); ?>"></script>
        <?php renderPrintEaseSWRegistration(); ?>
    </body>

    </html>
    <?php
}
?>
