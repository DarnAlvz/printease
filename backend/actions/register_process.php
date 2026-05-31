<?php
include "../config/db.php";

if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Only normal users can register
    $allowed_roles = ['customer', 'shop_owner'];

    if (!in_array($role, $allowed_roles)) {
        die("Invalid role selected.");
    }

    $account_status = 'incomplete';

    $sql = "INSERT INTO users (full_name, email, password, role, account_status) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "sssss", $full_name, $email, $password, $role, $account_status);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: ../../frontend/pages/login.php?registered=success");
        exit();
    } else {
        echo "Registration failed. Email may already exist.";
    }
}
?>