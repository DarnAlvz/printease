<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/remember_auth.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "frontend/pages/login.php");
    exit();
}

validateCsrf();

rememberRevokeCurrentDevice($conn);
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header("Location: " . BASE_URL . "frontend/pages/login.php");
exit();
?>
