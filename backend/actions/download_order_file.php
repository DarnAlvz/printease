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

function orderFileExtension($file_name, $file_type = '')
{
    $from_name = strtolower(pathinfo((string) $file_name, PATHINFO_EXTENSION));
    if ($from_name !== '') {
        return $from_name;
    }

    return strtolower(trim((string) $file_type));
}

// Single source of truth for office handling (mirrored in orders.php + live-updates.js).
// viewable: doc/docx -> preview via Office viewer (new tab), download via attachment.
// download-only: wps -> force download always (Office viewer has no .wps support).
// native: pdf/jpg/jpeg/png -> browser can render, keep existing behavior.
function officeFileKind($extension)
{
    $ext = strtolower(trim((string) $extension));

    if ($ext === 'doc' || $ext === 'docx') {
        return 'viewable';
    }

    if ($ext === 'wps') {
        return 'download-only';
    }

    return 'native';
}

function buildOfficeViewerUrl($remote_url)
{
    return 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($remote_url);
}

function buildCloudinaryAttachmentUrl($remote_url, $file_name)
{
    $safe = safeDownloadName($file_name, 'order-file');
    // Cloudinary raw delivery supports fl_attachment to force download instead of
    // inline render (which browsers cannot do for docx/wps -> blank white/black tab).
    $marker = '/upload/';
    $pos = strpos($remote_url, $marker);
    if ($pos === false) {
        return $remote_url;
    }

    $prefix = substr($remote_url, 0, $pos + strlen($marker));
    $suffix = substr($remote_url, $pos + strlen($marker));
    // Avoid double-injecting when already an attachment URL.
    if (str_starts_with($suffix, 'fl_attachment')) {
        return $remote_url;
    }

    return $prefix . 'fl_attachment:' . rawurlencode($safe) . '/' . $suffix;
}

function buildOrderFileDownloadUrl($file_id)
{
    $base = defined('BASE_URL') ? (string) BASE_URL : '/';
    return rtrim($base, '/') . '/backend/actions/download_order_file.php?file_id=' . (int) $file_id . '&mode=download';
}

