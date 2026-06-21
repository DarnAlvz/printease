<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/remember_auth.php";
require_once __DIR__ . "/../includes/rate_limit.php";

function redirectToLogin($query = '')
{
    $location = "../../frontend/pages/login.php";
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header("Location: " . $location);
    exit();
}

function recordFailedLogin(mysqli $conn, $email, $ip)
{
    rateLimitRecord($conn, 'login_email_ip', $email, $ip, 5, 15 * 60, 15 * 60);
    rateLimitRecord($conn, 'login_ip', 'all', $ip, 20, 15 * 60, 15 * 60);
}

if (!isset($_POST['login'])) {
    redirectToLogin();
}

$email = rateLimitIdentifier($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
$ip = rateLimitClientIp();

$email_limit = rateLimitCheck($conn, 'login_email_ip', $email, $ip, 5, 15 * 60);
$ip_limit = rateLimitCheck($conn, 'login_ip', 'all', $ip, 20, 15 * 60);

if (!$email_limit['allowed'] || !$ip_limit['allowed']) {
    redirectToLogin('error=too_many_attempts');
}

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    recordFailedLogin($conn, $email, $ip);
    redirectToLogin('error=email_not_found');
}

$user = mysqli_fetch_assoc($result);

if (in_array(($user['account_status'] ?? ''), ['rejected', 'inactive'], true)) {
    $error = $user['account_status'] === 'inactive' ? 'inactive' : 'rejected';
    redirectToLogin('error=' . $error);
}

if (!password_verify($password, $user['password'])) {
    recordFailedLogin($conn, $email, $ip);
    redirectToLogin('error=incorrect_password');
}

rateLimitClear($conn, 'login_email_ip', $email, $ip);

session_regenerate_id(true);
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'] ?? '';
$_SESSION['role'] = $user['role'];
$_SESSION['auth_provider'] = 'password';
$_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 1);

$remember_duration_days = $remember_me ? REMEMBER_LONG_DURATION_DAYS : REMEMBER_SHORT_DURATION_DAYS;
rememberCreateForUser($conn, (int) $user['user_id'], 'password', $remember_duration_days);

switch ($user['role']) {
    case 'shop_owner':
        $shop_check = mysqli_query($conn, "SELECT shop_id FROM print_shops WHERE owner_id = " . intval($user['user_id']));
        if (mysqli_num_rows($shop_check) == 0) {
            redirect(BASE_URL . "frontend/user/shop_owner/shop_profile.php");
        }
        redirect(BASE_URL . "frontend/user/shop_owner/dashboard.php");
        break;

    case 'super_admin':
        redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
        break;

    case 'customer':
        $account_status = $user['account_status'] ?? 'incomplete';
        if ($account_status === 'incomplete') {
            redirect(BASE_URL . "frontend/user/customer/profile.php");
        }
        redirect(BASE_URL . "frontend/user/customer/dashboard.php");
        break;

    default:
        redirectToLogin('error=invalid_role');
}
?>
