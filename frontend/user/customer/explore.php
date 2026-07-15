<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";

requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

$customer_id = (int) $_SESSION['user_id'];
$view = strtolower(trim((string) ($_GET['view'] ?? 'nearby')));
$view = in_array($view, ['nearby', 'all', 'favorites'], true) ? $view : 'nearby';
$search = $view === 'all' ? trim((string) ($_GET['search'] ?? '')) : '';
$selected_shop_id = isset($_GET['shop_id']) ? (int) $_GET['shop_id'] : 0;

function ensureCustomerFavoriteShopsTable(mysqli $conn): void
{
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS customer_favorite_shops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        shop_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_customer_shop (customer_id, shop_id),
        KEY idx_customer_favorites_customer (customer_id),
        KEY idx_customer_favorites_shop (shop_id)
    )");
}

ensureCustomerFavoriteShopsTable($conn);

function customerExploreUrl(string $view, array $extra = []): string
{
    $query = array_merge(['view' => $view], array_filter($extra, static fn($value) => $value !== '' && $value !== null));
    return 'explore.php?' . http_build_query($query);
}

function customerExploreShortAddress(array $shop): string
{
    $preferred = trim((string) ($shop['display_address'] ?? ''));
    if ($preferred !== '') return $preferred;
    $address_parts = explode(',', (string) ($shop['shop_address'] ?? $shop['address'] ?? ''));
    return trim($address_parts[0] ?? '') ?: 'Location not provided';
}

function customerExploreTimeMinutes(?string $value): ?int
{
    return preg_match('/^(\d{1,2}):(\d{2})/', (string) $value, $matches)
        ? ((int) $matches[1] * 60) + (int) $matches[2]
        : null;
}

function customerExploreScheduleForDay(array $shop, int $day): array
{
    $weekend = in_array($day, [0, 6], true);
    return $weekend
        ? ['open' => $shop['weekend_open_time'] ?? $shop['weekend_open'] ?? '', 'close' => $shop['weekend_close_time'] ?? $shop['weekend_close'] ?? '']
        : ['open' => $shop['weekday_open_time'] ?? $shop['weekday_open'] ?? '', 'close' => $shop['weekday_close_time'] ?? $shop['weekday_close'] ?? ''];
}

function customerExploreFormatTime(?string $value): string
{
    $minutes = customerExploreTimeMinutes($value);
    if ($minutes === null) return '';
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    return ($hour % 12 ?: 12) . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT) . ($hour >= 12 ? ' PM' : ' AM');
}

function customerExploreHoursLabel(array $shop): string
{
    $schedule = customerExploreScheduleForDay($shop, (int) date('w'));
    if (empty($schedule['open']) || empty($schedule['close'])) return 'Hours not set';
    return customerExploreFormatTime($schedule['open']) . ' - ' . customerExploreFormatTime($schedule['close']);
}

function customerExploreIsOpenNow(array $shop): bool
{
    $current = ((int) date('G') * 60) + (int) date('i');
    $today = customerExploreScheduleForDay($shop, (int) date('w'));
    $today_open = customerExploreTimeMinutes($today['open']);
    $today_close = customerExploreTimeMinutes($today['close']);

    if ($today_open !== null && $today_close !== null) {
        if ($today_open === $today_close) return true;
        if ($today_close > $today_open && $current >= $today_open && $current < $today_close) return true;
        if ($today_close < $today_open && $current >= $today_open) return true;
    }

    $previous = customerExploreScheduleForDay($shop, ((int) date('w') + 6) % 7);
    $previous_open = customerExploreTimeMinutes($previous['open']);
    $previous_close = customerExploreTimeMinutes($previous['close']);
    return $previous_open !== null && $previous_close !== null && $previous_close < $previous_open && $current < $previous_close;
}

function customerExploreMoney($value): string
{
    return is_numeric($value) ? '&#8369;' . number_format((float) $value, 2) : 'Price unavailable';
}

function customerExploreDirectionsUrl(array $shop): string
{
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode((string) $shop['latitude'] . ',' . (string) $shop['longitude']);
}