function officeDownloadMimeType($extension)
{
    $ext = strtolower(trim((string) $extension));
    if ($ext === 'docx') {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    if ($ext === 'doc') {
        return 'application/msword';
    }
    if ($ext === 'pdf') {
        return 'application/pdf';
    }

    return 'application/octet-stream';
}

// Proxy the remote file through PHP so mode=download always triggers a real
// file download (same-origin + attachment headers). Streams to avoid loading
// large files into memory on live. Falls back to redirect on preflight failure.
function proxyRemoteDownload($remote_url, $file_name, $file_ext, $fallback_url)
{
    if (!function_exists('curl_init')) {
        redirectToRemoteDownload($fallback_url);
    }

    $max_bytes = 30 * 1024 * 1024;

    // Preflight HEAD: reject oversized/missing remotes before sending headers.
    $head = curl_init($remote_url);
    if ($head === false) {
        redirectToRemoteDownload($fallback_url);
    }
    curl_setopt($head, CURLOPT_NOBODY, true);
    curl_setopt($head, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($head, CURLOPT_MAXREDIRS, 3);
    curl_setopt($head, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($head, CURLOPT_TIMEOUT, 20);
    curl_setopt($head, CURLOPT_FAILONERROR, true);
    curl_setopt($head, CURLOPT_USERAGENT, 'PrintEase-Order-Download/1.0');
    curl_exec($head);
    $head_code = (int) curl_getinfo($head, CURLINFO_HTTP_CODE);
    $head_errno = curl_errno($head);
    $head_length = (int) curl_getinfo($head, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($head);

    if ($head_errno !== 0 || $head_code < 200 || $head_code >= 300) {
        redirectToRemoteDownload($fallback_url);
    }
    if ($head_length > 0 && $head_length > $max_bytes) {
        redirectToRemoteDownload($fallback_url);
    }

    $mime = officeDownloadMimeType($file_ext);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // No Content-Length when unknown: chunked streaming still downloads fine.
    sendDownloadHeaders($file_name, $mime, $head_length > 0 ? $head_length : null);
    ignore_user_abort(true);
    set_time_limit(90);

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        redirectToRemoteDownload($fallback_url);
    }

    $ch = curl_init($remote_url);
    if ($ch === false) {
        fclose($out);
        redirectToRemoteDownload($fallback_url);
    }
    $downloaded = 0;
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PrintEase-Order-Download/1.0');
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use ($out, &$downloaded, $max_bytes) {
        $len = strlen($chunk);
        $downloaded += $len;
        if ($downloaded > $max_bytes) {
            return 0;
        }
        $written = fwrite($out, $chunk);
        return $written === false ? 0 : $len;
    });
    $ok = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    fclose($out);

    if ($ok === false || $curl_errno !== 0 || $http_code < 200 || $http_code >= 300) {
        // Headers already sent: cannot redirect, just end (browser keeps partial).
        exit();
    }
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
$file_ext = orderFileExtension($file['file_name'] ?? '', $file['file_type'] ?? '');
$file_kind = officeFileKind($file_ext);
$download_mode = strtolower(trim((string) ($_GET['mode'] ?? ''))) === 'download';

if (preg_match('/^https?:\/\//i', $file_path)) {
    $remote_url = validateRemoteOrderFileUrl($file_path);

    if ($remote_url === null) {
        failFileDownload('Remote file URL is not allowed.', 400);
    }

    $viewer_url = $file_kind === 'viewable' ? buildOfficeViewerUrl($remote_url) : null;
    $attachment_url = buildCloudinaryAttachmentUrl($remote_url, $file_name);
    $download_url = buildOrderFileDownloadUrl($file_id);

    if ($check_only) {
        sendJsonResponse([
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'file_ext' => $file_ext,
            'file_kind' => $file_kind,
            'remote' => true,
            'url' => $remote_url,
            'viewer_url' => $viewer_url,
            'download_url' => $download_url,
            'attachment_url' => $attachment_url,
            // wps has no Office viewer support -> client must force download.
            'force_download' => $file_kind !== 'native',
        ]);
    }

    // Accept & Download = viewer + auto-download. mode=download proxies the raw
    // file with attachment headers so the browser always downloads (never a
    // blank inline tab). Falls back to redirect if the proxy fails.
    if ($download_mode) {
        proxyRemoteDownload($remote_url, $file_name, $file_ext, $attachment_url);
    }

    // Direct navigation fallback (old preview links): viewable office files go
    // to the viewer instead of the raw docx (which renders blank white/black).
    if ($file_kind === 'viewable' && $viewer_url !== null) {
        redirectToRemoteDownload($viewer_url);
    }

    if ($file_kind === 'download-only') {
        proxyRemoteDownload($remote_url, $file_name, $file_ext, $attachment_url);
    }

    redirectToRemoteDownload($remote_url);
}

if (str_starts_with($file_path, '//')) {
    $remote_url = validateRemoteOrderFileUrl($file_path);

    if ($remote_url === null) {
        failFileDownload('Remote file URL is not allowed.', 400);
    }

    $viewer_url = $file_kind === 'viewable' ? buildOfficeViewerUrl($remote_url) : null;
    $attachment_url = buildCloudinaryAttachmentUrl($remote_url, $file_name);
    $download_url = buildOrderFileDownloadUrl($file_id);

    if ($check_only) {
        sendJsonResponse([
            'success' => true,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'file_ext' => $file_ext,
            'file_kind' => $file_kind,
            'remote' => true,
            'url' => $remote_url,
            'viewer_url' => $viewer_url,
            'download_url' => $download_url,
            'attachment_url' => $attachment_url,
            'force_download' => $file_kind !== 'native',
        ]);
    }

    if ($download_mode) {
        proxyRemoteDownload($remote_url, $file_name, $file_ext, $attachment_url);
    }

    if ($file_kind === 'viewable' && $viewer_url !== null) {
        redirectToRemoteDownload($viewer_url);
    }

    if ($file_kind === 'download-only') {
        proxyRemoteDownload($remote_url, $file_name, $file_ext, $attachment_url);
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
        'file_ext' => $file_ext,
        'file_kind' => $file_kind,
        'remote' => false,
        'url' => buildOrderFileDownloadUrl($file_id),
        'viewer_url' => null,
        'download_url' => buildOrderFileDownloadUrl($file_id),
        'mime_type' => $mime_type,
        'content_length' => filesize($absolute_path),
        // Local files already force download via attachment headers.
        'force_download' => true,
    ]);
}

sendDownloadHeaders($file_name, $mime_type, filesize($absolute_path));
readfile($absolute_path);
exit();
?>
