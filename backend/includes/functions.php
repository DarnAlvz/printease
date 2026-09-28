<?php
require_once __DIR__ . "/cache.php";

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function dbColumnExists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ss", $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $cache[$key] = ((int) ($row['total'] ?? 0)) > 0;

    return $cache[$key];
}

function customerOrderPrivacyColumnExists(mysqli $conn): bool {
    return dbColumnExists($conn, 'orders', 'customer_deleted_at');
}

function customerOrderPrivacySql(mysqli $conn, string $alias = ''): string {
    if (!customerOrderPrivacyColumnExists($conn)) {
        return '';
    }

    $prefix = trim($alias) !== '' ? trim($alias) . '.' : '';
    return " AND {$prefix}customer_deleted_at IS NULL";
}

function redirect($path) {
    header("Location: $path");
    exit();
}

function setFlash($key, $value) {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['flash'][$key] = $value;
}

function getFlash($key, $default = '') {
    if (session_status() !== PHP_SESSION_ACTIVE) return $default;
    $value = $_SESSION['flash'][$key] ?? $default;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function normalizeToastStatus($status) {
    $status = strtolower(trim((string) $status));

    return match ($status) {
        'success', 'verified' => 'success',
        'error', 'danger', 'rejected' => 'error',
        'warning', 'pending', 'incomplete' => 'warning',
        default => 'info',
    };
}

function isInternalAppUrl($url) {
    $url = trim((string) $url);
    if ($url === '' || str_starts_with($url, '//')) return false;

    if (defined('BASE_URL') && str_starts_with($url, BASE_URL)) return true;
    return str_starts_with($url, '/') && !str_contains($url, "\n") && !str_contains($url, "\r");
}

function printEaseFileUrl($path) {
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    if (str_starts_with($path, '//')) return 'https:' . $path;

    return defined('BASE_URL') ? BASE_URL . ltrim($path, '/') : $path;
}

function setToast($message, $status = 'info', array $options = []) {
    if (!isset($_SESSION['toasts']) || !is_array($_SESSION['toasts'])) {
        $_SESSION['toasts'] = [];
    }

    $_SESSION['toasts'][] = [
        'message' => (string) $message,
        'status' => normalizeToastStatus($status),
        'title' => trim((string) ($options['title'] ?? '')),
        'action_label' => trim((string) ($options['action_label'] ?? '')),
        'action_url' => isInternalAppUrl($options['action_url'] ?? '') ? (string) $options['action_url'] : '',
    ];

    $_SESSION['toasts'] = array_slice($_SESSION['toasts'], -10);
}

function setMessage($message, array $options = []) {
    setToast($message, 'success', $options);
}

function setError($message, array $options = []) {
    setToast($message, 'error', $options);
}

function consumeToasts() {
    $toasts = [];

    if (!empty($_SESSION['toasts']) && is_array($_SESSION['toasts'])) {
        foreach ($_SESSION['toasts'] as $toast) {
            if (!is_array($toast) || empty($toast['message'])) {
                continue;
            }

            $toasts[] = [
                'message' => (string) $toast['message'],
                'status' => normalizeToastStatus($toast['status'] ?? 'info'),
                'title' => trim((string) ($toast['title'] ?? '')),
                'action_label' => trim((string) ($toast['action_label'] ?? '')),
                'action_url' => isInternalAppUrl($toast['action_url'] ?? '') ? (string) $toast['action_url'] : '',
            ];
        }
    }
    unset($_SESSION['toasts']);

    if (isset($_SESSION['message'])) {
        $toasts[] = ['message' => (string) $_SESSION['message'], 'status' => 'success', 'title' => '', 'action_label' => '', 'action_url' => ''];
        unset($_SESSION['message']);
    }

    if (isset($_SESSION['error'])) {
        $toasts[] = ['message' => (string) $_SESSION['error'], 'status' => 'error', 'title' => '', 'action_label' => '', 'action_url' => ''];
        unset($_SESSION['error']);
    }

    return $toasts;
}

function showMessage() {
    foreach (consumeToasts() as $toast) {
        $classes = $toast['status'] === 'error'
            ? 'bg-red-100 text-red-700'
            : ($toast['status'] === 'warning'
                ? 'bg-yellow-100 text-yellow-800'
                : ($toast['status'] === 'info' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'));
        echo "<div class='" . $classes . " p-3 rounded-xl mb-4 text-sm'>" . e($toast['message']) . "</div>";
    }
}

function activityAuditValue($value) {
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return (string) $value;
}

function activityRequestIp() {
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($ip === '') {
        $forwarded_for = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded_for !== '') {
            $ip = trim(explode(',', $forwarded_for)[0]);
        }
    }

    return $ip !== '' ? substr($ip, 0, 45) : null;
}

function activityRequestUserAgent() {
    $user_agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $user_agent !== '' ? substr($user_agent, 0, 255) : null;
}

function logActivity($conn, $user_id, $action, $module, array $options = []) {
    $target_type = trim((string) ($options['target_type'] ?? '')) ?: null;
    $target_id = isset($options['target_id']) && $options['target_id'] !== '' ? (int) $options['target_id'] : null;
    $old_value = activityAuditValue($options['old_value'] ?? null);
    $new_value = activityAuditValue($options['new_value'] ?? null);
    $ip_address = activityRequestIp();
    $user_agent = activityRequestUserAgent();

    $sql = "INSERT INTO activity_logs
            (user_id, action, module, target_type, target_id, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "isssissss",
            $user_id,
            $action,
            $module,
            $target_type,
            $target_id,
            $old_value,
            $new_value,
            $ip_address,
            $user_agent
        );
        return mysqli_stmt_execute($stmt);
    }

    $fallback_sql = "INSERT INTO activity_logs (user_id, action, module) VALUES (?, ?, ?)";
    $fallback_stmt = mysqli_prepare($conn, $fallback_sql);
    if (!$fallback_stmt) {
        return false;
    }

    mysqli_stmt_bind_param($fallback_stmt, "iss", $user_id, $action, $module);
    return mysqli_stmt_execute($fallback_stmt);
}

function sendNotification($conn, $user_id, $message, array $options = []) {
    $type = trim((string) ($options['type'] ?? 'general')) ?: 'general';
    $title = trim((string) ($options['title'] ?? 'Notification')) ?: 'Notification';
    $target_url = isInternalAppUrl($options['target_url'] ?? '') ? (string) $options['target_url'] : null;
    $metadata = $options['metadata'] ?? null;
    $metadata_json = $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO notifications (user_id, type, title, message, target_url, metadata_json)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssss", $user_id, $type, $title, $message, $target_url, $metadata_json);
    $result = mysqli_stmt_execute($stmt);

    if ($result) {
        cacheInvalidate("notifications:{$user_id}");
    }

    return $result;
}

function sendRoleNotification($conn, $role, $message, array $options = []) {
    $sql = "SELECT user_id FROM users WHERE role = ? AND account_status NOT IN ('rejected', 'inactive')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $user_ids = [];
    while ($user = mysqli_fetch_assoc($result)) {
        $user_ids[] = (int) $user['user_id'];
    }

    if (empty($user_ids)) {
        return;
    }

    $type = trim((string) ($options['type'] ?? 'general')) ?: 'general';
    $title = trim((string) ($options['title'] ?? 'Notification')) ?: 'Notification';
    $target_url = isInternalAppUrl($options['target_url'] ?? '') ? (string) $options['target_url'] : null;
    $metadata = $options['metadata'] ?? null;
    $metadata_json = $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $placeholders = [];
    $params = [];
    $types = '';

    foreach ($user_ids as $uid) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?)';
        $types .= 'isssss';
        $params[] = $uid;
        $params[] = $type;
        $params[] = $title;
        $params[] = $message;
        $params[] = $target_url;
        $params[] = $metadata_json;
    }

    $bulk_sql = "INSERT INTO notifications (user_id, type, title, message, target_url, metadata_json)
                 VALUES " . implode(', ', $placeholders);
    $bulk_stmt = mysqli_prepare($conn, $bulk_sql);
    mysqli_stmt_bind_param($bulk_stmt, $types, ...$params);
    $result = mysqli_stmt_execute($bulk_stmt);

    if ($result) {
        foreach ($user_ids as $uid) {
            cacheInvalidate("notifications:{$uid}");
        }
    }
}

