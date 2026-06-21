<?php
require_once __DIR__ . "/env.php";
require_once __DIR__ . "/app.php";

function oauthProviders()
{
    $base_url = rtrim(BASE_URL, '/') . '/';

    return [
        'google' => [
            'name' => 'Google',
            'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri' => $base_url . 'backend/oauth/oauth_callback.php?provider=google',
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'scope' => 'openid email profile',
        ],
    ];
}

function oauthProvider($provider)
{
    $providers = oauthProviders();

    return $providers[$provider] ?? null;
}

function oauthProviderIsConfigured($config)
{
    return $config
        && !empty($config['client_id'])
        && !empty($config['client_secret'])
        && !oauthStringStartsWith($config['client_id'], 'PASTE_')
        && !oauthStringStartsWith($config['client_secret'], 'PASTE_');
}

function oauthStringStartsWith($value, $prefix)
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}
?>
