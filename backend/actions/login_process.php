<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/functions.php";

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        // Block rejected accounts
        if ($user['account_status'] == 'rejected') {
            header("Location: ../../frontend/pages/login.php?error=rejected");
            exit();
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Role-based redirection
            switch ($user['role']) {
                case 'shop_owner':
                    // Check if shop profile exists (not valid_id)
                    $shop_check = mysqli_query($conn, "SELECT shop_id FROM print_shops WHERE owner_id = " . intval($user['user_id']));
                    if (mysqli_num_rows($shop_check) == 0) {
                        redirect(BASE_URL . "frontend/user/shop_owner/shop_profile.php");
                    } else {
                        redirect(BASE_URL . "frontend/user/shop_owner/dashboard.php");
                    }
                    break;

                case 'super_admin':
                    redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
                    break;

                case 'customer':
                    $account_status = $user['account_status'] ?? 'incomplete';
                    if ($account_status === 'incomplete') {
                        redirect(BASE_URL . "frontend/user/customer/profile.php");
                    } else {
                        redirect(BASE_URL . "frontend/user/customer/dashboard.php");
                    }
                    break;

                default:
                    header("Location: ../../frontend/pages/login.php?error=invalid_role");
                    exit();
                    break;
            }
        } else {
            header("Location: ../../frontend/pages/login.php?error=incorrect_password");
            exit();
        }
    } else {
        header("Location: ../../frontend/pages/login.php?error=email_not_found");
        exit();
    }
}
?>
