<?php
require_once __DIR__ . "/../config/db.php";

if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Only normal users can register
    $allowed_roles = ['customer', 'shop_owner'];

    if (!in_array($role, $allowed_roles)) {
        header("Location: ../../frontend/pages/register.php?error=invalid_role");
        exit();
    }

    $account_status = 'incomplete';

    $sql = "INSERT INTO users (full_name, email, password, role, account_status) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        header("Location: ../../frontend/pages/register.php?error=registration_failed");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $password, $role, $account_status);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: ../../frontend/pages/login.php?registered=success");
        exit();
    } else {
        $error = mysqli_errno($conn) === 1062 ? 'duplicate_email' : 'registration_failed';
        header("Location: ../../frontend/pages/register.php?error=" . $error);
        exit();
    }
}
?>
