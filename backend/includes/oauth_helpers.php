<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/remember_auth.php";

function allowedOAuthProviders()
{
    return ['google'];
}

function isAllowedOAuthProvider($provider)
{
    return in_array($provider, allowedOAuthProviders(), true);
}

function redirectToLoginError($error)
{
    setFlash('auth_error', $error);
    redirect(BASE_URL . "frontend/pages/login.php");
}

function redirectLoggedInUser($conn, $user)
{
    if (in_array(($user['account_status'] ?? ''), ['rejected', 'inactive'], true)) {
        redirectToLoginError(($user['account_status'] ?? '') === 'inactive' ? 'inactive' : 'rejected');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['role'] = $user['role'];
    $_SESSION['auth_provider'] = 'google';
    $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 1);
    rememberCreateForUser($conn, (int) $user['user_id'], 'google', REMEMBER_LONG_DURATION_DAYS);

    switch ($user['role']) {
        case 'shop_owner':
            $sql = "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
            mysqli_stmt_execute($stmt);
            $shop = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($shop) === 0) {
                redirect(BASE_URL . "frontend/user/shop_owner/shop_profile.php");
            }

            redirect(BASE_URL . "frontend/user/shop_owner/dashboard.php");
            break;

        case 'super_admin':
            redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
            break;

        case 'customer':
            if (($user['account_status'] ?? 'incomplete') === 'incomplete') {
                redirect(BASE_URL . "frontend/user/customer/profile.php");
            }

            redirect(BASE_URL . "frontend/user/customer/dashboard.php");
            break;

        default:
            redirectToLoginError('invalid_role');
    }
}

function findUserByEmail($conn, $email)
{
    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function findUserBySocialAccount($conn, $provider, $provider_user_id)
{
    $sql = "SELECT u.*
            FROM user_social_accounts usa
            JOIN users u ON usa.user_id = u.user_id
            WHERE usa.provider = ?
            AND usa.provider_user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $provider, $provider_user_id);
    mysqli_stmt_execute($stmt);

    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

function linkSocialAccount($conn, $user_id, $provider, $provider_user_id, $provider_email)
{
    $sql = "INSERT INTO user_social_accounts
                (user_id, provider, provider_user_id, provider_email)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                provider_email = VALUES(provider_email),
                updated_at = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $provider, $provider_user_id, $provider_email);

    return mysqli_stmt_execute($stmt);
}

function oauthHttpGetJson($url, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    return oauthCurlJsonResponse($ch);
}

function oauthHttpPostJson($url, $fields)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    return oauthCurlJsonResponse($ch);
}

function oauthCurlJsonResponse($ch)
{
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($body === false) {
        error_log("[OAuth] cURL exec failed: errno=$errno error=$error url=$url");
        return ['ok' => false, 'error' => $error ?: 'OAuth request failed.'];
    }

    if ($status < 200 || $status >= 300) {
        error_log("[OAuth] HTTP $status from $url body=$body");
        return ['ok' => false, 'error' => "OAuth HTTP $status: $body"];
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        error_log("[OAuth] Invalid JSON from $url body=$body");
        return ['ok' => false, 'error' => 'OAuth response was not valid JSON.'];
    }

    return ['ok' => true, 'data' => $decoded];
}
?>
