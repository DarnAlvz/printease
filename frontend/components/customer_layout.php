<?php

require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/head.php';

function customerIcon($name, $class = 'customer-icon')
{
    if ($name === 'printer') {
        $logo_url = printEaseAssetUrl('assets/images/printing-logo.png');
        return '<img class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" src="' . htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') . '" style="width:35px; height:35px ; object-fit:contain; alt="Printer Logo">';
    }

    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/>',
        'shops' => '<path d="M3 9h18l-2-5H5L3 9Z"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'map' => '<path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15M15 6v15"/>',
        'explore' => '<path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15M15 6v15"/><circle cx="12" cy="12" r="2.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'orders' => '<path d="M6 2h9l4 4v16H6Z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'download' => '<path d="M12 3v12m0 0 5-5m-5 5-5-5"/><path d="M5 21h14"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'moon' => '<path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
        'pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m11 12 9-9M15 8l3 3M17 6l3 3"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="m3 3 18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-2.1 3.1M6.6 6.6C3.5 8.7 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'wallet' => '<path d="M3 6h15a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2Z"/><path d="M3 8V5a2 2 0 0 1 2-2h12v3"/><path d="M15 11h7v4h-7a2 2 0 0 1 0-4Z"/>',
        'package' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4.5 7.5 7.5 4 7.5-4M12 11.5V21"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
    ];
    $paths = $icons[$name] ?? $icons['home'];
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function customerNavigationItems()
{
    return [
        'home' => ['label' => 'Home', 'path' => 'dashboard.php', 'icon' => 'home'],
        'explore' => ['label' => 'Explore', 'path' => 'explore.php', 'icon' => 'explore'],
        'order' => ['label' => 'Order', 'path' => 'explore.php?view=all', 'icon' => 'plus'],
        'orders' => ['label' => 'Orders', 'path' => 'orders.php', 'icon' => 'orders'],
        'profile' => ['label' => 'Profile', 'path' => 'profile.php', 'icon' => 'profile'],
    ];
}

function customerActiveNavigation(?string $active = null)
{
    if ($active !== null) {
        return match ($active) {
            'shops', 'map', 'place_order' => 'explore',
            'payment' => 'orders',
            default => $active,
        };
    }
    return match (basename($_SERVER['PHP_SELF'] ?? 'dashboard.php')) {
        'explore.php', 'shops.php', 'shopLocation.php', 'place_order.php' => 'explore',
        'orders.php', 'payment.php' => 'orders',
        'profile.php' => 'profile',
        'notifications.php' => '',
        default => 'home',
    };
}

function renderCustomerHead()
{
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    $css_path = __DIR__ . '/../user/customer/assets/customer.css';
    $css_version = is_file($css_path) ? filemtime($css_path) : time();

    renderPrintEaseIcons();
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#03045e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <!-- Phase 2: <link rel="manifest" href="/printease/manifest.webmanifest"> -->
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('customerTheme'); } catch (error) { }
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = stored === 'light' || stored === 'dark' ? stored : (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-customer-theme', theme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(printEaseAssetUrl('frontend/user/customer/assets/customer.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $css_version; ?>">
    <script>
        window.addEventListener('beforeinstallprompt', function (event) {
            // Phase 2 hook: install prompting is intentionally not implemented yet.
        });
    </script>
    <?php
}

function customerIdentity()
{
    static $identity = null;
    if ($identity !== null) return $identity;
    $identity = ['name' => (string) ($_SESSION['full_name'] ?? 'Customer'), 'profile_picture' => '', 'notification_count' => 0];
    $conn = $GLOBALS['conn'] ?? null;
    $customer_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($conn instanceof mysqli && $customer_id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT full_name, profile_picture FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $customer_id);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($user) {
                $identity['name'] = (string) ($user['full_name'] ?: $identity['name']);
                $identity['profile_picture'] = (string) ($user['profile_picture'] ?? '');
            }
        }
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $customer_id);
            mysqli_stmt_execute($stmt);
            $identity['notification_count'] = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
        }
    }
    return $identity;
}

function customerInitials($name)
{
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'CU';
}

