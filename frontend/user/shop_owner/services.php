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
$paper_sizes = [];
$paper_types = [];
$print_types = [];
$total_price = 0;
while ($service = mysqli_fetch_assoc($services_result)) {
    $services[] = $service;
    $paper_sizes[$service['paper_size']] = true;
    $paper_types[$service['paper_type']] = true;
    $print_types[$service['print_type']] = true;
    $total_price += (float) $service['price_per_page'];
}

$total_services = count($services);
$paper_sizes = array_keys($paper_sizes);
$paper_types = array_keys($paper_types);
$print_types = array_keys($print_types);
sort($paper_sizes);
sort($paper_types);
sort($print_types);

$allowed_service_types = [
    'Document Printing',
    'Photocopy',
    'Photo Printing',
    'Tarpaulin Printing',
    'Lamination',
    'Binding',
    'Scanning',
    'ID Printing',
    'Invitation / Card Printing',
];

$shop_service_types = ['Document Printing'];
$st_stmt = mysqli_prepare($conn, "SELECT service_type FROM shop_service_types WHERE shop_id = ? ORDER BY service_type ASC");
if ($st_stmt) {
    mysqli_stmt_bind_param($st_stmt, "i", $shop_id);
    mysqli_stmt_execute($st_stmt);
    $st_result = mysqli_stmt_get_result($st_stmt);
    while ($st_row = mysqli_fetch_assoc($st_result)) {
        $service_type = trim((string) ($st_row['service_type'] ?? ''));
        if ($service_type !== '') {
            $shop_service_types[] = $service_type;
        }
    }
}
$shop_service_types = array_values(array_unique($shop_service_types));
$pricing_service_types = array_values(array_filter($allowed_service_types, function ($type) use ($shop_service_types) {
    return $type !== 'Document Printing' && in_array($type, $shop_service_types, true);
}));

$ssp_sql = "SELECT * FROM shop_service_pricing WHERE shop_id = ? ORDER BY service_type ASC, option_label ASC";
$ssp_stmt = mysqli_prepare($conn, $ssp_sql);
mysqli_stmt_bind_param($ssp_stmt, "i", $shop_id);
mysqli_stmt_execute($ssp_stmt);
$ssp_result = mysqli_stmt_get_result($ssp_stmt);

$service_pricing = [];
$ssp_groups = [];
$ssp_total = 0;
$ssp_total_price = 0;
$ssp_type_count = [];
while ($row = mysqli_fetch_assoc($ssp_result)) {
    if ($row['service_type'] === 'Document Printing' || !in_array($row['service_type'], $pricing_service_types, true)) {
        continue;
    }
    $service_pricing[] = $row;
    $ssp_groups[$row['service_type']][] = $row;
    $ssp_total++;
    $ssp_total_price += (float) $row['price'];
    $ssp_type_count[$row['service_type']] = ($ssp_type_count[$row['service_type']] ?? 0) + 1;
}

$pricing_total_items = $total_services + $ssp_total;
$pricing_total_amount = $total_price + $ssp_total_price;
$pricing_average_price = $pricing_total_items > 0 ? $pricing_total_amount / $pricing_total_items : 0;
$pricing_selected_count = count($shop_service_types);

ownerLayoutStart('services', 'Service Pricing Management', 'Manage document printing and selected service prices for your print shop.', $notif_count, $shop, $owner_toast);
?>

