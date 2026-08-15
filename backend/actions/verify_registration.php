<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

validateCsrf();

function redirectToRegistrationOtp($query = '')
{
    $location = "../../frontend/pages/verify_registration.php";
    if ($query !== '') {
        $location .= '?' . $query;
    }

    header("Location: " . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToRegistrationOtp();
}

$otp_ip = rateLimitClientIp();
$otp_ip_check = rateLimitCheck($conn, 'otp_verify_registration', 'all', $otp_ip, 10, 900);
if (!$otp_ip_check['allowed']) {
    redirectToRegistrationOtp('error=too_many_otp_attempts');
}
rateLimitRecord($conn, 'otp_verify_registration', 'all', $otp_ip, 10, 900);

$pending = $_SESSION['registration_pending'] ?? null;

if (!is_array($pending) || empty($pending['otp']) || empty($pending['otp_expires'])) {
    redirectToRegistrationOtp('error=session_expired');
}

if (time() > (int) $pending['otp_expires']) {
    unset($_SESSION['registration_pending']);
    redirectToRegistrationOtp('error=expired');
}

$submitted_otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));

if (strlen($submitted_otp) !== 6) {
    redirectToRegistrationOtp('error=incomplete');
}

if (!hash_equals((string) $pending['otp'], $submitted_otp)) {
    $_SESSION['registration_pending']['otp_failed_attempts'] = (int) ($pending['otp_failed_attempts'] ?? 0) + 1;

    if ($_SESSION['registration_pending']['otp_failed_attempts'] >= 5) {
        unset($_SESSION['registration_pending']);
        redirectToRegistrationOtp('error=too_many_otp_attempts');
    }

    redirectToRegistrationOtp('error=invalid_otp');
}

$email = strtolower(trim((string) ($pending['email'] ?? '')));
$full_name = trim((string) ($pending['full_name'] ?? ''));
$password_hash = (string) ($pending['password_hash'] ?? '');
$role = (string) ($pending['role'] ?? '');
$allowed_roles = ['customer', 'shop_owner'];

if ($full_name === '' || $email === '' || $password_hash === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $allowed_roles, true)) {
    unset($_SESSION['registration_pending']);
    redirectToRegistrationOtp('error=session_expired');
}

$duplicate_sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);

if (!$duplicate_stmt) {
    redirectToRegistrationOtp('error=server');
}

mysqli_stmt_bind_param($duplicate_stmt, "s", $email);
mysqli_stmt_execute($duplicate_stmt);
$existing_user = mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate_stmt));

if ($existing_user) {
    unset($_SESSION['registration_pending']);
    setFlash('auth_error', 'duplicate_email');
    header("Location: ../../frontend/pages/register.php");
    exit;
}

$account_status = 'incomplete';
$insert_sql = "INSERT INTO users (full_name, email, password, role, account_status)
               VALUES (?, ?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    redirectToRegistrationOtp('error=server');
}

mysqli_stmt_bind_param($insert_stmt, "sssss", $full_name, $email, $password_hash, $role, $account_status);

try {
    $execute_ok = mysqli_stmt_execute($insert_stmt);
} catch (mysqli_sql_exception $exception) {
    $execute_ok = false;
    $error_code = $exception->getCode();
}

if (!$execute_ok) {
    if (($error_code ?? mysqli_errno($conn)) === 1062) {
        unset($_SESSION['registration_pending']);
        setFlash('auth_error', 'duplicate_email');
        header("Location: ../../frontend/pages/register.php");
        exit;
    }

    redirectToRegistrationOtp('error=server');
}

unset($_SESSION['registration_pending']);

$new_user_id = mysqli_insert_id($conn);

if ($role === 'customer') {
    sendRoleNotification($conn, 'super_admin', $email . ' has signed up as a customer. Review their account.', [
        'type' => 'account_submitted',
        'title' => 'New customer registered: ' . $full_name,
        'target_url' => BASE_URL . 'frontend/user/superadmin/manage_users.php',
        'metadata' => ['user_id' => $new_user_id, 'role' => 'customer', 'stage' => 'registered'],
    ]);
} else {
    sendRoleNotification($conn, 'super_admin', $email . ' has signed up as a print shop owner. They still need to set up their shop.', [
        'type' => 'permit_submitted',
        'title' => 'New shop owner registered: ' . $full_name,
        'target_url' => BASE_URL . 'frontend/user/superadmin/manage_print_shops.php',
        'metadata' => ['user_id' => $new_user_id, 'role' => 'shop_owner', 'stage' => 'registered'],
    ]);
}

setFlash('auth_success', 'account_verified');
header("Location: ../../frontend/pages/login.php");
exit;
?>
