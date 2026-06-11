<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
checkRole("shop_owner");

if (isset($_POST['save_profile'])) {
    $owner_id = $_SESSION['user_id'];
    $shop_name = trim($_POST['shop_name']);
    $shop_address = trim($_POST['shop_address']);
    $contact_number = trim($_POST['contact_number']);
    $shop_status = $_POST['shop_status'];
    $latitude_raw = trim($_POST['latitude'] ?? '');
    $longitude_raw = trim($_POST['longitude'] ?? '');

    $latitude = null;
    $longitude = null;

    if ($latitude_raw !== '' || $longitude_raw !== '') {
        if ($latitude_raw === '' || $longitude_raw === '' || !is_numeric($latitude_raw) || !is_numeric($longitude_raw)) {
            setMessage("Please choose a valid shop location on the map.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $latitude = (float) $latitude_raw;
        $longitude = (float) $longitude_raw;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            setMessage("Please choose a valid shop location on the map.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

    $check_sql = "SELECT shop_id, permit_status, business_permit_file, shop_logo FROM print_shops WHERE owner_id = ? LIMIT 1";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $owner_id);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    $has_new_permit = isset($_FILES['business_permit_file'])
        && $_FILES['business_permit_file']['error'] === UPLOAD_ERR_OK
        && $_FILES['business_permit_file']['name'] !== '';

    $has_new_logo = isset($_FILES['shop_logo'])
        && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK
        && $_FILES['shop_logo']['name'] !== '';

    $new_name = null;
    $new_logo_name = null;

    if ($has_new_logo) {
        $allowed_logo_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
        $logo_name = $_FILES['shop_logo']['name'];
        $logo_tmp = $_FILES['shop_logo']['tmp_name'];
        $logo_extension = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));

        if (!in_array($logo_extension, $allowed_logo_extensions) || @getimagesize($logo_tmp) === false) {
            setMessage("Please upload a valid shop logo image.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $logo_dir = __DIR__ . "/../../uploads/shop_logos/";
        if (!is_dir($logo_dir)) {
            mkdir($logo_dir, 0775, true);
        }

        $new_logo_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $logo_extension;
        $logo_upload_path = $logo_dir . $new_logo_name;

        if (!move_uploaded_file($logo_tmp, $logo_upload_path)) {
            setMessage("Shop logo upload failed.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

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
            if ($has_new_logo) {
                $sql = "UPDATE print_shops SET 
                        shop_name=?, shop_address=?, contact_number=?, 
                        shop_status=?, business_permit_file=?, shop_logo=?, latitude=?, longitude=?, permit_status='pending'
                        WHERE shop_id=? AND owner_id=?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssddii", $shop_name, $shop_address, $contact_number, $shop_status, $new_name, $new_logo_name, $latitude, $longitude, $shop_id, $owner_id);
            } else {
                $sql = "UPDATE print_shops SET 
                        shop_name=?, shop_address=?, contact_number=?, 
                        shop_status=?, business_permit_file=?, latitude=?, longitude=?, permit_status='pending'
                        WHERE shop_id=? AND owner_id=?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssddii", $shop_name, $shop_address, $contact_number, $shop_status, $new_name, $latitude, $longitude, $shop_id, $owner_id);
            }
            $message = "Shop profile saved. Your new permit is pending verification by Admin.";
            $activity = "Saved shop profile with new permit (pending verification)";
        } else {
            if ($has_new_logo) {
                $sql = "UPDATE print_shops SET 
                        shop_name=?, shop_address=?, contact_number=?, shop_status=?, shop_logo=?, latitude=?, longitude=?
                        WHERE shop_id=? AND owner_id=?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssddii", $shop_name, $shop_address, $contact_number, $shop_status, $new_logo_name, $latitude, $longitude, $shop_id, $owner_id);
                $message = "Shop profile and logo saved.";
                $activity = "Saved shop profile with logo";
            } else {
                $sql = "UPDATE print_shops SET 
                        shop_name=?, shop_address=?, contact_number=?, shop_status=?, latitude=?, longitude=?
                        WHERE shop_id=? AND owner_id=?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssddii", $shop_name, $shop_address, $contact_number, $shop_status, $latitude, $longitude, $shop_id, $owner_id);
                $message = "Shop profile saved.";
                $activity = "Saved shop profile";
            }
        }
    } else {
        $sql = "INSERT INTO print_shops 
                (owner_id, shop_name, shop_address, latitude, longitude, contact_number, shop_status, business_permit_file, shop_logo, permit_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issddssss", $owner_id, $shop_name, $shop_address, $latitude, $longitude, $contact_number, $shop_status, $new_name, $new_logo_name);
        $message = "Shop profile saved. Your permit is pending verification by Admin.";
        $activity = "Saved shop profile (pending verification)";
    }

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    if (mysqli_stmt_execute($stmt)) {

        // After completing shop profile, set account status to pending
        if (!$existing || $has_new_permit) {
            $pending_user_sql = "UPDATE users 
                         SET account_status = 'pending' 
                         WHERE user_id = ? 
                         AND role = 'shop_owner'";

            $pending_user_stmt = mysqli_prepare($conn, $pending_user_sql);
            mysqli_stmt_bind_param($pending_user_stmt, "i", $owner_id);
            mysqli_stmt_execute($pending_user_stmt);
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