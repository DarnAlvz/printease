<?php

function rateLimitClientIp()
{
    $remote_addr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ip = filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : 'unknown';

    if ($ip !== 'unknown' && rateLimitIsTrustedProxy($ip) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($forwarded[0] ?? '');
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }

    return substr((string) $ip, 0, 45);
}

function rateLimitTrustedProxies()
{
    $raw = getenv('TRUSTED_PROXIES');
    if ($raw === false || trim((string) $raw) === '') {
        return [];
    }

    $proxies = [];
    foreach (explode(',', (string) $raw) as $proxy) {
        $proxy = trim($proxy);
        if ($proxy !== '' && filter_var($proxy, FILTER_VALIDATE_IP)) {
            $proxies[] = $proxy;
        }
    }

    return $proxies;
}

function rateLimitIsTrustedProxy($ip)
{
    return in_array((string) $ip, rateLimitTrustedProxies(), true);
}

function rateLimitIdentifier($value)
{
    return strtolower(trim((string) $value));
}

function rateLimitCompositeIdentifier(...$parts)
{
    $normalized = [];

    foreach ($parts as $part) {
        $part = preg_replace('/[^a-z0-9_.:@-]+/i', '_', strtolower(trim((string) $part)));
        $part = trim($part, '_');
        if ($part !== '') {
            $normalized[] = $part;
        }
    }

    $identifier = implode('|', $normalized);
    return substr($identifier !== '' ? $identifier : 'unknown', 0, 255);
}

function rateLimitCurrentUserKey($prefix = 'user')
{
    $prefix = rateLimitIdentifier($prefix) ?: 'user';
    $user_id = (int) ($_SESSION['user_id'] ?? 0);

    if ($user_id > 0) {
        return rateLimitCompositeIdentifier($prefix . ':' . $user_id);
    }

    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        return rateLimitCompositeIdentifier('guest:' . session_id());
    }

    return 'guest:unknown';
}

function rateLimitFormatSeconds($seconds)
{
    $seconds = max(1, (int) $seconds);
    if ($seconds >= 3600) {
        $hours = (int) ceil($seconds / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }

    if ($seconds >= 60) {
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    return $seconds . ' second' . ($seconds === 1 ? '' : 's');
}

function rateLimitFind(mysqli $conn, $action, $identifier, $ip_address)
{
    $sql = "SELECT * FROM rate_limit_events WHERE action = ? AND identifier = ? AND ip_address = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $action, $identifier, $ip_address);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function rateLimitCheck(mysqli $conn, $action, $identifier, $ip_address, $max_attempts, $window_seconds, $min_interval_seconds = 0)
{
    $row = rateLimitFind($conn, $action, $identifier, $ip_address);
    if (!$row) {
        return ['allowed' => true, 'retry_after' => 0, 'reason' => 'allowed'];
    }

    $now = time();
    $window_started = strtotime((string) $row['window_started_at']);
    $last_attempt = strtotime((string) $row['last_attempt_at']);
    $blocked_until = !empty($row['blocked_until']) ? strtotime((string) $row['blocked_until']) : false;

    if ($blocked_until && $blocked_until > $now) {
        return ['allowed' => false, 'retry_after' => $blocked_until - $now, 'reason' => 'blocked'];
    }

    if ($min_interval_seconds > 0 && $last_attempt && ($now - $last_attempt) < $min_interval_seconds) {
        return ['allowed' => false, 'retry_after' => $min_interval_seconds - ($now - $last_attempt), 'reason' => 'cooldown'];
    }

    if ($window_started && ($now - $window_started) >= $window_seconds) {
        return ['allowed' => true, 'retry_after' => 0, 'reason' => 'window_reset'];
    }

    if ((int) $row['attempt_count'] >= (int) $max_attempts) {
        $retry_after = $window_started ? max(1, $window_seconds - ($now - $window_started)) : $window_seconds;
        return ['allowed' => false, 'retry_after' => $retry_after, 'reason' => 'limit'];
    }

    return ['allowed' => true, 'retry_after' => 0, 'reason' => 'allowed'];
}

function rateLimitRecord(mysqli $conn, $action, $identifier, $ip_address, $max_attempts, $window_seconds, $block_seconds = 0)
{
    $row = rateLimitFind($conn, $action, $identifier, $ip_address);
    $now = time();
    $now_sql = date('Y-m-d H:i:s', $now);

    if (!$row) {
        $blocked_until = $max_attempts <= 1 && $block_seconds > 0 ? date('Y-m-d H:i:s', $now + $block_seconds) : null;
        $sql = "INSERT INTO rate_limit_events (action, identifier, ip_address, attempt_count, window_started_at, last_attempt_at, blocked_until)
                VALUES (?, ?, ?, 1, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssss", $action, $identifier, $ip_address, $now_sql, $now_sql, $blocked_until);
        mysqli_stmt_execute($stmt);
        return;
    }

    $window_started = strtotime((string) $row['window_started_at']);
    $attempt_count = (int) $row['attempt_count'];

    if (!$window_started || ($now - $window_started) >= $window_seconds) {
        $attempt_count = 1;
        $window_started_sql = $now_sql;
    } else {
        $attempt_count++;
        $window_started_sql = $row['window_started_at'];
    }

    $blocked_until = null;
    if ($attempt_count >= (int) $max_attempts && $block_seconds > 0) {
        $blocked_until = date('Y-m-d H:i:s', $now + $block_seconds);
    }

    $sql = "UPDATE rate_limit_events
            SET attempt_count = ?, window_started_at = ?, last_attempt_at = ?, blocked_until = ?
            WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssi", $attempt_count, $window_started_sql, $now_sql, $blocked_until, $row['id']);
    mysqli_stmt_execute($stmt);
}

function rateLimitClear(mysqli $conn, $action, $identifier, $ip_address)
{
    $sql = "DELETE FROM rate_limit_events WHERE action = ? AND identifier = ? AND ip_address = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $action, $identifier, $ip_address);
    mysqli_stmt_execute($stmt);
}

?>
