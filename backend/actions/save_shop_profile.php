<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("shop_owner");

if (isset($_POST['save_profile'])) {
    $owner_id = $_SESSION['user_id'];

    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $display_address = trim($_POST['display_address'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $gcash_name = trim($_POST['gcash_name'] ?? '');
    $gcash_number = trim($_POST['gcash_number'] ?? '');

    $latitude_raw = trim($_POST['latitude'] ?? '');
    $longitude_raw = trim($_POST['longitude'] ?? '');

    $weekday_open_time = !empty($_POST['weekday_open_time']) ? $_POST['weekday_open_time'] : null;
    $weekday_close_time = !empty($_POST['weekday_close_time']) ? $_POST['weekday_close_time'] : null;
    $weekend_open_time = !empty($_POST['weekend_open_time']) ? $_POST['weekend_open_time'] : null;
    $weekend_close_time = !empty($_POST['weekend_close_time']) ? $_POST['weekend_close_time'] : null;

    /*
        If shop owner leaves Street / Area Display empty,
        the system will automatically use the first part of the complete address.
        Example:
        Full address: Magsaysay Blvd, Brgy. Central, Calbayog City
        Display address: Magsaysay Blvd
    */
    if ($display_address === '' && $shop_address !== '') {
        $address_parts = array_filter(array_map('trim', explode(',', $shop_address)));
        $display_address = $address_parts[0] ?? $shop_address;
    }

    if (strlen($display_address) > 150) {
        setError("Street / Area Display is too long. Please keep it short.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if (strlen($landmark) > 150) {
        setError("Landmark is too long. Please keep it short.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    $latitude = null;
    $longitude = null;

    if ($latitude_raw !== '' || $longitude_raw !== '') {
        if ($latitude_raw === '' || $longitude_raw === '' || !is_numeric($latitude_raw) || !is_numeric($longitude_raw)) {
            setError("Please choose a valid shop location on the map.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $latitude = (float) $latitude_raw;
        $longitude = (float) $longitude_raw;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            setError("Please choose a valid shop location on the map.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

    $check_sql = "SELECT shop_id, permit_status, business_permit_file, shop_logo, shop_status, gcash_qr_file
                  FROM print_shops
                  WHERE owner_id = ?
                  LIMIT 1";

    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $owner_id);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
    $shop_status = $existing['shop_status'] ?? 'available';

    $has_new_permit = isset($_FILES['business_permit_file'])
        && $_FILES['business_permit_file']['error'] === UPLOAD_ERR_OK
        && $_FILES['business_permit_file']['name'] !== '';

    $has_new_logo = isset($_FILES['shop_logo'])
        && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK
        && $_FILES['shop_logo']['name'] !== '';

    $has_new_gcash_qr = isset($_FILES['gcash_qr_file'])
        && $_FILES['gcash_qr_file']['error'] === UPLOAD_ERR_OK
        && $_FILES['gcash_qr_file']['name'] !== '';

    $new_name = null;
    $new_logo_name = null;
    $new_gcash_qr_name = $existing['gcash_qr_file'] ?? null;

    if (strlen($gcash_name) > 150) {
        setError("GCash account name is too long.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($gcash_number !== '' && !preg_match('/^[0-9+\\-\\s]{7,30}$/', $gcash_number)) {
        setError("Please enter a valid GCash number.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($has_new_logo) {
        $allowed_logo_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
        $logo_name = $_FILES['shop_logo']['name'];
        $logo_tmp = $_FILES['shop_logo']['tmp_name'];
        $logo_extension = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));

        if (!in_array($logo_extension, $allowed_logo_extensions) || @getimagesize($logo_tmp) === false) {
            setError("Please upload a valid shop logo image.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $logo_dir = "../../uploads/shop_logos/";
        if (!is_dir($logo_dir)) {
            mkdir($logo_dir, 0775, true);
        }

        $new_logo_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $logo_extension;
        if (!move_uploaded_file($logo_tmp, $logo_dir . $new_logo_name)) {
            setError("Failed to upload shop logo.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

    if ($has_new_gcash_qr) {
        $allowed_gcash_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
        $gcash_name_file = $_FILES['gcash_qr_file']['name'];
        $gcash_tmp = $_FILES['gcash_qr_file']['tmp_name'];
        $gcash_extension = strtolower(pathinfo($gcash_name_file, PATHINFO_EXTENSION));

        if (!in_array($gcash_extension, $allowed_gcash_extensions) || @getimagesize($gcash_tmp) === false) {
            setError("Please upload a valid GCash QR image.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $gcash_dir = "../../uploads/gcash_qr/";
        if (!is_dir($gcash_dir)) {
            mkdir($gcash_dir, 0775, true);
        }

        $new_gcash_qr_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $gcash_extension;
        if (!move_uploaded_file($gcash_tmp, $gcash_dir . $new_gcash_qr_name)) {
            setError("Failed to upload GCash QR.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

    if ($has_new_permit) {
        $allowed_permit_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'pdf'];
        $permit_name = $_FILES['business_permit_file']['name'];
        $permit_tmp = $_FILES['business_permit_file']['tmp_name'];
        $permit_extension = strtolower(pathinfo($permit_name, PATHINFO_EXTENSION));

        if (!in_array($permit_extension, $allowed_permit_extensions)) {
            setError("Please upload a valid business permit file.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $permit_dir = "../../uploads/permits/";
        if (!is_dir($permit_dir)) {
            mkdir($permit_dir, 0775, true);
        }

        $new_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $permit_extension;
        if (!move_uploaded_file($permit_tmp, $permit_dir . $new_name)) {
            setError("Failed to upload business permit.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
    }

    if (!$existing && !$has_new_permit) {
        setToast("Please upload a business permit to complete your shop profile.", "warning");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($existing) {
        $shop_id = (int) $existing['shop_id'];

        if ($has_new_permit) {
            if ($has_new_logo) {
                $sql = "UPDATE print_shops SET 
                        shop_name = ?,
                        shop_address = ?,
                        display_address = ?,
                        landmark = ?,
                        shop_status = ?,
                        business_permit_file = ?,
                        shop_logo = ?,
                        latitude = ?,
                        longitude = ?,
                        permit_status = 'pending',
                        weekday_open_time = ?,
                        weekday_close_time = ?,
                        weekend_open_time = ?,
                        weekend_close_time = ?
                        WHERE shop_id = ? AND owner_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssssddssssii",
                    $shop_name,
                    $shop_address,
                    $display_address,
                    $landmark,
                    $shop_status,
                    $new_name,
                    $new_logo_name,
                    $latitude,
                    $longitude,
                    $weekday_open_time,
                    $weekday_close_time,
                    $weekend_open_time,
                    $weekend_close_time,
                    $shop_id,
                    $owner_id
                );
            } else {
                $sql = "UPDATE print_shops SET 
                        shop_name = ?,
                        shop_address = ?,
                        display_address = ?,
                        landmark = ?,
                        shop_status = ?,
                        business_permit_file = ?,
                        latitude = ?,
                        longitude = ?,
                        permit_status = 'pending',
                        weekday_open_time = ?,
                        weekday_close_time = ?,
                        weekend_open_time = ?,
                        weekend_close_time = ?
                        WHERE shop_id = ? AND owner_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssssddssssii",
                    $shop_name,
                    $shop_address,
                    $display_address,
                    $landmark,
                    $shop_status,
                    $new_name,
                    $latitude,
                    $longitude,
                    $weekday_open_time,
                    $weekday_close_time,
                    $weekend_open_time,
                    $weekend_close_time,
                    $shop_id,
                    $owner_id
                );
            }

            $message = "Shop profile saved. Your new permit is pending verification by Admin.";
            $activity = "Saved shop profile with new permit (pending verification)";
        } else {
            if ($has_new_logo) {
                $sql = "UPDATE print_shops SET 
                        shop_name = ?,
                        shop_address = ?,
                        display_address = ?,
                        landmark = ?,
                        shop_status = ?,
                        shop_logo = ?,
                        latitude = ?,
                        longitude = ?,
                        weekday_open_time = ?,
                        weekday_close_time = ?,
                        weekend_open_time = ?,
                        weekend_close_time = ?
                        WHERE shop_id = ? AND owner_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssddssssii",
                    $shop_name,
                    $shop_address,
                    $display_address,
                    $landmark,
                    $shop_status,
                    $new_logo_name,
                    $latitude,
                    $longitude,
                    $weekday_open_time,
                    $weekday_close_time,
                    $weekend_open_time,
                    $weekend_close_time,
                    $shop_id,
                    $owner_id
                );

                $message = "Shop profile and logo saved.";
                $activity = "Saved shop profile with logo";
            } else {
                $sql = "UPDATE print_shops SET 
                        shop_name = ?,
                        shop_address = ?,
                        display_address = ?,
                        landmark = ?,
                        shop_status = ?,
                        latitude = ?,
                        longitude = ?,
                        weekday_open_time = ?,
                        weekday_close_time = ?,
                        weekend_open_time = ?,
                        weekend_close_time = ?
                        WHERE shop_id = ? AND owner_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssddssssii",
                    $shop_name,
                    $shop_address,
                    $display_address,
                    $landmark,
                    $shop_status,
                    $latitude,
                    $longitude,
                    $weekday_open_time,
                    $weekday_close_time,
                    $weekend_open_time,
                    $weekend_close_time,
                    $shop_id,
                    $owner_id
                );

                $message = "Shop profile saved.";
                $activity = "Saved shop profile";
            }
        }
    } else {
        $sql = "INSERT INTO print_shops 
                (
                    owner_id,
                    shop_name,
                    shop_address,
                    display_address,
                    landmark,
                    latitude,
                    longitude,
                    shop_status,
                    business_permit_file,
                    shop_logo,
                    permit_status,
                    weekday_open_time,
                    weekday_close_time,
                    weekend_open_time,
                    weekend_close_time
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "issssddssssss",
            $owner_id,
            $shop_name,
            $shop_address,
            $display_address,
            $landmark,
            $latitude,
            $longitude,
            $shop_status,
            $new_name,
            $new_logo_name,
            $weekday_open_time,
            $weekday_close_time,
            $weekend_open_time,
            $weekend_close_time

        );

        $message = "Shop profile saved. Your permit is pending verification by Admin.";
        $activity = "Saved shop profile (pending verification)";
    }

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    if (mysqli_stmt_execute($stmt)) {
        if (!$existing) {
            $shop_id = mysqli_insert_id($conn);
        }

        $gcash_sql = "UPDATE print_shops
                      SET gcash_name = ?, gcash_number = ?, gcash_qr_file = ?
                      WHERE shop_id = ? AND owner_id = ?";
        $gcash_stmt = mysqli_prepare($conn, $gcash_sql);
        mysqli_stmt_bind_param($gcash_stmt, "sssii", $gcash_name, $gcash_number, $new_gcash_qr_name, $shop_id, $owner_id);
        mysqli_stmt_execute($gcash_stmt);

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
        if (!$existing || $has_new_permit) {
            sendRoleNotification($conn, 'super_admin', 'A print shop permit is ready for review.', [
                'type' => 'permit_submitted',
                'title' => 'Permit verification submitted',
                'target_url' => BASE_URL . 'frontend/user/superadmin/dashboard.php#pending-approvals',
                'metadata' => ['shop_id' => (int) $shop_id, 'owner_id' => $owner_id],
            ]);
        }
        setMessage($message);

        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    } else {
        echo "Failed to save shop profile.";
    }
}
?>
