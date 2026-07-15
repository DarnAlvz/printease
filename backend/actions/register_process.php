<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

validateCsrf();

function redirectToRegister($error = '') {
    if ($error !== '') {
        setFlash('auth_error', $error);
    }
    header("Location: ../../frontend/pages/register.php");
    exit();
}

if (isset($_POST['register'])) {
    $ip = rateLimitClientIp();
    $identifier = rateLimitCompositeIdentifier('register', $email ?? $_POST['email'] ?? '');

    $rate = rateLimitCheck($conn, 'register', $identifier, $ip, 5, 900);
    if (!$rate['allowed']) {
        setFlash('auth_error', 'rate_limited');
        header("Location: ../../frontend/pages/register.php");
        exit();
    }

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectToRegister('invalid_email');
    }

    $allowed_roles = ['customer', 'shop_owner'];

    if (!in_array($role, $allowed_roles)) {
        redirectToRegister('invalid_role');
    }

    $account_status = 'incomplete';

    $sql = "INSERT INTO users (full_name, email, password, role, account_status) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        redirectToRegister('registration_failed');
    }

    mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $password, $role, $account_status);

    try {
        $execute_ok = mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $e) {
        $execute_ok = false;
        $error_code = $e->getCode();
    }

    if ($execute_ok) {
        rateLimitClear($conn, 'register', $identifier, $ip);
        setFlash('auth_success', 'registered');
        header("Location: ../../frontend/pages/login.php");
        exit();
    } else {
        rateLimitRecord($conn, 'register', $identifier, $ip, 5, 900);
        $error = ($error_code ?? mysqli_errno($conn)) === 1062 ? 'duplicate_email' : 'registration_failed';
        redirectToRegister($error);
    }
}
?>
