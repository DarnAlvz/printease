<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";

checkRole("customer");
requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

if (!isset($_POST['submit_order'])) {
    redirect(BASE_URL . "frontend/user/customer/shops.php");
}

$customer_id = $_SESSION['user_id'];
$shop_id = intval($_POST['shop_id']);
$service_id = intval($_POST['service_id']);
$copies = intval($_POST['copies']);
$instruction = trim($_POST['customer_instruction']);
$pickup_datetime = $_POST['pickup_datetime'];

if ($copies < 1 || empty($pickup_datetime)) {
    setMessage("Invalid order details.");
    redirect(BASE_URL . "frontend/user/customer/shops.php");
}

// Fetch service and shop info
$service_sql = "SELECT ss.*, ps.owner_id, ps.shop_status, ps.permit_status
                FROM shop_services ss
                JOIN print_shops ps ON ss.shop_id = ps.shop_id
                WHERE ss.service_id = ?
                AND ss.shop_id = ?
                AND ss.is_available = 1
                LIMIT 1";

$service_stmt = mysqli_prepare($conn, $service_sql);
mysqli_stmt_bind_param($service_stmt, "ii", $service_id, $shop_id);
mysqli_stmt_execute($service_stmt);
$service = mysqli_fetch_assoc(mysqli_stmt_get_result($service_stmt));

if (!$service || $service['permit_status'] !== 'verified' || $service['shop_status'] !== 'available') {
    setMessage("Selected service is not available.");
    redirect(BASE_URL . "frontend/user/customer/shops.php");
}

if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    setMessage("Please upload a valid document.");
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

$total_amount = $service['price_per_page'] * $copies;

// File upload
$upload_dir = "../../uploads/orders/";
if (!is_dir($upload_dir))
    mkdir($upload_dir, 0777, true);

$original_name = basename($_FILES['document_file']['name']);
$file_type = pathinfo($original_name, PATHINFO_EXTENSION);
$new_name = time() . "_" . $original_name;
$target_path = $upload_dir . $new_name;
$db_path = "uploads/orders/" . $new_name;

mysqli_begin_transaction($conn);

try {
    if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $target_path)) {
        throw new Exception("File upload failed.");
    }


    // Generate unique order_code
    do {
        $random_code = strtoupper(bin2hex(random_bytes(3))); // 6-char random
        $order_code = 'PE-' . date('Ymd') . '-' . $random_code;

        // Check if it exists in DB
        $check_sql = "SELECT COUNT(*) as count FROM orders WHERE order_code = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $order_code);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $row = mysqli_fetch_assoc($result);
    } while ($row['count'] > 0); // loop until unique

    // Insert order
    $order_sql = "INSERT INTO orders
        (order_code, customer_id, shop_id, service_id, paper_size, paper_type, print_type, copies, customer_instruction, pickup_datetime, total_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $order_stmt = mysqli_prepare($conn, $order_sql);
    mysqli_stmt_bind_param(
        $order_stmt,
        "siiisssissd",
        $order_code,
        $customer_id,
        $shop_id,
        $service_id,
        $service['paper_size'],
        $service['paper_type'],
        $service['print_type'],
        $copies,
        $instruction,
        $pickup_datetime,
        $total_amount
    );
    mysqli_stmt_execute($order_stmt);

    $order_id = mysqli_insert_id($conn);

    // Insert uploaded file
    $file_sql = "INSERT INTO uploaded_files (order_id, file_name, file_path, file_type)
                 VALUES (?, ?, ?, ?)";
    $file_stmt = mysqli_prepare($conn, $file_sql);
    if (!$file_stmt)
        throw new Exception("Uploaded file SQL Error: " . mysqli_error($conn));
    mysqli_stmt_bind_param($file_stmt, "isss", $order_id, $original_name, $db_path, $file_type);
    mysqli_stmt_execute($file_stmt);

    // Notify shop owner
    sendNotification($conn, $service['owner_id'], "New print order received. Order #$order_code.");

    mysqli_commit($conn);

    setMessage("Order submitted successfully. Your Order # is " . $order_code . ".");
    redirect(BASE_URL . "frontend/user/customer/orders.php");

} catch (Exception $e) {
    mysqli_rollback($conn);
    setMessage("Order failed: " . $e->getMessage());
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}
?>