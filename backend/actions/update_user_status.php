<?php
include "../config/db.php";
include "../config/app.php";
include "../includes/auth.php";
include "../includes/functions.php";

checkRole("super_admin");

$redirect_page = BASE_URL . "frontend/user/superadmin/manage_users.php";

if (!isset($_POST['update_user_status'])) {
    redirect($redirect_page);
}

$user_id = intval($_POST['user_id']);
$account_status = trim($_POST['account_status']);

$allowed = ['pending', 'verified', 'rejected'];

if (!in_array($account_status, $allowed)) {
    setMessage("Invalid account status.");
    redirect($redirect_page);
}

// Get user role first
$user_sql = "SELECT role FROM users WHERE user_id = ? AND role != 'super_admin'";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    setMessage("User not found or cannot update super admin.");
    redirect($redirect_page);
}

// Update users table
$sql = "UPDATE users 
        SET account_status = ? 
        WHERE user_id = ? 
        AND role != 'super_admin'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $account_status, $user_id);

if (mysqli_stmt_execute($stmt)) {

    // If shop owner, also update print_shops permit_status
    if ($user['role'] === 'shop_owner') {

        // permit_status only allows pending, verified, rejected
        $permit_status = $account_status;

        $permit_sql = "UPDATE print_shops 
                       SET permit_status = ? 
                       WHERE owner_id = ?";

        $permit_stmt = mysqli_prepare($conn, $permit_sql);
        mysqli_stmt_bind_param($permit_stmt, "si", $permit_status, $user_id);
        mysqli_stmt_execute($permit_stmt);
    }

    logActivity($conn, $_SESSION['user_id'], "Updated user #$user_id to $account_status", "User Management");

    $status_message = "Your account status has been " . ucfirst($account_status) . ".";
    sendNotification($conn, $user_id, $status_message);

    setMessage("User status updated successfully.");
} else {
    setMessage("Failed to update user status.");
}

redirect($redirect_page);
?>