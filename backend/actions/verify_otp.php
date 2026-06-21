<?php
session_start();

function redirectToOtp($query = '')
{
    $location = "../../frontend/pages/verify_otp.php";
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header("Location: " . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToOtp();
}

if (
    empty($_SESSION['otp'])
    || empty($_SESSION['otp_email'])
    || empty($_SESSION['otp_expires'])
) {
    redirectToOtp('error=session_expired');
}

if (time() > (int) $_SESSION['otp_expires']) {
    unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_verified']);
    redirectToOtp('error=expired');
}

$submitted_otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));

if (strlen($submitted_otp) !== 6) {
    redirectToOtp('error=incomplete');
}

if (!hash_equals((string) $_SESSION['otp'], $submitted_otp)) {
    $_SESSION['otp_failed_attempts'] = (int) ($_SESSION['otp_failed_attempts'] ?? 0) + 1;

    if ($_SESSION['otp_failed_attempts'] >= 5) {
        unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['otp_verified'], $_SESSION['otp_failed_attempts']);
        redirectToOtp('error=too_many_otp_attempts');
    }

    redirectToOtp('error=invalid_otp');
}

$_SESSION['otp_verified'] = true;
unset($_SESSION['otp_failed_attempts']);

header("Location: ../../frontend/pages/reset_password.php?verified=1");
exit;
