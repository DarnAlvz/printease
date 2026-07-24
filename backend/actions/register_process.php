<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";
require_once __DIR__ . "/../helper/mailer.php";

validateCsrf();

function redirectToRegister($error = '') {
    if ($error !== '') {
        setFlash('auth_error', $error);
    }
    header("Location: ../../frontend/pages/register.php");
    exit();
}

function emailDomainCanReceiveMail($email) {
    $at_position = strrpos($email, '@');

    if ($at_position === false) {
        return false;
    }

    $domain = strtolower(trim(substr($email, $at_position + 1), " \t\n\r\0\x0B."));

    if ($domain === '' || strlen($domain) > 253 || strpos($domain, '.') === false) {
        return false;
    }

    if (!preg_match('/^[a-z0-9.-]+$/', $domain) || strpos($domain, '..') !== false) {
        return false;
    }

    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

if (isset($_POST['register'])) {
    $ip = rateLimitClientIp();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $identifier = rateLimitCompositeIdentifier('register', $email);

    $rate = rateLimitCheck($conn, 'register', $identifier, $ip, 5, 900);
    if (!$rate['allowed']) {
        setFlash('auth_error', 'rate_limited');
        header("Location: ../../frontend/pages/register.php");
        exit();
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $password_raw = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $role = $_POST['role'] ?? '';

    if ($full_name === '' || strlen($full_name) > 150) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('registration_failed');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('invalid_email');
    }

    if (!emailDomainCanReceiveMail($email)) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('invalid_email_domain');
    }

    if (strlen($password_raw) < 8) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('weak_password');
    }

    if ($password_raw !== $confirm_password) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('password_mismatch');
    }

    $allowed_roles = ['customer', 'shop_owner'];

    if (!in_array($role, $allowed_roles, true)) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('invalid_role');
    }

    $duplicate_sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);

    if (!$duplicate_stmt) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('registration_failed');
    }

    mysqli_stmt_bind_param($duplicate_stmt, "s", $email);
    mysqli_stmt_execute($duplicate_stmt);
    $existing_user = mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate_stmt));

    if ($existing_user) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('duplicate_email');
    }

    $otp = (string) random_int(100000, 999999);
    $_SESSION['registration_pending'] = [
        'full_name' => $full_name,
        'email' => $email,
        'password_hash' => password_hash($password_raw, PASSWORD_DEFAULT),
        'role' => $role,
        'otp' => $otp,
        'otp_expires' => time() + 300,
        'otp_failed_attempts' => 0,
    ];

    if (!sendRegistrationOTP($email, $otp)) {
        unset($_SESSION['registration_pending']);
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('mail_failed');
    }

    rateLimitClear($conn, 'register', $identifier, $ip);
    header("Location: ../../frontend/pages/verify_registration.php?sent=1");
    exit();
}
?>