function notificationRelativeTime($datetime) {
    $timestamp = strtotime((string) $datetime);
    if (!$timestamp) return '';
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) return 'Just now';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ago';
    if ($seconds < 86400) return floor($seconds / 3600) . 'h ago';
    if ($seconds < 604800) return floor($seconds / 86400) . 'd ago';
    return date('M j, Y', $timestamp);
}

function requireAuth()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../pages/login.php");
        exit;
    }
}

function requirePdfParserAutoload() {
    static $loaded = false;

    if ($loaded) {
        return true;
    }

    if (class_exists(\setasign\Fpdi\PdfParser\PdfParser::class)) {
        $loaded = true;
        return true;
    }

    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    $loaded = class_exists(\setasign\Fpdi\PdfParser\PdfParser::class);
    return $loaded;
}

function getPdfPageCountFromFpdi($filePath) {
    if (!is_file($filePath) || !is_readable($filePath) || !requirePdfParserAutoload()) {
        return null;
    }

    try {
        $stream = \setasign\Fpdi\PdfParser\StreamReader::createByFile($filePath);
        $parser = new \setasign\Fpdi\PdfParser\PdfParser($stream);
        $reader = new \setasign\Fpdi\PdfReader\PdfReader($parser);
        $page_count = $reader->getPageCount();
        $parser->cleanUp();

        return max(1, (int) $page_count);
    } catch (Throwable $exception) {
        return null;
    }
}

function validatePdfStructure($filePath) {
    return getPdfPageCountFromFpdi($filePath) !== null;
}

