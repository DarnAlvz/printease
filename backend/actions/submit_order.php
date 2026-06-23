<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../config/cloudinary.php";

checkRole("customer");
requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

if (!isset($_POST['submit_order'])) {
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

$customer_id = $_SESSION['user_id'];
$shop_id = intval($_POST['shop_id']);
$service_id = intval($_POST['service_id']);
$copies = intval($_POST['copies']);
$order_status = 'pending';
$instruction = trim($_POST['customer_instruction']);

// Basic validation - cant pick past date and time  
$pickup_datetime = $_POST['pickup_datetime'];
date_default_timezone_set('Asia/Manila');

$pickup_timestamp = strtotime($pickup_datetime);
$current_timestamp = time();

if ($pickup_timestamp === false || $pickup_timestamp < $current_timestamp) {
    setError("Please select a valid pickup date and time.");
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

if ($copies < 1 || empty($pickup_datetime)) {
    setError("Invalid order details.");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

if ($shop_id <= 0 || $service_id <= 0) {
    setError("Invalid shop or service selected.");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
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

if (!$service || $service['permit_status'] !== 'verified' || $service['shop_status'] === 'not_accepting') {
    setToast("Selected service is not available.", "warning");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    setError("Please upload a document file.");
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

$page_count = 1;
$detected_page_count = max(1, min(10000, (int) ($_POST['detected_page_count'] ?? 1)));
$original_name = basename($_FILES['document_file']['name']);
$file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

$file_tmp = $_FILES['document_file']['tmp_name'];
$is_pdf = $file_type === 'pdf';
$cloudinary_public_id = null;
$cloudinary_resource_type = null;
$transaction_started = false;

try {
    if ($is_pdf) {
        $page_count = getPdfPageCount($file_tmp, $detected_page_count);
    }

    $total_amount = (float) $service['price_per_page'] * $page_count * $copies;

    $uploadResult = $cloudinary->uploadApi()->upload(
        $file_tmp,
        [
            "folder" => "printease/orders",
            "resource_type" => "auto"
        ]
    );

    $db_path = $uploadResult['secure_url'] ?? '';
    $cloudinary_public_id = $uploadResult['public_id'] ?? null;
    $cloudinary_resource_type = $uploadResult['resource_type'] ?? 'auto';
    if ($db_path === '') {
        throw new Exception("Cloudinary upload did not return a file URL.");
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

    mysqli_begin_transaction($conn);
    $transaction_started = true;

    // Insert order
    $order_sql = "INSERT INTO orders
        (order_code, customer_id, shop_id, service_id, paper_size, paper_type, print_type, copies, page_count, customer_instruction, pickup_datetime, total_amount, order_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $order_stmt = mysqli_prepare($conn, $order_sql);
    if (!$order_stmt) {
        throw new Exception("Order SQL Error: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $order_stmt,
        "siiisssiissds",
        $order_code,
        $customer_id,
        $shop_id,
        $service_id,
        $service['paper_size'],
        $service['paper_type'],
        $service['print_type'],
        $copies,
        $page_count,
        $instruction,
        $pickup_datetime,
        $total_amount,
        $order_status
    );
    if (!mysqli_stmt_execute($order_stmt)) {
        throw new Exception("Failed to save order: " . mysqli_stmt_error($order_stmt));
    }

    $order_id = mysqli_insert_id($conn);


    // Insert uploaded file
    $file_sql = "INSERT INTO uploaded_files (order_id, file_name, file_path, file_type)
                 VALUES (?, ?, ?, ?)";
    $file_stmt = mysqli_prepare($conn, $file_sql);
    if (!$file_stmt)
        throw new Exception("Uploaded file SQL Error: " . mysqli_error($conn));
    mysqli_stmt_bind_param(
        $file_stmt,
        "isss",
        $order_id,
        $original_name,
        $db_path,
        $file_type
    );
    if (!mysqli_stmt_execute($file_stmt)) {
        throw new Exception("Failed to save uploaded file: " . mysqli_stmt_error($file_stmt));
    }

    // Notify shop owner
    if (!sendNotification($conn, $service['owner_id'], "New print order received. Order #$order_code.", [
        'type' => 'order_new',
        'title' => 'New print order',
        'target_url' => BASE_URL . "frontend/user/shop_owner/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code],
    ])) {
        throw new Exception("Failed to notify shop owner.");
    }

    mysqli_commit($conn);
    $transaction_started = false;

    setMessage("Order submitted successfully. Your Order # is " . $order_code . ".", [
        'title' => 'Order submitted',
        'action_label' => 'View order',
        'action_url' => BASE_URL . 'frontend/user/customer/orders.php?focus_order_id=' . $order_id,
    ]);
    redirect(BASE_URL . "frontend/user/customer/orders.php");

} catch (Throwable $e) {
    if ($transaction_started) {
        mysqli_rollback($conn);
    }

    if ($cloudinary_public_id) {
        try {
            $cloudinary->uploadApi()->destroy($cloudinary_public_id, [
                "resource_type" => $cloudinary_resource_type ?: "auto",
            ]);
        } catch (Throwable $cleanup_exception) {
            error_log("Cloudinary cleanup failed for {$cloudinary_public_id}: " . $cleanup_exception->getMessage());
        }
    }

    setError("Order submission failed: " . $e->getMessage());
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
    exit();
}

?>
