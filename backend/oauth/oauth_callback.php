<?php
session_start();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/oauth.php";
require_once __DIR__ . "/../includes/oauth_helpers.php";

if (!$conn) {
    die("Database connection failed.");
}

$provider = $_GET['provider'] ?? '';

if (!isAllowedOAuthProvider($provider)) {
    redirectToLoginError('oauth_invalid_provider');
}

if (isset($_GET['error'])) {
    redirectToLoginError('oauth_denied');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$expected_state = $_SESSION['oauth_state'][$provider] ?? '';
unset($_SESSION['oauth_state'][$provider]);

if ($code === '' || $state === '' || $expected_state === '' || !hash_equals($expected_state, $state)) {
    redirectToLoginError('oauth_invalid_state');
}

$config = oauthProvider($provider);

if (!oauthProviderIsConfigured($config)) {
    redirectToLoginError('oauth_not_configured');
}

$token_response = oauthHttpPostJson($config['token_url'], [
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'code' => $code,
    'grant_type' => 'authorization_code',
]);

if (!$token_response['ok'] || empty($token_response['data']['access_token'])) {
    redirectToLoginError('oauth_failed');
}

$access_token = $token_response['data']['access_token'];

$profile_response = oauthHttpGetJson($config['profile_url'], [
    'Authorization: Bearer ' . $access_token,
]);

if (!$profile_response['ok']) {
    redirectToLoginError('oauth_failed');
}

$profile = $profile_response['data'];

$provider_user_id = $profile['id'] ?? '';
$email = trim(strtolower($profile['email'] ?? ''));
$full_name = trim($profile['name'] ?? $email);


$email_verified = !empty($email);

if ($provider_user_id === '' || $email === '' || !$email_verified) {
    redirectToLoginError('oauth_missing_email');
}

$social_user = findUserBySocialAccount($conn, $provider, $provider_user_id);

if ($social_user) {
    redirectLoggedInUser($conn, $social_user);
}

$email_user = findUserByEmail($conn, $email);

if ($email_user) {
    if (in_array(($email_user['account_status'] ?? ''), ['rejected', 'inactive'], true)) {
        redirectToLoginError(($email_user['account_status'] ?? '') === 'inactive' ? 'inactive' : 'rejected');
    }

    if (!linkSocialAccount($conn, $email_user['user_id'], $provider, $provider_user_id, $email)) {
        redirectToLoginError('oauth_failed');
    }

    redirectLoggedInUser($conn, $email_user);
}

$_SESSION['pending_oauth_user'] = [
    'provider' => $provider,
    'provider_user_id' => $provider_user_id,
    'email' => $email,
    'full_name' => $full_name !== '' ? $full_name : $email,
];

redirect(BASE_URL . "frontend/pages/social_role.php");
?>
