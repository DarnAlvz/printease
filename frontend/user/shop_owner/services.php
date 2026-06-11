<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/includes/owner_layout.php";

requireCompleteShopProfile($conn);
$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

$owner_id = $_SESSION['user_id'];

$notif_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = (mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0);

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));
$shop_id = $shop['shop_id'];

$services_sql = "SELECT * FROM shop_services WHERE shop_id = ? ORDER BY paper_type ASC, paper_size ASC, print_type ASC";
$services_stmt = mysqli_prepare($conn, $services_sql);
mysqli_stmt_bind_param($services_stmt, "i", $shop_id);
mysqli_stmt_execute($services_stmt);
$services_result = mysqli_stmt_get_result($services_stmt);

$services = [];
$groups = [];
$paper_counts = [];
$total_price = 0;
while ($service = mysqli_fetch_assoc($services_result)) {
    $services[] = $service;
    $groups[$service['paper_type']][] = $service;
    $paper_counts[$service['paper_type']] = ($paper_counts[$service['paper_type']] ?? 0) + 1;
    $total_price += (float) $service['price_per_page'];
}

$total_services = count($services);
$average_price = $total_services > 0 ? $total_price / $total_services : 0;
if (!empty($paper_counts)) {
    arsort($paper_counts);
}
$most_used_paper = $total_services > 0 ? array_key_first($paper_counts) : 'No services yet';

ownerLayoutStart('services', 'Paper Pricing Management', 'Manage paper types, sizes, print types, and pricing for your print shop.', $notif_count, $shop, $owner_toast);
?>

<?php showMessage(); ?>

<section class="summary-grid" style="margin-bottom:24px;">
    <article class="metric-card">
        <div class="metric-head">
            <span>Total Services</span>
            <span class="metric-icon"><?php echo ownerIcon('layers', 'icon'); ?></span>
        </div>
        <strong><?php echo (int) $total_services; ?></strong>
        <p class="card-note">Configured print prices</p>
    </article>
    <article class="metric-card">
        <div class="metric-head">
            <span>Average Price</span>
            <span class="metric-icon"><?php echo ownerIcon('badge-dollar-sign', 'icon'); ?></span>
        </div>
        <strong><?php echo ownerMoney($average_price); ?></strong>
        <p class="card-note">Per page average</p>
    </article>
    <article class="metric-card">
        <div class="metric-head">
            <span>Paper Categories</span>
            <span class="metric-icon"><?php echo ownerIcon('files', 'icon'); ?></span>
        </div>
        <strong><?php echo e($most_used_paper); ?></strong>
        <p class="card-note"><?php echo count($groups); ?> active categories</p>
    </article>
</section>

<section class="owner-card" style="margin-bottom:24px;">
    <div class="card-head">
        <h2>Add Paper Pricing</h2>
        <span class="status-badge status-info">Owner Managed</span>
    </div>
    <form action="../../../backend/actions/add_service.php" method="POST" class="form-grid" style="margin-top:18px;">
        <div class="field">
            <label for="paper_size">Paper Size</label>
            <input id="paper_size" type="text" name="paper_size" placeholder="A4, Letter, Legal" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
        </div>
        <div class="field">
            <label for="paper_type">Paper Type</label>
            <input id="paper_type" type="text" name="paper_type" placeholder="Glossy, Matte, Bond Paper" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
        </div>
        <div class="field">
            <label for="print_type">Print Type</label>
            <input id="print_type" type="text" name="print_type" placeholder="Black & White or Color" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
        </div>
        <div class="field">
            <label for="price_per_page">Price Per Page</label>
            <input id="price_per_page" type="number" step="0.01" min="0.01" name="price_per_page" placeholder="16.00" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
        </div>
        <div class="field full">
            <button type="submit" name="add_service" class="btn btn-primary" <?php echo $owner_is_verified ? '' : 'disabled'; ?>><?php echo ownerIcon('plus', 'icon'); ?>Add Paper Category</button>
        </div>
    </form>
</section>

<?php if (empty($groups)): ?>
    <section class="owner-card empty-state">
        <h2>No pricing yet</h2>
        <p>Add your first paper size and print price to make your shop order-ready.</p>
    </section>
<?php else: ?>
    <div class="stack">
        <?php foreach ($groups as $paper_type => $paper_services): ?>
            <section class="owner-card service-section">
                <div class="service-header">
                    <h2><?php echo e($paper_type); ?></h2>
                    <p><?php echo count($paper_services); ?> service<?php echo count($paper_services) === 1 ? '' : 's'; ?> available</p>
                </div>
                <div class="service-body">
                    <div class="service-grid">
                        <?php foreach ($paper_services as $service): ?>
                            <article class="service-price-card">
                                <h3><?php echo e($service['paper_size']); ?></h3>
                                <p class="muted"><?php echo e($service['print_type']); ?></p>
                                <div class="price-box">
                                    <small>Price per page</small>
                                    <strong><?php echo ownerMoney($service['price_per_page']); ?></strong>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php ownerLayoutEnd(); ?>