function customerExploreReturnTo(string $view, string $search, int $selected_shop_id = 0): string
{
    $extra = [];
    if ($view === 'all') $extra['search'] = $search;
    if ($view === 'nearby' && $selected_shop_id > 0) $extra['shop_id'] = $selected_shop_id;
    return customerExploreUrl($view, $extra);
}

function renderExploreFavoriteForm(array $shop, string $return_to): void
{
    $is_favorite = !empty($shop['is_favorite']);
    ?>
    <form method="POST" action="<?php echo BASE_URL; ?>backend/actions/toggle_favorite_shop.php" class="customer-favorite-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="shop_id" value="<?php echo (int) $shop['shop_id']; ?>">
        <input type="hidden" name="intent" value="<?php echo $is_favorite ? 'remove' : 'add'; ?>">
        <input type="hidden" name="return_to" value="<?php echo e($return_to); ?>">
        <button type="submit" class="customer-favorite-button<?php echo $is_favorite ? ' is-favorite' : ''; ?>"
            aria-label="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>"
            title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
            <?php echo customerIcon('heart'); ?>
        </button>
    </form>
    <?php
}

function renderExploreVerifiedBadge(array $shop): void
{
    if (($shop['permit_status'] ?? '') !== 'verified' && empty($shop['is_verified'])) return;
    ?>
    <span class="customer-shop-verified-badge" title="Verified print shop" aria-label="Verified print shop">
        <?php echo customerIcon('check'); ?><span>Verified</span>
    </span>
    <?php
}

function renderExploreShopCard(array $shop, string $return_to, bool $selected = false): void
{
    $status = ($shop['shop_status'] ?? $shop['status'] ?? '') === 'available' ? 'available' : 'busy';
    $shop_name = (string) ($shop['shop_name'] ?? $shop['name'] ?? 'Print Shop');
    $short_address = customerExploreShortAddress($shop);
    $landmark = trim((string) ($shop['landmark'] ?? ''));
    $shop_logo = trim((string) ($shop['shop_logo'] ?? ''));
    $logo_url = $shop_logo !== '' ? SHOP_LOGOS_URL . $shop_logo : (string) ($shop['logo_url'] ?? '');
    $is_open = customerExploreIsOpenNow($shop);
    $contact = trim((string) ($shop['contact_number'] ?? $shop['contact'] ?? '')) ?: 'No contact listed';
    ?>
    <article class="customer-map-shop-card customer-shops-card<?php echo $selected ? ' selected' : ''; ?>">
        <div class="customer-map-shop-head">
            <div class="customer-map-shop-logo">
                <?php if ($logo_url !== ''): ?><img src="<?php echo e($logo_url); ?>" alt=""><?php else: ?><?php echo customerIcon('printer'); ?><?php endif; ?>
            </div>
            <div class="customer-map-shop-title">
                <div class="customer-map-shop-name">
                    <h3><?php echo e($shop_name); ?></h3>
                    <?php renderExploreVerifiedBadge($shop); ?>
                </div>
                <p><span aria-hidden="true">&#9679;</span><?php echo e($short_address); ?></p>
                <?php if ($landmark !== ''): ?><small><?php echo e($landmark); ?></small><?php endif; ?>
            </div>
            <div class="customer-shop-card-tools">
                <span class="customer-map-status <?php echo e($status); ?>"><?php echo $status === 'available' ? 'Available' : 'Busy'; ?></span>
                <?php renderExploreFavoriteForm($shop, $return_to); ?>
            </div>
        </div>

        <div class="customer-map-shop-facts">
            <span><strong><?php echo $is_open ? 'Open now' : 'Closed now'; ?></strong><?php echo e(customerExploreHoursLabel($shop)); ?></span>
            <span><strong><?php echo (int) ($shop['service_count'] ?? 0); ?> services</strong><?php echo customerExploreMoney($shop['starting_price'] ?? null); ?> start</span>
            <span><strong>Contact</strong><?php echo e($contact); ?></span>
        </div>

        <div class="customer-map-shop-actions">
            <a href="<?php echo e(customerExploreUrl('nearby', ['shop_id' => (int) $shop['shop_id']])); ?>">View Map</a>
            <a href="<?php echo e(customerExploreDirectionsUrl($shop)); ?>" target="_blank" rel="noopener">Directions</a>
            <a class="primary" href="place_order.php?shop_id=<?php echo (int) $shop['shop_id']; ?>"
                data-busy-shop-link data-shop-status="<?php echo e($status); ?>"
                data-shop-name="<?php echo e($shop_name); ?>">Order Now</a>
        </div>
    </article>
    <?php
}