<section class="pricing-admin-panel unified-pricing-panel" aria-label="Service pricing workspace">
    <form method="GET" data-live-search-form data-live-target="owner_services" hidden aria-hidden="true"></form>
    <div class="owner-card ssp-workspace unified-pricing-workspace">
        <div class="ssp-toolbar unified-pricing-toolbar">
            <div>
                <span class="ssp-eyebrow">Pricing Workspace</span>
                <h2><?php echo ownerIcon('package', 'icon'); ?>Service Pricing Management</h2>
                <p>Manage Document Printing and selected service prices in one clean directory.</p>
            </div>
            <span class="status-badge <?php echo $owner_is_verified ? 'status-info' : 'status-warning'; ?>">
                <?php echo $owner_is_verified ? 'Owner Managed' : 'Verification Required'; ?>
            </span>
        </div>

        <section class="pricing-metrics ssp-metrics" aria-label="Service pricing summary" data-live-region="owner-service-pricing-metrics">
            <article class="pricing-metric-card metric-blue">
                <span class="pricing-metric-icon"><?php echo ownerIcon('layers', 'icon'); ?></span>
                <div>
                    <span>Total Prices</span>
                    <strong><?php echo (int) $pricing_total_items; ?></strong>
                    <p>Document and service entries</p>
                </div>
            </article>
            <article class="pricing-metric-card metric-cyan">
                <span class="pricing-metric-icon"><?php echo ownerIcon('philippine-peso', 'icon'); ?></span>
                <div>
                    <span>Average Price</span>
                    <strong><?php echo ownerMoney($pricing_average_price); ?></strong>
                    <p>Across visible prices</p>
                </div>
            </article>
            <article class="pricing-metric-card metric-purple">
                <span class="pricing-metric-icon"><?php echo ownerIcon('package', 'icon'); ?></span>
                <div>
                    <span>Selected Services</span>
                    <strong><?php echo (int) $pricing_selected_count; ?></strong>
                    <p>From Shop Management</p>
                </div>
            </article>
        </section>

        <section class="ssp-form-panel unified-add-panel pricing-add-compact" aria-label="Add service price">
            <div class="ssp-panel-heading pricing-add-heading">
                <div>
                    <h3><?php echo ownerIcon('plus', 'icon-sm'); ?>Add Price</h3>
                    <p>Create one generic price record for any offered service.</p>
                </div>
                <button type="button" class="btn btn-primary pricing-modal-trigger" <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-open-pricing-modal>
                    <?php echo ownerIcon('plus', 'icon'); ?>Add Price
                </button>
            </div>
            <?php if (!$owner_is_verified): ?>
                <div class="ssp-locked-note">
                    <?php echo ownerIcon('lock', 'icon-sm'); ?>
                    Your shop must be verified before you can add or update service prices.
                </div>
            <?php endif; ?>

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

            <?php if (empty($pricing_service_types)): ?>
                <div class="ssp-locked-note ssp-profile-note">
                    <?php echo ownerIcon('info', 'icon-sm'); ?>
                    Document Printing is ready. Select services like Lamination, Binding, or Photo Printing in Shop Management to add more service prices here.
                </div>
            <?php endif; ?>
        </section>

        <div class="pricing-modal-shell" data-pricing-modal hidden>
            <div class="pricing-modal-backdrop" data-close-pricing-modal></div>
            <section class="pricing-modal owner-card" role="dialog" aria-modal="true" aria-labelledby="pricingModalTitle">
                <div class="pricing-modal-header">
                    <div>
                        <span class="ssp-eyebrow">Generic Pricing</span>
                        <h3 id="pricingModalTitle"><?php echo ownerIcon('plus', 'icon-sm'); ?>Add Price Record</h3>
                        <p>Use the same pricing format for Document Printing and every selected service.</p>
                    </div>
                    <button type="button" class="pricing-modal-close" aria-label="Close add price modal" data-close-pricing-modal>
                        <?php echo ownerIcon('x', 'icon-sm'); ?>
                    </button>
                </div>

                <form action="../../../backend/actions/add_service.php" method="POST" class="pricing-form-grid unified-pricing-form pricing-modal-form" data-unified-pricing-form data-owner-verified="<?php echo $owner_is_verified ? '1' : '0'; ?>">
                    <?php echo csrfField(); ?>

                    <div class="field">
                        <label for="pricing_service_type">Service</label>
                        <select id="pricing_service_type" name="service_type" required <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-pricing-service-select>
                            <option value="Document Printing" selected>Document Printing</option>
                            <?php foreach ($pricing_service_types as $type): ?>
                                <option value="<?php echo e($type); ?>"><?php echo e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="pricing_size_name">Size</label>
                        <input id="pricing_size_name" type="text" name="size_name" list="paper-size-options" maxlength="150"
                            placeholder="e.g., A4, 4R Glossy, Custom Size" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>
                    <div class="field">
                        <label for="pricing_basis">Charged By</label>
                        <select id="pricing_basis" name="pricing_basis" required <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-pricing-basis>
                            <option value="per page" selected>per page</option>
                            <option value="per piece">per piece</option>
                            <option value="per sheet">per sheet</option>
                            <option value="per sq ft">per sq ft</option>
                            <option value="per set">per set</option>
                            <option value="flat rate">flat rate</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="pricing_variant">Type</label>
                        <input id="pricing_variant" type="text" name="variant" list="print-type-options" maxlength="150"
                            placeholder="e.g., Colored, Matte, Glossy" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>
                    <div class="field">
                        <label for="pricing_price">Price (&#8369;)</label>
                        <input id="pricing_price" type="number" step="0.01" min="0.01" name="price"
                            placeholder="0.00" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>

                    <div class="pricing-advanced-fields">
                        <span>Optional details</span>
                        <div class="pricing-advanced-grid">
                            <label>
                                <span>Width</span>
                                <input type="number" step="0.01" min="0" name="width" placeholder="Optional" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                            </label>
                            <label>
                                <span>Height</span>
                                <input type="number" step="0.01" min="0" name="height" placeholder="Optional" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                            </label>
                            <label>
                                <span>Unit</span>
                                <select name="dimension_unit" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                                    <option value="">Not applicable</option>
                                    <option value="inch">inch</option>
                                    <option value="cm">cm</option>
                                    <option value="foot">foot</option>
                                </select>
                            </label>
                            <label>
                                <span>Minimum Qty</span>
                                <input type="number" min="0" step="1" name="min_quantity" placeholder="Optional" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                            </label>
                            <label>
                                <span>Maximum Qty</span>
                                <input type="number" min="0" step="1" name="max_quantity" placeholder="Optional" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                            </label>
                        </div>
                    </div>

                    <div class="pricing-modal-actions">
                        <button type="button" class="btn btn-soft" data-close-pricing-modal>Cancel</button>
                        <button type="submit" name="add_service" value="1" class="btn btn-primary pricing-submit" <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-pricing-submit>
                            <?php echo ownerIcon('plus', 'icon'); ?>Add Price
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <section class="ssp-table-panel unified-directory-panel" aria-label="Manage service prices" data-live-region="owner-service-pricing-directory">
            <div class="ssp-panel-heading ssp-table-heading">
                <div>
                    <h3><?php echo ownerIcon('settings', 'icon-sm'); ?>Service Price List</h3>
                    <p>Document Printing appears first, followed by the services selected in your shop profile.</p>
                </div>
                <?php if ($pricing_total_items > 0): ?>
                    <span class="ssp-count-pill"><?php echo (int) $pricing_total_items; ?> item<?php echo $pricing_total_items === 1 ? '' : 's'; ?></span>
                <?php endif; ?>
            </div>

            <div class="ssp-filter-row unified-filter-row">
                <label>
                    <span>Filter by service</span>
                    <select id="pricingTypeFilter">
                        <option value="">All services</option>
                        <option value="document printing">Document Printing (<?php echo (int) $total_services; ?>)</option>
                        <?php foreach (array_keys($ssp_groups) as $filter_type): ?>
                            <option value="<?php echo e(strtolower($filter_type)); ?>"><?php echo e($filter_type); ?> (<?php echo $ssp_type_count[$filter_type]; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Search</span>
                    <input type="search" id="pricingSearchFilter" placeholder="Search service, size, variant, charged by">
                </label>
            </div>

            <?php if ($pricing_total_items === 0): ?>
                <div class="ssp-empty-state">
                    <span><?php echo ownerIcon('package', 'icon'); ?></span>
                    <h3>No pricing yet</h3>
                    <p>Add your first Document Printing price to make your shop order-ready.</p>
                </div>
            <?php else: ?>
                <div class="unified-pricing-directory">
                    <?php if (!empty($services)): ?>
                        <section class="unified-price-group" data-price-group data-price-service="document printing">
                            <div class="unified-price-group-head">
                                <h3><?php echo ownerIcon('file-text', 'icon-sm'); ?>Document Printing</h3>
                                <span><?php echo (int) $total_services; ?> Price<?php echo $total_services === 1 ? '' : 's'; ?></span>
                            </div>
                            <div class="ssp-table-wrap">
                                <table class="ssp-pricing-table unified-pricing-table">
                                     <thead>
                                        <tr>
                                            <th scope="col">Size</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Charged By</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service): ?>
                                            <?php
                                            $paper_label = trim((string) ($service['paper_size'] ?? ''));
                                            $paper_type_label = trim((string) ($service['paper_type'] ?? ''));
                                            $print_type_label = trim((string) ($service['print_type'] ?? ''));
                                            $document_search = strtolower(trim($paper_label . ' ' . $print_type_label));
                                            $document_available = !isset($service['is_available']) || (int) $service['is_available'] === 1;
                                            ?>
                                            <tr data-price-row data-pricing-display-row
                                                data-price-type="document printing"
                                                data-price-label="<?php echo e($document_search); ?>">
                                                <td data-label="Size">
                                                    <strong class="ssp-option-name"><?php echo e($paper_label); ?></strong>
                                                </td>
                                                <td data-label="Type">
                                                    <span class="ssp-muted"><?php echo e($print_type_label); ?></span>
                                                </td>
                                                <td data-label="Charged By">
                                                    <span class="ssp-unit-badge">Per Page</span>
                                                </td>
                                                <td data-label="Price">
                                                    <strong class="ssp-price-value"><?php echo ownerMoney($service['price_per_page']); ?></strong>
                                                    <span class="ssp-price-unit">per page</span>
                                                </td>
                                                <td data-label="Status">
                                                    <span class="ssp-availability <?php echo $document_available ? 'available' : 'unavailable'; ?>">
                                                        <?php echo $document_available ? 'Available' : 'Unavailable'; ?>
                                                    </span>
                                                </td>
                                                <td data-label="Actions">
                                                    <details class="pricing-row-actions">
                                                        <summary aria-label="Manage <?php echo e($paper_label); ?>">
                                                            <?php echo ownerIcon('settings', 'icon-sm'); ?>
                                                            Manage
                                                        </summary>
                                                        <div class="pricing-row-menu">
                                                            <button type="button" data-edit-price-row><?php echo ownerIcon('edit-3', 'icon-sm'); ?>Edit</button>
                                                            <form method="POST" action="../../../backend/actions/toggle_service.php">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                                <button type="submit" name="toggle_service">
                                                                    <?php echo ownerIcon('refresh-cw', 'icon-sm'); ?><?php echo $document_available ? 'Disable' : 'Enable'; ?>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="../../../backend/actions/delete_service.php"
                                                                onsubmit="return confirm('Delete this document price? If it has existing orders, it will be blocked.');">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                                <button type="submit" name="delete_service" class="is-danger">
                                                                    <?php echo ownerIcon('circle-alert', 'icon-sm'); ?>Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                            <tr class="pricing-edit-row" data-pricing-edit-row hidden>
                                                <td colspan="6">
                                                    <form method="POST" action="../../../backend/actions/update_service.php" class="pricing-inline-edit">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                        <label>
                                                            <span>Size Name</span>
                                                            <input type="text" name="paper_size" value="<?php echo e($paper_label); ?>" required>
                                                        </label>
                                                        <label>
                                                            <span>Paper Type</span>
                                                            <input type="text" name="paper_type" value="<?php echo e($paper_type_label); ?>" placeholder="Optional">
                                                        </label>
                                                        <label>
                                                            <span>Type</span>
                                                            <input type="text" name="print_type" value="<?php echo e($print_type_label); ?>" required>
                                                        </label>
                                                        <label>
                                                            <span>Price</span>
                                                            <input type="number" step="0.01" min="0.01" name="price_per_page" value="<?php echo e($service['price_per_page']); ?>" required>
                                                        </label>
                                                        <div class="pricing-inline-actions">
                                                            <button type="submit" name="update_service" class="btn btn-primary"><?php echo ownerIcon('save', 'icon-sm'); ?>Save</button>
                                                            <button type="button" class="btn btn-soft" data-cancel-edit>Cancel</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="pricing-filter-empty" data-price-group-empty hidden>No Document Printing prices match these filters.</p>
                        </section>
                    <?php else: ?>
                        <section class="unified-price-group" data-price-group data-price-service="document printing">
                            <div class="ssp-empty-state unified-document-empty">
                                <span><?php echo ownerIcon('file-text', 'icon'); ?></span>
                                <h3>Document Printing needs a price</h3>
                                <p>Add at least one size, variant, charged-by value, and price above.</p>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php foreach ($ssp_groups as $service_type => $type_entries): ?>
                        <section class="unified-price-group" data-price-group data-price-service="<?php echo e(strtolower($service_type)); ?>">
                            <div class="unified-price-group-head">
                                <h3><?php echo ownerIcon('package', 'icon-sm'); ?><?php echo e($service_type); ?></h3>
                                <span><?php echo count($type_entries); ?> Price<?php echo count($type_entries) === 1 ? '' : 's'; ?></span>
                            </div>
                            <div class="ssp-table-wrap">
                                 <table class="ssp-pricing-table unified-pricing-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Size/Variant</th>
                                            <th scope="col">Charged By</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($type_entries as $entry): ?>
                                            <tr data-price-row data-pricing-display-row
                                                data-price-type="<?php echo e(strtolower($entry['service_type'])); ?>"
                                                data-price-label="<?php echo e(strtolower($entry['option_label'])); ?>">
                                                <td data-label="Size/Variant">
                                                    <strong class="ssp-option-name"><?php echo e($entry['option_label']); ?></strong>
                                                </td>
                                                <td data-label="Charged By">
                                                    <?php if (!empty($entry['unit'])): ?>
                                                        <span class="ssp-unit-badge"><?php echo e(ucwords($entry['unit'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="ssp-muted">No basis</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Price">
                                                    <strong class="ssp-price-value"><?php echo ownerMoney($entry['price']); ?></strong>
                                                    <span class="ssp-price-unit"><?php echo !empty($entry['unit']) ? e($entry['unit']) : 'no basis'; ?></span>
                                                </td>
                                                <td data-label="Status">
                                                    <span class="ssp-availability <?php echo $entry['is_available'] ? 'available' : 'unavailable'; ?>">
                                                        <?php echo $entry['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                    </span>
                                                </td>
                                                <td data-label="Actions">
                                                    <details class="pricing-row-actions">
                                                        <summary aria-label="Manage <?php echo e($entry['option_label']); ?>">
                                                            <?php echo ownerIcon('settings', 'icon-sm'); ?>
                                                            Manage
                                                        </summary>
                                                        <div class="pricing-row-menu">
                                                            <button type="button" data-edit-price-row><?php echo ownerIcon('edit-3', 'icon-sm'); ?>Edit</button>
                                                            <form method="POST" action="../../../backend/actions/toggle_service_pricing.php">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="pricing_id" value="<?php echo (int) $entry['id']; ?>">
                                                                <button type="submit" name="toggle_service_pricing">
                                                                    <?php echo ownerIcon('refresh-cw', 'icon-sm'); ?><?php echo $entry['is_available'] ? 'Disable' : 'Enable'; ?>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="../../../backend/actions/delete_service_pricing.php"
                                                                onsubmit="return confirm('Delete this service price? This cannot be undone.');">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="pricing_id" value="<?php echo (int) $entry['id']; ?>">
                                                                <button type="submit" name="delete_service_pricing" class="is-danger">
                                                                    <?php echo ownerIcon('circle-alert', 'icon-sm'); ?>Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                            <tr class="pricing-edit-row" data-pricing-edit-row hidden>
                                                <td colspan="5">
                                                    <form method="POST" action="../../../backend/actions/update_service_pricing.php" class="pricing-inline-edit">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="pricing_id" value="<?php echo (int) $entry['id']; ?>">
                                                        <label>
                                                            <span>Size / Variant</span>
                                                            <input type="text" name="option_label" maxlength="150" value="<?php echo e($entry['option_label']); ?>" required>
                                                        </label>
                                                        <label>
                                                            <span>Charged By</span>
                                                            <select name="unit">
                                                                <option value="" <?php echo empty($entry['unit']) ? 'selected' : ''; ?>>No basis</option>
                                                                <?php foreach (['per page', 'per piece', 'per sheet', 'per sq ft', 'per set', 'flat rate'] as $unit_option): ?>
                                                                    <option value="<?php echo e($unit_option); ?>" <?php echo ($entry['unit'] ?? '') === $unit_option ? 'selected' : ''; ?>><?php echo e($unit_option); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>
                                                            <span>Price</span>
                                                            <input type="number" step="0.01" min="0.01" name="price" value="<?php echo e($entry['price']); ?>" required>
                                                        </label>
                                                        <div class="pricing-inline-actions">
                                                            <button type="submit" name="update_service_pricing" class="btn btn-primary"><?php echo ownerIcon('save', 'icon-sm'); ?>Save</button>
                                                            <button type="button" class="btn btn-soft" data-cancel-edit>Cancel</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="pricing-filter-empty" data-price-group-empty hidden>No <?php echo e($service_type); ?> prices match these filters.</p>
                        </section>
                    <?php endforeach; ?>
                </div>
                <p class="pricing-filter-empty" data-pricing-empty hidden>No prices match these filters.</p>
            <?php endif; ?>
        </section>
    </div>
</section>

<script>
(function () {
    document.addEventListener('click', function (event) {
        var editButton = event.target.closest('[data-edit-price-row]');
        if (editButton) {
            var displayRow = editButton.closest('[data-pricing-display-row]');
            var editRow = displayRow ? displayRow.nextElementSibling : null;
            if (editRow && editRow.matches('[data-pricing-edit-row]')) {
                editRow.hidden = false;
                displayRow.classList.add('is-editing');
                var menu = editButton.closest('details');
                if (menu) menu.open = false;
                var firstInput = editRow.querySelector('input:not([type="hidden"]), select');
                if (firstInput) firstInput.focus();
            }
            return;
        }

        var cancelButton = event.target.closest('[data-cancel-edit]');
        if (cancelButton) {
            var row = cancelButton.closest('[data-pricing-edit-row]');
            var previous = row ? row.previousElementSibling : null;
            if (row) row.hidden = true;
            if (previous) previous.classList.remove('is-editing');
        }
    });
})();
</script>

<script>
(function () {
    var modal = document.querySelector('[data-pricing-modal]');
    var openButton = document.querySelector('[data-open-pricing-modal]');
    var form = document.querySelector('[data-unified-pricing-form]');
    if (!form || !modal) return;

    var serviceSelect = form.querySelector('[data-pricing-service-select]');
    var submit = form.querySelector('[data-pricing-submit]');
    var basis = form.querySelector('[data-pricing-basis]');
    var documentAction = '../../../backend/actions/add_service.php';
    var otherAction = '../../../backend/actions/add_service_pricing.php';
    var lastFocused = null;

    function openModal() {
        lastFocused = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('pricing-modal-open');
        syncFormMode();
        var firstField = form.querySelector('select, input:not([type="hidden"])');
        if (firstField) firstField.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('pricing-modal-open');
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function syncFormMode() {
        var isDocument = !serviceSelect || serviceSelect.value === 'Document Printing';
        form.action = isDocument ? documentAction : otherAction;
        if (submit) {
            submit.name = isDocument ? 'add_service' : 'add_service_pricing';
            submit.value = '1';
        }
        if (basis && isDocument) {
            basis.value = 'per page';
        }
    }

    if (openButton) openButton.addEventListener('click', openModal);
    modal.addEventListener('click', function (event) {
        if (event.target.closest('[data-close-pricing-modal]')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
    if (serviceSelect) serviceSelect.addEventListener('change', syncFormMode);
    syncFormMode();
})();
</script>

<script>
(function () {
    var typeFilter = document.getElementById('pricingTypeFilter');
    var searchFilter = document.getElementById('pricingSearchFilter');
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-price-group]'));
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-price-row]'));
    var allEmpty = document.querySelector('[data-pricing-empty]');

    function applyPricingFilters() {
        var typeVal = typeFilter ? typeFilter.value.trim().toLowerCase() : '';
        var searchVal = searchFilter ? searchFilter.value.trim().toLowerCase() : '';
        var totalVisible = 0;

        groups.forEach(function (group) {
            var groupType = (group.dataset.priceService || '').toLowerCase();
            var groupRows = Array.prototype.slice.call(group.querySelectorAll('[data-price-row]'));
            var groupEmpty = group.querySelector('[data-price-group-empty]');
            var groupTypeMatches = !typeVal || groupType === typeVal;
            var visibleInGroup = 0;

            if (!groupRows.length) {
                group.hidden = !groupTypeMatches;
                return;
            }

            groupRows.forEach(function (row) {
                var rowType = (row.dataset.priceType || '').toLowerCase();
                var rowLabel = (row.dataset.priceLabel || '').toLowerCase();
                var matchesType = !typeVal || rowType === typeVal;
                var matchesSearch = !searchVal || rowLabel.indexOf(searchVal) !== -1;
                var visible = matchesType && matchesSearch;
                row.hidden = !visible;
                if (visible) {
                    visibleInGroup += 1;
                    totalVisible += 1;
                }
            });

            group.hidden = !groupTypeMatches || visibleInGroup === 0;
            if (groupEmpty) groupEmpty.hidden = visibleInGroup !== 0 || !groupTypeMatches;
        });

        if (allEmpty) allEmpty.hidden = totalVisible !== 0;
    }

    if (typeFilter) typeFilter.addEventListener('change', applyPricingFilters);
    if (searchFilter) searchFilter.addEventListener('input', applyPricingFilters);
})();
</script>

<?php ownerLayoutEnd(); ?>
