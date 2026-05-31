<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
checkRole("shop_owner");

if (isset($_POST['save_profile'])) {
    $owner_id = $_SESSION['user_id'];
    $shop_name = trim($_POST['shop_name']);
    $shop_address = trim($_POST['shop_address']);
    $contact_number = trim($_POST['contact_number']);
    $shop_status = $_POST['shop_status'];

    $check_sql = "SELECT shop_id, permit_status, business_permit_file FROM print_shops WHERE owner_id = ? LIMIT 1";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $owner_id);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    $has_new_permit = isset($_FILES['business_permit_file'])
        && $_FILES['business_permit_file']['error'] === UPLOAD_ERR_OK
        && $_FILES['business_permit_file']['name'] !== '';

    $new_name = null;

    if ($has_new_permit) {
        $permit_name = $_FILES['business_permit_file']['name'];
        $permit_tmp = $_FILES['business_permit_file']['tmp_name'];
        $new_name = time() . "_" . basename($permit_name);
        $upload_path = "../../uploads/permits/" . $new_name;

        if (!move_uploaded_file($permit_tmp, $upload_path)) {
            echo "Business permit upload failed.";
            exit();
        }
    }

    if (!$existing && !$has_new_permit) {
        setMessage("Please upload a business permit to complete your shop profile.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($existing) {
        $shop_id = (int) $existing['shop_id'];

        if ($has_new_permit) {
            $sql = "UPDATE print_shops SET 
                    shop_name=?, shop_address=?, contact_number=?, 
                    shop_status=?, business_permit_file=?, permit_status='pending'
                    WHERE shop_id=? AND owner_id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssii", $shop_name, $shop_address, $contact_number, $shop_status, $new_name, $shop_id, $owner_id);
            $message = "Shop profile saved. Your new permit is pending verification by Admin.";
            $activity = "Saved shop profile with new permit (pending verification)";
        } else {
            $sql = "UPDATE print_shops SET 
                    shop_name=?, shop_address=?, contact_number=?, shop_status=?
                    WHERE shop_id=? AND owner_id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssii", $shop_name, $shop_address, $contact_number, $shop_status, $shop_id, $owner_id);
            $message = "Shop profile saved.";
            $activity = "Saved shop profile";
        }
    } else {
        $sql = "INSERT INTO print_shops 
                (owner_id, shop_name, shop_address, contact_number, shop_status, business_permit_file, permit_status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssss", $owner_id, $shop_name, $shop_address, $contact_number, $shop_status, $new_name);
        $message = "Shop profile saved. Your permit is pending verification by Admin.";
        $activity = "Saved shop profile (pending verification)";
    }

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    if (mysqli_stmt_execute($stmt)) {

        // After completing shop profile, set account status to pending
        $pending_user_sql = "UPDATE users 
                         SET account_status = 'pending' 
                         WHERE user_id = ? 
                         AND role = 'shop_owner'
                         AND account_status IN ('incomplete', 'rejected')";

        $pending_user_stmt = mysqli_prepare($conn, $pending_user_sql);
        mysqli_stmt_bind_param($pending_user_stmt, "i", $owner_id);
        mysqli_stmt_execute($pending_user_stmt);

        // If new permit uploaded, force account back to pending for review
        if ($has_new_permit) {
            $review_sql = "UPDATE users 
                       SET account_status = 'pending' 
                       WHERE user_id = ? 
                       AND role = 'shop_owner'";

            $review_stmt = mysqli_prepare($conn, $review_sql);
            mysqli_stmt_bind_param($review_stmt, "i", $owner_id);
            mysqli_stmt_execute($review_stmt);
        }

        logActivity($conn, $owner_id, $activity, "Shop Profile");
        setMessage($message);

        header("Location: ../../frontend/user/shop_owner/dashboard.php");
        exit();
    } else {
        echo "Failed to save shop profile.";
    }
}
?>
