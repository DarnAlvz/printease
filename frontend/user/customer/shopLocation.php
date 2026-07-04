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

$redirect_query = ['view' => 'nearby'];
if ((int) ($_GET['shop_id'] ?? 0) > 0) {
    $redirect_query['shop_id'] = (int) $_GET['shop_id'];
}
header("Location: explore.php?" . http_build_query($redirect_query));
exit();

$search = trim($_GET['search'] ?? '');
$selected_shop_id = isset($_GET['shop_id']) ? (int) $_GET['shop_id'] : 0;

$sql = "SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.display_address, ps.landmark,
               ps.contact_number, ps.shop_logo, ps.shop_status, ps.latitude, ps.longitude,
               ps.weekday_open_time, ps.weekday_close_time, ps.weekend_open_time, ps.weekend_close_time,
               COUNT(ss.service_id) AS service_count,
               MIN(ss.price_per_page) AS starting_price
        FROM print_shops ps
        LEFT JOIN shop_services ss ON ps.shop_id = ss.shop_id AND ss.is_available = 1
        WHERE ps.permit_status = 'verified'
          AND ps.shop_status IN ('available', 'busy')
          AND ps.latitude IS NOT NULL
          AND ps.longitude IS NOT NULL";

$types = '';
$params = [];
if ($search !== '') {
    $sql .= " AND (
                ps.shop_name LIKE ?
                OR ps.shop_address LIKE ?
                OR ps.display_address LIKE ?
                OR ps.landmark LIKE ?
            )";
    $like = "%$search%";
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
}

$sql .= " GROUP BY ps.shop_id
          HAVING service_count > 0
          ORDER BY CASE ps.shop_status WHEN 'available' THEN 0 ELSE 1 END, ps.shop_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$shops = mysqli_stmt_get_result($stmt);

