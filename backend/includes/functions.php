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

function setError($message) {
    $_SESSION['error'] = $message;
}

function showMessage() {
    if (isset($_SESSION['message'])) {
        echo "<div class='bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-sm'>" . e($_SESSION['message']) . "</div>";
        unset($_SESSION['message']);
    }

    if (isset($_SESSION['error'])) {
        echo "<div class='bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-sm'>" . e($_SESSION['error']) . "</div>";
        unset($_SESSION['error']);
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