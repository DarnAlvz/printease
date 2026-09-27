<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/profile_guard.php";
require_once __DIR__ . "/../includes/rate_limit.php";
require_once __DIR__ . "/../config/cloudinary.php";

checkRole("customer");
requireCustomerFeatureAccess($conn);

validateCsrf();

$expected_token = $_SESSION['order_submit_token'] ?? null;
$submitted_token = trim((string) ($_POST['order_submit_token'] ?? ''));
if ($expected_token === null || $submitted_token === '' || !hash_equals($expected_token, $submitted_token)) {
    setMessage("Your request was already submitted. Please check your Orders page.", [
        'title' => 'Request already submitted',
        'action_label' => 'View requests',
        'action_url' => BASE_URL . 'frontend/user/customer/orders.php',
    ]);
    redirect(BASE_URL . "frontend/user/customer/orders.php");
}

$order_customer_key = rateLimitCurrentUserKey('order');
$order_ip = rateLimitClientIp();
$order_rate = rateLimitCheck($conn, 'order_place', $order_customer_key, $order_ip, 15, 3600);
if (!$order_rate['allowed']) {
    $wait_label = rateLimitFormatSeconds($order_rate['retry_after']);
    setError("You have reached the maximum number of requests. Please try again in {$wait_label}.");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

if (!isset($_POST['submit_order'])) {
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

$customer_id = $_SESSION['user_id'];
$shop_id = intval($_POST['shop_id']);
$service_id = intval($_POST['service_id']);
$order_service_type = trim((string) ($_POST['order_service_type'] ?? $_POST['customer_service_type'] ?? 'Document Printing'));
$is_document_order = $order_service_type === '' || $order_service_type === 'Document Printing';
$service_pricing_id = intval($_POST['service_pricing_id'] ?? 0);
$copies = $is_document_order ? max(1, min(1000, intval($_POST['copies'] ?? 1))) : max(1, min(1000, intval($_POST['service_quantity'] ?? 1)));
$order_status = 'pending';
$instruction = trim((string) ($_POST['customer_instruction'] ?? ''));

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
    setError("Invalid request details.");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

if ($shop_id <= 0) {
    setError("Invalid shop or service selected.");
    redirect(BASE_URL . "frontend/user/customer/explore.php?view=all");
}

if (!$is_document_order && $service_id <= 0) {
    $fallback_sql = "SELECT service_id FROM shop_services WHERE shop_id = ? AND is_available = 1 ORDER BY service_id ASC LIMIT 1";
    $fallback_stmt = mysqli_prepare($conn, $fallback_sql);
    mysqli_stmt_bind_param($fallback_stmt, "i", $shop_id);
    mysqli_stmt_execute($fallback_stmt);
    $fallback = mysqli_fetch_assoc(mysqli_stmt_get_result($fallback_stmt));
    $service_id = (int) ($fallback['service_id'] ?? 0);
}

if ($service_id <= 0) {
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

$service_price = null;
if (!$is_document_order) {
    if ($service_pricing_id <= 0) {
        setError("Please select a valid service option.");
        redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
    }

    $pricing_sql = "SELECT spp.id, spp.service_type, spp.option_size, spp.option_label, spp.unit, spp.price, sst.online_available
                    FROM shop_service_pricing spp
                    INNER JOIN shop_service_types sst
                        ON sst.shop_id = spp.shop_id
                        AND sst.service_type = spp.service_type
                    WHERE spp.id = ?
                    AND spp.shop_id = ?
                    AND spp.is_available = 1
                    AND sst.service_offered = 1
                    AND sst.online_available = 1
                    AND spp.service_type IN ('Lamination', 'Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing')
                    LIMIT 1";
    $pricing_stmt = mysqli_prepare($conn, $pricing_sql);
    mysqli_stmt_bind_param($pricing_stmt, "ii", $service_pricing_id, $shop_id);
    mysqli_stmt_execute($pricing_stmt);
    $service_price = mysqli_fetch_assoc(mysqli_stmt_get_result($pricing_stmt));

    if (!$service_price) {
        setToast("Selected service option is not available.", "warning");
        redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
    }
}

if ($is_document_order && (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK)) {
    setError("Please upload a document file.");
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

function isOfficeDocumentExtension($extension)
{
    return in_array($extension, ['doc', 'docx', 'wps'], true);
}

function officeDocumentAllowedMime($extension, $mime)
{
    $mime = strtolower((string) $mime);
    $allowed_by_extension = [
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'doc' => [
            'application/msword',
            'application/octet-stream',
            'application/x-ole-storage',
        ],
        'wps' => [
            'application/vnd.ms-works',
            'application/x-wps',
            'application/wps-office',
            'application/msword',
            'application/zip',
            'application/octet-stream',
        ],
    ];

    $allowed = $allowed_by_extension[$extension] ?? [];
    return in_array($mime, $allowed, true);
}

function validateOrderDocumentUpload(array $file)
{
    $max_file_size = 25 * 1024 * 1024;

    if (($file['size'] ?? 0) > $max_file_size) {
        setError("Document file must be 25MB or smaller.");
        return false;
    }

    $original_name = basename((string) ($file['name'] ?? ''));
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if ($extension !== 'pdf' && !isOfficeDocumentExtension($extension)) {
        setError("Only PDF, DOC, DOCX, or WPS files are accepted for print requests.");
        return false;
    }

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        if (isOfficeDocumentExtension($extension)) {
            setError("Please upload a valid document file.");
            return false;
        }
        setError("Please upload a valid PDF file.");
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);

    if (isOfficeDocumentExtension($extension)) {
        if (!officeDocumentAllowedMime($extension, $mime)) {
            setError("Only PDF, DOC, DOCX, or WPS files are accepted for print requests.");
            return false;
        }
        return true;
    }
    if ($mime !== 'application/pdf') {
        setError("Only PDF files are accepted for print requests.");
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

    if (!validatePdfStructure($tmp_name)) {
        setError("Please upload a valid PDF file. The file you uploaded is not a readable PDF.");
        return false;
    }

    return true;
}

function validateServiceUpload(array $file)
{
    $max_file_size = 25 * 1024 * 1024;
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        setError("Please upload a file for this service request.");
        return false;
    }

    if ($error !== UPLOAD_ERR_OK) {
        setError("Please upload a valid attachment.");
        return false;
    }

    if (($file['size'] ?? 0) > $max_file_size) {
        setError("Attachment must be 25MB or smaller.");
        return false;
    }

    $original_name = basename((string) ($file['name'] ?? ''));
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        setError("Attachment must be a PDF, JPG, or PNG file.");
        return false;
    }

    $extension_mime_map = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];
    $expected_mime = $extension_mime_map[$extension] ?? null;

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        setError("Please upload a valid attachment.");
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);
    $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mime, $allowed_mimes, true)) {
        setError("Attachment must be a PDF, JPG, or PNG file.");
        return false;
    }

    if ($expected_mime !== null && $mime !== $expected_mime) {
        setError("Attachment file type does not match its extension.");
        return false;
    }

    if ($mime === 'application/pdf' && !validatePdfStructure($tmp_name)) {
        setError("Attachment must be a valid PDF file. The file you uploaded is not a readable PDF.");
        return false;
    }

    if (($mime === 'image/jpeg' || $mime === 'image/png') && !validateImageStructure($tmp_name, $mime)) {
        setError("Attachment must be a valid image file. The file you uploaded is not a readable image.");
        return false;
    }

    return true;
}

