<?php
include "../config/db.php";
include "../config/app.php";
include "../includes/auth.php";
include "../includes/functions.php";
include "../includes/status_guard.php";

checkRole("customer");
requireVerifiedStatus($conn);

if (isset($_POST['place_order'])) {
    $customer_id = $_SESSION['user_id'];
    $shop_id = intval($_POST['shop_id']);
    $paper_size = trim($_POST['paper_size']);
    $print_type = trim($_POST['print_type']);
    $copies = intval($_POST['copies']);
    $total_amount = floatval($_POST['total_amount']);

    // File upload validation
    if (!isset($_FILES['order_file']) || $_FILES['order_file']['error'] !== 0) {
        setMessage("Please upload a valid file.");
        redirect(BASE_URL . "frontend/pages/place_order.php");
    }

    $file_name = $_FILES['order_file']['name'];
    $file_tmp = $_FILES['order_file']['tmp_name'];
    $new_name = time() . "_" . basename($file_name);
    $upload_path = "../../uploads/orders/" . $new_name;

    if (!move_uploaded_file($file_tmp, $upload_path)) {
        setMessage("File upload failed.");
        redirect(BASE_URL . "frontend/pages/place_order.php");
    }

    // Prepare SQL variables
    $customer_id_var = $customer_id;
    $shop_id_var = $shop_id;
    $paper_size_var = $paper_size;
    $print_type_var = $print_type;
    $copies_var = $copies;
    $total_amount_var = $total_amount;

    // Insert order
    $sql = "INSERT INTO orders (customer_id, shop_id, paper_size, print_type, copies, order_status, total_amount) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iissid", $customer_id_var, $shop_id_var, $paper_size_var, $print_type_var, $copies_var, $total_amount_var);
    mysqli_stmt_execute($stmt);
    $order_id = mysqli_insert_id($conn);

    // Insert uploaded file record
    $file_sql = "INSERT INTO uploaded_files (order_id, file_name, file_path) VALUES (?, ?, ?)";
    $file_stmt = mysqli_prepare($conn, $file_sql);
    $file_path_db = "uploads/orders/" . $new_name;
    mysqli_stmt_bind_param($file_stmt, "iss", $order_id, $file_name, $file_path_db);
    mysqli_stmt_execute($file_stmt);

    // Notify the shop owner
    $shop_sql = "SELECT owner_id FROM print_shops WHERE shop_id = ?";
    $shop_stmt = mysqli_prepare($conn, $shop_sql);
    mysqli_stmt_bind_param($shop_stmt, "i", $shop_id);
    mysqli_stmt_execute($shop_stmt);
    $shop_result = mysqli_stmt_get_result($shop_stmt);
    $shop = mysqli_fetch_assoc($shop_result);

    sendNotification($conn, $shop['owner_id'], "New order #$order_id has been placed.");

    // Log activity
    logActivity($conn, $customer_id, "Placed order #$order_id", "Order Placement");

    setMessage("Order placed successfully.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}
?>
