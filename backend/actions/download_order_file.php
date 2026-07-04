<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$owner_id = (int) ($_SESSION['user_id'] ?? 0);
$file_id = (int) ($_GET['file_id'] ?? 0);
$check_only = isset($_GET['check']) && (string) $_GET['check'] === '1';

if ($file_id <= 0) {
    if ($check_only) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid file.']);
        exit();
    }

    http_response_code(400);
    exit("Invalid file.");
}

$sql = "SELECT uf.file_id, uf.file_name, uf.file_path, uf.file_type, o.order_id
        FROM uploaded_files uf
        JOIN orders o ON uf.order_id = o.order_id
        JOIN print_shops ps ON o.shop_id = ps.shop_id
        WHERE uf.file_id = ?
          AND ps.owner_id = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $file_id, $owner_id);
mysqli_stmt_execute($stmt);
$file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$file) {
    if ($check_only) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit();
    }

    http_response_code(404);
    exit("File not found.");
}

function safeDownloadName($name, $fallback = 'order-file')
{
    $name = trim((string) $name);
    if ($name === '') {
        $name = $fallback;
    }

    $name = preg_replace('/[^\w.\- ()]+/', '_', $name);
    return trim($name, '._ ') ?: $fallback;
}

function sendDownloadHeaders($file_name, $mime_type = 'application/octet-stream', $content_length = null)
{
    header('Content-Type: ' . ($mime_type ?: 'application/octet-stream'));
    if ($content_length !== null && $content_length >= 0) {
        header('Content-Length: ' . (int) $content_length);
    }
    header('Content-Disposition: attachment; filename="' . addcslashes($file_name, '"\\') . '"');
    header('X-Content-Type-Options: nosniff');
}

function sendJsonResponse(array $payload, $status_code = 200)
{
    http_response_code((int) $status_code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function validateRemoteOrderFileUrl($url)
{
    $url = trim((string) $url);

    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');

    if ($scheme !== 'https' || $host !== 'res.cloudinary.com' || $path === '' || $path === '/') {
        return null;
    }

    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
        return null;
    }

    if (preg_match('/[\r\n\0]/', $url)) {
        return null;
    }

    $validated = 'https://res.cloudinary.com' . $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $validated .= '?' . $parts['query'];
    }

    return $validated;
}

function redirectToRemoteDownload($url)
{
    header('Location: ' . $url);
    header('X-Content-Type-Options: nosniff');
    exit();
}

function failFileDownload($message = 'File not found.', $status_code = 404)
{
    global $check_only;

    if ($check_only) {
        sendJsonResponse(['success' => false, 'message' => $message], $status_code);
    }

    http_response_code((int) $status_code);
    exit($message);
}

function resolveLocalOrderFilePath($file_path)
{
    $root = realpath(__DIR__ . "/../..");
    $orders_root = realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'orders');

    if ($root === false || $orders_root === false) {
        return null;
    }

    $relative_path = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $file_path), DIRECTORY_SEPARATOR);
    $absolute_path = realpath($root . DIRECTORY_SEPARATOR . $relative_path);

    if ($absolute_path === false || !is_file($absolute_path)) {
        return null;
    }

    $orders_root = rtrim(normalizePathForCompare($orders_root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $absolute_compare = normalizePathForCompare($absolute_path);

    return str_starts_with($absolute_compare, $orders_root) ? $absolute_path : null;
}

function normalizePathForCompare($path)
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $path);
    return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
}

$file_path = trim((string) ($file['file_path'] ?? ''));
$file_name = safeDownloadName($file['file_name'] ?? '', 'order-file-' . $file_id);

if (preg_match('/^https?:\/\//i', $file_path)) {
    $remote_url = validateRemoteOrderFileUrl($file_path);

    if ($remote_url === null) {
        failFileDownload('Remote file URL is not allowed.', 400);
    }

    if ($check_only) {
        sendJsonResponse([
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'remote' => true,
            'url' => $remote_url,
        ]);
    }

    redirectToRemoteDownload($remote_url);
}

if (str_starts_with($file_path, '//')) {
    $remote_url = validateRemoteOrderFileUrl($file_path);

    if ($remote_url === null) {
        failFileDownload('Remote file URL is not allowed.', 400);
    }

    if ($check_only) {
        sendJsonResponse([
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'remote' => true,
            'url' => $remote_url,
        ]);
    }

    redirectToRemoteDownload($remote_url);
}

$absolute_path = resolveLocalOrderFilePath($file_path);

if ($absolute_path === null) {
    failFileDownload('File not found.', 404);
}

$mime_type = mime_content_type($absolute_path) ?: 'application/octet-stream';

if ($check_only) {
    sendJsonResponse([
        'success' => true,
        'file_id' => $file_id,
        'file_name' => $file_name,
        'remote' => false,
        'mime_type' => $mime_type,
        'content_length' => filesize($absolute_path),
    ]);
}

sendDownloadHeaders($file_name, $mime_type, filesize($absolute_path));
readfile($absolute_path);
exit();
?>
