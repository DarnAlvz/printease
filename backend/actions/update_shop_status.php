<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("shop_owner");
requireCompleteShopProfile($conn);
requireVerifiedStatus($conn);

if (isset($_POST['update_status'])) {
    $owner_id = $_SESSION['user_id'];
    $shop_status = $_POST['shop_status'] ?? '';
    $return_to = $_POST['return_to'] ?? '';
    $redirect_url = $return_to === 'shop_profile.php'
        ? '../../frontend/user/shop_owner/shop_profile.php'
        : '../../frontend/user/shop_owner/dashboard.php?status=updated';

    $allowed = ['available', 'busy', 'not_accepting'];

    if (!in_array($shop_status, $allowed)) {
        setToast("Invalid shop status selected.", "error");
        redirect($redirect_url);
    }

    $sql = "UPDATE print_shops SET shop_status = ? WHERE owner_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $shop_status, $owner_id);

    if (mysqli_stmt_execute($stmt)) {
        setToast("Shop status updated successfully.", "success");
        redirect($redirect_url);
    }

    setToast("Failed to update shop status. Please try again.", "error");
    redirect($redirect_url);
}
?>
