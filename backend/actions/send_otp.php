<?php
session_start();

include "../config/db.php";
require_once "../helper/mailer.php";
require_once "../includes/rate_limit.php";

function redirectToForgotPassword($query = '')
{
    $location = "../../frontend/pages/forgot_password.php";
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header("Location: " . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToForgotPassword();
}

$email = trim($_POST['email'] ?? '');
$email_key = rateLimitIdentifier($email);
$ip = rateLimitClientIp();

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectToForgotPassword('error=invalid_email');
}

$otp_minute_limit = rateLimitCheck($conn, 'otp_email_minute', $email_key, 'all', 1, 60, 60);
$otp_hour_limit = rateLimitCheck($conn, 'otp_email_hour', $email_key, 'all', 3, 60 * 60);
$otp_day_limit = rateLimitCheck($conn, 'otp_email_day', $email_key, 'all', 10, 24 * 60 * 60);
$otp_ip_limit = rateLimitCheck($conn, 'otp_ip_hour', 'all', $ip, 5, 60 * 60);

if (!$otp_minute_limit['allowed']) {
    redirectToForgotPassword('error=otp_wait');
}

if (!$otp_hour_limit['allowed'] || !$otp_day_limit['allowed']) {
    redirectToForgotPassword('error=otp_hourly_limit');
}

if (!$otp_ip_limit['allowed']) {
    redirectToForgotPassword('error=too_many_requests');
}

rateLimitRecord($conn, 'otp_email_minute', $email_key, 'all', 1, 60);
rateLimitRecord($conn, 'otp_email_hour', $email_key, 'all', 3, 60 * 60);
rateLimitRecord($conn, 'otp_email_day', $email_key, 'all', 10, 24 * 60 * 60);
rateLimitRecord($conn, 'otp_ip_hour', 'all', $ip, 5, 60 * 60);

$sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    redirectToForgotPassword('error=server');
}

mysqli_stmt_bind_param($stmt, "s", $email_key);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

if (!$user) {
    redirectToForgotPassword('error=email_not_found');
}

$otp = (string) random_int(100000, 999999);

$_SESSION['otp'] = $otp;
$_SESSION['otp_email'] = $email_key;
$_SESSION['otp_expires'] = time() + 300;
$_SESSION['otp_failed_attempts'] = 0;
unset($_SESSION['otp_verified']);

if (!sendOTP($email, $otp)) {
    unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_verified']);
    redirectToForgotPassword('error=mail_failed');
}

header("Location: ../../frontend/pages/verify_otp.php?sent=1");
exit;