function renderCustomerLayout(array $options)
{
    $title = (string) ($options['title'] ?? 'PrintEase');
    $welcome_label = (string) ($options['welcome_label'] ?? '');
    $identity = customerIdentity();
    $notification_count = array_key_exists('notification_count', $options) ? max(0, (int) $options['notification_count']) : $identity['notification_count'];
    $page_title = $welcome_label !== '' ? 'Customer Dashboard' : $title;
    $profile_url = $identity['profile_picture'] !== '' ? printEaseAssetUrl($identity['profile_picture']) : '';
    ?>
    <header class="customer-topbar">
        <a class="customer-topbar-brand" href="<?php echo htmlspecialchars(printEaseAssetUrl('frontend/user/customer/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="PrintEase customer home">
           <?php echo customerIcon('printer'); ?><strong><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></strong>
        </a>
        <div class="customer-topbar-actions">
            <button type="button" class="customer-install-placeholder" data-customer-install hidden aria-label="Install PrintEase">
                <?php echo customerIcon('download'); ?><span>Install</span>
            </button>
            <button type="button" class="customer-theme-toggle" data-customer-theme-toggle
                aria-label="Switch to dark mode" aria-pressed="false">
                <span class="customer-theme-toggle__sun"><?php echo customerIcon('sun'); ?></span>
                <span class="customer-theme-toggle__moon"><?php echo customerIcon('moon'); ?></span>
            </button>
            <a class="customer-notification-link" href="<?php echo htmlspecialchars(printEaseAssetUrl('frontend/user/customer/notifications.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Notifications">
                <?php echo customerIcon('bell'); ?>
                <?php if ($notification_count > 0): ?><span><?php echo $notification_count; ?></span><?php endif; ?>
            </a>
            <a class="customer-user" href="<?php echo htmlspecialchars(printEaseAssetUrl('frontend/user/customer/profile.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Open customer profile">
                <div><strong><?php echo htmlspecialchars($identity['name'], ENT_QUOTES, 'UTF-8'); ?></strong><span>Customer</span></div>
                <?php if ($profile_url !== ''): ?><img src="<?php echo htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($identity['name'], ENT_QUOTES, 'UTF-8'); ?> profile picture"><?php else: ?><b><?php echo htmlspecialchars(customerInitials($identity['name']), ENT_QUOTES, 'UTF-8'); ?></b><?php endif; ?>
            </a>
        </div>
    </header>
    <?php
}

function renderCustomerLayoutEnd(?string $active = null)
{
    $active = customerActiveNavigation($active);
    $items = customerNavigationItems();
    $render_nav_item = function (string $key) use ($items, $active) {
        $item = $items[$key];
        ?>
        <a href="<?php echo htmlspecialchars(printEaseAssetUrl('frontend/user/customer/' . $item['path']), ENT_QUOTES, 'UTF-8'); ?>"
            class="customer-bottom-nav__item<?php echo $key === 'order' ? ' customer-bottom-nav__order' : ''; ?><?php echo $active === $key ? ' is-active active' : ''; ?>"
            data-route="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="<?php echo htmlspecialchars($key === 'order' ? 'Start order' : $item['label'], ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
            <?php if ($key === 'order'): ?>
                <span class="customer-bottom-nav__order-icon"><?php echo customerIcon($item['icon'], 'customer-bottom-nav-icon'); ?></span>
            <?php else: ?>
                <?php echo customerIcon($item['icon'], 'customer-bottom-nav-icon'); ?>
                <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </a>
        <?php
    };
    $nav_order = ['home', 'explore', 'order', 'orders', 'profile'];
    ?>
    <nav class="customer-bottom-nav customer-bottom-nav--pwa" aria-label="Customer navigation">
        <?php foreach ($nav_order as $key): ?>
            <?php $render_nav_item($key); ?>
        <?php endforeach; ?>
    </nav>
    <script>
        (function () {
            var themeToggle = document.querySelector('[data-customer-theme-toggle]');

            function setCustomerTheme(theme) {
                var isDark = theme === 'dark';
                document.documentElement.setAttribute('data-customer-theme', isDark ? 'dark' : 'light');
                if (themeToggle) {
                    themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                    themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                }
            }

            if (themeToggle) {
                setCustomerTheme(document.documentElement.getAttribute('data-customer-theme') || 'light');
                themeToggle.addEventListener('click', function () {
                    var current = document.documentElement.getAttribute('data-customer-theme') === 'dark' ? 'dark' : 'light';
                    var next = current === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('customerTheme', next); } catch (error) { }
                    setCustomerTheme(next);
                });
            }

            var file = (location.pathname.split('/').pop() || 'dashboard.php').toLowerCase();
            var routeByFile = {
                'dashboard.php': 'home',
                'explore.php': 'explore',
                'shops.php': 'explore',
                'shoplocation.php': 'explore',
                'place_order.php': 'explore',
                'orders.php': 'orders',
                'payment.php': 'orders',
                'profile.php': 'profile'
            };
            var activeRoute = routeByFile[file] || 'home';

            document.querySelectorAll('.customer-bottom-nav__item').forEach(function (item) {
                var isActive = item.dataset.route === activeRoute;
                item.classList.toggle('is-active', isActive);
                item.classList.toggle('active', isActive);
                if (isActive) {
                    item.setAttribute('aria-current', 'page');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        })();
    </script>
    <?php
}