$active_upload_key = $is_document_order ? 'document_file' : 'service_file';
$has_upload = isset($_FILES[$active_upload_key]) && (int) ($_FILES[$active_upload_key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($is_document_order && !validateOrderDocumentUpload($_FILES['document_file'])) {
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

if (!$is_document_order && (!isset($_FILES['service_file']) || !validateServiceUpload($_FILES['service_file']))) {
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
}

function buildCloudinaryOrderSafeName($original_name)
{
    $original_name = basename((string) $original_name);
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $base_name = pathinfo($original_name, PATHINFO_FILENAME);

    $safe_base = strtolower($base_name);
    $safe_base = preg_replace('/[^a-z0-9]+/', '_', $safe_base);
    $safe_base = trim($safe_base, '_');

    if ($safe_base === '') {
        $safe_base = 'order_file';
    }

    $safe_base = substr($safe_base, 0, 80);
    $unique_suffix = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $public_id = $safe_base . '_' . $unique_suffix;

    if ($extension !== '') {
        $safe_extension = preg_replace('/[^a-z0-9]/', '', $extension);
        if ($safe_extension !== '') {
            $public_id .= '.' . $safe_extension;
        }
    }

    return $public_id;
}

function createCloudinaryOrderUploadCopy($source_path, $safe_name)
{
    $root = realpath(__DIR__ . "/../..");
    if ($root === false) {
        throw new Exception("Project upload directory is not available.");
    }

    $temp_dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order_upload_tmp';
    if (!is_dir($temp_dir) && !mkdir($temp_dir, 0755, true)) {
        throw new Exception("Could not prepare request upload temp directory.");
    }

    $temp_dir_real = realpath($temp_dir);
    if ($temp_dir_real === false || !is_dir($temp_dir_real)) {
        throw new Exception("Request upload temp directory is not available.");
    }

    $target_path = $temp_dir_real . DIRECTORY_SEPARATOR . $safe_name;
    if (!copy($source_path, $target_path)) {
        throw new Exception("Could not prepare uploaded file for cloud storage.");
    }

    return $target_path;
}

function isDuplicateKeyError($conn)
{
    return (int) mysqli_errno($conn) === 1062;
}

function isSubmitTokenDuplicateError($conn)
{
    return (int) mysqli_errno($conn) === 1062
        && str_contains((string) mysqli_error($conn), 'uq_orders_submit_token');
}

class OrderAlreadySubmittedException extends Exception
{
}

$page_count = 1;
$detected_page_count = max(1, min(10000, (int) ($_POST['detected_page_count'] ?? 1)));
$original_name = $has_upload ? basename($_FILES[$active_upload_key]['name']) : '';
$file_type = $has_upload ? strtolower(pathinfo($original_name, PATHINFO_EXTENSION)) : '';
$file_tmp = $has_upload ? $_FILES[$active_upload_key]['tmp_name'] : '';
$cloudinary_public_id = null;
$cloudinary_resource_type = null;
$cloudinary_upload_copy = null;
$transaction_started = false;

try {
    if ($is_document_order) {
        if ($file_type === 'pdf') {
            $page_count = getPdfPageCount($file_tmp, $detected_page_count);
        } else {
            $page_count = max(1, min(10000, $detected_page_count));
        }
        $total_amount = (float) $service['price_per_page'] * $page_count * $copies;
        $order_paper_size = $service['paper_size'];
        $order_paper_type = $service['paper_type'];
        $order_print_type = $service['print_type'];
    } else {
        $page_count = 1;
        $total_amount = (float) $service_price['price'] * $copies;
        if (in_array(($service_price['service_type'] ?? ''), ['Photo Printing', 'Tarpaulin Printing', 'ID Printing', 'Invitation / Card Printing'], true)) {
            $order_paper_size = $service_price['option_size'];
            $order_paper_type = $service_price['option_label'];
            $order_print_type = $service_price['unit'];
            $instruction_prefix = "Service request: {$service_price['service_type']} - {$order_paper_size} / {$order_paper_type} / {$order_print_type}.";
        } else {
            $order_paper_size = $service_price['option_label'];
            $order_paper_type = $service_price['service_type'];
            $order_print_type = 'per item';
            $instruction_prefix = "Service request: {$service_price['service_type']} - {$service_price['option_label']}.";
        }
        $instruction = trim($instruction_prefix . ($instruction !== '' ? "\n\n" . $instruction : ''));
    }

    $db_path = '';
    if ($has_upload) {
        $cloudinary_safe_name = buildCloudinaryOrderSafeName($original_name);
        $cloudinary_upload_copy = createCloudinaryOrderUploadCopy($file_tmp, $cloudinary_safe_name);

        $uploadResult = $cloudinary->uploadApi()->upload(
            $cloudinary_upload_copy,
            [
                "folder" => "printease/orders",
                "resource_type" => "raw",
                "public_id" => $cloudinary_safe_name,
                "use_filename" => false,
                "unique_filename" => false
            ]
        );

        if ($cloudinary_upload_copy && is_file($cloudinary_upload_copy)) {
            unlink($cloudinary_upload_copy);
            $cloudinary_upload_copy = null;
        }

        $db_path = $uploadResult['secure_url'] ?? '';
        $cloudinary_public_id = $uploadResult['public_id'] ?? null;
        $cloudinary_resource_type = $uploadResult['resource_type'] ?? 'auto';
        if ($db_path === '') {
            throw new Exception("Cloudinary upload did not return a file URL.");
        }
    }

    mysqli_begin_transaction($conn);
    $transaction_started = true;

    // Insert order
    $order_sql = "INSERT INTO orders
        (order_code, submit_token, customer_id, shop_id, service_id, paper_size, paper_type, print_type, copies, page_count, customer_instruction, pickup_datetime, total_amount, order_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $order_stmt = mysqli_prepare($conn, $order_sql);
    if (!$order_stmt) {
        throw new Exception("Failed to prepare request. Please try again.");
    }

    $order_code = '';
    $submit_token = $submitted_token;
    mysqli_stmt_bind_param(
        $order_stmt,
        "ssiiisssiissds",
        $order_code,
        $submit_token,
        $customer_id,
        $shop_id,
        $service_id,
        $order_paper_size,
        $order_paper_type,
        $order_print_type,
        $copies,
        $page_count,
        $instruction,
        $pickup_datetime,
        $total_amount,
        $order_status
    );

    $order_inserted = false;
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $random_code = strtoupper(bin2hex(random_bytes(3)));
        $order_code = 'PE-' . date('Ymd') . '-' . $random_code;

        if (mysqli_stmt_execute($order_stmt)) {
            $order_inserted = true;
            break;
        }

        if (isSubmitTokenDuplicateError($conn)) {
            throw new OrderAlreadySubmittedException();
        }

        if (!isDuplicateKeyError($conn)) {
            throw new Exception("Failed to save request. Please try again.");
        }
    }

    if (!$order_inserted) {
        throw new Exception("Could not generate a unique request code. Please try again.");
    }

    $order_id = mysqli_insert_id($conn);


    if ($has_upload) {
        // Insert uploaded file
        $file_sql = "INSERT INTO uploaded_files (order_id, file_name, file_path, file_type)
                     VALUES (?, ?, ?, ?)";
        $file_stmt = mysqli_prepare($conn, $file_sql);
        if (!$file_stmt)
            throw new Exception("Failed to prepare file upload. Please try again.");
        mysqli_stmt_bind_param(
            $file_stmt,
            "isss",
            $order_id,
            $original_name,
            $db_path,
            $file_type
        );
        if (!mysqli_stmt_execute($file_stmt)) {
            throw new Exception("Failed to save uploaded file. Please try again.");
        }
    }

    // Notify shop owner
    $notification_label = $is_document_order ? 'print request' : strtolower((string) ($service_price['service_type'] ?? $order_paper_type)) . ' request';
    if (!sendNotification($conn, $service['owner_id'], "New {$notification_label} received. Request #$order_code.", [
        'type' => 'order_new',
        'title' => $is_document_order ? 'New print request' : 'New service request',
        'target_url' => BASE_URL . "frontend/user/shop_owner/orders.php?focus_order_id=$order_id",
        'metadata' => ['order_id' => $order_id, 'order_code' => $order_code],
    ])) {
        throw new Exception("Failed to notify shop owner.");
    }

    mysqli_commit($conn);
    $transaction_started = false;

    setMessage("Request submitted successfully. Your request # is " . $order_code . ".", [
        'title' => 'Request submitted',
        'action_label' => 'View request',
        'action_url' => BASE_URL . 'frontend/user/customer/orders.php?focus_order_id=' . $order_id,
    ]);
    unset($_SESSION['order_submit_token']);
    rateLimitRecord($conn, 'order_place', $order_customer_key, $order_ip, 15, 3600, 3600);
    redirect(BASE_URL . "frontend/user/customer/orders.php");

} catch (Throwable $e) {
    if ($cloudinary_upload_copy && is_file($cloudinary_upload_copy)) {
        unlink($cloudinary_upload_copy);
    }

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

    if ($e instanceof OrderAlreadySubmittedException) {
        unset($_SESSION['order_submit_token']);
        setMessage("Your request was already submitted. Please check your Orders page.", [
            'title' => 'Request already submitted',
            'action_label' => 'View requests',
            'action_url' => BASE_URL . 'frontend/user/customer/orders.php',
        ]);
        redirect(BASE_URL . "frontend/user/customer/orders.php");
    }

    setError("Request submission failed: " . $e->getMessage());
    redirect(BASE_URL . "frontend/user/customer/place_order.php?shop_id=" . $shop_id);
    exit();
}

?>
