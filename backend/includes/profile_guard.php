<?php

function requireCompleteShopProfile($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../../frontend/auth/login.php");
        exit();
    }

    $owner_id = $_SESSION['user_id'];

    $sql = "SELECT shop_name, shop_address, contact_number, business_permit_file 
            FROM print_shops 
            WHERE owner_id = ? 
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $shop = mysqli_fetch_assoc($result);

    if (
        !$shop ||
        empty($shop['shop_name']) ||
        empty($shop['shop_address']) ||
        empty($shop['contact_number']) ||
        empty($shop['business_permit_file'])
    ) {
        setMessage("Please complete your shop profile first.");
        header("Location: ../../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }
}
?>