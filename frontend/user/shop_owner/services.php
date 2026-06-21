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
$paper_sizes = [];
$paper_types = [];
$print_types = [];
$category_print_types = [];
$total_price = 0;
while ($service = mysqli_fetch_assoc($services_result)) {
    $services[] = $service;
    $groups[$service['paper_type']][] = $service;
    $paper_counts[$service['paper_type']] = ($paper_counts[$service['paper_type']] ?? 0) + 1;
    $paper_sizes[$service['paper_size']] = true;
    $paper_types[$service['paper_type']] = true;
    $print_types[$service['print_type']] = true;
    $category_print_types[$service['paper_type']][$service['print_type']] = true;
    $total_price += (float) $service['price_per_page'];
}

$total_services = count($services);
$average_price = $total_services > 0 ? $total_price / $total_services : 0;
$category_count = count($groups);
$paper_sizes = array_keys($paper_sizes);
$paper_types = array_keys($paper_types);
$print_types = array_keys($print_types);
sort($paper_sizes);
sort($paper_types);
sort($print_types);

ownerLayoutStart('services', 'Paper Pricing Management', 'Manage paper types, sizes, print types, and pricing for your print shop.', $notif_count, $shop, $owner_toast);
?>

<section class="pricing-admin-panel" aria-label="Paper pricing admin panel">
    <section class="pricing-metrics" aria-label="Paper pricing summary">
        <article class="pricing-metric-card metric-blue">
            <span class="pricing-metric-icon"><?php echo ownerIcon('layers', 'icon'); ?></span>
            <div>
                <span>Total Services</span>
                <strong><?php echo (int) $total_services; ?></strong>
                <p>Configured print prices</p>
            </div>
        </article>
        <article class="pricing-metric-card metric-cyan">
            <span class="pricing-metric-icon"><?php echo ownerIcon('philippine-peso', 'icon'); ?></span>
            <div>
                <span>Average Price</span>
                <strong><?php echo ownerMoney($average_price); ?></strong>
                <p>Per page average</p>
            </div>
        </article>
        <article class="pricing-metric-card metric-purple">
            <span class="pricing-metric-icon"><?php echo ownerIcon('folder', 'icon'); ?></span>
            <div>
                <span>Paper Categories</span>
                <strong><?php echo (int) $category_count; ?></strong>
                <p>Active categories</p>
            </div>
        </article>
    </section>

    <section class="owner-card pricing-form-panel">
        <div class="pricing-section-title">
            <div>
                <h2><?php echo ownerIcon('file-text', 'icon'); ?>Add Paper Pricing</h2>
            </div>
            <span class="status-badge status-info">Owner Managed</span>
        </div>
        <form action="../../../backend/actions/add_service.php" method="POST" class="pricing-form-grid">
            <div class="field">
                <label for="paper_size">Paper Size</label>
                <input id="paper_size" type="text" name="paper_size" list="paper-size-options"
                    placeholder="Select paper size (e.g., A4, Letter, Legal)" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
                <label for="paper_type">Paper Type</label>
                <input id="paper_type" type="text" name="paper_type" list="paper-type-options"
                    placeholder="Select paper type (e.g., Glossy, Matte, Bond Paper)" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
                <label for="print_type">Print Type</label>
                <input id="print_type" type="text" name="print_type" list="print-type-options"
                    placeholder="Select print type (e.g., Black & White or Color)" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
            </div>
            <div class="field">
                <label for="price_per_page">Price Per Page (&#8369;)</label>
                <input id="price_per_page" type="number" step="0.01" min="0.01" name="price_per_page"
                    placeholder="Enter price per page" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
            </div>
            <div class="field full">
                <button type="submit" name="add_service" class="btn btn-primary pricing-submit" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    <?php echo ownerIcon('plus', 'icon'); ?>Add Paper Category
                </button>
            </div>
        </form>

        <datalist id="paper-size-options">
            <?php foreach ($paper_sizes as $paper_size_option): ?>
                <option value="<?php echo e($paper_size_option); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <datalist id="paper-type-options">
            <?php foreach ($paper_types as $paper_type_option): ?>
                <option value="<?php echo e($paper_type_option); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <datalist id="print-type-options">
            <?php foreach ($print_types as $print_type_option): ?>
                <option value="<?php echo e($print_type_option); ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </section>

    <?php if (empty($groups)): ?>
        <section class="owner-card empty-state">
            <h2>No pricing yet</h2>
            <p>Add your first paper size and print price to make your shop order-ready.</p>
        </section>
    <?php else: ?>
        <div class="pricing-category-stack">
            <?php foreach ($groups as $paper_type => $paper_services): ?>
                <?php
                $category_key = 'pricing-category-' . md5($paper_type);
                $category_types = array_keys($category_print_types[$paper_type] ?? []);
                sort($category_types);
                ?>
                <section class="owner-card service-section pricing-category" id="<?php echo e($category_key); ?>" data-pricing-category>
                    <div class="service-header">
                        <div class="service-title-row">
                            <div>
                                <h2><?php echo ownerIcon('file-text', 'icon'); ?><?php echo e($paper_type); ?></h2>
                                <span><?php echo count($paper_services); ?> service<?php echo count($paper_services) === 1 ? '' : 's'; ?> available</span>
                            </div>
                            <details class="pricing-manage-menu">
                                <summary>
                                    <?php echo ownerIcon('settings', 'icon-sm'); ?>
                                    Manage Category
                                    <?php echo ownerIcon('chevron-down', 'icon-sm'); ?>
                                </summary>
                                <div class="pricing-menu-panel">
                                    <p><strong><?php echo e($paper_type); ?></strong></p>
                                    <p><?php echo count($paper_services); ?> configured service<?php echo count($paper_services) === 1 ? '' : 's'; ?></p>
                                    <button type="button" data-clear-category>Clear Filters</button>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="service-body">
                        <div class="pricing-filter-row" aria-label="<?php echo e($paper_type); ?> filters">
                            <label>
                                <span>Search size</span>
                                <input type="search" placeholder="Filter by paper size" data-category-search>
                            </label>
                            <label>
                                <span>Print type</span>
                                <select data-category-print-type>
                                    <option value="">All print types</option>
                                    <?php foreach ($category_types as $category_type): ?>
                                        <option value="<?php echo e(strtolower((string) $category_type)); ?>"><?php echo e($category_type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="service-grid pricing-service-grid">
                            <?php foreach ($paper_services as $service): ?>
                                <?php
                                $paper_size_label = (string) ($service['paper_size'] ?? '');
                                $tile_label = strlen($paper_size_label) <= 4 ? $paper_size_label : substr($paper_size_label, 0, 4);
                                ?>
                                <article class="service-price-card pricing-service-card"
                                    data-service-card
                                    data-paper-size="<?php echo e(strtolower($paper_size_label)); ?>"
                                    data-print-type="<?php echo e(strtolower((string) $service['print_type'])); ?>">
                                    <span class="pricing-paper-tile"><?php echo e($tile_label); ?></span>
                                    <div class="pricing-service-copy">
                                        <h3><?php echo e($paper_size_label); ?></h3>
                                        <p><?php echo e($service['print_type']); ?></p>
                                        <small>Price per page</small>
                                        <strong><?php echo ownerMoney($service['price_per_page']); ?></strong>
                                    </div>
                                    <span class="pricing-card-action" aria-hidden="true"><?php echo ownerIcon('copy', 'icon-sm'); ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="pricing-filter-empty" data-category-empty hidden>No services match these filters.</p>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
    (function () {
        document.querySelectorAll('[data-pricing-category]').forEach(function (category) {
            var search = category.querySelector('[data-category-search]');
            var printType = category.querySelector('[data-category-print-type]');
            var cards = Array.prototype.slice.call(category.querySelectorAll('[data-service-card]'));
            var empty = category.querySelector('[data-category-empty]');
            var clear = category.querySelector('[data-clear-category]');

            function applyFilters() {
                var query = search ? search.value.trim().toLowerCase() : '';
                var selectedType = printType ? printType.value.trim().toLowerCase() : '';
                var visibleCount = 0;

                cards.forEach(function (card) {
                    var matchesSize = !query || (card.dataset.paperSize || '').indexOf(query) !== -1;
                    var matchesType = !selectedType || (card.dataset.printType || '') === selectedType;
                    var isVisible = matchesSize && matchesType;
                    card.hidden = !isVisible;
                    if (isVisible) visibleCount += 1;
                });

                if (empty) {
                    empty.hidden = visibleCount !== 0;
                }
            }

            if (search) search.addEventListener('input', applyFilters);
            if (printType) printType.addEventListener('change', applyFilters);
            if (clear) {
                clear.addEventListener('click', function () {
                    if (search) search.value = '';
                    if (printType) printType.value = '';
                    applyFilters();
                    var menu = clear.closest('details');
                    if (menu) menu.open = false;
                });
            }
        });
    })();
</script>

<?php ownerLayoutEnd(); ?>
