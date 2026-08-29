<?php
function isRequestSecure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwarded_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwarded_proto !== '') {
        $first_proto = trim(explode(',', $forwarded_proto)[0]);
        return $first_proto === 'https';
    }

    return false;
}

$csp_nonce = base64_encode(random_bytes(32));
$GLOBALS['csp_nonce'] = $csp_nonce;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$csp_nonce}' https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; worker-src 'self' blob: https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; media-src 'self'; connect-src 'self' https://nominatim.openstreetmap.org https://unpkg.com; frame-src 'self' https://www.google.com https://accounts.google.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

if (isRequestSecure()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
