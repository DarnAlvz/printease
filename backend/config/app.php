<?php
require_once __DIR__ . "/env.php";

$app_env = envValue("APP_ENV", "local");

if ($app_env === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$base_url = rtrim((string) envValue("BASE_URL", "http://localhost/printease/"), "/") . "/";

if (!defined("APP_NAME")) define("APP_NAME", envValue("APP_NAME", "PrintEase"));
if (!defined("APP_ENV")) define("APP_ENV", $app_env);
if (!defined("BASE_URL")) define("BASE_URL", $base_url);
if (!defined("UPLOADS_URL")) define("UPLOADS_URL", BASE_URL . "uploads/");
if (!defined("PERMITS_URL")) define("PERMITS_URL", UPLOADS_URL . "permits/");
if (!defined("SHOP_LOGOS_URL")) define("SHOP_LOGOS_URL", UPLOADS_URL . "shop_logos/");
if (!defined("GCASH_QR_URL")) define("GCASH_QR_URL", UPLOADS_URL . "gcash_qr/");
?>
