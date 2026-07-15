<?php
require_once __DIR__ . "/../includes/session.php";
secureSession();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/oauth_helpers.php";
require_once __DIR__ . "/../includes/functions.php";

validateCsrf();

if (!isset($_POST['complete_social_register'])) {
    redirect(BASE_URL . "frontend/pages/login.php");
}

$pending = $_SESSION['pending_oauth_user'] ?? null;

if (!$pending || !is_array($pending)) {
    redirectToLoginError('oauth_session_expired');
}

$role = $_POST['role'] ?? '';
$allowed_roles = ['customer', 'shop_owner'];

if (!in_array($role, $allowed_roles, true)) {
    setFlash('auth_error', 'invalid_role');
    header("Location: ../../frontend/pages/social_role.php");
    exit();
}

if (($_POST['terms_privacy'] ?? '') !== '1') {
    setFlash('auth_error', 'terms_required');
    header("Location: ../../frontend/pages/social_role.php");
    exit();
}

$provider = $pending['provider'] ?? '';
$provider_user_id = $pending['provider_user_id'] ?? '';
$email = trim(strtolower($pending['email'] ?? ''));
$full_name = trim($pending['full_name'] ?? $email);

if (!isAllowedOAuthProvider($provider) || $provider_user_id === '' || $email === '') {
    unset($_SESSION['pending_oauth_user']);
    redirectToLoginError('oauth_session_expired');
}

$existing_social_user = findUserBySocialAccount($conn, $provider, $provider_user_id);

if ($existing_social_user) {
    unset($_SESSION['pending_oauth_user']);
    redirectLoggedInUser($conn, $existing_social_user);
}

$existing_email_user = findUserByEmail($conn, $email);

if ($existing_email_user) {
    if (in_array(($existing_email_user['account_status'] ?? ''), ['rejected', 'inactive'], true)) {
        unset($_SESSION['pending_oauth_user']);
        redirectToLoginError(($existing_email_user['account_status'] ?? '') === 'inactive' ? 'inactive' : 'rejected');
    }

    if (!linkSocialAccount($conn, $existing_email_user['user_id'], $provider, $provider_user_id, $email)) {
        redirectToLoginError('oauth_failed');
    }

    unset($_SESSION['pending_oauth_user']);
    redirectLoggedInUser($conn, $existing_email_user);
}

$password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$account_status = 'incomplete';

mysqli_begin_transaction($conn);

$sql = "INSERT INTO users (full_name, email, password, role, account_status)
        VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    mysqli_rollback($conn);
    redirectToLoginError('oauth_failed');
}

mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $password, $role, $account_status);

try {
    $execute_ok = mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
    $execute_ok = false;
    $error_code = $e->getCode();
}

if (!$execute_ok) {
    mysqli_rollback($conn);
    $error = ($error_code ?? mysqli_errno($conn)) === 1062 ? 'duplicate_email' : 'oauth_failed';
    redirectToLoginError($error);
}

$user_id = mysqli_insert_id($conn);

if (!linkSocialAccount($conn, $user_id, $provider, $provider_user_id, $email)) {
    mysqli_rollback($conn);
    redirectToLoginError('oauth_failed');
}

mysqli_commit($conn);
unset($_SESSION['pending_oauth_user']);

$user = findUserByEmail($conn, $email);
redirectLoggedInUser($conn, $user);
?>