$sql = "SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.display_address, ps.landmark,
               ps.contact_number, ps.shop_logo, ps.shop_status, ps.permit_status, ps.latitude, ps.longitude,
               ps.weekday_open_time, ps.weekday_close_time, ps.weekend_open_time, ps.weekend_close_time,
               COUNT(ss.service_id) AS service_count,
               MIN(ss.price_per_page) AS starting_price,
               MAX(cfs.id) AS favorite_id
        FROM print_shops ps
        LEFT JOIN shop_services ss ON ps.shop_id = ss.shop_id AND ss.is_available = 1
        LEFT JOIN customer_favorite_shops cfs ON cfs.shop_id = ps.shop_id AND cfs.customer_id = ?
        WHERE ps.permit_status = 'verified'
          AND ps.shop_status IN ('available', 'busy')
          AND ps.latitude IS NOT NULL
          AND ps.longitude IS NOT NULL";

$types = 'i';
$params = [$customer_id];
if ($search !== '') {
    $sql .= " AND (
                LOWER(ps.shop_name) LIKE ?
                OR LOWER(ps.shop_address) LIKE ?
                OR LOWER(ps.display_address) LIKE ?
                OR LOWER(ps.landmark) LIKE ?
            )";
    $like = '%' . strtolower($search) . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$sql .= " GROUP BY ps.shop_id
          HAVING service_count > 0";
if ($view === 'favorites') {
    $sql .= " AND favorite_id IS NOT NULL";
}
$sql .= " ORDER BY CASE ps.shop_status WHEN 'available' THEN 0 ELSE 1 END, ps.shop_name ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$shops_result = mysqli_stmt_get_result($stmt);

$shops = [];
$shop_locations = [];
while ($shop = mysqli_fetch_assoc($shops_result)) {
    $shop['is_favorite'] = !empty($shop['favorite_id']);
    $shop['is_verified'] = ($shop['permit_status'] ?? '') === 'verified';
    $shops[] = $shop;
    $shop_locations[] = [
        'shop_id' => (int) $shop['shop_id'],
        'name' => (string) $shop['shop_name'],
        'address' => (string) ($shop['shop_address'] ?? ''),
        'display_address' => (string) ($shop['display_address'] ?? ''),
        'landmark' => (string) ($shop['landmark'] ?? ''),
        'contact' => (string) ($shop['contact_number'] ?? ''),
        'logo_url' => !empty($shop['shop_logo']) ? SHOP_LOGOS_URL . $shop['shop_logo'] : '',
        'status' => (string) $shop['shop_status'],
        'is_verified' => ($shop['permit_status'] ?? '') === 'verified',
        'service_count' => (int) $shop['service_count'],
        'starting_price' => $shop['starting_price'] !== null ? (float) $shop['starting_price'] : null,
        'lat' => (float) $shop['latitude'],
        'lng' => (float) $shop['longitude'],
        'weekday_open' => (string) ($shop['weekday_open_time'] ?? ''),
        'weekday_close' => (string) ($shop['weekday_close_time'] ?? ''),
        'weekend_open' => (string) ($shop['weekend_open_time'] ?? ''),
        'weekend_close' => (string) ($shop['weekend_close_time'] ?? ''),
        'is_favorite' => !empty($shop['favorite_id']),
    ];
}

