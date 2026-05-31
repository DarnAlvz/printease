<?php
session_start();

function checkRole($required_role) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../frontend/pages/login.php");
        exit();
    }

    if ($_SESSION['role'] != $required_role) {
        echo "Access denied.";
        exit();
    }
}
?>