<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("super_admin");

$redirect_url = BASE_URL . 'frontend/user/superadmin/settings.php';

function redirectAdminPassword($redirect_url, $message, $status = 'error')
{
    setToast($message, $status);
    redirect($redirect_url);
}

if (!isset($_POST['change_password'])) {
    redirect($redirect_url);
}

$admin_id = (int) $_SESSION['user_id'];
$auth_provider = $_SESSION['auth_provider'] ?? 'password';
$csrf_token = $_POST['csrf_token'] ?? '';
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (
    empty($_SESSION['admin_password_csrf'])
    || $csrf_token === ''
    || !hash_equals($_SESSION['admin_password_csrf'], $csrf_token)
) {
    redirectAdminPassword($redirect_url, 'Your password form expired. Please try again.');
}

if (strlen($new_password) < 8) {
    redirectAdminPassword($redirect_url, 'Your new password must be at least 8 characters long.');
}

if ($new_password !== $confirm_password) {
    redirectAdminPassword($redirect_url, 'The new passwords do not match.');
}

$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ? AND role = 'super_admin' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    redirectAdminPassword($redirect_url, 'Unable to update your password. Please try again.');
}

if ($auth_provider !== 'google') {
    if ($current_password === '' || !password_verify($current_password, $user['password'])) {
        redirectAdminPassword($redirect_url, 'Your current password is incorrect.');
    }
}

if (password_verify($new_password, $user['password'])) {
    redirectAdminPassword($redirect_url, 'Your new password must be different from your current password.');
}

$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
mysqli_begin_transaction($conn);

$update_stmt = mysqli_prepare(
    $conn,
    "UPDATE users
     SET password = ?, auth_version = auth_version + 1
     WHERE user_id = ? AND role = 'super_admin'"
);
mysqli_stmt_bind_param($update_stmt, "si", $password_hash, $admin_id);

if (!mysqli_stmt_execute($update_stmt) || mysqli_stmt_affected_rows($update_stmt) !== 1) {
    mysqli_rollback($conn);
    redirectAdminPassword($redirect_url, 'Unable to update your password. Please try again.');
}

rememberRevokeAllForUser($conn, $admin_id);

$version_stmt = mysqli_prepare($conn, "SELECT auth_version FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($version_stmt, "i", $admin_id);
mysqli_stmt_execute($version_stmt);
$version_row = mysqli_fetch_assoc(mysqli_stmt_get_result($version_stmt));

if (!$version_row) {
    mysqli_rollback($conn);
    redirectAdminPassword($redirect_url, 'Unable to update your password. Please try again.');
}

logActivity($conn, $admin_id, 'Changed account password', 'Account Security');
mysqli_commit($conn);

session_regenerate_id(true);
$_SESSION['auth_version'] = (int) $version_row['auth_version'];
unset($_SESSION['admin_password_csrf']);
redirectAdminPassword($redirect_url, 'Your password was updated successfully.', 'success');
?>