function validateImageStructure($filePath, $expected_mime) {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return false;
    }

    $info = @getimagesize($filePath);
    if ($info === false || empty($info['mime'])) {
        return false;
    }

    $allowed_mimes = ['image/jpeg', 'image/png'];
    if (!in_array($expected_mime, $allowed_mimes, true)) {
        return false;
    }

    if ($info['mime'] !== $expected_mime) {
        return false;
    }

    return true;
}

// Single source of truth for Word / WPS uploads.
// Used by both Document Printing and other services so allowlists never drift.
function isOfficeDocumentExtension($extension) {
    return in_array(strtolower((string) $extension), ['doc', 'docx', 'wps'], true);
}

function officeDocumentAllowedMime($extension, $mime) {
    $extension = strtolower((string) $extension);
    $mime = strtolower((string) $mime);
    $allowed_by_extension = [
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'doc' => [
            'application/msword',
            'application/octet-stream',
            'application/x-ole-storage',
        ],
        'wps' => [
            'application/vnd.ms-works',
            'application/x-wps',
            'application/wps-office',
            'application/msword',
            'application/zip',
            'application/octet-stream',
        ],
    ];

    $allowed = $allowed_by_extension[$extension] ?? [];
    return in_array($mime, $allowed, true);
}

function serviceUploadAllowedExtensions() {
    return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'wps'];
}

// Photo/ID services accept images only (gallery picker is correct there).
// Lamination/Tarpaulin/Invitation accept images + PDF/DOC/DOCX (Files picker).
// NOTE: doc/wps stay supported only via the generic list above for legacy
// rows; the per-service matrix below is the enforced rule for new uploads.
function servicePhotoOnlyType($service_type) {
    return in_array(trim((string) $service_type), ['Photo Printing', 'ID Printing'], true);
}

function serviceUploadAllowedExtensionsFor($service_type) {
    if (servicePhotoOnlyType($service_type)) {
        return ['jpg', 'jpeg', 'png'];
    }
    return ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
}

function serviceUploadAllowedMimesFor($service_type) {
    if (servicePhotoOnlyType($service_type)) {
        return ['image/jpeg', 'image/png'];
    }
    return ['application/pdf', 'image/jpeg', 'image/png'];
}

function serviceUploadTypeErrorFor($service_type) {
    if (servicePhotoOnlyType($service_type)) {
        return "Attachment must be a JPG or PNG file.";
    }
    return "Attachment must be a JPG, PNG, PDF, DOC, or DOCX file.";
}

function orderMaxUploadBytes() {
    return 25 * 1024 * 1024;
}

// Maps PHP upload error codes to user-safe messages.
// Never expose ini paths or server internals to the customer.
function orderUploadErrorMessage($error, $max_bytes) {
    $max_mb = max(1, (int) round($max_bytes / (1024 * 1024)));
    switch ((int) $error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File is too large for the server. Please upload a file {$max_mb}MB or smaller.";
        case UPLOAD_ERR_PARTIAL:
            return "Upload was interrupted. Please try uploading again.";
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return "Upload is temporarily unavailable. Please try again later.";
        default:
            return "Please upload a valid file.";
    }
}

// Detects post_max_size overflow where PHP empties $_POST and $_FILES.
// Must run before reading $_POST keys to avoid undefined-key warnings on live.
function orderPostOverflowed() {
    if (!empty($_POST) || !empty($_FILES)) {
        return false;
    }
    $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    return $method === 'POST' && $content_length > 0;
}

function orderFinfoMime($tmp_path) {
    if (!class_exists('finfo')) {
        return null;
    }
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = @$finfo->file($tmp_path);
        if ($mime === false || $mime === '') {
            return '';
        }
        return strtolower((string) $mime);
    } catch (Throwable $exception) {
        return null;
    }
}

function getPdfPageCount($filePath, $fallback = 1) {
    $fallback = max(1, (int) $fallback);

    $fpdi_count = getPdfPageCountFromFpdi($filePath);
    if ($fpdi_count !== null) {
        return $fpdi_count;
    }

    if (class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->pingImage($filePath);
            $page_count = $imagick->getNumberImages();
            $imagick->clear();
            $imagick->destroy();

            return max(1, (int) $page_count);
        } catch (Throwable $exception) {
            return getPdfPageCountFromText($filePath, $fallback);
        }
    }

    return getPdfPageCountFromText($filePath, $fallback);
}

function getPdfPageCountFromText($filePath, $fallback = 1) {
    $fallback = max(1, (int) $fallback);
    if (!is_readable($filePath)) {
        return $fallback;
    }

    $contents = @file_get_contents($filePath);
    if ($contents === false || $contents === '') {
        return $fallback;
    }

    if (preg_match_all('/\/Type\s*\/Page\b/', $contents, $matches)) {
        return max(1, count($matches[0]));
    }

    return $fallback;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function validateCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("Invalid security token. Please go back and try again.");
    }
}

?>
