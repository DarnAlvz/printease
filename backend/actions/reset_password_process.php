<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();

include "../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

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

if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_email'])) {
    redirectToResetPassword('error=session_expired');
}

$password = (string) ($_POST['password'] ?? '');

if (strlen($password) < 8) {
    redirectToResetPassword('error=weak_password');
}

$email = (string) $_SESSION['otp_email'];
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    redirectToResetPassword('error=server');
}

mysqli_stmt_bind_param($stmt, "ss", $password_hash, $email);
mysqli_stmt_execute($stmt);

unset($_SESSION['otp'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_verified']);

setFlash('auth_success', 'password_reset');
header("Location: ../../frontend/pages/login.php");
exit;
