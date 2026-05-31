<?php

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: $path");
    exit();
}

function setMessage($message) {
    $_SESSION['message'] = $message;
}

function showMessage() {
    if (isset($_SESSION['message'])) {
        echo "<p style='color: green;'>" . e($_SESSION['message']) . "</p>";
        unset($_SESSION['message']);
    }
}

function logActivity($conn, $user_id, $action, $module) {
    $sql = "INSERT INTO activity_logs (user_id, action, module) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $module);
    mysqli_stmt_execute($stmt);
}

function sendNotification($conn, $user_id, $message) {
    $sql = "INSERT INTO notifications (user_id, message) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $message);
    mysqli_stmt_execute($stmt);
}
?>