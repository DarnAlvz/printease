<?php
require_once __DIR__ . "/../includes/session.php";
require_once __DIR__ . "/../config/app.php";
secureSession();

include "../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/remember_auth.php";
require_once __DIR__ . "/../includes/rate_limit.php";

validateCsrf();

function redirectToResetPassword($query = '')
{
    $location = "../../frontend/pages/reset_password.php";
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header("Location: " . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToResetPassword();
}

$rate_guard = rateLimitGuardRequest($conn, 'reset_password', 5, 3600);
if (!$rate_guard['allowed']) {
    redirectToResetPassword('error=too_many_reset_attempts');
}
rateLimitRecordRequest($conn, 'reset_password', $rate_guard['identifier'], $rate_guard['ip_address'], 5, 3600);

if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_email'])) {
    redirectToResetPassword('error=session_expired');
}

$password = (string) ($_POST['password'] ?? '');

if (strlen($password) < 8) {
    redirectToResetPassword('error=weak_password');
}

$email = (string) $_SESSION['otp_email'];
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$user_sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$user_stmt = mysqli_prepare($conn, $user_sql);
$reset_user_id = null;
if ($user_stmt) {
    mysqli_stmt_bind_param($user_stmt, "s", $email);
    mysqli_stmt_execute($user_stmt);
    $reset_user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
    $reset_user_id = isset($reset_user['user_id']) ? (int) $reset_user['user_id'] : null;
}

$sql = "UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    redirectToResetPassword('error=server');
}

mysqli_stmt_bind_param($stmt, "ss", $password_hash, $email);
mysqli_stmt_execute($stmt);

if ($reset_user_id !== null) {
    rememberRevokeAllForUser($conn, $reset_user_id);
}

unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_verified']);

setFlash('auth_success', 'password_reset');
header("Location: ../../frontend/pages/login.php");
exit;
