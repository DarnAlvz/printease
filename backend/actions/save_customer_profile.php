<?php
include "../config/db.php";
include "../config/app.php";
include "../includes/auth.php";
include "../includes/functions.php";

checkRole("customer");

$customer_id = $_SESSION['user_id'];

if(isset($_POST['save_profile'])) {
    $phone = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    // Profile Picture upload
    $profile_picture_path = null;
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0){
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = time() . "_" . basename($_FILES['profile_picture']['name']);
        $upload_path = "../../uploads/customers/" . $file_name;
        move_uploaded_file($file_tmp, $upload_path);
        $profile_picture_path = "uploads/customers/" . $file_name;
    }

    // Valid ID upload
    $valid_id_path = null;
    if(isset($_FILES['valid_id_file']) && $_FILES['valid_id_file']['error'] == 0){
        $file_tmp = $_FILES['valid_id_file']['tmp_name'];
        $file_name = time() . "_id_" . basename($_FILES['valid_id_file']['name']);
        $upload_path = "../../uploads/customers/" . $file_name;
        move_uploaded_file($file_tmp, $upload_path);
        $valid_id_path = "uploads/customers/" . $file_name;
    }

    // Update users table; do NOT auto-verify
    $sql = "UPDATE users SET 
            phone_number=?, 
            address=?, 
            profile_picture=COALESCE(?, profile_picture), 
            valid_id_file=COALESCE(?, valid_id_file), 
            account_status='pending'
            WHERE user_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $phone, $address, $profile_picture_path, $valid_id_path, $customer_id);
    mysqli_stmt_execute($stmt);

    logActivity($conn, $customer_id, "Updated customer profile and uploaded ID (pending verification)", "Customer Profile");
    setMessage("Profile saved. Pending verification by Super Admin.");
    redirect(BASE_URL . "frontend/user/customer/profile.php");
}
?>