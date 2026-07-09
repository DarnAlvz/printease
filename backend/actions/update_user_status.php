<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("super_admin");

$redirect_page = BASE_URL . "frontend/user/superadmin/manage_users.php";

function userStatusLabel($status)
{
    return match ((string) $status) {
        'verified' => 'Active',
        'rejected' => 'Rejected',
        'inactive' => 'Inactive',
        default => 'Pending',
    };
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_user_status'])) {
    redirect($redirect_page);
}

$csrf_token = (string) ($_POST['csrf_token'] ?? '');
if (
    empty($_SESSION['admin_user_status_csrf'])
    || $csrf_token === ''
    || !hash_equals($_SESSION['admin_user_status_csrf'], $csrf_token)
) {
    setError("Security check failed. Please refresh the page and try again.");
    redirect($redirect_page);
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$account_status = trim((string) ($_POST['account_status'] ?? ''));
$allowed = ['pending', 'verified', 'rejected', 'inactive'];

if (!$user_id || !in_array($account_status, $allowed, true)) {
    setError("Invalid account status.");
    redirect($redirect_page);
}

$user_sql = "SELECT user_id, full_name, email, role, account_status FROM users WHERE user_id = ? AND role != 'super_admin' LIMIT 1";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    setError("User not found or cannot update super admin.");
    redirect($redirect_page);
}

$sql = "UPDATE users
        SET account_status = ?
        WHERE user_id = ?
        AND role != 'super_admin'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $account_status, $user_id);

if (mysqli_stmt_execute($stmt)) {
    if ($user['role'] === 'shop_owner' && in_array($account_status, ['pending', 'verified', 'rejected'], true)) {
        $permit_status = $account_status;
        $permit_sql = "UPDATE print_shops
                       SET permit_status = ?
                       WHERE owner_id = ?";

        $permit_stmt = mysqli_prepare($conn, $permit_sql);
        mysqli_stmt_bind_param($permit_stmt, "si", $permit_status, $user_id);
        mysqli_stmt_execute($permit_stmt);
    }

    if (in_array($account_status, ['rejected', 'inactive'], true)) {
        rememberRevokeAllForUser($conn, $user_id);
    }

    logActivity($conn, $_SESSION['user_id'], "Updated user #$user_id to $account_status", "User Management", [
        'target_type' => 'user',
        'target_id' => $user_id,
        'old_value' => [
            'account_status' => $user['account_status'] ?? null,
        ],
        'new_value' => [
            'account_status' => $account_status,
            'role' => $user['role'] ?? null,
            'email' => $user['email'] ?? null,
            'full_name' => $user['full_name'] ?? null,
        ],
    ]);

    $label = userStatusLabel($account_status);
    $status_message = "Your account has been set to " . $label . ".";
    if ($account_status === 'inactive') {
        $status_message = "Your account has been deactivated by the administrator. Please contact support for assistance.";
    }

    $profile_target = $user['role'] === 'shop_owner'
        ? BASE_URL . 'frontend/user/shop_owner/shop_profile.php'
        : BASE_URL . 'frontend/user/customer/profile.php';
    sendNotification($conn, $user_id, $status_message, [
        'type' => 'account_status',
        'title' => 'Account status updated',
        'target_url' => $profile_target,
        'metadata' => ['status' => $account_status, 'role' => $user['role']],
    ]);

    setToast("User status updated to " . $label . ".", "success");
} else {
    setError("Failed to update user status.");
}

redirect($redirect_page);
?>
