<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/rate_limit.php";

checkRole("customer");

$customer_id = $_SESSION['user_id'];
$profile_is_ajax = authIsAjaxRequest();

function profileSetupLog($message, array $context = [])
{
    $log_dir = __DIR__ . "/../logs";
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0,
        'ip' => activityRequestIp(),
        'event' => $message,
        'context' => $context,
    ];

    @file_put_contents($log_dir . "/profile_setup.log", json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function customerProfileAjaxResponse($success, $message, $redirect_url, $status_code, $toast_status = 'info')
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => (bool) $success,
        'message' => $message,
        'redirect' => $redirect_url,
        'status' => $toast_status,
    ]);
    exit();
}

function redirectCustomerProfileError($message, array $context = [])
{
    profileSetupLog($message, $context);
    if ($GLOBALS['profile_is_ajax']) {
        customerProfileAjaxResponse(false, $message, BASE_URL . "frontend/user/customer/profile.php", 422, 'error');
    }
    setError($message);
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}

function validateCustomerProfileCsrf()
{
    $token = $_POST['csrf_token'] ?? '';
    if ($token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        return;
    }

    profileSetupLog("Invalid CSRF token", [
        'stage' => 'csrf_validation',
        'has_post_token' => $token !== '',
        'has_session_token' => !empty($_SESSION['csrf_token']),
    ]);

    if ($GLOBALS['profile_is_ajax']) {
        customerProfileAjaxResponse(false, "Invalid security token. Please refresh the page and try again.", BASE_URL . "frontend/user/customer/profile.php", 403, 'error');
    }

    http_response_code(403);
    setError("Invalid security token. Please refresh the page and try again.");
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}

function saveCustomerUpload($field, $upload_dir, array $allowed_mimes, $prefix, $max_bytes = 10 * 1024 * 1024)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $upload_dir = rtrim($upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $upload_log_context = [
        'field' => $field,
        'name' => $_FILES[$field]['name'] ?? '',
        'size_bytes' => $_FILES[$field]['size'] ?? 0,
        'error' => $_FILES[$field]['error'] ?? null,
        'mime' => isset($_FILES[$field]['tmp_name']) && is_file($_FILES[$field]['tmp_name'])
            ? (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name'])
            : null,
    ];

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        redirectCustomerProfileError("Upload failed. The file must be 10MB or smaller. Please compress or resize and try again.", [
            'stage' => 'php_upload_error_check',
            'upload' => $upload_log_context,
        ]);
    }

    if ($_FILES[$field]['size'] > $max_bytes) {
        redirectCustomerProfileError("Uploaded files must be 10MB or smaller.", [
            'stage' => 'size_check',
            'upload' => $upload_log_context,
            'max_bytes' => $max_bytes,
        ]);
    }

    $tmp_name = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp_name)) {
        redirectCustomerProfileError("Upload failed. Please choose the file again and try saving your profile.", [
            'stage' => 'is_uploaded_file',
            'upload' => $upload_log_context,
        ]);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);

    if (!isset($allowed_mimes[$mime])) {
        redirectCustomerProfileError("Invalid file type. Please upload an allowed file format.", [
            'stage' => 'mime_check',
            'upload' => $upload_log_context,
            'detected_mime' => $mime,
        ]);
    }

    $file_name = $prefix . "_" . bin2hex(random_bytes(16)) . "." . $allowed_mimes[$mime];
    $destination = $upload_dir . $file_name;
    if (!move_uploaded_file($tmp_name, $destination)) {
        redirectCustomerProfileError("Unable to save uploaded file. Please try again.", [
            'stage' => 'move_uploaded_file',
            'upload' => $upload_log_context,
            'destination_dir' => $upload_dir,
            'destination_writable' => is_writable($upload_dir),
            'latest_error' => error_get_last(),
        ]);
    }

    profileSetupLog("Upload saved: " . $field, [
        'field' => $field,
        'size_bytes' => $_FILES[$field]['size'] ?? 0,
        'stored_as' => "uploads/customers/" . $file_name,
    ]);

    return "uploads/customers/" . $file_name;
}

