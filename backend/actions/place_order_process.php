<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/status_guard.php";
require_once __DIR__ . "/../includes/profile_guard.php";

checkRole("customer");
requireVerifiedStatus($conn);
requireCompleteCustomerProfile($conn);

validateCsrf();

function validatePlaceOrderUpload(array $file): bool
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        setError("Please upload a document file.");
        return false;
    }

    $max_file_size = 25 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_file_size) {
        setError("Document file must be 25MB or smaller.");
        return false;
    }

    $original_name = basename((string) ($file['name'] ?? ''));
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        setError("Only PDF files are accepted for print orders.");
        return false;
    }

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        setError("Please upload a valid PDF file.");
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);
    if ($mime !== 'application/pdf') {
        setError("Only PDF files are accepted for print orders.");
        return false;
    }

    $handle = fopen($tmp_name, 'rb');
    if ($handle === false) {
        setError("Please upload a valid PDF file.");
        return false;
    }

    $header = fread($handle, 4);
    fclose($handle);

    if ($header !== '%PDF') {
        setError("Please upload a valid PDF file.");
        return false;
    }

    return true;
}

if (isset($_POST['place_order'])) {
    $customer_id = $_SESSION['user_id'];
    $shop_id = intval($_POST['shop_id']);
    $paper_size = trim($_POST['paper_size']);
    $print_type = trim($_POST['print_type']);
    $copies = intval($_POST['copies']);
    $total_amount = floatval($_POST['total_amount']);

    if (!isset($_FILES['order_file']) || !validatePlaceOrderUpload($_FILES['order_file'])) {
        redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
    }

    // File upload
    $upload_dir = "../../uploads/orders/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_name = time() . "_" . bin2hex(random_bytes(4)) . ".pdf";
    $target_path = $upload_dir . $file_name;
    move_uploaded_file($_FILES['order_file']['tmp_name'], $target_path);
    $file_path_db = "uploads/orders/" . $file_name;

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
    mysqli_stmt_bind_param($file_stmt, "iss", $order_id, $file_name, $file_path_db);
    mysqli_stmt_execute($file_stmt);

    // Notify the shop owner
    $shop_sql = "SELECT owner_id FROM print_shops WHERE shop_id = ?";
    $shop_stmt = mysqli_prepare($conn, $shop_sql);
    mysqli_stmt_bind_param($shop_stmt, "i", $shop_id);
    mysqli_stmt_execute($shop_stmt);
    $shop_result = mysqli_stmt_get_result($shop_stmt);
    $shop = mysqli_fetch_assoc($shop_result);

    sendNotification($conn, $shop['owner_id'], "New order #$order_id has been placed.", [
        'type' => 'order_new', 'title' => 'New order',
        'target_url' => BASE_URL . "frontend/user/shop_owner/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id],
    ]);

    // Log activity
    logActivity($conn, $customer_id, "Placed order #$order_id", "Order Placement");

    setMessage("Order placed successfully.");
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}
?>
