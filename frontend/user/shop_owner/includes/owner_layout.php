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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>frontend/user/shop_owner/assets/owner.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="owner-body">
    <aside class="owner-sidebar">
        <div class="owner-brand">
            <?php if ($shop_logo_url !== ''): ?>
                <img src="<?php echo $shop_logo_url; ?>" class="owner-brand-logo" alt="<?php echo e($shop_name); ?> logo">
            <?php else: ?>
                <div class="owner-brand-mark">PE</div>
            <?php endif; ?>
            <div>
                <span>Print Shop</span>
                <strong><?php echo e($shop_name); ?></strong>
                <small><?php echo e($shop_address); ?></small>
            </div>
        </div>

        <nav class="owner-nav" aria-label="Shop owner navigation">
            <?php foreach ($nav as $item): ?>
                <a class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>">
                    <?php echo ownerIcon($item['icon'], 'icon nav-icon'); ?>
                    <?php echo e($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <a class="owner-logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php">
            <?php echo ownerIcon('log-out', 'icon nav-icon'); ?>
            Logout
        </a>
    </aside>

    <div class="owner-shell">
        <header class="owner-topbar">
            <div class="topbar-title">
                <span class="menu-mark"></span>
                <strong>Admin Panel</strong>
            </div>
            <div class="topbar-actions">
                <a class="notification-link" href="notifications.php" aria-label="Notifications">
                    <?php echo ownerIcon('bell', 'icon'); ?>
                    <?php if ($notif_count > 0): ?>
                        <span class="notification-badge"><?php echo (int) $notif_count; ?></span>
                    <?php endif; ?>
                </a>
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
    </script>
</body>
</html>
<?php
}
?>
