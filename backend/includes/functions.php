<?php

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: $path");
    exit();
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

function logActivity($conn, $user_id, $action, $module) {
    $sql = "INSERT INTO activity_logs (user_id, action, module) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $module);
    mysqli_stmt_execute($stmt);
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
    return mysqli_stmt_execute($stmt);
}

function sendRoleNotification($conn, $role, $message, array $options = []) {
    $sql = "SELECT user_id FROM users WHERE role = ? AND account_status NOT IN ('rejected', 'inactive')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($user = mysqli_fetch_assoc($result)) {
        sendNotification($conn, (int) $user['user_id'], $message, $options);
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

?>

