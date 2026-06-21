<?php
session_start();

require_once __DIR__ . "/../config/oauth.php";
require_once __DIR__ . "/../includes/oauth_helpers.php";

$provider = $_GET['provider'] ?? '';

if (!isAllowedOAuthProvider($provider)) {
    redirectToLoginError('oauth_invalid_provider');
}

$config = oauthProvider($provider);

if (!oauthProviderIsConfigured($config)) {
    redirectToLoginError('oauth_not_configured');
}

$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'][$provider] = $state;

$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => $config['scope'],
    'state' => $state,
];

if ($provider === 'google') {
    $params['access_type'] = 'online';
    $params['prompt'] = 'select_account';
}

$redirectUrl = $config['authorize_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

redirect($redirectUrl);
?>
