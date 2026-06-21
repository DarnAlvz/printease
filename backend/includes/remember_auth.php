<?php

const REMEMBER_COOKIE_NAME = 'printease_remember';
const REMEMBER_SHORT_DURATION_DAYS = 1;
const REMEMBER_LONG_DURATION_DAYS = 7;
const REMEMBER_RENEW_INTERVAL_SECONDS = 3600;

function rememberCookiePath()
{
    $path = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
    return '/' . trim($path, '/') . '/';
}

function rememberCookieIsSecure()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function rememberSetCookie($value, $expires_at)
{
    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires' => $expires_at,
        'path' => rememberCookiePath(),
        'secure' => rememberCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[REMEMBER_COOKIE_NAME] = $value;
}

function rememberClearCookie()
{
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => rememberCookiePath(),
        'secure' => rememberCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function rememberParseCookie()
{
    $value = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (!is_string($value) || !preg_match('/^([a-f0-9]{36}):([a-f0-9]{64})$/', $value, $matches)) {
        return null;
    }

    return [
        'selector' => $matches[1],
        'validator' => $matches[2],
    ];
}

function rememberDeleteSelector($conn, $selector)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM user_remember_tokens WHERE selector = ?");
    mysqli_stmt_bind_param($stmt, "s", $selector);
    mysqli_stmt_execute($stmt);
}

function rememberRevokeCurrentDevice($conn)
{
    $cookie = rememberParseCookie();
    if ($cookie) {
        rememberDeleteSelector($conn, $cookie['selector']);
    }

    rememberClearCookie();
    unset($_SESSION['remember_last_renewal']);
}

function rememberRevokeAllForUser($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM user_remember_tokens WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    rememberClearCookie();
    unset($_SESSION['remember_last_renewal']);
}

function rememberNormalizeDurationDays($duration_days)
{
    return (int) $duration_days === REMEMBER_LONG_DURATION_DAYS
        ? REMEMBER_LONG_DURATION_DAYS
        : REMEMBER_SHORT_DURATION_DAYS;
}

function rememberDurationSeconds($duration_days)
{
    return rememberNormalizeDurationDays($duration_days) * 86400;
}

function rememberTokenSupportsColumn($conn, $column_name)
{
    static $supported_columns = [];
    $column_name = (string) $column_name;

    if (array_key_exists($column_name, $supported_columns)) {
        return $supported_columns[$column_name];
    }

    $result = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'user_remember_tokens'
           AND COLUMN_NAME = '" . mysqli_real_escape_string($conn, $column_name) . "'"
    );

    $row = $result ? mysqli_fetch_assoc($result) : null;
    $supported_columns[$column_name] = (int) ($row['total'] ?? 0) > 0;
    return $supported_columns[$column_name];
}

function rememberTokenSupportsAuthProvider($conn)
{
    return rememberTokenSupportsColumn($conn, 'auth_provider');
}

function rememberTokenSupportsDuration($conn)
{
    return rememberTokenSupportsColumn($conn, 'remember_duration_days');
}

function rememberCreateForUser($conn, $user_id, $auth_provider = 'password', $duration_days = REMEMBER_SHORT_DURATION_DAYS)
{
    rememberRevokeCurrentDevice($conn);

    $selector = bin2hex(random_bytes(18));
    $validator = bin2hex(random_bytes(32));
    $validator_hash = hash('sha256', $validator);
    $duration_days = rememberNormalizeDurationDays($duration_days);
    $expires_timestamp = time() + rememberDurationSeconds($duration_days);
    $expires_at = date('Y-m-d H:i:s', $expires_timestamp);
    $auth_provider = $auth_provider === 'google' ? 'google' : 'password';
    $supports_auth_provider = rememberTokenSupportsAuthProvider($conn);
    $supports_duration = rememberTokenSupportsDuration($conn);

    if ($supports_auth_provider && $supports_duration) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, auth_provider, remember_duration_days, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isssis", $user_id, $selector, $validator_hash, $auth_provider, $duration_days, $expires_at);
    } elseif ($supports_auth_provider) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, auth_provider, expires_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $selector, $validator_hash, $auth_provider, $expires_at);
    } elseif ($supports_duration) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, remember_duration_days, expires_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "issis", $user_id, $selector, $validator_hash, $duration_days, $expires_at);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $selector, $validator_hash, $expires_at);
    }

    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    rememberSetCookie($selector . ':' . $validator, $expires_timestamp);
    $_SESSION['remember_last_renewal'] = time();
    $_SESSION['remember_duration_days'] = $duration_days;
    return true;
}

