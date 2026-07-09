<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("customer");

$customer_id = $_SESSION['user_id'];

function redirectCustomerProfileError($message)
{
    setError($message);
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}

function saveCustomerUpload($field, $upload_dir, array $allowed_mimes, $prefix)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        redirectCustomerProfileError("Upload failed. Please choose the file again.");
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        redirectCustomerProfileError("Uploaded files must be 5MB or smaller.");
    }

    $tmp_name = $_FILES[$field]['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);

    if (!isset($allowed_mimes[$mime])) {
        redirectCustomerProfileError("Invalid file type. Please upload an allowed file format.");
    }

    $file_name = $prefix . "_" . bin2hex(random_bytes(8)) . "." . $allowed_mimes[$mime];
    if (!move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
        redirectCustomerProfileError("Unable to save uploaded file. Please try again.");
    }

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
    $full_name = normalizeCustomerFullName($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    $current_sql = "SELECT full_name, phone_number, address, profile_picture, valid_id_file, account_status FROM users WHERE user_id = ? LIMIT 1";
    $current_stmt = mysqli_prepare($conn, $current_sql);
    mysqli_stmt_bind_param($current_stmt, "i", $customer_id);
    mysqli_stmt_execute($current_stmt);
    $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));

    $profile_picture_path = $current_user['profile_picture'] ?? null;
    $valid_id_path = $current_user['valid_id_file'] ?? null;
    $current_status = $current_user['account_status'] ?? 'incomplete';

    $upload_dir = "../../uploads/customers/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_profile_picture_path = saveCustomerUpload('profile_picture', $upload_dir, [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 'profile_' . $customer_id);

    if ($new_profile_picture_path !== null) {
        $profile_picture_path = $new_profile_picture_path;
    }

    $new_valid_id_path = saveCustomerUpload('valid_id_file', $upload_dir, [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ], 'valid_id_' . $customer_id);

    if ($new_valid_id_path !== null) {
        $valid_id_path = $new_valid_id_path;
    }

    if ($current_status === 'verified') {
        $new_status = 'verified';
    } elseif (!empty($phone) && !empty($address) && !empty($valid_id_path)) {
        $new_status = 'pending';
    } else {
        $new_status = 'incomplete';
    }

    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;

    $sql = "UPDATE users SET 
        full_name = ?,
        phone_number = ?, 
        address = ?, 
        profile_picture = ?, 
        valid_id_file = ?, 
        latitude = ?, 
        longitude = ?, 
        account_status = ?
        WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssddsi", $full_name, $phone, $address, $profile_picture_path, $valid_id_path, $lng, $lat, $new_status, $customer_id);
    mysqli_stmt_execute($stmt);

    $changed_fields = [];
    if (($current_user['full_name'] ?? '') !== $full_name) $changed_fields[] = 'full_name';
    if (($current_user['phone_number'] ?? '') !== $phone) $changed_fields[] = 'phone_number';
    if (($current_user['address'] ?? '') !== $address) $changed_fields[] = 'address';
    if ($new_profile_picture_path !== null) $changed_fields[] = 'profile_picture';
    if ($new_valid_id_path !== null) $changed_fields[] = 'valid_id_file';
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
            'has_valid_id' => !empty($current_user['valid_id_file']),
        ],
        'new_value' => [
            'full_name' => $full_name,
            'phone_number' => $phone,
            'address' => $address,
            'account_status' => $new_status,
            'changed_fields' => $changed_fields,
            'profile_picture_updated' => $new_profile_picture_path !== null,
            'valid_id_updated' => $new_valid_id_path !== null,
        ],
    ]);

    $_SESSION['full_name'] = $full_name;

    if ($new_status === 'pending' && $current_status !== 'pending') {
        sendRoleNotification($conn, 'super_admin', 'A customer profile is ready for verification.', [
            'type' => 'account_submitted', 'title' => 'Customer verification submitted',
            'target_url' => BASE_URL . 'frontend/user/superadmin/manage_users.php',
            'metadata' => ['user_id' => $customer_id, 'role' => 'customer'],
        ]);
    }

    setToast(
        $new_status === 'verified' ? "Profile updated successfully." : "Profile saved. Pending verification by Super Admin.",
        $new_status === 'verified' ? 'success' : 'warning'
    );
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}
?>
