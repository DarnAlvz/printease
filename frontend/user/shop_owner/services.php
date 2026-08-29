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

$active_service_types = [
    'Document Printing',
    'Lamination',
    'Photo Printing',
    'Tarpaulin Printing',
    'ID Printing',
    'Invitation / Card Printing',
];
$future_service_types = [
    'Photocopy',
    'Binding',
    'Scanning',
];
$allowed_service_types = array_merge($active_service_types, $future_service_types);

$shop_service_types = ['Document Printing'];
$online_service_types = ['Document Printing'];
$st_stmt = mysqli_prepare($conn, "SELECT service_type, online_available FROM shop_service_types WHERE shop_id = ? AND service_offered = 1 ORDER BY service_type ASC");
if ($st_stmt) {
    mysqli_stmt_bind_param($st_stmt, "i", $shop_id);
    mysqli_stmt_execute($st_stmt);
    $st_result = mysqli_stmt_get_result($st_stmt);
    while ($st_row = mysqli_fetch_assoc($st_result)) {
        $service_type = trim((string) ($st_row['service_type'] ?? ''));
        if ($service_type !== '') {
            $shop_service_types[] = $service_type;
            if ((int) ($st_row['online_available'] ?? 1) === 1) {
                $online_service_types[] = $service_type;
            }
        }
    }
}
$shop_service_types = array_values(array_unique($shop_service_types));
$online_service_types = array_values(array_unique($online_service_types));
$pricing_service_types = array_values(array_filter($active_service_types, function ($type) use ($online_service_types) {
    return $type !== 'Document Printing' && in_array($type, $online_service_types, true);
}));

