<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("shop_owner");

validateCsrf();

if (isset($_POST['save_profile'])) {
    $owner_id = $_SESSION['user_id'];

    $rate_guard = rateLimitGuardRequest($conn, 'save_shop_profile', 10, 3600);
    if (!$rate_guard['allowed']) {
        setError("Too many profile save attempts. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }
    rateLimitRecordRequest($conn, 'save_shop_profile', $rate_guard['identifier'], $rate_guard['ip_address'], 10, 3600);

    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $display_address = trim($_POST['display_address'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $gcash_name = trim($_POST['gcash_name'] ?? '');
    $gcash_number = trim($_POST['gcash_number'] ?? '');
    $merchant_link = trim($_POST['merchant_link'] ?? '');
    $payment_instructions = trim($_POST['payment_instructions'] ?? '');

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
    $existing_payment_channels = [];

    if ($existing) {
        $channels_sql = "SELECT * FROM shop_payment_channels WHERE shop_id = ?";
        $channels_stmt = mysqli_prepare($conn, $channels_sql);
        mysqli_stmt_bind_param($channels_stmt, "i", $existing['shop_id']);
        mysqli_stmt_execute($channels_stmt);
        $channels_result = mysqli_stmt_get_result($channels_stmt);
        while ($channel_row = mysqli_fetch_assoc($channels_result)) {
            $existing_payment_channels[$channel_row['channel']] = $channel_row;
        }
    }

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
    $new_gcash_qr_name = $existing_payment_channels['gcash_qr']['gcash_qr_code'] ?? ($existing['gcash_qr_file'] ?? null);

    if ($gcash_name !== '' && strlen($gcash_name) > 150) {
        setError("Please enter a valid GCash account name.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($gcash_number !== '' && !preg_match('/^[0-9+\\-\\s]{7,30}$/', $gcash_number)) {
        setError("Please enter a valid GCash number.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($payment_instructions === '' || strlen($payment_instructions) > 1000) {
        setError("Please enter payment instructions up to 1000 characters.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    if ($merchant_link !== '' && (strlen($merchant_link) > 500 || !filter_var($merchant_link, FILTER_VALIDATE_URL) || !preg_match('/^https?:\\/\\//i', $merchant_link))) {
        setError("Please enter a valid optional GCash merchant link that starts with http:// or https://.");
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

        $new_logo_name = time() . "_" . bin2hex(random_bytes(16)) . "." . $logo_extension;
        if (!move_uploaded_file($logo_tmp, $logo_dir . $new_logo_name)) {
            setError("Failed to upload shop logo.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
        if (!empty($existing['shop_logo']) && $existing['shop_logo'] !== $new_logo_name) {
            $old_logo = __DIR__ . '/../../uploads/shop_logos/' . $existing['shop_logo'];
            if (is_file($old_logo)) @unlink($old_logo);
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

        $gcash_extension = ($gcash_extension === 'jfif') ? 'jpg' : $gcash_extension;
        $new_gcash_qr_name = time() . "_" . bin2hex(random_bytes(16)) . "." . $gcash_extension;
        if (!move_uploaded_file($gcash_tmp, $gcash_dir . $new_gcash_qr_name)) {
            setError("Failed to upload GCash QR.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
        $old_gcash = $existing['gcash_qr_file'] ?? null;
        if (!empty($old_gcash) && $old_gcash !== $new_gcash_qr_name) {
            $old_gcash_path = __DIR__ . '/../../uploads/gcash_qr/' . $old_gcash;
            if (is_file($old_gcash_path)) @unlink($old_gcash_path);
        }
    }

    $remove_gcash_qr = isset($_POST['remove_gcash_qr']) && !$has_new_gcash_qr;
    if ($remove_gcash_qr) {
        $new_gcash_qr_name = null;
    }

    if (empty($new_gcash_qr_name) && trim($merchant_link) === '') {
        setError("Please provide at least one payment method (GCash QR Code or GCash Merchant Link).");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }

    $normalize_payment_value = static function ($value) {
        return trim((string) ($value ?? ''));
    };

    $existing_qr_channel = $existing_payment_channels['gcash_qr'] ?? null;
    $existing_link_channel = $existing_payment_channels['gcash_merchant_link'] ?? null;

    if ($remove_gcash_qr) {
        $old_qr_file = $existing_qr_channel['gcash_qr_code'] ?? ($existing['gcash_qr_file'] ?? null);
        if (!empty($old_qr_file)) {
            $old_qr_path = __DIR__ . '/../../uploads/gcash_qr/' . $old_qr_file;
            if (is_file($old_qr_path)) @unlink($old_qr_path);
        }
    }

    $qr_details_changed = $existing_qr_channel === null
        || $has_new_gcash_qr
        || $remove_gcash_qr
        || $normalize_payment_value($existing_qr_channel['gcash_account_name'] ?? '') !== $gcash_name
        || $normalize_payment_value($existing_qr_channel['gcash_number'] ?? '') !== $gcash_number
        || $normalize_payment_value($existing_qr_channel['instructions'] ?? '') !== $payment_instructions
        || $normalize_payment_value($existing_qr_channel['gcash_qr_code'] ?? '') !== $normalize_payment_value($new_gcash_qr_name);

    $merchant_link_changed = ($merchant_link !== '') && ($existing_link_channel === null
        || $normalize_payment_value($existing_link_channel['merchant_link'] ?? '') !== $merchant_link
        || $normalize_payment_value($existing_link_channel['instructions'] ?? '') !== $payment_instructions);

    $payment_details_changed = ($qr_details_changed && !$remove_gcash_qr) || $merchant_link_changed;

    $link_removal_requested = $existing_link_channel !== null && $merchant_link === '';
    $payment_update_needed = $qr_details_changed || $merchant_link_changed || $link_removal_requested;

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

        if (($_FILES['business_permit_file']['size'] ?? 0) > 10 * 1024 * 1024) {
            setError("Business permit file must be 10MB or smaller.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        $permit_mime = (new finfo(FILEINFO_MIME_TYPE))->file($permit_tmp);
        $permit_is_pdf = $permit_mime === 'application/pdf';
        $permit_is_image = in_array($permit_mime, ['image/jpeg', 'image/png', 'image/webp'], true);

        if (!$permit_is_pdf && !$permit_is_image) {
            setError("Please upload a valid business permit file.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        if ($permit_is_image && @getimagesize($permit_tmp) === false) {
            setError("Please upload a valid business permit image.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        if ($permit_is_pdf) {
            $permit_handle = fopen($permit_tmp, 'rb');
            $permit_header = $permit_handle !== false ? fread($permit_handle, 4) : '';
            if ($permit_handle !== false) {
                fclose($permit_handle);
            }
            if ($permit_header !== '%PDF') {
                setError("Please upload a valid business permit PDF.");
                header("Location: ../../frontend/user/shop_owner/shop_profile.php");
                exit();
            }
        }

        $permit_dir = "../../uploads/permits/";
        if (!is_dir($permit_dir)) {
            mkdir($permit_dir, 0775, true);
        }

        $new_name = time() . "_" . bin2hex(random_bytes(16)) . "." . $permit_extension;
        if (!move_uploaded_file($permit_tmp, $permit_dir . $new_name)) {
            setError("Failed to upload business permit.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }
        if (!empty($existing['business_permit_file']) && $existing['business_permit_file'] !== $new_name) {
            $old_permit = __DIR__ . '/../../uploads/permits/' . $existing['business_permit_file'];
            if (is_file($old_permit)) @unlink($old_permit);
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
                    "ssssssddssssii",
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            error_log("SQL prepare error in save_shop_profile insert: " . mysqli_error($conn));
            setError("Unable to save shop profile. Please try again.");
            header("Location: ../../frontend/user/shop_owner/shop_profile.php");
            exit();
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issssddsssssss",
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

        if ($payment_update_needed) {
            if ($qr_details_changed && ($remove_gcash_qr ? $existing_qr_channel !== null : !empty($new_gcash_qr_name))) {
                $qr_channel_sql = "INSERT INTO shop_payment_channels
                    (shop_id, channel, gcash_account_name, gcash_number, gcash_qr_code, instructions, approval_status, is_active, created_at, updated_at)
                    VALUES (?, 'gcash_qr', ?, ?, ?, ?, 'pending', 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        gcash_account_name = VALUES(gcash_account_name),
                        gcash_number = VALUES(gcash_number),
                        gcash_qr_code = VALUES(gcash_qr_code),
                        instructions = VALUES(instructions),
                        is_active = CASE
                            WHEN VALUES(gcash_qr_code) IS NULL OR VALUES(gcash_qr_code) = '' THEN 0
                            ELSE 1
                        END,
                        approval_status = CASE
                            WHEN VALUES(gcash_qr_code) IS NULL OR VALUES(gcash_qr_code) = '' THEN approval_status
                            ELSE 'pending'
                        END,
                        approved_by = CASE
                            WHEN VALUES(gcash_qr_code) IS NULL OR VALUES(gcash_qr_code) = '' THEN approved_by
                            ELSE NULL
                        END,
                        approved_at = CASE
                            WHEN VALUES(gcash_qr_code) IS NULL OR VALUES(gcash_qr_code) = '' THEN approved_at
                            ELSE NULL
                        END,
                        rejected_reason = CASE
                            WHEN VALUES(gcash_qr_code) IS NULL OR VALUES(gcash_qr_code) = '' THEN rejected_reason
                            ELSE NULL
                        END,
                        updated_at = NOW()";
                $qr_channel_stmt = mysqli_prepare($conn, $qr_channel_sql);
                mysqli_stmt_bind_param($qr_channel_stmt, "issss", $shop_id, $gcash_name, $gcash_number, $new_gcash_qr_name, $payment_instructions);
                mysqli_stmt_execute($qr_channel_stmt);
            }

            if ($merchant_link_changed || $existing_link_channel !== null) {
                $old_link_value = $existing_link_channel['merchant_link'] ?? '';
                $link_channel_sql = "INSERT INTO shop_payment_channels
                    (shop_id, channel, merchant_link, instructions, approval_status, is_active, created_at, updated_at)
                    VALUES (?, 'gcash_merchant_link', ?, ?, 'pending', 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        merchant_link = VALUES(merchant_link),
                        instructions = VALUES(instructions),
                        is_active = CASE WHEN VALUES(merchant_link) = '' THEN 0 ELSE 1 END,
                        approval_status = CASE
                            WHEN VALUES(merchant_link) = '' THEN approval_status
                            WHEN ? = VALUES(merchant_link) THEN approval_status
                            ELSE 'pending'
                        END,
                        approved_by = CASE
                            WHEN VALUES(merchant_link) = '' THEN approved_by
                            WHEN ? = VALUES(merchant_link) THEN approved_by
                            ELSE NULL
                        END,
                        approved_at = CASE
                            WHEN VALUES(merchant_link) = '' THEN approved_at
                            WHEN ? = VALUES(merchant_link) THEN approved_at
                            ELSE NULL
                        END,
                        rejected_reason = CASE
                            WHEN VALUES(merchant_link) = '' THEN rejected_reason
                            WHEN ? = VALUES(merchant_link) THEN rejected_reason
                            ELSE NULL
                        END,
                        updated_at = NOW()";
                $link_channel_stmt = mysqli_prepare($conn, $link_channel_sql);
                mysqli_stmt_bind_param($link_channel_stmt, "issssss", $shop_id, $merchant_link, $payment_instructions, $old_link_value, $old_link_value, $old_link_value, $old_link_value);
                mysqli_stmt_execute($link_channel_stmt);
            }
        }

        $allowed_service_types = [
            'Document Printing',
            'Lamination',
            'Photo Printing',
            'Tarpaulin Printing',
            'ID Printing',
            'Invitation / Card Printing',
            'Photocopy',
            'Binding',
            'Scanning',
        ];
        $visit_only_service_types = ['Photocopy', 'Binding', 'Scanning'];
        $default_service_notes = [
            'Photocopy' => 'This service requires physical documents. Online request is unavailable.',
            'Binding' => 'Physical document submission is required. Please visit the shop.',
            'Scanning' => 'Original documents are required. Online request is unavailable.',
        ];
        $service_types = array_values(array_unique(array_intersect(
            array_filter(array_map('trim', $_POST['service_types'] ?? [])),
            $allowed_service_types
        )));
        if (!in_array('Document Printing', $service_types, true)) {
            array_unshift($service_types, 'Document Printing');
        }
        $del_st = mysqli_prepare($conn, "DELETE FROM shop_service_types WHERE shop_id = ?");
        mysqli_stmt_bind_param($del_st, "i", $shop_id);
        mysqli_stmt_execute($del_st);
        if (!empty($service_types)) {
            $ins_st = mysqli_prepare($conn, "INSERT INTO shop_service_types (shop_id, service_type, service_offered, online_available, customer_note) VALUES (?, ?, 1, ?, ?)");
            foreach ($service_types as $st) {
                $online_available = in_array($st, $visit_only_service_types, true) ? 0 : 1;
                $customer_note = $default_service_notes[$st] ?? null;
                mysqli_stmt_bind_param($ins_st, "isis", $shop_id, $st, $online_available, $customer_note);
                mysqli_stmt_execute($ins_st);
            }
        }

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
            sendRoleNotification($conn, 'super_admin', '"' . $shop_name . '" submitted its business permit for review.', [
                'type' => 'permit_submitted',
                'title' => 'Permit verification submitted: ' . $shop_name,
                'target_url' => BASE_URL . 'frontend/user/superadmin/manage_print_shops.php?status=pending',
                'metadata' => ['shop_id' => (int) $shop_id, 'owner_id' => $owner_id],
            ]);
        }
        if ($payment_details_changed) {
            $changed_channels = [];
            if (!empty($qr_details_changed) && !$remove_gcash_qr) $changed_channels[] = 'GCash QR Code';
            if (!empty($merchant_link_changed)) $changed_channels[] = 'GCash Merchant Link';
            $channel_label = !empty($changed_channels) ? implode(' & ', $changed_channels) : 'Payment settings';

            sendRoleNotification($conn, 'super_admin', $channel_label . ' for "' . $shop_name . '" is ready for review.', [
                'type' => 'payment_settings_submitted',
                'title' => 'Payment details updated: ' . $shop_name,
                'target_url' => BASE_URL . 'frontend/user/superadmin/manage_print_shops.php#payment-settings-review',
                'metadata' => ['shop_id' => (int) $shop_id, 'owner_id' => $owner_id, 'channels' => $changed_channels],
            ]);
        }
        setMessage($message);

        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    } else {
        error_log("SQL execute error in save_shop_profile: " . mysqli_stmt_error($stmt));
        setError("Unable to save shop profile. Please try again.");
        header("Location: ../../frontend/user/shop_owner/shop_profile.php");
        exit();
    }
}
?>
