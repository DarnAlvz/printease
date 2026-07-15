<?php
function requireVerifiedShop($conn) {
    $owner_id = $_SESSION['user_id'];

    $sql = "SELECT permit_status FROM print_shops WHERE owner_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $shop = mysqli_fetch_assoc($result);

    $shop_profile_url = BASE_URL . "frontend/user/shop_owner/shop_profile.php";

    if (!$shop) {
        setFlash("toast", "Please complete your shop profile first.");
        redirect($shop_profile_url);
    }

    if ($shop['permit_status'] !== 'verified') {
        if ($shop['permit_status'] === 'disabled') {
            setFlash("toast", "Your shop has been disabled by the Admin. Please contact support for assistance.");
            redirect($shop_profile_url);
        }

        setFlash("toast", "Your shop must be verified before accessing this page.");
        redirect($shop_profile_url);
    }
}
?>