$return_to = customerExploreReturnTo($view, $search, $selected_shop_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Explore Print Shops</title>
    <?php renderCustomerHead(); ?>
    <?php if ($view === 'nearby'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
</head>

<body class="customer-body customer-map-page customer-explore-page bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="customer-page-frame min-h-screen">
        <?php renderCustomerLayout([
            'title' => 'Explore Print Shops',
            'subtitle' => 'Find nearby shops, browse every option, and save favorites.'
        ]); ?>

        <main class="customer-map-main">
            <nav class="customer-explore-tabs" aria-label="Explore sections">
                <a href="<?php echo e(customerExploreUrl('nearby', ['shop_id' => $selected_shop_id ?: null])); ?>" class="<?php echo $view === 'nearby' ? 'active' : ''; ?>">Nearby</a>
                <a href="<?php echo e(customerExploreUrl('all', ['search' => $search])); ?>" class="<?php echo $view === 'all' ? 'active' : ''; ?>">All Shops</a>
                <a href="<?php echo e(customerExploreUrl('favorites')); ?>" class="<?php echo $view === 'favorites' ? 'active' : ''; ?>">Favorites</a>
            </nav>

            <?php if ($view === 'all'): ?>
            <section class="customer-map-toolbar" aria-label="Shop search">
                <form method="GET" action="explore.php" class="customer-map-search" role="search" data-live-search-form data-live-target="customer_explore" data-live-min="1">
                    <input type="hidden" name="view" value="all">
                    <span class="customer-map-search-icon" aria-hidden="true"><?php echo customerIcon('search'); ?></span>
                    <input id="shopExploreSearch" type="search" name="search" value="<?php echo e($search); ?>"
                        placeholder="Search shop, street, or landmark" aria-label="Search print shops">
                    <button type="submit">Search</button>
                </form>
            </section>
            <?php endif; ?>

            <?php if ($view === 'nearby'): ?>
                <?php if (empty($shop_locations)): ?>
                    <section class="customer-map-empty" role="status" data-live-region="customer-explore-results">
                        <span><?php echo customerIcon('map'); ?></span>
                        <h2>No mapped shops found</h2>
                        <p>No nearby mapped shops are available right now. Browse all verified print shops instead.</p>
                        <a href="explore.php?view=all">Browse Shops</a>
                    </section>
                <?php else: ?>
                    <section class="customer-map-explorer">
                        <div class="customer-map-canvas-card">
                            <div class="customer-map-statusbar">
                                <div>
                                    <strong><span id="visibleShopCount"><?php echo count($shop_locations); ?></span> shops found</strong>
                                    <p id="locationStatus" role="status">Enable your location to sort shops by distance.</p>
                                </div>
                                <button type="button" id="useLocationButton" class="customer-location-button">
                                    <?php echo customerIcon('pin'); ?><span>Use My Location</span>
                                </button>
                            </div>
                            <div id="map" aria-label="Map showing verified print shops"></div>
                        </div>

                        <aside class="customer-map-results" id="shopResultsPanel" aria-label="Print shop results">
                            <button type="button" class="customer-results-sheet-toggle" id="resultsSheetToggle"
                                aria-expanded="true" aria-controls="shopList">
                                <span aria-hidden="true"></span>
                                <strong>Nearby Shops</strong>
                                <small>Tap to expand or collapse</small>
                            </button>
                            <div class="customer-map-results-heading">
                                <div><strong>Nearby Shops</strong><span>Choose a shop to view details</span></div>
                                <span id="resultsCountBadge"><?php echo count($shop_locations); ?></span>
                            </div>
                            <div id="shopList" class="customer-map-shop-list" aria-live="polite"></div>
                            <div id="filteredEmptyState" class="customer-map-filter-empty" hidden>
                                <strong>No nearby shops to show</strong>
                                <span>Try enabling your location again or browse all verified print shops.</span>
                            </div>
                        </aside>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($shops)): ?>
                    <section class="customer-map-empty" role="status" data-live-region="customer-explore-results">
                        <span><?php echo customerIcon($view === 'favorites' ? 'heart' : 'printer'); ?></span>
                        <h2><?php echo $view === 'favorites' ? 'No favorites yet' : 'No print shops found'; ?></h2>
                        <p><?php echo $view === 'favorites' ? 'Save shops from Nearby or All Shops to see them here.' : 'Try another search or check Nearby mode.'; ?></p>
                        <a href="explore.php?view=<?php echo $view === 'favorites' ? 'all' : 'nearby'; ?>"><?php echo $view === 'favorites' ? 'Browse Shops' : 'Open Nearby'; ?></a>
                    </section>
                <?php else: ?>
                    <div class="customer-shops-grid" data-live-region="customer-explore-results">
                        <?php foreach ($shops as $shop): ?>
                            <?php renderExploreShopCard($shop, $return_to, (int) $shop['shop_id'] === $selected_shop_id); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <?php renderCustomerLayoutEnd('explore'); ?>

    <div class="customer-busy-shop-modal" id="busyShopModal" role="dialog" aria-modal="true"
        aria-labelledby="busyShopTitle" aria-describedby="busyShopMessage" hidden>
        <div class="customer-busy-shop-backdrop" data-busy-shop-close></div>
        <section class="customer-busy-shop-panel" tabindex="-1">
            <span class="customer-busy-shop-icon" aria-hidden="true"><?php echo customerIcon('clock'); ?></span>
            <div class="customer-busy-shop-copy">
                <small>Busy shop notice</small>
                <h2 id="busyShopTitle">This shop is currently busy</h2>
                <p id="busyShopMessage">
                    Notice: This shop is currently in high demand and has many orders at the moment.
                    Your request may take longer than usual. You may continue or choose another shop.
                </p>
                <strong data-busy-shop-name></strong>
            </div>
            <div class="customer-busy-shop-actions">
                <button type="button" class="customer-busy-shop-secondary" data-busy-shop-close>Choose Another Shop</button>
                <button type="button" class="customer-busy-shop-primary" data-busy-shop-continue>Continue</button>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('busyShopModal');
            if (!modal) return;

            const panel = modal.querySelector('.customer-busy-shop-panel');
            const shopNameLabel = modal.querySelector('[data-busy-shop-name]');
            const continueButton = modal.querySelector('[data-busy-shop-continue]');
            const acknowledgedBusyLinks = new Set();
            let pendingHref = '';
            let lastTrigger = null;

            function openBusyShopModal(link) {
                pendingHref = link.href;
                lastTrigger = link;
                if (shopNameLabel) {
                    shopNameLabel.textContent = link.dataset.shopName || 'Selected print shop';
                }
                modal.hidden = false;
                modal.classList.add('is-visible');
                document.body.classList.add('customer-modal-open');
                window.setTimeout(function () {
                    (panel || continueButton || modal).focus();
                }, 0);
            }

            function closeBusyShopModal() {
                modal.classList.remove('is-visible');
                modal.hidden = true;
                document.body.classList.remove('customer-modal-open');
                pendingHref = '';
                if (lastTrigger && document.contains(lastTrigger)) {
                    lastTrigger.focus();
                }
            }

            document.addEventListener('click', function (event) {
                const link = event.target.closest('[data-busy-shop-link]');
                if (!link) return;

                const status = String(link.dataset.shopStatus || '').toLowerCase();
                if (status !== 'busy' || acknowledgedBusyLinks.has(link.href)) return;

                event.preventDefault();
                openBusyShopModal(link);
            });

            modal.querySelectorAll('[data-busy-shop-close]').forEach(function (button) {
                button.addEventListener('click', closeBusyShopModal);
            });

            if (continueButton) {
                continueButton.addEventListener('click', function () {
                    if (!pendingHref) return;
                    acknowledgedBusyLinks.add(pendingHref);
                    window.location.href = pendingHref;
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeBusyShopModal();
                }
            });
        });
    </script>

    <?php if ($view === 'nearby' && !empty($shop_locations)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const shops = <?php echo json_encode($shop_locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                const favoriteAction = <?php echo json_encode(BASE_URL . 'backend/actions/toggle_favorite_shop.php'); ?>;
                const returnTo = <?php echo json_encode($return_to); ?>;
                let selectedShopId = <?php echo $selected_shop_id; ?>;
                const calbayogCenter = [12.0432, 124.5946];
                const map = L.map('map', { zoomControl: false }).setView(calbayogCenter, 13);
                const markers = new Map();
                let customerMarker = null;
                let customerLocation = null;

                L.control.zoom({ position: 'bottomright' }).addTo(map);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                const list = document.getElementById('shopList');
                const emptyState = document.getElementById('filteredEmptyState');
                const locationStatus = document.getElementById('locationStatus');
                const visibleShopCount = document.getElementById('visibleShopCount');
                const resultsCountBadge = document.getElementById('resultsCountBadge');
                const resultsPanel = document.getElementById('shopResultsPanel');
                const resultsToggle = document.getElementById('resultsSheetToggle');

                function escapeHtml(value) {
                    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }
                function shortAddress(shop) {
                    const preferred = String(shop.display_address || '').trim();
                    if (preferred) return preferred;
                    const firstPart = String(shop.address || '').split(',')[0].trim();
                    return firstPart || 'Location not provided';
                }
                function timeParts(value) {
                    const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
                    return match ? (Number(match[1]) * 60) + Number(match[2]) : null;
                }
                function scheduleForDay(shop, day) {
                    const weekend = [0, 6].includes(day);
                    return weekend ? { open: shop.weekend_open, close: shop.weekend_close } : { open: shop.weekday_open, close: shop.weekday_close };
                }
                function formatTime(value) {
                    const minutes = timeParts(value);
                    if (minutes === null) return '';
                    const hour = Math.floor(minutes / 60);
                    const minute = minutes % 60;
                    return (hour % 12 || 12) + ':' + String(minute).padStart(2, '0') + (hour >= 12 ? ' PM' : ' AM');
                }
                function isOpenNow(shop) {
                    const now = new Date();
                    const current = (now.getHours() * 60) + now.getMinutes();
                    const today = scheduleForDay(shop, now.getDay());
                    const todayOpen = timeParts(today.open);
                    const todayClose = timeParts(today.close);
                    if (todayOpen !== null && todayClose !== null) {
                        if (todayOpen === todayClose) return true;
                        if (todayClose > todayOpen && current >= todayOpen && current < todayClose) return true;
                        if (todayClose < todayOpen && current >= todayOpen) return true;
                    }
                    const previous = scheduleForDay(shop, (now.getDay() + 6) % 7);
                    const previousOpen = timeParts(previous.open);
                    const previousClose = timeParts(previous.close);
                    return previousOpen !== null && previousClose !== null && previousClose < previousOpen && current < previousClose;
                }
                function hoursLabel(shop) {
                    const schedule = scheduleForDay(shop, new Date().getDay());
                    if (!schedule.open || !schedule.close) return 'Hours not set';
                    return formatTime(schedule.open) + ' - ' + formatTime(schedule.close);
                }
                function distanceKm(from, to) {
                    const radius = 6371;
                    const dLat = (to.lat - from.lat) * Math.PI / 180;
                    const dLng = (to.lng - from.lng) * Math.PI / 180;
                    const lat1 = from.lat * Math.PI / 180;
                    const lat2 = to.lat * Math.PI / 180;
                    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
                    return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                }
                function formatDistance(distance) {
                    if (!Number.isFinite(distance)) return 'Enable location for distance';
                    return distance < 1 ? Math.round(distance * 1000) + ' m away' : distance.toFixed(1) + ' km away';
                }
                function money(value) {
                    return Number.isFinite(Number(value)) ? '&#8369;' + Number(value).toFixed(2) : 'Price unavailable';
                }
                function markerIcon(shop, selected) {
                    const state = shop.status === 'available' ? 'available' : 'busy';
                    return L.divIcon({
                        className: 'customer-map-marker-wrap',
                        html: '<span class="customer-map-marker ' + state + (selected ? ' selected' : '') + '"><i></i></span>',
                        iconSize: [34, 42],
                        iconAnchor: [17, 39],
                        popupAnchor: [0, -36]
                    });
                }
                function directionsUrl(shop) {
                    return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(shop.lat + ',' + shop.lng);
                }
                function favoriteFormHtml(shop) {
                    const favorite = !!shop.is_favorite;
                    return '<form method="POST" action="' + favoriteAction + '" class="customer-favorite-form">' +
                        '<input type="hidden" name="shop_id" value="' + shop.shop_id + '">' +
                        '<input type="hidden" name="intent" value="' + (favorite ? 'remove' : 'add') + '">' +
                        '<input type="hidden" name="return_to" value="' + escapeHtml(returnTo) + '">' +
                        '<button type="submit" class="customer-favorite-button' + (favorite ? ' is-favorite' : '') + '" aria-label="' + (favorite ? 'Remove from favorites' : 'Add to favorites') + '">' +
                        '<svg class="customer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>' +
                        '</button></form>';
                }
                function verifiedBadgeHtml(shop) {
                    if (!shop.is_verified) return '';
                    return '<span class="customer-shop-verified-badge" title="Verified print shop" aria-label="Verified print shop">' +
                        '<svg class="customer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>' +
                        '<span>Verified</span>' +
                    '</span>';
                }
                function popupContent(shop) {
                    return '<div class="customer-map-popup">' +
                        '<div class="customer-map-popup-title"><strong>' + escapeHtml(shop.name) + '</strong>' + verifiedBadgeHtml(shop) + '</div>' +
                        '<span>' + escapeHtml(shortAddress(shop)) + '</span>' +
                        '<small>' + escapeHtml(hoursLabel(shop)) + ' &middot; ' + money(shop.starting_price) + ' start</small>' +
                        '<a href="place_order.php?shop_id=' + shop.shop_id + '" data-busy-shop-link data-shop-status="' + escapeHtml(shop.status) + '" data-shop-name="' + escapeHtml(shop.name) + '">Order Now</a>' +
                    '</div>';
                }
                function logoHtml(shop) {
                    if (shop.logo_url) return '<img src="' + escapeHtml(shop.logo_url) + '" alt="">';
                    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/></svg>';
                }
                function cardHtml(shop) {
                    const selected = shop.shop_id === selectedShopId;
                    const open = isOpenNow(shop);
                    return '<article class="customer-map-shop-card' + (selected ? ' selected' : '') + '" data-shop-card="' + shop.shop_id + '" tabindex="0">' +
                        '<div class="customer-map-shop-head">' +
                            '<div class="customer-map-shop-logo">' + logoHtml(shop) + '</div>' +
                            '<div class="customer-map-shop-title"><div class="customer-map-shop-name"><h3>' + escapeHtml(shop.name) + '</h3>' + verifiedBadgeHtml(shop) + '</div><p><span aria-hidden="true">&#9679;</span>' + escapeHtml(shortAddress(shop)) + '</p>' + (shop.landmark ? '<small>' + escapeHtml(shop.landmark) + '</small>' : '') + '</div>' +
                            '<div class="customer-shop-card-tools"><span class="customer-map-status ' + escapeHtml(shop.status) + '">' + (shop.status === 'available' ? 'Available' : 'Busy') + '</span>' + favoriteFormHtml(shop) + '</div>' +
                        '</div>' +
                        '<div class="customer-map-shop-facts">' +
                            '<span><strong>' + (open ? 'Open now' : 'Closed now') + '</strong>' + escapeHtml(hoursLabel(shop)) + '</span>' +
                            '<span><strong>' + shop.service_count + ' services</strong>' + money(shop.starting_price) + ' start</span>' +
                            '<span><strong>' + escapeHtml(formatDistance(shop.distance)) + '</strong>' + escapeHtml(shop.contact || 'No contact listed') + '</span>' +
                        '</div>' +
                        '<div class="customer-map-shop-actions"><button type="button" data-focus-shop="' + shop.shop_id + '">View Map</button><a href="' + directionsUrl(shop) + '" target="_blank" rel="noopener">Directions</a><a class="primary" href="place_order.php?shop_id=' + shop.shop_id + '" data-busy-shop-link data-shop-status="' + escapeHtml(shop.status) + '" data-shop-name="' + escapeHtml(shop.name) + '">Order Now</a></div>' +
                    '</article>';
                }
                function createMarkers() {
                    shops.forEach(function (shop) {
                        const marker = L.marker([shop.lat, shop.lng], { icon: markerIcon(shop, false) }).addTo(map);
                        marker.bindPopup(popupContent(shop));
                        marker.on('click', function () { selectShop(shop.shop_id, false); });
                        markers.set(shop.shop_id, marker);
                    });
                }
                function filteredShops() {
                    let result = shops.slice();
                    result.sort(function (a, b) {
                        if (customerLocation) return a.distance - b.distance;
                        if (a.status !== b.status) return a.status === 'available' ? -1 : 1;
                        return a.name.localeCompare(b.name);
                    });
                    return result;
                }
                function updateMarkers(visibleShops) {
                    const visibleIds = new Set(visibleShops.map(function (shop) { return shop.shop_id; }));
                    shops.forEach(function (shop) {
                        const marker = markers.get(shop.shop_id);
                        if (visibleIds.has(shop.shop_id)) {
                            if (!map.hasLayer(marker)) marker.addTo(map);
                            marker.setIcon(markerIcon(shop, shop.shop_id === selectedShopId));
                            marker.bindPopup(popupContent(shop));
                        } else if (map.hasLayer(marker)) {
                            map.removeLayer(marker);
                        }
                    });
                }
                function renderList() {
                    const visible = filteredShops();
                    list.innerHTML = visible.map(cardHtml).join('');
                    emptyState.hidden = visible.length > 0;
                    visibleShopCount.textContent = visible.length;
                    resultsCountBadge.textContent = visible.length;
                    updateMarkers(visible);
                    list.querySelectorAll('[data-focus-shop]').forEach(function (button) {
                        button.addEventListener('click', function () { selectShop(Number(button.dataset.focusShop), true); });
                    });
                    list.querySelectorAll('[data-shop-card]').forEach(function (card) {
                        card.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                selectShop(Number(card.dataset.shopCard), true);
                            }
                        });
                    });
                    return visible;
                }
                function selectShop(shopId, openPopup) {
                    const shop = shops.find(function (item) { return item.shop_id === shopId; });
                    const marker = markers.get(shopId);
                    if (!shop || !marker) return;
                    selectedShopId = shopId;
                    renderList();
                    map.setView([shop.lat, shop.lng], 17, { animate: true });
                    if (openPopup) marker.openPopup();
                    const card = list.querySelector('[data-shop-card="' + shopId + '"]');
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                function fitVisibleShops(visible) {
                    const points = visible.map(function (shop) { return [shop.lat, shop.lng]; });
                    if (customerLocation) points.push([customerLocation.lat, customerLocation.lng]);
                    if (points.length === 1) map.setView(points[0], 15);
                    if (points.length > 1) map.fitBounds(points, { padding: [42, 42], maxZoom: 16 });
                }
                function useCustomerLocation() {
                    const button = document.getElementById('useLocationButton');
                    if (!navigator.geolocation) {
                        locationStatus.textContent = 'Location is not supported by this browser.';
                        return;
                    }
                    button.disabled = true;
                    locationStatus.textContent = 'Waiting for location permission...';
                    navigator.geolocation.getCurrentPosition(function (position) {
                        customerLocation = { lat: position.coords.latitude, lng: position.coords.longitude };
                        shops.forEach(function (shop) { shop.distance = distanceKm(customerLocation, shop); });
                        if (!customerMarker) {
                            customerMarker = L.circleMarker([customerLocation.lat, customerLocation.lng], {
                                radius: 9, color: '#fff', weight: 3, fillColor: '#0077b6', fillOpacity: 1
                            }).addTo(map).bindPopup('Your current location');
                        } else {
                            customerMarker.setLatLng([customerLocation.lat, customerLocation.lng]);
                        }
                        locationStatus.textContent = 'Nearest shops are sorted from your current location.';
                        const visible = renderList();
                        fitVisibleShops(visible);
                        button.disabled = false;
                        button.querySelector('span').textContent = 'Location Enabled';
                    }, function (error) {
                        const messages = {
                            1: 'Location permission was denied. You can still browse mapped shops.',
                            2: 'Your location is currently unavailable. Please try again.',
                            3: 'Location request timed out. Please try again.'
                        };
                        locationStatus.textContent = messages[error.code] || 'Could not get your location.';
                        button.disabled = false;
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
                }
                document.getElementById('useLocationButton').addEventListener('click', useCustomerLocation);
                resultsToggle.addEventListener('click', function () {
                    const collapsed = resultsPanel.classList.toggle('collapsed');
                    resultsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    window.setTimeout(function () { map.invalidateSize(); }, 240);
                });
                createMarkers();
                const initialVisible = renderList();
                fitVisibleShops(initialVisible);
                if (selectedShopId > 0) window.setTimeout(function () { selectShop(selectedShopId, true); }, 250);
                window.addEventListener('resize', function () { map.invalidateSize(); });
            });
        </script>
    <?php endif; ?>
</body>
</html>