$shop_locations = [];
while ($shop = mysqli_fetch_assoc($shops)) {
    $shop_locations[] = [
        'shop_id' => (int) $shop['shop_id'],
        'name' => (string) $shop['shop_name'],
        'address' => (string) ($shop['shop_address'] ?? ''),
        'display_address' => (string) ($shop['display_address'] ?? ''),
        'landmark' => (string) ($shop['landmark'] ?? ''),
        'contact' => (string) ($shop['contact_number'] ?? ''),
        'logo_url' => !empty($shop['shop_logo']) ? SHOP_LOGOS_URL . $shop['shop_logo'] : '',
        'status' => (string) $shop['shop_status'],
        'service_count' => (int) $shop['service_count'],
        'starting_price' => $shop['starting_price'] !== null ? (float) $shop['starting_price'] : null,
        'lat' => (float) $shop['latitude'],
        'lng' => (float) $shop['longitude'],
        'weekday_open' => (string) ($shop['weekday_open_time'] ?? ''),
        'weekday_close' => (string) ($shop['weekday_close_time'] ?? ''),
        'weekend_open' => (string) ($shop['weekend_open_time'] ?? ''),
        'weekend_close' => (string) ($shop['weekend_close_time'] ?? ''),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Nearby Print Shops</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
</head>

<body class="customer-body customer-map-page bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="customer-page-frame min-h-screen">
        <?php renderCustomerLayout([
            'title' => 'Explore Print Shops',
            'subtitle' => 'Compare nearby verified shops, check availability, and start your order.'
        ]); ?>

        <main class="customer-map-main">
            <section class="customer-map-toolbar" aria-label="Shop map search and filters">
                <form method="GET" class="customer-map-search" role="search">
                    <span class="customer-map-search-icon" aria-hidden="true"><?php echo customerIcon('search'); ?></span>
                    <input id="shopMapSearch" type="search" name="search" value="<?php echo e($search); ?>"
                        placeholder="Search shop, street, or landmark" aria-label="Search print shops">
                    <?php if ($selected_shop_id > 0): ?>
                        <input type="hidden" name="shop_id" value="<?php echo $selected_shop_id; ?>">
                    <?php endif; ?>
                    <button type="submit">Search</button>
                </form>

                <div class="customer-map-filter-row" aria-label="Shop filters">
                    <button type="button" class="customer-map-filter" data-filter="available" aria-pressed="false">Available</button>
                    <button type="button" class="customer-map-filter" data-filter="open" aria-pressed="false">Open Now</button>
                    <button type="button" class="customer-map-filter" data-filter="nearest" aria-pressed="false">Nearest</button>
                    <a href="shopLocation.php" class="customer-map-reset">Reset</a>
                </div>
            </section>

            <?php if (empty($shop_locations)): ?>
                <section class="customer-map-empty" role="status">
                    <span><?php echo customerIcon('map'); ?></span>
                    <h2>No mapped shops found</h2>
                    <p>Try another search or browse all verified print shops.</p>
                    <a href="shops.php">Browse Shops</a>
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
                            <strong>No shops match these filters</strong>
                            <span>Try turning off a filter or resetting the search.</span>
                        </div>
                    </aside>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php renderCustomerLayoutEnd('map'); ?>

    <?php if (!empty($shop_locations)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const shops = <?php echo json_encode($shop_locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                let selectedShopId = <?php echo $selected_shop_id; ?>;
                const calbayogCenter = [12.0432, 124.5946];
                const map = L.map('map', { zoomControl: false }).setView(calbayogCenter, 13);
                const markers = new Map();
                const activeFilters = new Set();
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
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
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
                    return weekend
                        ? { label: 'Weekend hours', open: shop.weekend_open, close: shop.weekend_close }
                        : { label: 'Today hours', open: shop.weekday_open, close: shop.weekday_close };
                }

                function scheduleForToday(shop) {
                    return scheduleForDay(shop, new Date().getDay());
                }

                function formatTime(value) {
                    const minutes = timeParts(value);
                    if (minutes === null) return '';
                    const hour = Math.floor(minutes / 60);
                    const minute = minutes % 60;
                    const displayHour = hour % 12 || 12;
                    return displayHour + ':' + String(minute).padStart(2, '0') + (hour >= 12 ? ' PM' : ' AM');
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

                    const previousDay = (now.getDay() + 6) % 7;
                    const previous = scheduleForDay(shop, previousDay);
                    const previousOpen = timeParts(previous.open);
                    const previousClose = timeParts(previous.close);
                    return previousOpen !== null && previousClose !== null && previousClose < previousOpen && current < previousClose;
                }

                function hoursLabel(shop) {
                    const schedule = scheduleForToday(shop);
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

                function popupContent(shop) {
                    return '<div class="customer-map-popup">' +
                        '<strong>' + escapeHtml(shop.name) + '</strong>' +
                        '<span>' + escapeHtml(shortAddress(shop)) + '</span>' +
                        '<small>' + escapeHtml(hoursLabel(shop)) + ' &middot; ' + money(shop.starting_price) + ' start</small>' +
                        '<a href="place_order.php?shop_id=' + shop.shop_id + '">Order Now</a>' +
                    '</div>';
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
                    let result = shops.filter(function (shop) {
                        if (activeFilters.has('available') && shop.status !== 'available') return false;
                        if (activeFilters.has('open') && !isOpenNow(shop)) return false;
                        return true;
                    });
                    result.sort(function (a, b) {
                        if (activeFilters.has('nearest') && customerLocation) return a.distance - b.distance;
                        if (a.status !== b.status) return a.status === 'available' ? -1 : 1;
                        return a.name.localeCompare(b.name);
                    });
                    return result;
                }

                function logoHtml(shop) {
                    if (shop.logo_url) {
                        return '<img src="' + escapeHtml(shop.logo_url) + '" alt="">';
                    }
                    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/></svg>';
                }

                function cardHtml(shop) {
                    const selected = shop.shop_id === selectedShopId;
                    const open = isOpenNow(shop);
                    return '<article class="customer-map-shop-card' + (selected ? ' selected' : '') + '" data-shop-card="' + shop.shop_id + '" tabindex="0">' +
                        '<div class="customer-map-shop-head">' +
                            '<div class="customer-map-shop-logo">' + logoHtml(shop) + '</div>' +
                            '<div class="customer-map-shop-title"><h3>' + escapeHtml(shop.name) + '</h3>' +
                                '<p><span aria-hidden="true">&#9679;</span>' + escapeHtml(shortAddress(shop)) + '</p>' +
                                (shop.landmark ? '<small>' + escapeHtml(shop.landmark) + '</small>' : '') +
                            '</div>' +
                            '<span class="customer-map-status ' + escapeHtml(shop.status) + '">' + (shop.status === 'available' ? 'Available' : 'Busy') + '</span>' +
                        '</div>' +
                        '<div class="customer-map-shop-facts">' +
                            '<span><strong>' + (open ? 'Open now' : 'Closed now') + '</strong>' + escapeHtml(hoursLabel(shop)) + '</span>' +
                            '<span><strong>' + shop.service_count + ' services</strong>' + money(shop.starting_price) + ' start</span>' +
                            '<span><strong>' + escapeHtml(formatDistance(shop.distance)) + '</strong>' + escapeHtml(shop.contact || 'No contact listed') + '</span>' +
                        '</div>' +
                        '<div class="customer-map-shop-actions">' +
                            '<button type="button" data-focus-shop="' + shop.shop_id + '">View Map</button>' +
                            '<a href="' + directionsUrl(shop) + '" target="_blank" rel="noopener">Directions</a>' +
                            '<a class="primary" href="place_order.php?shop_id=' + shop.shop_id + '">Order Now</a>' +
                        '</div>' +
                    '</article>';
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
                        activeFilters.add('nearest');
                        document.querySelector('[data-filter="nearest"]').classList.add('active');
                        document.querySelector('[data-filter="nearest"]').setAttribute('aria-pressed', 'true');
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

                document.querySelectorAll('[data-filter]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const filter = button.dataset.filter;
                        if (filter === 'nearest' && !customerLocation) {
                            locationStatus.textContent = 'Use your location before sorting by nearest shops.';
                            document.getElementById('useLocationButton').focus();
                            return;
                        }
                        if (activeFilters.has(filter)) activeFilters.delete(filter); else activeFilters.add(filter);
                        const active = activeFilters.has(filter);
                        button.classList.toggle('active', active);
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                        const visible = renderList();
                        fitVisibleShops(visible);
                    });
                });

                document.getElementById('useLocationButton').addEventListener('click', useCustomerLocation);
                resultsToggle.addEventListener('click', function () {
                    const collapsed = resultsPanel.classList.toggle('collapsed');
                    resultsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    window.setTimeout(function () { map.invalidateSize(); }, 240);
                });

                createMarkers();
                const initialVisible = renderList();
                fitVisibleShops(initialVisible);
                if (selectedShopId > 0) {
                    window.setTimeout(function () { selectShop(selectedShopId, true); }, 250);
                }
                window.addEventListener('resize', function () { map.invalidateSize(); });
            });
        </script>
    <?php endif; ?>
</body>
</html>