function normalizeCustomerFullName($name)
{
    $name = trim((string) $name);
    $name = preg_replace('/\s+/', ' ', $name);

    if ($name === '') {
        redirectCustomerProfileError("Full name is required.");
    }

    $name_length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($name_length < 2 || $name_length > 100) {
        redirectCustomerProfileError("Full name must be between 2 and 100 characters.");
    }

    if (!preg_match("/^[\p{L}][\p{L} .'-]*[\p{L}.]$/u", $name)) {
        redirectCustomerProfileError("Full name may only contain letters, spaces, periods, hyphens, and apostrophes.");
    }

    if (!preg_match('/[\p{L}]{2,}/u', $name)) {
        redirectCustomerProfileError("Full name must include at least two letters.");
    }

    return $name;
}

if (isset($_POST['save_profile'])) {
    validateCustomerProfileCsrf();

    $rate_guard = rateLimitGuardRequest($conn, 'save_customer_profile', 10, 3600);
    if (!$rate_guard['allowed']) {
        redirectCustomerProfileError("Too many profile save attempts. Please try again in " . rateLimitFormatSeconds($rate_guard['retry_after']) . ".");
    }
    rateLimitRecordRequest($conn, 'save_customer_profile', $rate_guard['identifier'], $rate_guard['ip_address'], 10, 3600);

    profileSetupLog("Profile save request received", [
        'fields_present' => [
            'full_name' => isset($_POST['full_name']),
            'phone_number' => isset($_POST['phone_number']),
            'address' => isset($_POST['address']),
            'latitude' => ($_POST['latitude'] ?? '') !== '',
            'longitude' => ($_POST['longitude'] ?? '') !== '',
        ],
        'files' => array_map(function ($file) {
            return [
                'name' => $file['name'] ?? '',
                'size_bytes' => $file['size'] ?? 0,
                'error' => $file['error'] ?? null,
            ];
        }, $_FILES),
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
        'php_limits' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
        ],
    ]);

    $full_name = normalizeCustomerFullName($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($phone === '') {
        redirectCustomerProfileError("Phone number is required.", ['stage' => 'phone_validation']);
    }
    $phone_length = function_exists('mb_strlen') ? mb_strlen($phone, 'UTF-8') : strlen($phone);
    if ($phone_length < 7 || $phone_length > 20) {
        redirectCustomerProfileError("Phone number must be between 7 and 20 characters.", ['stage' => 'phone_validation', 'length' => $phone_length]);
    }
    if (!preg_match('/^[0-9+\-\s().]+$/', $phone)) {
        redirectCustomerProfileError("Phone number may only contain digits, spaces, and the + - ( ) characters.", ['stage' => 'phone_validation', 'value' => $phone]);
    }

    if ($address === '') {
        redirectCustomerProfileError("Address is required.", ['stage' => 'address_validation']);
    }
    $address_length = function_exists('mb_strlen') ? mb_strlen($address, 'UTF-8') : strlen($address);
    if ($address_length > 500) {
        redirectCustomerProfileError("Address must be 500 characters or fewer.", ['stage' => 'address_validation', 'length' => $address_length]);
    }

    $current_sql = "SELECT full_name, phone_number, address, profile_picture, valid_id_front_file, valid_id_back_file, account_status, latitude, longitude FROM users WHERE user_id = ? LIMIT 1";
    $current_stmt = mysqli_prepare($conn, $current_sql);
    mysqli_stmt_bind_param($current_stmt, "i", $customer_id);
    mysqli_stmt_execute($current_stmt);
    $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));

    $profile_picture_path = $current_user['profile_picture'] ?? null;
    $valid_id_front_path = $current_user['valid_id_front_file'] ?? null;
    $valid_id_back_path = $current_user['valid_id_back_file'] ?? null;
    $current_status = $current_user['account_status'] ?? 'incomplete';

    $upload_dir = __DIR__ . "/../../uploads/customers/";
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        redirectCustomerProfileError("Unable to prepare the upload folder. Please try again.", [
            'stage' => 'mkdir_upload_dir',
            'upload_dir' => $upload_dir,
            'latest_error' => error_get_last(),
        ]);
    }

    if (!is_writable($upload_dir)) {
        redirectCustomerProfileError("Unable to save uploads because the upload folder is not writable.", [
            'stage' => 'upload_dir_not_writable',
            'upload_dir' => $upload_dir,
        ]);
    }

    $new_profile_picture_path = saveCustomerUpload('profile_picture', $upload_dir, [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 'profile_' . $customer_id);

    if ($new_profile_picture_path !== null) {
        if (!empty($current_user['profile_picture']) && $current_user['profile_picture'] !== $new_profile_picture_path) {
            $old_file = __DIR__ . '/../../' . $current_user['profile_picture'];
            if (is_file($old_file)) @unlink($old_file);
        }
        $profile_picture_path = $new_profile_picture_path;
    }

    $new_valid_id_front_path = saveCustomerUpload('valid_id_front_file', $upload_dir, [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 'valid_id_front_' . $customer_id);

    if ($new_valid_id_front_path !== null) {
        if (!empty($current_user['valid_id_front_file']) && $current_user['valid_id_front_file'] !== $new_valid_id_front_path) {
            $old_file = __DIR__ . '/../../' . $current_user['valid_id_front_file'];
            if (is_file($old_file)) @unlink($old_file);
        }
        $valid_id_front_path = $new_valid_id_front_path;
    }

    $new_valid_id_back_path = saveCustomerUpload('valid_id_back_file', $upload_dir, [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 'valid_id_back_' . $customer_id);

    if ($new_valid_id_back_path !== null) {
        if (!empty($current_user['valid_id_back_file']) && $current_user['valid_id_back_file'] !== $new_valid_id_back_path) {
            $old_file = __DIR__ . '/../../' . $current_user['valid_id_back_file'];
            if (is_file($old_file)) @unlink($old_file);
        }
        $valid_id_back_path = $new_valid_id_back_path;
    }

    if ($current_status === 'verified') {
        $new_status = 'verified';
    } elseif (!empty($phone) && !empty($address) && !empty($valid_id_front_path) && !empty($valid_id_back_path)) {
        $new_status = 'pending';
    } else {
        $new_status = 'incomplete';
    }

    $lat_raw = trim($_POST['latitude'] ?? '');
    $lng_raw = trim($_POST['longitude'] ?? '');
    $lat = null;
    $lng = null;

    if ($lat_raw !== '' || $lng_raw !== '') {
        if ($lat_raw === '' || $lng_raw === '' || !is_numeric($lat_raw) || !is_numeric($lng_raw)) {
            redirectCustomerProfileError("Please choose a valid location on the map.", ['stage' => 'location_validation', 'lat' => $lat_raw, 'lng' => $lng_raw]);
        }

        $lat = (float) $lat_raw;
        $lng = (float) $lng_raw;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            redirectCustomerProfileError("Please choose a valid location on the map.", ['stage' => 'location_validation', 'lat' => $lat, 'lng' => $lng]);
        }
    } else {
        $lat = ($current_user['latitude'] ?? null) !== null ? (float) $current_user['latitude'] : null;
        $lng = ($current_user['longitude'] ?? null) !== null ? (float) $current_user['longitude'] : null;
    }

    $sql = "UPDATE users SET 
        full_name = ?,
        phone_number = ?, 
        address = ?, 
        profile_picture = ?, 
        valid_id_front_file = ?, 
        valid_id_back_file = ?, 
        latitude = ?, 
        longitude = ?, 
        account_status = ?
        WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $sql_error = mysqli_error($conn);
        error_log("SQL prepare error in save_customer_profile: " . $sql_error);
        profileSetupLog("SQL prepare error", ['sql_error' => $sql_error]);
        redirectCustomerProfileError("Unable to save profile. Please try again.", ['stage' => 'sql_prepare', 'sql_error' => $sql_error]);
    }

    mysqli_stmt_bind_param($stmt, "ssssssddsi", $full_name, $phone, $address, $profile_picture_path, $valid_id_front_path, $valid_id_back_path, $lat, $lng, $new_status, $customer_id);
    if (!mysqli_stmt_execute($stmt)) {
        $sql_error = mysqli_stmt_error($stmt);
        error_log("SQL execute error in save_customer_profile: " . $sql_error);
        profileSetupLog("SQL execute error", ['sql_error' => $sql_error]);
        redirectCustomerProfileError("Unable to save profile. Please try again.", ['stage' => 'sql_execute', 'sql_error' => $sql_error]);
    }

    $changed_fields = [];
    if (($current_user['full_name'] ?? '') !== $full_name) $changed_fields[] = 'full_name';
    if (($current_user['phone_number'] ?? '') !== $phone) $changed_fields[] = 'phone_number';
    if (($current_user['address'] ?? '') !== $address) $changed_fields[] = 'address';
    if ($new_profile_picture_path !== null) $changed_fields[] = 'profile_picture';
    if ($new_valid_id_front_path !== null) $changed_fields[] = 'valid_id_front_file';
    if ($new_valid_id_back_path !== null) $changed_fields[] = 'valid_id_back_file';
    if ($current_status !== $new_status) $changed_fields[] = 'account_status';

    logActivity($conn, $customer_id, "Updated customer profile", "Customer Profile", [
        'target_type' => 'user',
        'target_id' => $customer_id,
        'old_value' => [
            'full_name' => $current_user['full_name'] ?? null,
            'phone_number' => $current_user['phone_number'] ?? null,
            'address' => $current_user['address'] ?? null,
            'account_status' => $current_status,
            'has_profile_picture' => !empty($current_user['profile_picture']),
            'has_valid_id_front' => !empty($current_user['valid_id_front_file']),
            'has_valid_id_back' => !empty($current_user['valid_id_back_file']),
        ],
        'new_value' => [
            'full_name' => $full_name,
            'phone_number' => $phone,
            'address' => $address,
            'account_status' => $new_status,
            'changed_fields' => $changed_fields,
            'profile_picture_updated' => $new_profile_picture_path !== null,
            'valid_id_front_updated' => $new_valid_id_front_path !== null,
            'valid_id_back_updated' => $new_valid_id_back_path !== null,
        ],
    ]);

    $_SESSION['full_name'] = $full_name;

    if ($new_status === 'pending' && $current_status !== 'pending') {
        $customer_email = (string) ($_SESSION['email'] ?? '');
        sendRoleNotification($conn, 'super_admin', $full_name . ' (' . $customer_email . ') submitted their profile for verification.', [
            'type' => 'account_submitted', 'title' => 'Customer verification submitted: ' . $full_name,
            'target_url' => BASE_URL . 'frontend/user/superadmin/manage_users.php',
            'metadata' => ['user_id' => $customer_id, 'role' => 'customer'],
        ]);
    }

    if ($new_status === 'verified') {
        $profile_message = "Profile updated successfully.";
        $profile_toast_status = 'success';
    } elseif ($new_status === 'pending') {
        $profile_message = "Profile saved. Pending verification by Super Admin.";
        $profile_toast_status = 'warning';
    } else {
        $missing_fields = [];
        if (empty($phone)) $missing_fields[] = 'phone number';
        if (empty($address)) $missing_fields[] = 'address';
        if (empty($valid_id_front_path)) $missing_fields[] = 'front side of your valid ID';
        if (empty($valid_id_back_path)) $missing_fields[] = 'back side of your valid ID';
        $profile_message = "Profile saved, but it is still incomplete. Please provide: " . implode(', ', $missing_fields) . ".";
        $profile_toast_status = 'warning';
    }

    profileSetupLog("Profile save completed", [
        'new_status' => $new_status,
        'changed_fields' => $changed_fields,
        'message' => $profile_message,
    ]);

    if ($profile_is_ajax) {
        customerProfileAjaxResponse(true, $profile_message, BASE_URL . "frontend/user/customer/profile.php", 200, $profile_toast_status);
    }

    setToast($profile_message, $profile_toast_status);
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}
?>