function rememberLoadToken($conn, $selector)
{
    $auth_provider_select = rememberTokenSupportsAuthProvider($conn)
        ? "rt.auth_provider"
        : "'password' AS auth_provider";
    $duration_select = rememberTokenSupportsDuration($conn)
        ? "rt.remember_duration_days"
        : REMEMBER_LONG_DURATION_DAYS . " AS remember_duration_days";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT rt.user_id, rt.validator_hash, " . $auth_provider_select . ", " . $duration_select . ", rt.expires_at,
                (rt.expires_at > NOW()) AS is_unexpired,
                u.full_name, u.email, u.role, u.account_status, u.auth_version
         FROM user_remember_tokens rt
         JOIN users u ON u.user_id = rt.user_id
         WHERE rt.selector = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $selector);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function rememberRestoreSession($conn)
{
    $cookie = rememberParseCookie();
    if (!$cookie) {
        if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
            rememberClearCookie();
        }
        return false;
    }

    $token = rememberLoadToken($conn, $cookie['selector']);
    $valid = $token
        && (int) $token['is_unexpired'] === 1
        && !in_array(($token['account_status'] ?? ''), ['rejected', 'inactive'], true)
        && hash_equals($token['validator_hash'], hash('sha256', $cookie['validator']));

    if (!$valid) {
        rememberDeleteSelector($conn, $cookie['selector']);
        rememberClearCookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $token['user_id'];
    $_SESSION['full_name'] = $token['full_name'];
    $_SESSION['email'] = $token['email'] ?? '';
    $_SESSION['role'] = $token['role'];
    $_SESSION['auth_provider'] = $token['auth_provider'] === 'google' ? 'google' : 'password';
    $_SESSION['auth_version'] = (int) $token['auth_version'];
    $_SESSION['remember_duration_days'] = rememberNormalizeDurationDays($token['remember_duration_days'] ?? REMEMBER_LONG_DURATION_DAYS);

    rememberDeleteSelector($conn, $cookie['selector']);
    rememberCreateForUser($conn, (int) $token['user_id'], $_SESSION['auth_provider'], $_SESSION['remember_duration_days']);
    return true;
}

function rememberRenewCurrentDevice($conn)
{
    $last_renewal = (int) ($_SESSION['remember_last_renewal'] ?? 0);
    if ($last_renewal > 0 && time() - $last_renewal < REMEMBER_RENEW_INTERVAL_SECONDS) {
        return;
    }

    $cookie = rememberParseCookie();
    if (!$cookie) {
        return;
    }

    $token = rememberLoadToken($conn, $cookie['selector']);
    if (
        !$token
        || (int) $token['user_id'] !== (int) ($_SESSION['user_id'] ?? 0)
        || (int) $token['is_unexpired'] !== 1
        || in_array(($token['account_status'] ?? ''), ['rejected', 'inactive'], true)
        || !hash_equals($token['validator_hash'], hash('sha256', $cookie['validator']))
    ) {
        rememberRevokeCurrentDevice($conn);
        return;
    }

    $duration_days = rememberNormalizeDurationDays($token['remember_duration_days'] ?? ($_SESSION['remember_duration_days'] ?? REMEMBER_LONG_DURATION_DAYS));
    $expires_timestamp = time() + rememberDurationSeconds($duration_days);
    $expires_at = date('Y-m-d H:i:s', $expires_timestamp);
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE user_remember_tokens
         SET expires_at = ?, last_used_at = CURRENT_TIMESTAMP
         WHERE selector = ?"
    );
    mysqli_stmt_bind_param($stmt, "ss", $expires_at, $cookie['selector']);
    mysqli_stmt_execute($stmt);

    rememberSetCookie($cookie['selector'] . ':' . $cookie['validator'], $expires_timestamp);
    $_SESSION['remember_last_renewal'] = time();
    $_SESSION['remember_duration_days'] = $duration_days;
}

?>
