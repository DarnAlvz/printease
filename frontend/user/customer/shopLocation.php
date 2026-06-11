<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";

requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

$search = trim($_GET['search'] ?? '');
$selected_shop_id = isset($_GET['shop_id']) ? (int) $_GET['shop_id'] : 0;

$sql = "SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.contact_number, ps.shop_status,
               COUNT(ss.service_id) AS service_count,
               MIN(ss.price_per_page) AS starting_price,
               ps.latitude, ps.longitude
        FROM print_shops ps
        LEFT JOIN shop_services ss ON ps.shop_id = ss.shop_id AND ss.is_available = 1
        WHERE ps.permit_status = 'verified'
          AND ps.shop_status IN ('available', 'busy')
          AND ps.latitude IS NOT NULL
          AND ps.longitude IS NOT NULL";

$types = '';
$params = [];
if ($search !== '') {
    $sql .= " AND (ps.shop_name LIKE ? OR ps.shop_address LIKE ?)";
    $like = "%$search%";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
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
        'name' => $shop['shop_name'],
        'address' => $shop['shop_address'],
        'contact' => $shop['contact_number'] ?? 'N/A',
        'status' => $shop['shop_status'],
        'service_count' => (int) $shop['service_count'],
        'starting_price' => (float) $shop['starting_price'],
        'lat' => (float) $shop['latitude'],
        'lng' => (float) $shop['longitude']
    ];
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Nearby Print Shops</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">
        <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
            <h1 class="text-2xl font-bold">Nearby Print Shops</h1>
            <p class="text-sm opacity-90 mt-1">Find verified shops and order from the closest option.</p>
        </header>

        <main class="p-4 md:p-6">
            <?php showMessage(); ?>

            <form method="GET" class="mb-4">
                <input type="text" name="search" value="<?php echo e($search); ?>"
                    placeholder="Search shop or address..."
                    class="w-full p-3 rounded-xl border focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if ($selected_shop_id > 0): ?>
                    <input type="hidden" name="shop_id" value="<?php echo $selected_shop_id; ?>">
                <?php endif; ?>
            </form>

            <?php if (empty($shop_locations)): ?>
                <div class="bg-white p-5 rounded-2xl shadow text-center">
                    <h2 class="font-bold text-gray-800">No mapped shops found</h2>
                    <p class="text-sm text-gray-500 mt-1">Try another search or check again after shops set their map pin.</p>
                    <a href="shops.php" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded-xl">
                        Browse Shops
                    </a>
                </div>
            <?php else: ?>
                <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.8fr)] gap-4">
                    <div class="bg-white rounded-2xl shadow overflow-hidden">
                        <div class="p-4 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-gray-800">Shop Map</h2>
                                <p class="text-sm text-gray-500" id="locationStatus">Requesting your location to sort nearest shops.</p>
                            </div>
                            <button type="button" id="useLocationButton"
                                class="bg-blue-600 text-white px-4 py-2 rounded-xl font-semibold">
                                Use My Location
                            </button>
                        </div>
                        <div id="map" class="w-full h-[62vh] min-h-[420px]"></div>
                    </div>

                    <div class="bg-white rounded-2xl shadow overflow-hidden">
                        <div class="p-4 border-b">
                            <h2 class="font-bold text-gray-800">Available Shops</h2>
                            <p class="text-sm text-gray-500">Nearest shops move to the top after location access.</p>
                        </div>
                        <div id="shopList" class="divide-y max-h-[62vh] overflow-y-auto"></div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t shadow md:static md:shadow-none md:border md:rounded-2xl md:mt-6">
        <div class="max-w-md md:max-w-6xl mx-auto grid grid-cols-5 text-center text-xs">
            <a href="dashboard.php" class="py-3 text-gray-600">Home</a>
            <a href="shops.php" class="py-3 text-gray-600">Shops</a>
            <a href="shopLocation.php" class="py-3 text-blue-700 font-bold">Map</a>
            <a href="orders.php" class="py-3 text-gray-600">Track</a>
            <a href="profile.php" class="py-3 text-gray-600">Profile</a>
        </div>
    </nav>

    <?php if (!empty($shop_locations)): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const shops = <?php echo json_encode($shop_locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                const selectedShopId = <?php echo $selected_shop_id; ?>;
                const calbayogCenter = [12.0432, 124.5946];
                const map = L.map('map').setView(calbayogCenter, 13);
                const markers = new Map();
                let customerMarker = null;
                let customerLocation = null;

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function distanceKm(from, to) {
                    const earthRadiusKm = 6371;
                    const dLat = (to.lat - from.lat) * Math.PI / 180;
                    const dLng = (to.lng - from.lng) * Math.PI / 180;
                    const lat1 = from.lat * Math.PI / 180;
                    const lat2 = to.lat * Math.PI / 180;
                    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                        Math.cos(lat1) * Math.cos(lat2) *
                        Math.sin(dLng / 2) * Math.sin(dLng / 2);
                    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                }

                function formatDistance(distance) {
                    if (distance === null || typeof distance === 'undefined') {
                        return 'Distance unavailable';
                    }
                    return distance < 1
                        ? Math.round(distance * 1000) + ' m away'
                        : distance.toFixed(2) + ' km away';
                }

                function statusClasses(status) {
                    return status === 'available'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700';
                }

                function statusLabel(status) {
                    return status === 'available' ? 'Available' : 'Busy';
                }

                function sortedShops() {
                    return shops.slice().sort(function (a, b) {
                        if (customerLocation) {
                            return a.distance - b.distance;
                        }
                        if (a.status !== b.status) {
                            return a.status === 'available' ? -1 : 1;
                        }
                        return a.name.localeCompare(b.name);
                    });
                }

                function popupContent(shop) {
                    return `
                        <strong>${escapeHtml(shop.name)}</strong><br>
                        ${escapeHtml(shop.address)}<br>
                        Status: ${escapeHtml(statusLabel(shop.status))}<br>
                        Services: ${shop.service_count}<br>
                        Starting at: &#8369;${Number(shop.starting_price).toFixed(2)}<br>
                        ${customerLocation ? formatDistance(shop.distance) + '<br>' : ''}
                        <a href="place_order.php?shop_id=${shop.shop_id}" class="text-blue-600 font-semibold">Order Here</a>
                    `;
                }

                function renderMarkers() {
                    shops.forEach(function (shop) {
                        let marker = markers.get(shop.shop_id);
                        if (!marker) {
                            marker = L.marker([shop.lat, shop.lng]).addTo(map);
                            markers.set(shop.shop_id, marker);
                        }
                        marker.bindPopup(popupContent(shop));
                    });
                }

                function renderList() {
                    const list = document.getElementById('shopList');
                    list.innerHTML = sortedShops().map(function (shop) {
                        return `
                            <article class="p-4 ${shop.shop_id === selectedShopId ? 'bg-blue-50' : 'bg-white'}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-gray-800">${escapeHtml(shop.name)}</h3>
                                        <p class="text-sm text-gray-500 mt-1">${escapeHtml(shop.address)}</p>
                                        <p class="text-sm text-gray-500">Contact: ${escapeHtml(shop.contact)}</p>
                                    </div>
                                    <span class="text-xs px-3 py-1 rounded-full ${statusClasses(shop.status)}">
                                        ${statusLabel(shop.status)}
                                    </span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                    <span class="font-semibold text-blue-700">${formatDistance(shop.distance)}</span>
                                    <span class="text-gray-500">&#8369;${Number(shop.starting_price).toFixed(2)} start</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <button type="button" data-focus-shop="${shop.shop_id}"
                                        class="border text-gray-700 py-2 rounded-xl font-semibold">
                                        View Map
                                    </button>
                                    <a href="place_order.php?shop_id=${shop.shop_id}"
                                        class="bg-blue-600 text-white text-center py-2 rounded-xl font-semibold">
                                        Order Here
                                    </a>
                                </div>
                            </article>
                        `;
                    }).join('');

                    list.querySelectorAll('[data-focus-shop]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const shopId = parseInt(button.dataset.focusShop, 10);
                            focusShop(shopId, true);
                        });
                    });
                }

                function fitMapToVisiblePoints() {
                    const points = shops.map(function (shop) {
                        return [shop.lat, shop.lng];
                    });
                    if (customerLocation) {
                        points.push([customerLocation.lat, customerLocation.lng]);
                    }
                    map.fitBounds(L.latLngBounds(points), { padding: [28, 28], maxZoom: 16 });
                }

                function focusShop(shopId, openPopup) {
                    const shop = shops.find(function (item) {
                        return item.shop_id === shopId;
                    });
                    const marker = markers.get(shopId);
                    if (!shop || !marker) {
                        return;
                    }
                    map.setView([shop.lat, shop.lng], 17);
                    if (openPopup) {
                        marker.openPopup();
                    }
                }

                function updateDistances() {
                    if (!customerLocation) {
                        return;
                    }
                    shops.forEach(function (shop) {
                        shop.distance = distanceKm(customerLocation, { lat: shop.lat, lng: shop.lng });
                    });
                }

                function useCustomerLocation() {
                    const status = document.getElementById('locationStatus');
                    if (!navigator.geolocation) {
                        status.textContent = 'Location is not supported by this browser. Showing mapped shops only.';
                        renderList();
                        return;
                    }

                    status.textContent = 'Waiting for location permission...';
                    navigator.geolocation.getCurrentPosition(function (position) {
                        customerLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        updateDistances();

                        if (!customerMarker) {
                            customerMarker = L.circleMarker([customerLocation.lat, customerLocation.lng], {
                                radius: 8,
                                color: '#1d4ed8',
                                fillColor: '#2563eb',
                                fillOpacity: 0.9
                            }).addTo(map).bindPopup('Your current location');
                        } else {
                            customerMarker.setLatLng([customerLocation.lat, customerLocation.lng]);
                        }

                        status.textContent = 'Showing nearest shops first from your current location.';
                        renderMarkers();
                        renderList();
                        fitMapToVisiblePoints();
                    }, function () {
                        status.textContent = 'Location access was not allowed. Showing mapped shops without distance sorting.';
                        renderList();
                        fitMapToVisiblePoints();
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                }

                renderMarkers();
                renderList();
                fitMapToVisiblePoints();

                if (selectedShopId > 0) {
                    setTimeout(function () {
                        focusShop(selectedShopId, true);
                    }, 250);
                }

                document.getElementById('useLocationButton').addEventListener('click', useCustomerLocation);
                useCustomerLocation();
            });
        </script>
    <?php endif; ?>

</body>

</html>
