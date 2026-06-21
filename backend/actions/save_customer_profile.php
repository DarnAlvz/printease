<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("customer");

$customer_id = $_SESSION['user_id'];

if (isset($_POST['save_profile'])) {
    $phone = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    $current_sql = "SELECT profile_picture, valid_id_file, account_status FROM users WHERE user_id = ? LIMIT 1";
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

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $file_name = time() . "_" . basename($_FILES['profile_picture']['name']);
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $file_name);
        $profile_picture_path = "uploads/customers/" . $file_name;
    }

    if (isset($_FILES['valid_id_file']) && $_FILES['valid_id_file']['error'] == 0) {
        $file_name = time() . "_id_" . basename($_FILES['valid_id_file']['name']);
        move_uploaded_file($_FILES['valid_id_file']['tmp_name'], $upload_dir . $file_name);
        $valid_id_path = "uploads/customers/" . $file_name;
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
        phone_number = ?, 
        address = ?, 
        profile_picture = ?, 
        valid_id_file = ?, 
        latitude = ?, 
        longitude = ?, 
        account_status = ?
        WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssddsi", $phone, $address, $profile_picture_path, $valid_id_path, $lng, $lat, $new_status, $customer_id);
    mysqli_stmt_execute($stmt);

    logActivity($conn, $customer_id, "Updated customer profile", "Customer Profile");

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
