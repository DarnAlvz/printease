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

    $permit_status = $shop['permit_status'] ?? 'pending';

    if ($permit_status !== 'verified') {
        if ($permit_status === 'disabled') {
            setFlash("toast", "Your shop has been disabled by the Admin. Please contact support for assistance.");
            redirect($shop_profile_url);
        }

        if ($permit_status === 'rejected') {
            setFlash("toast", "Your shop profile was rejected. Please update your details or contact the Super Admin.");
            redirect($shop_profile_url);
        }

        setFlash("toast", "Your shop profile is submitted and waiting for Super Admin approval.");
        redirect($shop_profile_url);
    }
}
?>
