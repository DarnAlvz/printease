<?php

function requireVerifiedStatus($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../frontend/pages/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    if ($role === 'super_admin') {
        return true;
    }

    if ($role === 'customer') {
        $sql = "SELECT account_status FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {
            die("User not found.");
        }

        $status = $user['account_status'] ?? 'incomplete';

        switch ($status) {
            case 'verified':
                return true;

            case 'incomplete':
                $_SESSION['message'] = "Please complete your profile before accessing this feature.";
                header("Location: ../../frontend/user/customer/profile.php");
                exit();

            case 'pending':
                die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>
                    <h2>Account Pending Verification</h2>
                    <p>Your account is pending verification by the Super Admin. Please wait for approval.</p>
                    <p><a href='dashboard.php' style='color:#2563eb;'>Back to Dashboard</a></p>
                </div>");

            case 'rejected':
                die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>
                    <h2>Account Rejected</h2>
                    <p>Your account has been rejected. Please contact the administrator.</p>
                    <p><a href='dashboard.php' style='color:#2563eb;'>Back to Dashboard</a></p>
                </div>");

            default:
                die("Invalid account status.");
        }
    }

    if ($role === 'shop_owner') {
        $sql = "SELECT permit_status FROM print_shops WHERE owner_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $shop = mysqli_fetch_assoc($result);

        $permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : null;

        if (!$shop || $permit_status === null) {
            $_SESSION['message'] = "Please complete your shop profile before accessing this feature.";
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        switch ($permit_status) {
            case 'verified':
                return true;

            case 'pending':
                die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>
                    <h2>Permit Pending Verification</h2>
                    <p>Your business permit is pending verification by the Super Admin. Please wait for approval.</p>
                    <p><a href='dashboard.php' style='color:#2563eb;'>Back to Dashboard</a></p>
                </div>");

            case 'rejected':
                die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>
                    <h2>Permit Rejected</h2>
                    <p>Your business permit has been rejected. Please contact the administrator.</p>
                    <p><a href='dashboard.php' style='color:#2563eb;'>Back to Dashboard</a></p>
                </div>");

            default:
                die("Invalid permit status.");
        }
    }

    die("Access denied.");
}
