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
        $_SESSION['owner_toast'] = [
            'status' => 'incomplete',
            'message' => 'Please complete your shop profile before accessing this feature.',
        ];
        header("Location: " . BASE_URL . "frontend/user/shop_owner/shop_profile.php");
        exit();
    }
}

function requireCompleteCustomerProfile($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../../frontend/auth/login.php");
        exit();
    }

    $customer_id = $_SESSION['user_id'];

    $sql = "SELECT phone_number, address, valid_id_file 
            FROM users 
            WHERE user_id = ? 
            AND role = 'customer'
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = mysqli_fetch_assoc($result);

    if (
        !$customer ||
        empty($customer['phone_number']) ||
        empty($customer['address']) ||
        empty($customer['valid_id_file'])
    ) {
        setToast("Please complete your customer profile first.", "warning");
        header("Location: ../../../frontend/user/customer/profile.php");
        exit();
    }
}
?>
