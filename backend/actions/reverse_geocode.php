<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    if (authIsAjaxRequest()) {
        authJsonResponse(false, 'Authentication required.', 401);
    }
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit();
}

$lat_raw = trim((string) ($_GET['lat'] ?? ''));
$lng_raw = trim((string) ($_GET['lng'] ?? ''));

if ($lat_raw === '' || $lng_raw === '' || !is_numeric($lat_raw) || !is_numeric($lng_raw)) {
    authJsonResponse(false, 'Missing or invalid coordinates.', 400);
}

$lat = (float) $lat_raw;
$lng = (float) $lng_raw;

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    authJsonResponse(false, 'Coordinates are out of range.', 400);
}

function ensureGeocodeCacheTable(mysqli $conn): void
{
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS geocode_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lat DECIMAL(10,7) NOT NULL,
        lng DECIMAL(10,7) NOT NULL,
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_coord (lat, lng)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureGeocodeCacheTable($conn);

function reverseGeocodeLookup(mysqli $conn, float $lat, float $lng): ?string
{
    $stmt = mysqli_prepare($conn, "SELECT address FROM geocode_cache WHERE lat = ? AND lng = ? LIMIT 1");
    $lat_key = round($lat, 7);
    $lng_key = round($lng, 7);
    mysqli_stmt_bind_param($stmt, "dd", $lat_key, $lng_key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row ? (string) $row['address'] : null;
}

function reverseGeocodeStore(mysqli $conn, float $lat, float $lng, string $address): void
{
    $stmt = mysqli_prepare($conn, "INSERT INTO geocode_cache (lat, lng, address) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE address = VALUES(address)");
    $lat_key = round($lat, 7);
    $lng_key = round($lng, 7);
    mysqli_stmt_bind_param($stmt, "dds", $lat_key, $lng_key, $address);
    mysqli_stmt_execute($stmt);
}

function reverseGeocodeRemote(float $lat, float $lng): ?string
{
    $url = "https://nominatim.openstreetmap.org/reverse"
        . "?format=json&zoom=18&addressdetails=1"
        . "&lat=" . urlencode((string) $lat)
        . "&lon=" . urlencode((string) $lng);

    $headers = [
        'User-Agent: PrintEase/1.0 (+reverse-geocode-proxy)',
        'Accept: application/json',
    ];

    $body = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code < 200 || $http_code >= 300) {
            return null;
        }
    } else {
        $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 8, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }
    }

    if ($body === false || trim((string) $body) === '' || trim((string) $body[0]) !== '{') {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['display_name'])) {
        return null;
    }

    return (string) $data['display_name'];
}

$cached = reverseGeocodeLookup($conn, $lat, $lng);
if ($cached !== null && $cached !== '') {
    echo json_encode(['success' => true, 'address' => $cached]);
    exit();
}

$address = reverseGeocodeRemote($lat, $lng);
if ($address === null || $address === '') {
    authJsonResponse(false, 'Location found, but address was not detected. Please type your address manually.');
}

reverseGeocodeStore($conn, $lat, $lng, $address);
echo json_encode(['success' => true, 'address' => $address]);
?>