$ssp_sql = "SELECT * FROM shop_service_pricing WHERE shop_id = ? ORDER BY service_type ASC, option_size ASC, option_label ASC, unit ASC";
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
                <h2><?php echo ownerIcon('package', 'icon'); ?></h2>
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

        <section class="ssp-form-panel unified-add-panel pricing-add-compact" aria-label="Add service pricing">
            <div class="ssp-panel-heading pricing-add-heading">
                <div>
                    <h3><?php echo ownerIcon('plus', 'icon-sm'); ?>Add Service Pricing</h3>
                    <p>Document Printing is live. Add Lamination, Photo Printing, Tarpaulin Printing, ID Printing, or Invitation / Card Printing pricing when your shop is ready to offer them.</p>
                </div>
                <button type="button" class="btn btn-primary pricing-modal-trigger" <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-open-pricing-modal
                    title="<?php echo $owner_is_verified ? 'Add a new service price' : 'Shop must be verified before adding prices.'; ?>">
                    <?php echo ownerIcon('plus', 'icon'); ?>Add Service Pricing
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
                    Document Printing is ready. Select additional supported services in Shop Management to add more prices.
                </div>
            <?php endif; ?>
        </section>

        <div class="pricing-modal-shell" data-pricing-modal hidden>
            <div class="pricing-modal-backdrop" data-close-pricing-modal></div>
            <section class="pricing-modal owner-card" role="dialog" aria-modal="true" aria-labelledby="pricingModalTitle">
                <div class="pricing-modal-header">
                    <div>
                        <span class="ssp-eyebrow">Generic Pricing</span>
                        <h3 id="pricingModalTitle"><?php echo ownerIcon('plus', 'icon-sm'); ?>Add Service Pricing</h3>
                        <p>For Lamination, use Size and Price. For detailed services, use Size, Paper Type or Material, Print Type, and Price.</p>
                    </div>
                    <button type="button" class="pricing-modal-close" aria-label="Close add price modal" title="Close add price modal" data-close-pricing-modal>
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
                            placeholder="e.g., A4, Short, Long, Letter" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>
                    <div class="field" data-pricing-paper-type-field>
                        <label for="pricing_paper_type">Paper Type</label>
                        <input id="pricing_paper_type" type="text" name="paper_type" list="paper-type-options" maxlength="150"
                            placeholder="e.g., Bond Paper, Colored Paper, Glossy" required <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-pricing-paper-type>
                    </div>
                    <div class="field" data-pricing-basis-field>
                        <label for="pricing_basis">Charged By</label>
                        <strong id="pricing_basis" class="pricing-fixed-value">per page</strong>
                        <input type="hidden" name="pricing_basis" value="per page" data-pricing-basis>
                    </div>
                    <div class="field" data-pricing-variant-field>
                        <label for="pricing_variant">Print Type</label>
                        <input id="pricing_variant" type="text" name="variant" list="print-type-options" maxlength="150"
                            placeholder="e.g., Black & White, Colored" <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>
                    <div class="field">
                        <label for="pricing_price">Price (&#8369;)</label>
                        <input id="pricing_price" type="number" step="0.01" min="0.01" name="price"
                            placeholder="0.00" required <?php echo $owner_is_verified ? '' : 'disabled'; ?>>
                    </div>

                    <div class="pricing-modal-actions">
                        <button type="button" class="btn btn-soft" data-close-pricing-modal>Cancel</button>
                        <button type="submit" name="add_service" value="1" class="btn btn-primary pricing-submit" <?php echo $owner_is_verified ? '' : 'disabled'; ?> data-pricing-submit>
                            <?php echo ownerIcon('plus', 'icon'); ?>Add Service Pricing
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div class="pricing-modal-shell" data-edit-pricing-modal hidden>
            <div class="pricing-modal-backdrop" data-close-edit-pricing-modal></div>
            <section class="pricing-modal owner-card" role="dialog" aria-modal="true" aria-labelledby="editPricingModalTitle">
                <div class="pricing-modal-header">
                    <div>
                        <span class="ssp-eyebrow">Service Pricing</span>
                        <h3 id="editPricingModalTitle"><?php echo ownerIcon('edit-3', 'icon-sm'); ?>Edit Service Pricing</h3>
                        <p>Update this price record without leaving the price list.</p>
                    </div>
                    <button type="button" class="pricing-modal-close" aria-label="Close edit price modal" title="Close edit price modal" data-close-edit-pricing-modal>
                        <?php echo ownerIcon('x', 'icon'); ?>
                    </button>
                </div>

                <form action="../../../backend/actions/update_service.php" method="POST" class="pricing-form-grid pricing-modal-form" data-edit-pricing-form>
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="service_id" data-edit-document-id>
                    <input type="hidden" name="pricing_id" data-edit-service-id disabled>

                    <div class="field" data-edit-document-field>
                        <label for="edit_pricing_size">Size Name</label>
                        <input id="edit_pricing_size" type="text" name="paper_size" maxlength="150" required data-edit-size>
                    </div>
                    <div class="field" data-edit-document-field>
                        <label for="edit_pricing_paper_type">Paper Type</label>
                        <input id="edit_pricing_paper_type" type="text" name="paper_type" list="paper-type-options" maxlength="150" placeholder="e.g., Bond Paper, Colored Paper, Glossy" data-edit-paper-type>
                    </div>
                    <div class="field" data-edit-document-field>
                        <label for="edit_pricing_print_type">Print Type</label>
                        <input id="edit_pricing_print_type" type="text" name="print_type" list="print-type-options" maxlength="150" required data-edit-print-type>
                    </div>
                    <div class="field" data-edit-service-field hidden>
                        <label for="edit_pricing_option_size" data-edit-option-size-label>Size</label>
                        <input id="edit_pricing_option_size" type="text" name="option_size" maxlength="50" disabled data-edit-option-size>
                    </div>
                    <div class="field" data-edit-service-field hidden>
                        <label for="edit_pricing_option_label" data-edit-option-label-label>Size</label>
                        <input id="edit_pricing_option_label" type="text" name="option_label" maxlength="150" required disabled data-edit-option-label>
                    </div>
                    <div class="field" data-edit-service-unit-field hidden>
                        <label for="edit_pricing_unit">Print Type</label>
                        <input id="edit_pricing_unit" type="text" name="unit" list="print-type-options" maxlength="150" disabled data-edit-unit>
                    </div>
                    <div class="field" data-edit-document-field>
                        <label for="edit_pricing_document_price">Price (&#8369;)</label>
                        <input id="edit_pricing_document_price" type="number" step="0.01" min="0.01" name="price_per_page" required data-edit-document-price>
                    </div>
                    <div class="field" data-edit-service-field hidden>
                        <label for="edit_pricing_service_price">Price (&#8369;)</label>
                        <input id="edit_pricing_service_price" type="number" step="0.01" min="0.01" name="price" required disabled data-edit-service-price>
                    </div>

                    <div class="pricing-modal-actions">
                        <button type="button" class="btn btn-soft" data-close-edit-pricing-modal>Cancel</button>
                        <button type="submit" name="update_service" value="1" class="btn btn-primary pricing-submit" data-edit-pricing-submit>
                            <?php echo ownerIcon('save', 'icon'); ?>Save Changes
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <section class="ssp-table-panel unified-directory-panel" aria-label="Manage service prices" data-live-region="owner-service-pricing-directory">
            <div class="ssp-panel-heading ssp-table-heading">
                <div>
                    <h3><?php echo ownerIcon('settings', 'icon-sm'); ?>Service Price List</h3>
                    <p>Document Printing appears first. Other supported services appear after you select them and add pricing.</p>
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
                    <input type="search" id="pricingSearchFilter" placeholder="Search service, size, paper type, print type">
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
                                <table class="ssp-pricing-table unified-pricing-table document-pricing-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Size</th>
                                            <th scope="col">Paper Type</th>
                                            <th scope="col">Print Type</th>
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
                                            $document_search = strtolower(trim($paper_label . ' ' . $paper_type_label . ' ' . $print_type_label));
                                            $document_available = !isset($service['is_available']) || (int) $service['is_available'] === 1;
                                            ?>
                                            <tr data-price-row data-pricing-display-row
                                                data-price-type="document printing"
                                                data-price-label="<?php echo e($document_search); ?>">
                                                <td data-label="Size">
                                                    <strong class="ssp-option-name"><?php echo e($paper_label); ?></strong>
                                                </td>
                                                <td data-label="Paper Type">
                                                    <strong class="ssp-option-name"><?php echo e($paper_type_label !== '' ? $paper_type_label : 'No paper type'); ?></strong>
                                                </td>
                                                <td data-label="Print Type">
                                                    <span class="ssp-muted"><?php echo e($print_type_label !== '' ? $print_type_label : 'No print type'); ?></span>
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
                                                        <summary aria-label="Manage <?php echo e($paper_label); ?>" title="Manage this document price">
                                                            <?php echo ownerIcon('settings', 'icon-sm'); ?>
                                                            Manage
                                                        </summary>
                                                        <div class="pricing-row-menu">
                                                            <button type="button" data-edit-price-row title="Edit this price"
                                                                data-edit-mode="document"
                                                                data-record-id="<?php echo (int) $service['service_id']; ?>"
                                                                data-size="<?php echo e($paper_label); ?>"
                                                                data-paper-type="<?php echo e($paper_type_label); ?>"
                                                                data-print-type="<?php echo e($print_type_label); ?>"
                                                                data-price="<?php echo e($service['price_per_page']); ?>"><?php echo ownerIcon('edit-3', 'icon-sm'); ?>Edit</button>
                                                            <form method="POST" action="../../../backend/actions/toggle_service.php">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                                <button type="submit" name="toggle_service" title="<?php echo $document_available ? 'Disable this price' : 'Enable this price'; ?>">
                                                                    <?php echo ownerIcon('refresh-cw', 'icon-sm'); ?><?php echo $document_available ? 'Disable' : 'Enable'; ?>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="../../../backend/actions/delete_service.php"
                                                                data-confirm-title="Are you sure you want to delete it?"
                                                                data-confirm-message="If it has existing orders, the deletion will be blocked." nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="delete_service" value="1">
                                                                <input type="hidden" name="service_id" value="<?php echo (int) $service['service_id']; ?>">
                                                                <button type="submit" name="delete_service" class="is-danger" title="Delete this price record">
                                                                    <?php echo ownerIcon('circle-alert', 'icon-sm'); ?>Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </details>
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
                                <p>Add at least one size, paper type, print type, and price above.</p>
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
                                <table class="ssp-pricing-table unified-pricing-table <?php echo $service_type === 'Lamination' ? 'lamination-pricing-table' : ''; ?>">
                                    <thead>
                                        <tr>
                                            <th scope="col">Size</th>
                                            <?php if (in_array($service_type, ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)): ?>
                                                <th scope="col"><?php echo in_array($service_type, ['Tarpaulin Printing', 'ID Printing'], true) ? 'Material' : 'Paper Type'; ?></th>
                                                <th scope="col">Print Type</th>
                                            <?php endif; ?>
                                            <th scope="col">Price</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($type_entries as $entry): ?>
                                            <?php
                                            $uses_detailed_options = in_array($entry['service_type'], ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true);
                                            $entry_size = $uses_detailed_options
                                                ? trim((string) ($entry['option_size'] ?? ''))
                                                : trim((string) ($entry['option_label'] ?? ''));
                                            $entry_detail_label = trim((string) ($entry['option_label'] ?? ''));
                                            $entry_print_type = trim((string) ($entry['unit'] ?? ''));
                                            ?>
                                            <tr data-price-row data-pricing-display-row
                                                data-price-type="<?php echo e(strtolower($entry['service_type'])); ?>"
                                                data-price-label="<?php echo e(strtolower(trim($entry_size . ' ' . $entry_detail_label . ' ' . $entry_print_type))); ?>">
                                                <td data-label="Size">
                                                    <strong class="ssp-option-name"><?php echo e($entry_size !== '' ? $entry_size : $entry['option_label']); ?></strong>
                                                </td>
                                                <?php if ($uses_detailed_options): ?>
                                                    <td data-label="<?php echo in_array($service_type, ['Tarpaulin Printing', 'ID Printing'], true) ? 'Material' : 'Paper Type'; ?>">
                                                        <strong class="ssp-option-name"><?php echo e($entry_detail_label !== '' ? $entry_detail_label : (in_array($service_type, ['Tarpaulin Printing', 'ID Printing'], true) ? 'No material' : 'No paper type')); ?></strong>
                                                    </td>
                                                    <td data-label="Print Type">
                                                        <span class="ssp-muted"><?php echo e($entry_print_type !== '' ? $entry_print_type : 'No print type'); ?></span>
                                                    </td>
                                                <?php endif; ?>
                                                <td data-label="Price">
                                                    <strong class="ssp-price-value"><?php echo ownerMoney($entry['price']); ?></strong>
                                                    <?php if (in_array($service_type, ['Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)): ?>
                                                        <span class="ssp-price-unit">per piece</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Status">
                                                    <span class="ssp-availability <?php echo $entry['is_available'] ? 'available' : 'unavailable'; ?>">
                                                        <?php echo $entry['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                    </span>
                                                </td>
                                                <td data-label="Actions">
                                                    <details class="pricing-row-actions">
                                                        <summary aria-label="Manage <?php echo e($entry['option_label']); ?>" title="Manage this service price">
                                                            <?php echo ownerIcon('settings', 'icon-sm'); ?>
                                                            Manage
                                                        </summary>
                                                        <div class="pricing-row-menu">
                                                            <button type="button" data-edit-price-row title="Edit this price"
                                                                data-edit-mode="service"
                                                                data-service-type="<?php echo e($entry['service_type']); ?>"
                                                                data-record-id="<?php echo (int) $entry['id']; ?>"
                                                                data-option-size="<?php echo e($entry['option_size'] ?? ''); ?>"
                                                                data-option-label="<?php echo e($entry['option_label']); ?>"
                                                                data-unit="<?php echo e($entry['unit'] ?? ''); ?>"
                                                                data-price="<?php echo e($entry['price']); ?>"><?php echo ownerIcon('edit-3', 'icon-sm'); ?>Edit</button>
                                                            <form method="POST" action="../../../backend/actions/toggle_service_pricing.php">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="pricing_id" value="<?php echo (int) $entry['id']; ?>">
                                                                <button type="submit" name="toggle_service_pricing" title="<?php echo $entry['is_available'] ? 'Disable this price' : 'Enable this price'; ?>">
                                                                    <?php echo ownerIcon('refresh-cw', 'icon-sm'); ?><?php echo $entry['is_available'] ? 'Disable' : 'Enable'; ?>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="../../../backend/actions/delete_service_pricing.php"
                                                                data-confirm-title="Are you sure you want to delete it?"
                                                                data-confirm-message="This cannot be undone. You can re-add this price later if needed." nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="delete_service_pricing" value="1">
                                                                <input type="hidden" name="pricing_id" value="<?php echo (int) $entry['id']; ?>">
                                                                <button type="submit" name="delete_service_pricing" class="is-danger" title="Delete this price record">
                                                                    <?php echo ownerIcon('circle-alert', 'icon-sm'); ?>Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </details>
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

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        var modal = document.querySelector('[data-edit-pricing-modal]');
        var form = document.querySelector('[data-edit-pricing-form]');
        if (!modal || !form) return;

        var documentAction = '../../../backend/actions/update_service.php';
        var serviceAction = '../../../backend/actions/update_service_pricing.php';
        var documentId = form.querySelector('[data-edit-document-id]');
        var serviceId = form.querySelector('[data-edit-service-id]');
        var documentFields = Array.prototype.slice.call(form.querySelectorAll('[data-edit-document-field]'));
        var serviceFields = Array.prototype.slice.call(form.querySelectorAll('[data-edit-service-field]'));
        var sizeInput = form.querySelector('[data-edit-size]');
        var paperTypeInput = form.querySelector('[data-edit-paper-type]');
        var printTypeInput = form.querySelector('[data-edit-print-type]');
        var documentPriceInput = form.querySelector('[data-edit-document-price]');
        var optionSizeInput = form.querySelector('[data-edit-option-size]');
        var optionSizeLabel = form.querySelector('[data-edit-option-size-label]');
        var optionLabelInput = form.querySelector('[data-edit-option-label]');
        var optionLabelLabel = form.querySelector('[data-edit-option-label-label]');
        var unitField = form.querySelector('[data-edit-service-unit-field]');
        var unitInput = form.querySelector('[data-edit-unit]');
        var servicePriceInput = form.querySelector('[data-edit-service-price]');
        var submit = form.querySelector('[data-edit-pricing-submit]');
        var lastFocused = null;

        function setGroupEnabled(fields, enabled) {
            fields.forEach(function(field) {
                field.hidden = !enabled;
                Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function(input) {
                    input.disabled = !enabled;
                });
            });
        }

        function openModal(button) {
            var mode = button.dataset.editMode === 'service' ? 'service' : 'document';
            var isDocument = mode === 'document';
            var serviceType = button.dataset.serviceType || '';
            var isDetailedService = serviceType === 'Photo Printing' || serviceType === 'Tarpaulin Printing' || serviceType === 'ID Printing' || serviceType === 'Invitation / Card Printing';
            var usesMaterialLabel = serviceType === 'Tarpaulin Printing' || serviceType === 'ID Printing';

            lastFocused = document.activeElement;
            form.action = isDocument ? documentAction : serviceAction;
            if (submit) {
                submit.name = isDocument ? 'update_service' : 'update_service_pricing';
                submit.value = '1';
            }

            if (documentId) {
                documentId.value = isDocument ? (button.dataset.recordId || '') : '';
                documentId.disabled = !isDocument;
            }
            if (serviceId) {
                serviceId.value = isDocument ? '' : (button.dataset.recordId || '');
                serviceId.disabled = isDocument;
            }

            setGroupEnabled(documentFields, isDocument);
            setGroupEnabled(serviceFields, !isDocument);
            if (optionSizeInput) {
                optionSizeInput.value = isDetailedService ? (button.dataset.optionSize || '') : '';
                optionSizeInput.required = isDetailedService;
                optionSizeInput.disabled = !isDetailedService;
                var optionSizeField = optionSizeInput.closest('.field');
                if (optionSizeField) optionSizeField.hidden = !isDetailedService;
            }
            if (optionSizeLabel) {
                optionSizeLabel.textContent = 'Size';
            }
            if (optionLabelLabel) {
                optionLabelLabel.textContent = isDetailedService ? (usesMaterialLabel ? 'Material' : 'Paper Type') : 'Size';
            }
            if (unitField) {
                unitField.hidden = !isDetailedService;
            }
            if (unitInput) {
                unitInput.value = isDetailedService ? (button.dataset.unit || '') : '';
                unitInput.required = isDetailedService;
                unitInput.disabled = !isDetailedService;
            }

            if (isDocument) {
                if (sizeInput) sizeInput.value = button.dataset.size || '';
                if (paperTypeInput) paperTypeInput.value = button.dataset.paperType || '';
                if (printTypeInput) printTypeInput.value = button.dataset.printType || '';
                if (documentPriceInput) documentPriceInput.value = button.dataset.price || '';
            } else {
                if (optionLabelInput) optionLabelInput.value = button.dataset.optionLabel || '';
                if (servicePriceInput) servicePriceInput.value = button.dataset.price || '';
            }

            modal.hidden = false;
            document.body.classList.add('pricing-modal-open');
            var firstField = form.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled])');
            if (firstField) firstField.focus();
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('pricing-modal-open');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        document.addEventListener('click', function(event) {
            var editButton = event.target.closest('[data-edit-price-row]');
            if (editButton) {
                var menu = editButton.closest('details');
                if (menu) menu.open = false;
                openModal(editButton);
                return;
            }

            if (event.target.closest('[data-close-edit-pricing-modal]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    })();
</script>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        var modal = document.querySelector('[data-pricing-modal]');
        var openButton = document.querySelector('[data-open-pricing-modal]');
        var form = document.querySelector('[data-unified-pricing-form]');
        if (!form || !modal) return;

        var serviceSelect = form.querySelector('[data-pricing-service-select]');
        var submit = form.querySelector('[data-pricing-submit]');
        var sizeInput = form.querySelector('#pricing_size_name');
        var basisField = form.querySelector('[data-pricing-basis-field]');
        var basis = form.querySelector('[data-pricing-basis]');
        var paperTypeField = form.querySelector('[data-pricing-paper-type-field]');
        var paperTypeInput = form.querySelector('[data-pricing-paper-type]');
        var variantField = form.querySelector('[data-pricing-variant-field]');
        var variantInput = form.querySelector('#pricing_variant');
        var documentAction = '../../../backend/actions/add_service.php';
        var otherAction = '../../../backend/actions/add_service_pricing.php';
        var canEditPricing = form.dataset.ownerVerified === '1';
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
            var isPhotoPrinting = serviceSelect && serviceSelect.value === 'Photo Printing';
            var isTarpaulinPrinting = serviceSelect && serviceSelect.value === 'Tarpaulin Printing';
            var isIdPrinting = serviceSelect && serviceSelect.value === 'ID Printing';
            var isInvitationCardPrinting = serviceSelect && serviceSelect.value === 'Invitation / Card Printing';
            var isDetailedService = isPhotoPrinting || isTarpaulinPrinting || isIdPrinting || isInvitationCardPrinting;
            var usesMaterialLabel = isTarpaulinPrinting || isIdPrinting;
            var isPerPieceService = isTarpaulinPrinting || isIdPrinting || isInvitationCardPrinting;
            var isLamination = serviceSelect && serviceSelect.value === 'Lamination';
            form.action = isDocument ? documentAction : otherAction;
            if (submit) {
                submit.name = isDocument ? 'add_service' : 'add_service_pricing';
                submit.value = '1';
            }
            if (basis && isDocument) {
                basis.value = 'per page';
            } else if (basis && isPerPieceService) {
                basis.value = 'per piece';
            }
            if (sizeInput) {
                sizeInput.placeholder = isDocument ? 'e.g., A4, Short, Long, Letter' : (isPhotoPrinting ? 'e.g., 2R, 3R, 4R, A4' : (isTarpaulinPrinting ? 'e.g., 2 x 3 ft, 3 x 4 ft' : (isIdPrinting ? 'e.g., Standard ID (CR80), Paper ID' : (isInvitationCardPrinting ? 'e.g., A6, Business Card, Wedding Invitation' : 'e.g., Badge, A5, A4, A3'))));
            }
            if (basisField) {
                basisField.hidden = !(isDocument || isPerPieceService);
            }
            if (basis) {
                basis.value = isDocument ? 'per page' : (isPerPieceService ? 'per piece' : '');
                basis.disabled = !(isDocument || isPerPieceService);
                var fixedValue = basisField ? basisField.querySelector('.pricing-fixed-value') : null;
                if (fixedValue) fixedValue.textContent = isPerPieceService ? 'per piece' : 'per page';
            }
            if (paperTypeField) {
                paperTypeField.hidden = !(isDocument || isDetailedService);
                var paperTypeLabel = paperTypeField.querySelector('label');
                if (paperTypeLabel) paperTypeLabel.textContent = usesMaterialLabel ? 'Material' : 'Paper Type';
            }
            if (paperTypeInput) {
                paperTypeInput.required = (isDocument || isDetailedService) && canEditPricing;
                paperTypeInput.disabled = !(isDocument || isDetailedService) || !canEditPricing;
                paperTypeInput.placeholder = isPhotoPrinting ? 'e.g., Photo Paper' : (isTarpaulinPrinting ? 'e.g., Standard Tarpaulin' : (isIdPrinting ? 'e.g., PVC Card, Cardstock' : (isInvitationCardPrinting ? 'e.g., Cardstock, Matte Card, Premium Paper' : 'e.g., Bond Paper, Colored Paper, Glossy')));
                if (isLamination) {
                    paperTypeInput.value = '';
                }
            }
            if (variantField) {
                variantField.hidden = !(isDocument || isDetailedService);
            }
            if (variantInput) {
                variantInput.required = (isDocument || isDetailedService) && canEditPricing;
                variantInput.disabled = !(isDocument || isDetailedService) || !canEditPricing;
                variantInput.placeholder = isPhotoPrinting ? 'e.g., Glossy, Matte' : (isTarpaulinPrinting ? 'e.g., Full Color' : (isIdPrinting ? 'e.g., Single-sided Color, Double-sided Color' : (isInvitationCardPrinting ? 'e.g., Full Color, Glossy Color' : 'e.g., Black & White, Colored')));
                if (isLamination) {
                    variantInput.value = '';
                }
            }
        }

        if (openButton) openButton.addEventListener('click', openModal);
        modal.addEventListener('click', function(event) {
            if (event.target.closest('[data-close-pricing-modal]')) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
        if (serviceSelect) serviceSelect.addEventListener('change', syncFormMode);
        syncFormMode();
    })();
</script>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        var typeFilter = document.getElementById('pricingTypeFilter');
        var searchFilter = document.getElementById('pricingSearchFilter');
        var groups = Array.prototype.slice.call(document.querySelectorAll('[data-price-group]'));
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-price-row]'));
        var allEmpty = document.querySelector('[data-pricing-empty]');

        function applyPricingFilters() {
            var typeVal = typeFilter ? typeFilter.value.trim().toLowerCase() : '';
            var searchVal = searchFilter ? searchFilter.value.trim().toLowerCase() : '';
            var totalVisible = 0;

            groups.forEach(function(group) {
                var groupType = (group.dataset.priceService || '').toLowerCase();
                var groupRows = Array.prototype.slice.call(group.querySelectorAll('[data-price-row]'));
                var groupEmpty = group.querySelector('[data-price-group-empty]');
                var groupTypeMatches = !typeVal || groupType === typeVal;
                var visibleInGroup = 0;

                if (!groupRows.length) {
                    group.hidden = !groupTypeMatches;
                    return;
                }

                groupRows.forEach(function(row) {
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

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        var menus = Array.prototype.slice.call(document.querySelectorAll('.pricing-row-actions'));

        function closeAll(except) {
            menus.forEach(function(menu) {
                if (menu !== except) menu.open = false;
            });
        }

        document.addEventListener('click', function(event) {
            var summary = event.target.closest('.pricing-row-actions summary');
            if (summary) {
                closeAll(summary.closest('details'));
                return;
            }
            if (!event.target.closest('.pricing-row-actions')) {
                closeAll();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeAll();
        });
    })();
</script>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        document.addEventListener('submit', function(event) {
            var form = event.target.closest('form[data-confirm-title]');
            if (!form) return;

            if (form.dataset.confirmSubmitting === 'true') return;
            event.preventDefault();

            var options = {
                title: form.dataset.confirmTitle || 'Are you sure?',
                message: form.dataset.confirmMessage || '',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                tone: 'danger'
            };

            var ask = (typeof window.appConfirm === 'function')
                ? window.appConfirm(options)
                : Promise.resolve(window.confirm(form.dataset.confirmMessage || form.dataset.confirmTitle));

            ask.then(function(confirmed) {
                if (!confirmed) return;
                form.dataset.confirmSubmitting = 'true';
                form.submit();
            });
        });
    })();
</script>

<?php ownerLayoutEnd(); ?>