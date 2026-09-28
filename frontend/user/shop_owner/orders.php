<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/actions/pickup_reminder_checker.php";
require_once __DIR__ . "/includes/owner_layout.php";

requireCompleteShopProfile($conn);
$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

function orderPageUrl($page, $search_code, $status_filter)
{
    $params = ['page' => max(1, (int) $page)];
    if ($search_code !== '') {
        $params['order_code'] = $search_code;
    }
    if ($status_filter !== 'all') {
        $params['status'] = $status_filter;
    }
    return 'orders.php?' . http_build_query($params);
}

$owner_id = $_SESSION['user_id'];
$focus_order_id = max(0, (int) ($_GET['focus_order_id'] ?? 0));
$focus_order_code = trim($_GET['focus_order_code'] ?? '');

function markRelatedOwnerOrderNotificationsRead($conn, $owner_id, $focus_order_id, $focus_order_code)
{
    $focus_order_id = (int) $focus_order_id;
    $focus_order_code = trim((string) $focus_order_code);

    if ($focus_order_id <= 0 && $focus_order_code === '') {
        return false;
    }

    $updated = false;

    if ($focus_order_id > 0) {
        $target_like_end = '%focus_order_id=' . $focus_order_id;
        $target_like_with_more_params = '%focus_order_id=' . $focus_order_id . '&%';
        $json_like = '%"order_id":' . $focus_order_id . '%';
        $spaced_json_like = '%"order_id": ' . $focus_order_id . '%';
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = COALESCE(read_at, NOW())
                WHERE user_id = ?
                  AND is_read = 0
                  AND (
                      target_url LIKE ?
                      OR target_url LIKE ?
                      OR metadata_json LIKE ?
                      OR metadata_json LIKE ?
                  )";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issss", $owner_id, $target_like_end, $target_like_with_more_params, $json_like, $spaced_json_like);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) > 0 || $updated;
    }

    if ($focus_order_code !== '') {
        $like = '%focus_order_code=' . $focus_order_code . '%';
        $json_like = '%"order_code":"' . $focus_order_code . '"%';
        $spaced_json_like = '%"order_code": "' . $focus_order_code . '"%';
        $sql = "UPDATE notifications
                SET is_read = 1, read_at = COALESCE(read_at, NOW())
                WHERE user_id = ?
                  AND is_read = 0
                  AND (
                      target_url LIKE ?
                      OR metadata_json LIKE ?
                      OR metadata_json LIKE ?
                  )";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isss", $owner_id, $like, $json_like, $spaced_json_like);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) > 0 || $updated;
    }

    return $updated;
}

markRelatedOwnerOrderNotificationsRead($conn, $owner_id, $focus_order_id, $focus_order_code);

$notif_sql = "SELECT COUNT(*) AS total
              FROM notifications
              WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_row = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt));
$notif_count = $notif_row['total'] ?? 0;

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    die("Please complete your shop profile first.");
}

$shop_id = (int) $shop['shop_id'];
$search_code = trim($_GET['order_code'] ?? '');
$allowed_filters = ['all', 'pending', 'processing', 'ready_for_pickup', 'completed'];
$status_filter = $_GET['status'] ?? 'all';
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

$per_page = 5;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = "WHERE o.shop_id = ?";
$where .= " AND o.order_status != 'cancelled'";
$types = "i";
$params = [$shop_id];

if ($search_code !== '') {
    $where .= " AND LOWER(o.order_code) LIKE ?";
    $types .= "s";
    $params[] = '%' . strtolower($search_code) . '%';
}

if ($status_filter !== 'all') {
    $where .= " AND o.order_status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

$count_filtered_sql = "SELECT COUNT(*) AS total
                       FROM orders o
                       JOIN users u ON o.customer_id = u.user_id
                       $where";
$count_filtered_stmt = mysqli_prepare($conn, $count_filtered_sql);
mysqli_stmt_bind_param($count_filtered_stmt, $types, ...$params);
mysqli_stmt_execute($count_filtered_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($count_filtered_stmt))['total'] ?? 0);

$total_pages = max(1, (int) ceil($filtered_total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$focus_sort = '';
$focus_sort_types = '';
$focus_sort_params = [];
if ($focus_order_id > 0 && $focus_order_code !== '') {
    $focus_sort = 'CASE WHEN o.order_id = ? OR o.order_code = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 'is';
    $focus_sort_params = [$focus_order_id, $focus_order_code];
} elseif ($focus_order_id > 0) {
    $focus_sort = 'CASE WHEN o.order_id = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 'i';
    $focus_sort_params = [$focus_order_id];
} elseif ($focus_order_code !== '') {
    $focus_sort = 'CASE WHEN o.order_code = ? THEN 0 ELSE 1 END,';
    $focus_sort_types = 's';
    $focus_sort_params = [$focus_order_code];
}

$orders_sql = "SELECT o.*, u.full_name, u.email, u.profile_picture, p.payment_id, p.payment_status, p.verification_status,
                       p.reference_number, p.ocr_reference_number, p.payment_reference_match,
                       p.ocr_payment_date, p.proof_of_payment_file, p.rejection_reason, p.created_at
                AS payment_submitted_at
               FROM orders o
               JOIN users u ON o.customer_id = u.user_id
               LEFT JOIN payments p ON o.order_id = p.order_id
               $where
               ORDER BY $focus_sort
                        CASE o.order_status
                            WHEN 'processing' THEN 1
                            WHEN 'pending' THEN 2
                            WHEN 'ready_for_pickup' THEN 3
                            WHEN 'completed' THEN 4
                            ELSE 5
                        END,
                        o.created_at DESC
               LIMIT ? OFFSET ?";
$orders_stmt = mysqli_prepare($conn, $orders_sql);
$orders_types = $types . $focus_sort_types . "ii";
$orders_params = array_merge($params, $focus_sort_params, [$per_page, $offset]);
mysqli_stmt_bind_param($orders_stmt, $orders_types, ...$orders_params);
mysqli_stmt_execute($orders_stmt);
$result = mysqli_stmt_get_result($orders_stmt);

$orders = [];
while ($order = mysqli_fetch_assoc($result)) {
    $orders[] = $order;
}

$order_notes_map = [];
$order_notes_table_exists = false;
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'order_notes'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $order_notes_table_exists = true;
    $note_order_ids = [];
    foreach ($orders as $note_order) {
        $note_order_ids[(int) ($note_order['order_id'] ?? 0)] = true;
    }
    unset($note_order_ids[0]);
    if (!empty($note_order_ids)) {
        $note_placeholders = implode(',', array_fill(0, count($note_order_ids), '?'));
        $note_types = str_repeat('i', count($note_order_ids));
        $note_params = array_keys($note_order_ids);
        $notes_sql = "SELECT note_id, order_id, note_type, note_text, created_at FROM order_notes WHERE order_id IN ($note_placeholders) ORDER BY created_at DESC, note_id DESC";
        $notes_stmt = mysqli_prepare($conn, $notes_sql);
        if ($notes_stmt) {
            mysqli_stmt_bind_param($notes_stmt, $note_types, ...$note_params);
            mysqli_stmt_execute($notes_stmt);
            $notes_result = mysqli_stmt_get_result($notes_stmt);
            while ($note_row = mysqli_fetch_assoc($notes_result)) {
                // Overwrite semantics: keep only the latest note per order (legacy rows may stack).
                $map_order_id = (int) $note_row['order_id'];
                if (!isset($order_notes_map[$map_order_id])) {
                    $order_notes_map[$map_order_id] = [$note_row];
                }
            }
        }
    }
}

$shop_service_type_lookup = [];
$service_types_stmt = mysqli_prepare($conn, "SELECT service_type FROM shop_service_types WHERE shop_id = ?");
if ($service_types_stmt) {
    mysqli_stmt_bind_param($service_types_stmt, "i", $shop_id);
    mysqli_stmt_execute($service_types_stmt);
    $service_types_result = mysqli_stmt_get_result($service_types_stmt);
    while ($service_type_row = mysqli_fetch_assoc($service_types_result)) {
        $service_type = trim((string) ($service_type_row['service_type'] ?? ''));
        if ($service_type !== '') {
            $shop_service_type_lookup[mb_strtolower($service_type, 'UTF-8')] = $service_type;
        }
    }
}

$counts = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'ready_for_pickup' => 0,
    'completed' => 0,
];
$count_sql = "SELECT
                SUM(CASE WHEN order_status != 'cancelled' THEN 1 ELSE 0 END) AS total,
                SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) AS ready_for_pickup,
                SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) AS completed
              FROM orders
              WHERE shop_id = ?";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $shop_id);
mysqli_stmt_execute($count_stmt);
$counts = array_merge($counts, mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt)) ?: []);

$showing_start = $filtered_total > 0 ? $offset + 1 : 0;
$showing_end = min($offset + count($orders), $filtered_total);

// Helper function to determine payment status label
function paymentStatusLabel($payment_status, $verification_status)
{
    if ($payment_status === 'paid' && $verification_status === 'verified') {
        return 'Paid';
    } elseif ($verification_status === 'pending') {
        return 'For Verification';
    } elseif ($verification_status === 'rejected') {
        return 'Rejected';
    }
    return 'Unpaid';
}

function ownerOrderIsPaidAndPending(array $order)
{
    return ($order['order_status'] ?? '') === 'pending'
        && ($order['payment_status'] ?? '') === 'paid'
        && ($order['verification_status'] ?? '') === 'verified';
}

function orderHasActivePaymentProof(array $order)
{
    if (empty($order['payment_id'])) {
        return false;
    }

    return !(($order['payment_status'] ?? '') === 'unpaid' && ($order['verification_status'] ?? '') === 'rejected');
}

function ownerSelectedServiceName(array $order, array $service_type_lookup)
{
    $instruction = trim((string) ($order['customer_instruction'] ?? ''));
    if (preg_match('/Service request:\s*([^-\.]+?)\s*-/i', $instruction, $matches)) {
        return trim($matches[1]);
    }

    $candidate = trim((string) ($order['paper_type'] ?? ''));
    if ($candidate !== '') {
        $key = mb_strtolower($candidate, 'UTF-8');
        if (isset($service_type_lookup[$key])) {
            return $service_type_lookup[$key];
        }
    }

    return 'Document Printing';
}

function ownerOrderCustomerInstruction(array $order)
{
    $instruction = trim((string) ($order['customer_instruction'] ?? ''));

    return trim((string) preg_replace('/^Service request:.*?\.\s*/is', '', $instruction));
}

function ownerDownloadFileUrl(array $file)
{
    $file_id = (int) ($file['file_id'] ?? 0);
    if ($file_id <= 0) {
        return '';
    }

    return BASE_URL . 'backend/actions/download_order_file.php?file_id=' . $file_id;
}

function ownerAcceptDownloadUrl(array $file)
{
    $file_id = (int) ($file['file_id'] ?? 0);
    if ($file_id <= 0) {
        return '';
    }

    // Accept & Download must force download (like PDF): never open raw docx inline.
    return BASE_URL . 'backend/actions/download_order_file.php?file_id=' . $file_id . '&mode=download';
}

function ownerOrderFileExtension(array $file)
{
    $from_name = strtolower(pathinfo((string) ($file['file_name'] ?? ''), PATHINFO_EXTENSION));
    if ($from_name !== '') {
        return $from_name;
    }

    return strtolower(trim((string) ($file['file_type'] ?? '')));
}

// viewable: doc/docx -> click opens Office viewer (new tab).
// download-only: wps -> click forces download (viewer has no .wps support).
// native: pdf/images -> keep existing inline preview.
function ownerOrderFileKind(array $file)
{
    $ext = ownerOrderFileExtension($file);

    if ($ext === 'doc' || $ext === 'docx') {
        return 'viewable';
    }

    if ($ext === 'wps') {
        return 'download-only';
    }

    return 'native';
}

function ownerOrderViewerUrl(array $file)
{
    if (ownerOrderFileKind($file) !== 'viewable') {
        return '';
    }

    $path = trim((string) ($file['file_path'] ?? ''));
    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, '//')) {
        $path = 'https:' . $path;
    }

    // Only public Cloudinary URLs can be fed to the Office viewer.
    if (!preg_match('/^https:\/\/res\.cloudinary\.com\//i', $path)) {
        return '';
    }

    return 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($path);
}

function ownerOrderPreviewUrl(array $file)
{
    $kind = ownerOrderFileKind($file);
    if ($kind === 'viewable') {
        $viewer = ownerOrderViewerUrl($file);
        if ($viewer !== '') {
            return $viewer;
        }
    }

    if ($kind !== 'native') {
        $forced = ownerAcceptDownloadUrl($file);
        if ($forced !== '') {
            return $forced;
        }
    }

    return ownerDownloadFileUrl($file);
}

function ownerDownloadFileName(array $file)
{
    $file_name = trim((string) ($file['file_name'] ?? ''));
    if ($file_name === '') {
        $file_name = 'request-file';
    }

    $file_name = preg_replace('/[^\w.\- ()]+/', '_', $file_name);
    return trim($file_name, '._ ') ?: 'request-file';
}

function ownerCustomerInitials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'C';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'C';
}

function ownerCustomerProfilePictureUrl(array $order)
{
    $path = trim((string) ($order['profile_picture'] ?? ''));
    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return BASE_URL . ltrim($path, '/');
}

function renderOwnerCustomerIdentity(array $order, $show_email = false)
{
    $name = trim((string) ($order['full_name'] ?? ''));
    if ($name === '') {
        $name = 'Customer';
    }

    $email = trim((string) ($order['email'] ?? ''));
    $photo_url = ownerCustomerProfilePictureUrl($order);
?>
    <span class="owner-customer-identity">
        <span class="owner-customer-avatar" aria-hidden="true">
            <?php if ($photo_url !== ''): ?>
                <img src="<?php echo e($photo_url); ?>" alt="">
            <?php else: ?>
                <b><?php echo e(ownerCustomerInitials($name)); ?></b>
            <?php endif; ?>
        </span>
        <span class="owner-customer-copy">
            <strong title="<?php echo e($name); ?>"><?php echo e($name); ?></strong>
            <?php if ($show_email && $email !== ''): ?>
                <small><?php echo e($email); ?></small>
            <?php endif; ?>
        </span>
    </span>
<?php
}

function renderAcceptDownloadForm(array $order, array $file_rows, $hidden = false)
{
?>
    <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
        class="orders-update-form orders-status-action order-modal-accept-form" data-accept-download-form
        data-owner-local-handler="true"
        data-order-id="<?php echo e($order['order_id']); ?>" <?php echo $hidden ? 'hidden' : ''; ?>>
        <?php echo csrfField(); ?>
        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
        <input type="hidden" name="order_status" value="processing">
        <?php foreach ($file_rows as $file): ?>
            <?php $download_url = ownerAcceptDownloadUrl($file); ?>
            <?php if ($download_url !== ''): ?>
                <input type="hidden" data-download-url value="<?php echo e($download_url); ?>"
                    data-download-name="<?php echo e(ownerDownloadFileName($file)); ?>"
                    data-file-ext="<?php echo e(ownerOrderFileExtension($file)); ?>"
                    data-file-kind="<?php echo e(ownerOrderFileKind($file)); ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" name="update_order" class="btn order-btn-completed">
            Accept &amp; Download
        </button>
    </form>
<?php
}

function renderDeclineOrderForm(array $order)
{
?>
    <form action="<?php echo BASE_URL; ?>backend/actions/decline_order.php" method="POST"
        class="orders-update-form orders-status-action" data-decline-order-form
        data-order-id="<?php echo e($order['order_id']); ?>">
        <?php echo csrfField(); ?>
        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
        <input type="hidden" name="decline_order" value="1">
        <button type="submit" name="decline_order" class="btn btn-danger">Decline Request</button>
    </form>
<?php
}

ownerLayoutStart('orders', 'Print Job Management', '', $notif_count, $shop, $owner_toast);
?>

<nav class="orders-tabs" aria-label="Print job status filters" data-live-region="owner-order-tabs">
    <?php
    $tabs = [
        'all' => ['label' => 'All Jobs', 'count' => (int) $counts['total'], 'icon' => 'package'],
        'pending' => ['label' => 'Pending Jobs', 'count' => (int) $counts['pending'], 'icon' => 'clock'],
        'processing' => ['label' => 'In Progress', 'count' => (int) $counts['processing'], 'icon' => 'trending-up'],
        'ready_for_pickup' => ['label' => 'Ready for Pickup', 'count' => (int) $counts['ready_for_pickup'], 'icon' => 'package-check'],
        'completed' => ['label' => 'Completed', 'count' => (int) $counts['completed'], 'icon' => 'circle-check'],
    ];
    foreach ($tabs as $key => $tab):
        $tab_url = orderPageUrl(1, $search_code, $key);
    ?>
        <a class="<?php echo $status_filter === $key ? 'active' : ''; ?>" href="<?php echo e($tab_url); ?>">
            <?php echo ownerIcon($tab['icon'], 'icon-sm'); ?>
            <?php echo e($tab['label']); ?>
            <span><?php echo (int) $tab['count']; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<section class="orders-summary-grid" data-live-region="owner-order-summary">
    <article class="orders-summary-card pending">
        <div class="orders-summary-icon"><?php echo ownerIcon('clock', 'icon'); ?></div>
        <strong><?php echo (int) $counts['pending']; ?></strong>
        <h2>Pending Jobs</h2>
        <p>Awaiting acceptance</p>
    </article>
    <article class="orders-summary-card processing">
        <div class="orders-summary-icon"><?php echo ownerIcon('trending-up', 'icon'); ?></div>
        <strong><?php echo (int) $counts['processing']; ?></strong>
        <h2>In Progress</h2>
        <p>Currently printing</p>
    </article>
    <article class="orders-summary-card ready">
        <div class="orders-summary-icon"><?php echo ownerIcon('package', 'icon'); ?></div>
        <strong><?php echo (int) $counts['ready_for_pickup']; ?></strong>
        <h2>Ready Jobs</h2>
        <p>Ready for pickup</p>
    </article>
    <article class="orders-summary-card completed">
        <div class="orders-summary-icon"><?php echo ownerIcon('circle-check', 'icon'); ?></div>
        <strong><?php echo (int) $counts['completed']; ?></strong>
        <h2>Completed Jobs</h2>
        <p>Successfully picked up</p>
    </article>
</section>

<section class="orders-search-card">
    <form method="GET" data-live-search-form data-live-target="owner_orders" data-live-min="1">
        <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
        <div class="orders-search-box">
            <?php echo ownerIcon('search', 'icon'); ?>
            <input type="text" name="order_code" placeholder="Search by request code..."
                value="<?php echo e($search_code); ?>">
        </div>
        <button type="submit" class="orders-submit-hidden">Search</button>
        <?php if ($search_code !== ''): ?>
            <a href="orders.php<?php echo $status_filter !== 'all' ? '?status=' . e($status_filter) : ''; ?>"
                class="orders-clear-search" aria-label="Clear search"><?php echo ownerIcon('x', 'icon-sm'); ?></a>
        <?php endif; ?>
    </form>
</section>

<?php if (empty($orders)): ?>
    <section class="owner-card empty-state" data-live-region="owner-order-results">
        <h2>No print jobs found</h2>
        <p>New customer print requests will appear here.</p>
    </section>
<?php else: ?>
    <?php $order_files = []; ?>
    <section class="orders-table-card" data-live-region="owner-order-results">
        <div class="owner-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Request Code</th>
                        <th>Customer Name</th>
                        <th>File Name</th>
                        <th>Print Details</th>
                        <th>Pickup Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                        $selected_service_name = ownerSelectedServiceName($order, $shop_service_type_lookup);
                        $is_document_service = strcasecmp($selected_service_name, 'Document Printing') === 0;
                        $is_photo_printing = strcasecmp($selected_service_name, 'Photo Printing') === 0;
                        $is_tarpaulin_printing = strcasecmp($selected_service_name, 'Tarpaulin Printing') === 0;
                        $is_id_printing = strcasecmp($selected_service_name, 'ID Printing') === 0;
                        $is_invitation_card_printing = strcasecmp($selected_service_name, 'Invitation / Card Printing') === 0;
                        $is_detailed_service = $is_photo_printing || $is_tarpaulin_printing || $is_id_printing || $is_invitation_card_printing;
                        $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                        $file_stmt = mysqli_prepare($conn, $file_sql);
                        mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                        mysqli_stmt_execute($file_stmt);
                        $files = mysqli_stmt_get_result($file_stmt);
                        $file_rows = [];
                        while ($file = mysqli_fetch_assoc($files)) {
                            $file_rows[] = $file;
                        }
                        $first_file = $file_rows[0]['file_name'] ?? 'No uploaded file';
                        $order_files[(int) $order['order_id']] = $file_rows;
                        ?>
                        <?php $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0) || ($search_code !== '' && strcasecmp($search_code, $order['order_code']) === 0); ?>
                        <tr class="<?php echo $is_focused_order ? 'order-focused' : ''; ?>"
                            data-order-row="<?php echo e($order['order_id']); ?>">
                            <td><strong><?php echo e($order['order_code']); ?></strong></td>
                            <td>
                                <?php renderOwnerCustomerIdentity($order); ?>
                            </td>
                            <td><span class="order-file-name" title="<?php echo e($first_file); ?>"><?php echo e($first_file); ?></span></td>
                            <td>
                                <div class="print-detail-chips">
                                    <span title="Service: <?php echo e($selected_service_name); ?>"><?php echo ownerIcon('package', 'icon-sm'); ?>Service: <?php echo e($selected_service_name); ?></span>
                                    <span title="<?php echo $is_document_service ? 'Paper: ' : 'Size: '; ?><?php echo e($order['paper_size']); ?>"><?php echo ownerIcon('file-text', 'icon-sm'); ?><?php echo $is_document_service ? 'Paper: ' : 'Size: '; ?><?php echo e($order['paper_size']); ?></span>
                                    <?php if ($is_document_service): ?>
                                        <span title="<?php echo e($order['print_type']); ?>"><?php echo ownerIcon('printer', 'icon-sm'); ?><?php echo e($order['print_type']); ?></span>
                                        <span title="<?php echo e($order_page_count); ?> pages x<?php echo e($order['copies']); ?>"><?php echo e($order_page_count); ?>p x<?php echo e($order['copies']); ?></span>
                                    <?php elseif ($is_detailed_service): ?>
                                        <span title="<?php echo e($order['paper_type']); ?> / <?php echo e($order['print_type']); ?>"><?php echo ownerIcon('printer', 'icon-sm'); ?><?php echo e($order['paper_type']); ?> / <?php echo e($order['print_type']); ?></span>
                                        <span class="chip-qty" title="Qty: <?php echo e($order['copies']); ?>">Qty: <?php echo e($order['copies']); ?></span>
                                    <?php else: ?>
                                        <span class="chip-qty" title="Qty: <?php echo e($order['copies']); ?>"><?php echo ownerIcon('printer', 'icon-sm'); ?>Qty: <?php echo e($order['copies']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <small class="muted print-detail-muted" title="<?php echo $is_document_service || $is_detailed_service ? e($order['paper_type']) : e($selected_service_name); ?>"><?php echo $is_document_service || $is_detailed_service ? e($order['paper_type']) : e($selected_service_name); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($order['pickup_datetime'])): ?>
                                    <strong><?php echo ownerIcon('clock', 'icon-sm'); ?><?php echo e(date("g:i A", strtotime($order['pickup_datetime']))); ?></strong>
                                <?php else: ?>
                                    <span class="muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span
                                    class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                                    data-order-status-badge="<?php echo e($order['order_id']); ?>">
                                    <?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?>
                                    <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="orders-actions">
                                    <button type="button" class="btn order-btn-navy"
                                        data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View
                                        Details</button>

                                    <?php if ($owner_is_verified && $order['order_status'] === 'processing'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                                            class="orders-update-form orders-status-action">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="ready_for_pickup">
                                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as
                                                Ready</button>
                                        </form>
                                    <?php elseif ($owner_is_verified && $order['order_status'] === 'ready_for_pickup'): ?>
                                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                                            class="orders-update-form orders-status-action">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                            <input type="hidden" name="order_status" value="completed">
                                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as
                                                Completed</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($orders as $order): ?>
            <?php
            $file_rows = $order_files[(int) $order['order_id']] ?? [];
            $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
            $selected_service_name = ownerSelectedServiceName($order, $shop_service_type_lookup);
            $is_document_service = strcasecmp($selected_service_name, 'Document Printing') === 0;
            $is_photo_printing = strcasecmp($selected_service_name, 'Photo Printing') === 0;
            $is_tarpaulin_printing = strcasecmp($selected_service_name, 'Tarpaulin Printing') === 0;
            $is_id_printing = strcasecmp($selected_service_name, 'ID Printing') === 0;
            $is_invitation_card_printing = strcasecmp($selected_service_name, 'Invitation / Card Printing') === 0;
            $is_detailed_service = $is_photo_printing || $is_tarpaulin_printing || $is_id_printing || $is_invitation_card_printing;
            $pricing_basis = $is_document_service ? 'Per Page' : trim((string) ($order['print_type'] ?? ''));
            ?>
            <div class="order-modal" id="order-modal-<?php echo e($order['order_id']); ?>" aria-hidden="true">
                <div class="order-modal-backdrop" data-order-modal-close></div>
                <section class="order-modal-dialog" role="dialog" aria-modal="true"
                    aria-labelledby="order-modal-title-<?php echo e($order['order_id']); ?>">
                    <header class="order-modal-header">
                        <h2 id="order-modal-title-<?php echo e($order['order_id']); ?>">Print Job Details</h2>
                        <button type="button" class="order-modal-close" data-order-modal-close aria-label="Close print job details">
                            <?php echo ownerIcon('x', 'icon'); ?>
                        </button>
                    </header>

                    <div class="order-modal-body">
                        <section class="order-modal-note">
                            <?php echo ownerIcon('info', 'icon-sm'); ?>
                            <div>
                                <h3>Customer Instructions</h3>
                                <p><?php echo e(ownerOrderCustomerInstruction($order) ?: 'No instruction'); ?></p>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>File Preview</h3>
                            <div class="order-file-preview">
                                <?php echo ownerIcon('file-text', 'icon-xl'); ?>
                                <?php if (empty($file_rows)): ?>
                                    <strong>No uploaded file</strong>
                                    <span>No file is attached to this print job.</span>
                                <?php else: ?>
                                    <?php
                                    $first_file_ext = strtolower(pathinfo((string) ($file_rows[0]['file_name'] ?? ''), PATHINFO_EXTENSION));
                                    $first_file_type = strtolower((string) ($file_rows[0]['file_type'] ?? $first_file_ext));
                                    $is_office_file = in_array($first_file_type, ['doc', 'docx', 'wps'], true);
                                    $file_label = count($file_rows) > 1
                                        ? count($file_rows) . ' Uploaded Files'
                                        : ($is_office_file ? 'Word / WPS Document' : 'PDF Document');
                                    ?>
                                    <strong><?php echo e($file_label); ?></strong>
                                    <?php foreach ($file_rows as $file): ?>
                                        <?php
                                        $preview_url = ownerOrderPreviewUrl($file);
                                        $preview_kind = ownerOrderFileKind($file);
                                        $preview_hint = $preview_kind === 'viewable'
                                            ? 'Preview in new tab (Office viewer)'
                                            : ($preview_kind === 'download-only' ? 'Download file' : 'Open file in new tab');
                                        if ($preview_url === '') {
                                            $preview_url = ownerDownloadFileUrl($file);
                                        }
                                        ?>
                                        <a href="<?php echo e($preview_url); ?>"
                                            target="_blank" rel="noopener" class="order-file-name" title="<?php echo e($file['file_name'] . ' — ' . $preview_hint); ?>"><?php echo e($file['file_name']); ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>Print Settings</h3>
                            <div class="order-settings-grid">
                                <div class="order-setting-card">
                                    <span>Service</span>
                                    <strong><?php echo e($selected_service_name); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span><?php echo $is_document_service ? 'Paper Size' : 'Size'; ?></span>
                                    <strong><?php echo e($order['paper_size'] ?: 'Not set'); ?></strong>
                                </div>
                                <?php if ($is_document_service || $is_detailed_service): ?>
                                    <div class="order-setting-card">
                                        <span><?php echo ($is_tarpaulin_printing || $is_id_printing) ? 'Material' : 'Paper Type'; ?></span>
                                        <strong><?php echo e($order['paper_type'] ?: 'Not set'); ?></strong>
                                    </div>
                                    <div class="order-setting-card">
                                        <span>Print Type</span>
                                        <strong><?php echo e($order['print_type'] ?: 'Not set'); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($is_document_service): ?>
                                    <div class="order-setting-card">
                                        <span>Pricing Basis</span>
                                        <strong><?php echo e($pricing_basis ?: 'Not set'); ?></strong>
                                    </div>
                                    <div class="order-setting-card">
                                        <span>Pages</span>
                                        <strong><?php echo e($order_page_count); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="order-setting-card">
                                    <span><?php echo $is_document_service ? 'Copies' : 'Quantity'; ?></span>
                                    <strong><?php echo e($order['copies'] ?: 'Not set'); ?></strong>
                                </div>
                                <div class="order-setting-card">
                                    <span>Print Volume</span>
                                    <strong><?php echo e($order_page_count); ?> x <?php echo e($order['copies'] ?: 1); ?></strong>
                                </div>
                            </div>
                        </section>

                        <section class="order-modal-section">
                            <h3>Print Job Information</h3>
                            <div class="order-info-list">
                                <div>
                                    <span>Request Code</span>
                                    <strong><?php echo e($order['order_code']); ?></strong>
                                </div>
                                <div class="order-info-customer">
                                    <span>Customer</span>
                                    <?php renderOwnerCustomerIdentity($order, true); ?>
                                </div>
                                <div>
                                    <span>Request Sent</span>
                                    <strong><?php echo e(!empty($order['created_at']) ? date('M d, Y - g:i A', strtotime($order['created_at'])) : 'Not set'); ?></strong>
                                </div>
                                <div>
                                    <span>Preferred Pickup Time</span>
                                    <strong><?php echo e(!empty($order['pickup_datetime']) ? date('g:i A', strtotime($order['pickup_datetime'])) : 'Not set'); ?></strong>
                                </div>
                                <div>
                                    <span>Status</span>
                                    <strong><span
                                            class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                                            data-order-status-badge="<?php echo e($order['order_id']); ?>"><?php echo ownerIcon($order['order_status'] === 'completed' ? 'circle-check' : ($order['order_status'] === 'processing' ? 'trending-up' : ($order['order_status'] === 'ready_for_pickup' ? 'package' : 'clock')), 'icon-sm'); ?><?php echo e(ownerStatusLabel($order['order_status'])); ?></span></strong>
                                </div>
                                <div>
                                    <span>Payment Status</span>
                                    <strong>
                                        <span class="status-badge" data-payment-status-label="<?php echo e($order['payment_id'] ?? ''); ?>">
                                            <?php echo e(paymentStatusLabel($order['payment_status'] ?? '', $order['verification_status'] ?? '')); ?>
                                        </span>
                                    </strong>
                                </div>

                                <div>
                                    <span>Verification Status</span>
                                    <strong data-verification-status-label="<?php echo e($order['payment_id'] ?? ''); ?>">
                                        <?php
                                        if (empty($order['payment_id'])) {
                                            echo "No Payment Submitted";
                                        } elseif (($order['verification_status'] ?? '') === 'verified') {
                                            echo "Verified";
                                        } elseif (($order['verification_status'] ?? '') === 'pending') {
                                            echo "For Verification";
                                        } elseif (($order['verification_status'] ?? '') === 'rejected') {
                                            echo "Rejected";
                                        } else {
                                            echo "Unverified";
                                        }
                                        ?>
                                    </strong>
                                </div>

                                <?php if (!empty($order['reference_number']) && empty($order['ocr_reference_number'])): ?>
                                    <div>
                                        <span>Reference No.</span>
                                        <strong><?php echo e($order['reference_number']); ?></strong>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($order['payment_id'])): ?>
                                    <div>
                                        <span>Detected Reference No.</span>
                                        <strong><?php echo e($order['ocr_reference_number'] ?: $order['reference_number'] ?: 'Not detected'); ?></strong>
                                    </div>
                                    <div>
                                        <span>Detected Payment Date</span>
                                        <strong><?php echo !empty($order['ocr_payment_date']) ? e(date('M d, Y', strtotime($order['ocr_payment_date']))) : 'Not detected'; ?></strong>
                                    </div>
                                    <div>
                                        <span>Proof Submitted</span>
                                        <strong><?php echo !empty($order['payment_submitted_at']) ? e(date('M d, Y - g:i A', strtotime($order['payment_submitted_at']))) : 'Not available'; ?></strong>
                                    </div>
                                    <div>
                                        <span>OCR Status</span>
                                        <strong>
                                            <?php
                                            $match_status = $order['payment_reference_match'] ?: 'not_detected';
                                            $match_class = match ($match_status) {
                                                'detected' => 'status-success',
                                                'partial' => 'status-warning',
                                                'not_detected' => 'status-warning',
                                                default => 'status-info',
                                            };
                                            ?>
                                            <span class="status-badge <?php echo e($match_class); ?>">
                                                <?php echo e(ucwords(str_replace('_', ' ', $match_status))); ?>
                                            </span>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                                <div class="order-payment-proof-card">
                                    <span>Proof of Payment</span>
                                    <strong>
                                        <?php if (!empty($order['proof_of_payment_file'])): ?>
                                            <?php
                                            $proof = BASE_URL . e($order['proof_of_payment_file']);
                                            $ext = strtolower(pathinfo($order['proof_of_payment_file'], PATHINFO_EXTENSION));
                                            ?>
                                            <button type="button" class="text-blue-700 font-semibold hover:underline proof-toggle"
                                                data-proof-url="<?php echo $proof; ?>"
                                                data-proof-type="<?php echo e($ext); ?>">
                                                View Proof
                                            </button>
                                        <?php else: ?>
                                            No proof uploaded
                                        <?php endif; ?>
                                    </strong>
                                </div>

                                <?php if (!empty($order['payment_id']) && ($order['verification_status'] ?? '') === 'pending'): ?>
                                    <div class="order-payment-action-card" data-payment-action-card="<?php echo e($order['payment_id']); ?>">
                                        <span>Payment Action</span>
                                        <div class="payment-action-group">
                                            <form action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST"
                                                class="orders-update-form" data-payment-verify-form
                                                data-payment-id="<?php echo e($order['payment_id']); ?>">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="payment_id"
                                                    value="<?php echo e($order['payment_id']); ?>">
                                                <button type="submit" name="verify_payment" class="btn order-btn-completed">
                                                    Mark as Paid
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger" data-payment-reject-toggle>
                                                Reject Payment
                                            </button>

                                            <form action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST"
                                                class="orders-update-form" data-payment-reject-form
                                                data-payment-id="<?php echo e($order['payment_id']); ?>" hidden>
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="payment_id"
                                                    value="<?php echo e($order['payment_id']); ?>">
                                                <label for="reject-reason-<?php echo e($order['payment_id']); ?>">Reason for rejection</label>
                                                <textarea id="reject-reason-<?php echo e($order['payment_id']); ?>" name="rejection_reason"
                                                    placeholder="Tell the customer what needs to be corrected"
                                                    class="payment-reject-textarea" maxlength="500" required></textarea>
                                                <button type="submit" name="reject_payment" class="btn btn-danger">
                                                    Confirm Reject
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span>Total Amount</span>
                                    <strong><?php echo ownerMoney($order['total_amount']); ?></strong>
                                </div>
                            </div>
                        </section>

                        <?php
                        $modal_notes = $order_notes_map[(int) $order['order_id']] ?? [];
                        $show_adjust_form = !empty($file_rows) && !empty($is_office_file) && $is_document_service && ($order['order_status'] ?? '') === 'ready_for_pickup';
                        ?>
                        <?php if ($show_adjust_form || !empty($modal_notes)): ?>
                            <section class="order-modal-section">
                                <h3 data-adjust-title>Pickup Balance</h3>
                                <?php if (!empty($modal_notes)): ?>
                                    <div class="order-notes-list">
                                        <?php foreach ($modal_notes as $note): ?>
                                            <div class="order-note-item order-note-<?php echo e($note['note_type']); ?>">
                                                <strong><?php echo ($note['note_type'] ?? '') === 'refund' ? 'Refund' : 'Additional balance'; ?></strong>
                                                <span><?php echo e($note['note_text']); ?></span>
                                                <small><?php echo e(!empty($note['created_at']) ? date('M d, Y - g:i A', strtotime($note['created_at'])) : ''); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($show_adjust_form && $owner_is_verified): ?>
                                    <?php
                                    $template_additional = 'Your printing request has an additional balance of ₱{X}. Please settle the remaining amount upon pickup.';
                                    $template_refund = 'Your printing request has a refund of ₱{X}. Please claim it in cash upon pickup.';
                                    ?>
                                    <form action="<?php echo BASE_URL; ?>backend/actions/send_order_adjustment_note.php" method="POST"
                                        class="orders-update-form order-adjust-form" data-adjust-form
                                        data-template-additional="<?php echo e($template_additional); ?>"
                                        data-template-refund="<?php echo e($template_refund); ?>">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                                        <div class="order-adjust-pills" role="radiogroup" aria-label="Note type">
                                            <label class="order-adjust-pill">
                                                <input type="radio" name="note_type" value="additional" data-adjust-pill checked>
                                                <span>Request additional</span>
                                            </label>
                                            <label class="order-adjust-pill">
                                                <input type="radio" name="note_type" value="refund" data-adjust-pill>
                                                <span>Request refund</span>
                                            </label>
                                        </div>
                                        <label for="adjust-text-<?php echo e($order['order_id']); ?>">Note to customer (edit amount manually)</label>
                                        <textarea id="adjust-text-<?php echo e($order['order_id']); ?>" name="note_text" rows="3"
                                            class="payment-reject-textarea" data-adjust-text maxlength="500" required><?php echo e($template_additional); ?></textarea>
                                        <button type="submit" name="send_order_adjustment_note" class="btn order-btn-ready">Send Note</button>
                                        <small class="muted">Cash settlement on pickup. Sending a new note replaces the previous one.</small>
                                    </form>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>
                    </div>

                    <footer class="order-modal-footer">
                        <button type="button" class="btn order-modal-secondary" data-order-modal-close>Close</button>
                        <?php if ($owner_is_verified && $order['order_status'] === 'pending'): ?>
                            <?php renderAcceptDownloadForm($order, $file_rows, !ownerOrderIsPaidAndPending($order)); ?>
                            <?php if (!orderHasActivePaymentProof($order)): ?>
                                <?php renderDeclineOrderForm($order); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </footer>
                </section>
            </div>
        <?php endforeach; ?>

        <div class="orders-mobile-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $order_page_count = max(1, (int) ($order['page_count'] ?? 1));
                $selected_service_name = ownerSelectedServiceName($order, $shop_service_type_lookup);
                $is_document_service = strcasecmp($selected_service_name, 'Document Printing') === 0;
                $is_photo_printing = strcasecmp($selected_service_name, 'Photo Printing') === 0;
                $is_tarpaulin_printing = strcasecmp($selected_service_name, 'Tarpaulin Printing') === 0;
                $is_id_printing = strcasecmp($selected_service_name, 'ID Printing') === 0;
                $is_invitation_card_printing = strcasecmp($selected_service_name, 'Invitation / Card Printing') === 0;
                $is_detailed_service = $is_photo_printing || $is_tarpaulin_printing || $is_id_printing || $is_invitation_card_printing;
                $file_sql = "SELECT * FROM uploaded_files WHERE order_id = ?";
                $file_stmt = mysqli_prepare($conn, $file_sql);
                mysqli_stmt_bind_param($file_stmt, "i", $order['order_id']);
                mysqli_stmt_execute($file_stmt);
                $files = mysqli_stmt_get_result($file_stmt);
                $file_rows = $order_files[(int) $order['order_id']] ?? [];
                ?>
                <?php $is_focused_order = ((int) $order['order_id'] === $focus_order_id) || ($focus_order_code !== '' && strcasecmp($focus_order_code, $order['order_code']) === 0) || ($search_code !== '' && strcasecmp($search_code, $order['order_code']) === 0); ?>
                <article class="owner-card order-card-mobile <?php echo $is_focused_order ? 'order-focused' : ''; ?>"
                    data-order-card="<?php echo e($order['order_id']); ?>">
                    <div class="card-head">
                        <h2><?php echo e($order['order_code']); ?></h2>
                        <span
                            class="status-badge order-status-badge order-status-<?php echo e($order['order_status']); ?> <?php echo ownerStatusClass($order['order_status']); ?>"
                            data-order-status-badge="<?php echo e($order['order_id']); ?>">
                            <?php echo e(ownerStatusLabel($order['order_status'])); ?>
                        </span>
                    </div>
                    <div class="order-card-mobile-customer">
                        <?php renderOwnerCustomerIdentity($order); ?>
                    </div>
                    <p class="order-card-line" title="Service: <?php echo e($selected_service_name); ?>"><strong>Service:</strong> <?php echo e($selected_service_name); ?></p>
                    <?php if ($is_document_service): ?>
                        <?php $mobile_details_text = $order['paper_size'] . ', ' . $order['paper_type'] . ', ' . $order['print_type'] . ', ' . $order_page_count . ' pages x' . $order['copies']; ?>
                        <p class="order-card-line" title="<?php echo e($mobile_details_text); ?>"><strong>Details:</strong> <?php echo e($mobile_details_text); ?>
                        </p>
                    <?php elseif ($is_detailed_service): ?>
                        <?php $mobile_details_text = 'Size: ' . $order['paper_size'] . ', ' . (($is_tarpaulin_printing || $is_id_printing) ? 'Material' : 'Paper') . ': ' . $order['paper_type'] . ', Print: ' . $order['print_type'] . ', Quantity: ' . $order['copies']; ?>
                        <p class="order-card-line" title="<?php echo e($mobile_details_text); ?>"><strong>Details:</strong> <?php echo e($mobile_details_text); ?></p>
                    <?php else: ?>
                        <?php $mobile_details_text = 'Size: ' . $order['paper_size'] . ', Quantity: ' . $order['copies']; ?>
                        <p class="order-card-line" title="<?php echo e($mobile_details_text); ?>"><strong>Details:</strong> <?php echo e($mobile_details_text); ?></p>
                    <?php endif; ?>
                    <p><strong>Total:</strong> <?php echo ownerMoney($order['total_amount']); ?></p>
                    <p class="order-card-line" title="<?php echo e(ownerOrderCustomerInstruction($order) ?: 'No instruction'); ?>"><strong>Instruction:</strong> <?php echo e(ownerOrderCustomerInstruction($order) ?: 'No instruction'); ?></p>
                    <div class="row-actions">
                        <button type="button" class="btn order-btn-navy"
                            data-order-modal-target="order-modal-<?php echo e($order['order_id']); ?>">View Details</button>
                    </div>
                    <?php if ($owner_is_verified && $order['order_status'] === 'processing'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                            class="orders-update-form orders-status-action mobile">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="ready_for_pickup">
                            <button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>
                        </form>
                    <?php elseif ($owner_is_verified && $order['order_status'] === 'ready_for_pickup'): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/update_order_status.php" method="POST"
                            class="orders-update-form orders-status-action mobile">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="order_status" value="completed">
                            <button type="submit" name="update_order" class="btn order-btn-completed">Mark as Completed</button>
                        </form>
                    <?php elseif ($owner_is_verified && $order['order_status'] === 'pending' && !orderHasActivePaymentProof($order)): ?>
                        <form action="<?php echo BASE_URL; ?>backend/actions/decline_order.php" method="POST"
                            class="orders-update-form orders-status-action mobile" data-decline-order-form
                            data-order-id="<?php echo e($order['order_id']); ?>">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="order_id" value="<?php echo e($order['order_id']); ?>">
                            <input type="hidden" name="decline_order" value="1">
                            <button type="submit" name="decline_order" class="btn btn-danger">Decline Request</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <footer class="orders-pagination">
            <p>Showing <strong><?php echo (int) $showing_start; ?>-<?php echo (int) $showing_end; ?></strong> of
                <?php echo (int) $filtered_total; ?> jobs
            </p>
            <div>
                <?php if ($page > 1): ?>
                    <a class="pagination-btn"
                        href="<?php echo e(orderPageUrl($page - 1, $search_code, $status_filter)); ?>"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</a>
                <?php else: ?>
                    <span class="pagination-btn disabled"><?php echo ownerIcon('chevron-left', 'icon-sm'); ?>Previous</span>
                <?php endif; ?>
                <span class="pagination-current"><?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                    <a class="pagination-btn"
                        href="<?php echo e(orderPageUrl($page + 1, $search_code, $status_filter)); ?>">Next<?php echo ownerIcon('chevron-right', 'icon-sm'); ?></a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Next<?php echo ownerIcon('chevron-right', 'icon-sm'); ?></span>
                <?php endif; ?>
            </div>
        </footer>
    </section>
<?php endif; ?>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
    (function() {
        const openButtons = document.querySelectorAll('[data-order-modal-target]');
        const closeSelector = '[data-order-modal-close]';
        let activeModal = null;

        const focusedOrder = document.querySelector('.order-focused');

        function openModal(modal) {
            if (!modal) {
                return;
            }

            activeModal = modal;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('order-modal-open');
        }

        if (focusedOrder) {
            window.setTimeout(function() {
                focusedOrder.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, 120);
        }

        function closeModal(modal) {
            if (!modal) {
                return;
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (activeModal === modal) {
                activeModal = null;
            }
            if (!document.querySelector('.order-modal.is-open')) {
                document.body.classList.remove('order-modal-open');
            }
        }

        openButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                openModal(document.getElementById(button.dataset.orderModalTarget));
            });
        });

        document.addEventListener('change', function (event) {
            const target = event.target;
            if (!target || typeof target.closest !== 'function') return;
            const pill = target.closest('[data-adjust-pill]');
            if (!pill) return;
            // Only react to the radio that became checked; some browsers fire
            // change on the unchecked sibling too, which used to swap in the
            // wrong template and could leave the textarea blank.
            if (pill.type === 'radio' && !pill.checked) return;
            if (pill.value !== 'refund' && pill.value !== 'additional') return;
            const form = pill.closest('[data-adjust-form]');
            const textarea = form ? form.querySelector('[data-adjust-text]') : null;
            if (!form || !textarea) return;
            const fallbackAdditional = 'Your printing request has an additional balance of \u20B1{X}. Please settle the remaining amount upon pickup.';
            const fallbackRefund = 'Your printing request has a refund of \u20B1{X}. Please claim it in cash upon pickup.';
            const templateRefund = form.dataset.templateRefund || fallbackRefund;
            const templateAdditional = form.dataset.templateAdditional || fallbackAdditional;
            const current = pill.value === 'refund' ? 'additional' : 'refund';
            try {
                form.dataset['draft_' + current] = textarea.value;
            } catch (datasetError) {
                /* drafts are best-effort only */
            }
            const saved = form.dataset['draft_' + pill.value];
            let next = (saved !== undefined && saved !== '')
                ? saved
                : (pill.value === 'refund' ? templateRefund : templateAdditional);
            // Never blank the textarea: a blank swap is what made the
            // Pickup Balance block look like a white/empty modal.
            if (next === undefined || next === null || String(next).trim() === '') {
                next = pill.value === 'refund' ? templateRefund : templateAdditional;
            }
            textarea.value = next;
            // Keep the section title in sync with the selected pill.
            const adjustSection = form.closest('section');
            const adjustTitle = adjustSection ? adjustSection.querySelector('[data-adjust-title]') : null;
            if (adjustTitle) {
                adjustTitle.textContent = pill.value === 'refund' ? 'Pickup Refund' : 'Pickup Balance';
            }
        });

        document.addEventListener('click', function(event) {
            const rejectToggle = event.target.closest('[data-payment-reject-toggle]');
            if (rejectToggle) {
                event.preventDefault();
                event.stopPropagation();

                const actionCard = rejectToggle.closest('[data-payment-action-card]');
                const rejectForm = actionCard ? actionCard.querySelector('[data-payment-reject-form]') : null;
                if (!rejectForm) {
                    return;
                }

                rejectForm.hidden = !rejectForm.hidden;
                rejectToggle.setAttribute('aria-expanded', rejectForm.hidden ? 'false' : 'true');

                if (!rejectForm.hidden) {
                    const textarea = rejectForm.querySelector('[name="rejection_reason"]');
                    if (textarea) {
                        textarea.focus();
                    }
                }

                return;
            }

            const closeTarget = event.target.closest(closeSelector);
            if (closeTarget) {
                closeModal(closeTarget.closest('.order-modal'));
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });

        function setOrderProcessing(orderId) {
            const processingIcon = <?php echo json_encode(ownerIcon('trending-up', 'icon-sm')); ?>;
            document.querySelectorAll('[data-order-status-badge="' + orderId + '"]').forEach(function(badge) {
                badge.className = 'status-badge order-status-badge order-status-processing status-info';
                badge.innerHTML = processingIcon + 'Processing';
            });
        }

        function setOrderCancelled(orderId) {
            document.querySelectorAll('[data-decline-order-form][data-order-id="' + orderId + '"], [data-accept-download-form][data-order-id="' + orderId + '"]').forEach(function(form) {
                form.remove();
            });

            let removedOrderRow = false;
            document.querySelectorAll('[data-order-row="' + orderId + '"], [data-order-card="' + orderId + '"]').forEach(function(element) {
                element.remove();
                removedOrderRow = true;
            });

            const pendingTabCount = document.querySelector('[data-live-region="owner-order-tabs"] a[href*="status=pending"] span');
            if (pendingTabCount) {
                pendingTabCount.textContent = Math.max(0, (parseInt(pendingTabCount.textContent, 10) || 0) - 1);
            }

            const allTabCount = document.querySelector('[data-live-region="owner-order-tabs"] a:not([href*="status="]) span');
            if (allTabCount) {
                allTabCount.textContent = Math.max(0, (parseInt(allTabCount.textContent, 10) || 0) - 1);
            }

            const pendingSummaryCount = document.querySelector('[data-live-region="owner-order-summary"] .orders-summary-card.pending strong');
            if (pendingSummaryCount) {
                pendingSummaryCount.textContent = Math.max(0, (parseInt(pendingSummaryCount.textContent, 10) || 0) - 1);
            }

            if (removedOrderRow && !document.querySelector('.orders-table tbody tr') && !document.querySelector('.order-card-mobile')) {
                setTimeout(function() { window.location.reload(); }, 1500);
            }
        }

        function createReadyForm(orderId, isMobile) {
            const readyForm = document.createElement('form');
            readyForm.action = '<?php echo BASE_URL; ?>backend/actions/update_order_status.php';
            readyForm.method = 'POST';
            readyForm.className = 'orders-update-form orders-status-action' + (isMobile ? ' mobile' : '');

            readyForm.innerHTML =
                '<input type="hidden" name="order_id" value="' + orderId + '">' +
                '<input type="hidden" name="order_status" value="ready_for_pickup">' +
                '<button type="submit" name="update_order" class="btn order-btn-ready">Mark as Ready</button>';

            return readyForm;
        }

        function showReadyAction(orderId) {
            const row = document.querySelector('[data-order-row="' + orderId + '"]');
            const rowActions = row ? row.querySelector('.orders-actions') : null;
            if (rowActions && !rowActions.querySelector('.order-btn-ready')) {
                rowActions.appendChild(createReadyForm(orderId, false));
            }

            const mobileCard = document.querySelector('[data-order-card="' + orderId + '"]');
            if (mobileCard && !mobileCard.querySelector('.order-btn-ready')) {
                mobileCard.appendChild(createReadyForm(orderId, true));
            }
        }

        function showAcceptDownloadAction(orderId) {
            document.querySelectorAll('.order-modal [data-accept-download-form][data-order-id="' + orderId + '"]').forEach(function(form) {
                form.hidden = false;
            });
        }

        function setPaymentVerified(paymentId) {
            document.querySelectorAll('[data-payment-status-label="' + paymentId + '"]').forEach(function(label) {
                label.textContent = 'Paid';
            });

            document.querySelectorAll('[data-verification-status-label="' + paymentId + '"]').forEach(function(label) {
                label.textContent = 'Verified';
            });

            document.querySelectorAll('[data-payment-action-card="' + paymentId + '"]').forEach(function(card) {
                card.remove();
            });
        }

        function setPaymentRejected(paymentId) {
            document.querySelectorAll('[data-payment-status-label="' + paymentId + '"]').forEach(function(label) {
                label.textContent = 'Rejected';
            });

            document.querySelectorAll('[data-verification-status-label="' + paymentId + '"]').forEach(function(label) {
                label.textContent = 'Rejected';
            });

            document.querySelectorAll('[data-payment-action-card="' + paymentId + '"]').forEach(function(card) {
                card.remove();
            });
        }

        document.querySelectorAll('[data-payment-verify-form]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (form.dataset.paymentSubmitting === 'true') {
                    return;
                }

                form.dataset.paymentSubmitting = 'true';
                form.classList.add('is-loading');
                const submitButton = form.querySelector('[type="submit"]');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Marking...';
                }

                const formData = new FormData(form);
                if (!formData.has('verify_payment')) {
                    formData.append('verify_payment', '1');
                }

                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(response) {
                        return response.json().then(function(data) {
                            if (!response.ok || !data || !data.success) {
                                throw new Error((data && data.message) || 'Payment update failed.');
                            }
                            return data;
                        });
                    })
                    .then(function(data) {
                        setPaymentVerified(data.payment_id || form.dataset.paymentId);
                        if (data.order_id) {
                            showAcceptDownloadAction(data.order_id);
                        }
                        if (window.ownerShowToast) {
                            window.ownerShowToast(data.message || 'Payment verified successfully.', 'success');
                        }
                    })
                    .catch(function(error) {
                        form.dataset.paymentSubmitting = 'false';
                        form.classList.remove('is-loading');
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = originalText || 'Mark as Paid';
                        }
                        if (window.ownerShowToast) {
                            window.ownerShowToast(error.message || 'Failed to verify payment. Please try again.', 'error');
                        }
                    });
            });
        });

        document.querySelectorAll('[data-payment-reject-form]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (form.dataset.paymentSubmitting === 'true') {
                    return;
                }

                const reasonField = form.querySelector('[name="rejection_reason"]');
                const reason = reasonField ? reasonField.value.trim() : '';
                if (!reason) {
                    if (window.ownerShowToast) {
                        window.ownerShowToast('Please enter a reason before rejecting this payment proof.', 'error');
                    }
                    if (reasonField) {
                        reasonField.focus();
                    }
                    return;
                }

                form.dataset.paymentSubmitting = 'true';
                form.classList.add('is-loading');
                const submitButton = form.querySelector('[type="submit"]');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Rejecting...';
                }

                const formData = new FormData(form);
                if (!formData.has('reject_payment')) {
                    formData.append('reject_payment', '1');
                }

                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(response) {
                        return response.json().then(function(data) {
                            if (!response.ok || !data || !data.success) {
                                throw new Error((data && data.message) || 'Payment rejection failed.');
                            }
                            return data;
                        });
                    })
                    .then(function(data) {
                        setPaymentRejected(data.payment_id || form.dataset.paymentId);
                        if (window.ownerShowToast) {
                            window.ownerShowToast(data.message || 'Payment proof rejected.', 'warning');
                        }
                    })
                    .catch(function(error) {
                        form.dataset.paymentSubmitting = 'false';
                        form.classList.remove('is-loading');
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = originalText || 'Confirm Reject';
                        }
                        if (window.ownerShowToast) {
                            window.ownerShowToast(error.message || 'Failed to reject payment. Please try again.', 'error');
                        }
                    });
            });
        });

        document.querySelectorAll('[data-decline-order-form]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (form.dataset.declineSubmitting === 'true') {
                    return;
                }

                const declineButton = form.querySelector('[type="submit"]');

                window.appConfirm({
                        title: 'Decline this print request?',
                        message: 'The customer will be notified.',
                        confirmText: 'Decline Request',
                        cancelText: 'Keep Request',
                        tone: 'danger'
                    })
                    .then(function(confirmed) {
                        if (!confirmed) {
                            return;
                        }

                        form.dataset.declineSubmitting = 'true';
                        if (declineButton) {
                            declineButton.disabled = true;
                            declineButton.textContent = 'Declining...';
                        }

                        const parentModal = form.closest('.order-modal.is-open');
                        if (parentModal) {
                            closeModal(parentModal);
                        }

                        fetch(form.action, {
                                method: 'POST',
                                body: new FormData(form),
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(function(response) {
                                return response.json().then(function(data) {
                                    if (!response.ok || !data || !data.success) {
                                        throw new Error((data && data.message) || 'Failed to decline the print request.');
                                    }
                                    return data;
                                });
                            })
                            .then(function(data) {
                                setOrderCancelled(data.order_id || form.dataset.orderId);

                                if (window.ownerShowToast) {
                                    window.ownerShowToast(data.message || 'Print request declined. The customer has been notified.', 'success');
                                }
                            })
                            .catch(function(error) {
                                form.dataset.declineSubmitting = 'false';
                                if (declineButton) {
                                    declineButton.disabled = false;
                                    declineButton.textContent = 'Decline Request';
                                }
                                if (window.ownerShowToast) {
                                    window.ownerShowToast(error.message || 'Failed to decline the print request. Please try again.', 'error');
                                }
                            });
                    });
            });
        });

        document.querySelectorAll('[data-accept-download-form]').forEach(function(form) {
            // Single-source guard: delegated handler in live-updates.js skips forms
            // flagged here, preventing the double-tab bug on Accept & Download.
            form.dataset.ownerLocalHandler = 'true';
            if (form.dataset.acceptBound === 'true') {
                return;
            }
            form.dataset.acceptBound = 'true';
            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                event.stopPropagation();
                // Stop immediate propagation as well so no other delegated
                // submit handler can open a second tab for the same click.
                if (typeof event.stopImmediatePropagation === 'function') {
                    try { event.stopImmediatePropagation(); } catch (stopError) { /* best-effort */ }
                }

                if (form.dataset.downloadStarted === 'true') {
                    return;
                }

                const files = Array.from(form.querySelectorAll('[data-download-url]'))
                    .map(function(input, index) {
                        return {
                            url: input.value,
                            name: input.dataset.downloadName || ('request-file-' + (index + 1))
                        };
                    })
                    .filter(function(file) {
                        return file.url;
                    });

                form.dataset.downloadStarted = 'true';
                form.classList.add('is-loading');
                const submitButton = form.querySelector('[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Checking file...';
                }

                try {
                    if (!files.length) {
                        throw new Error('No downloadable file found for this print job.');
                    }

                    const resolvedFiles = [];

                    for (const file of files) {
                        const checkUrl = new URL(file.url, window.location.href);
                        checkUrl.searchParams.set('check', '1');

                        const response = await fetch(checkUrl.toString(), {
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const checkData = await response.json().catch(function() {
                            return null;
                        });

                        if (!response.ok) {
                            throw new Error((checkData && checkData.message) || 'Download failed. Please open the file preview link and download manually.');
                        }

                        if (!checkData || !checkData.success) {
                            throw new Error((checkData && checkData.message) || 'Download failed. Please open the file preview link and download manually.');
                        }

                        // Accept = viewer + auto-download for doc/docx/pdf (new-tab preview
                        // plus a real file download via the proxy). wps/images: download
                        // or preview only. fileExt comes from the hardened check endpoint.
                        const fileKind = checkData.file_kind || '';
                        const fileExt = String(checkData.file_ext || '').toLowerCase();
                        const forceDownload = Boolean(checkData.force_download)
                            || fileKind === 'viewable'
                            || fileKind === 'download-only';
                        const downloadUrl = (forceDownload && checkData.download_url)
                            ? checkData.download_url
                            : ((checkData.remote && checkData.url) ? checkData.url : file.url);

                        let viewerUrl = (fileKind === 'viewable' && checkData.viewer_url) ? checkData.viewer_url : null;
                        // PDF live behavior: same as Word — raw PDF in new tab (browser
                        // renders it like before) plus proxy auto-download.
                        if (!viewerUrl && fileKind === 'native' && fileExt === 'pdf'
                            && checkData.remote && checkData.url && checkData.download_url) {
                            viewerUrl = checkData.url;
                        }

                        resolvedFiles.push({
                            downloadUrl: fileKind === 'native' && fileExt === 'pdf' && checkData.download_url ? checkData.download_url : downloadUrl,
                            viewerUrl: viewerUrl,
                            name: (checkData.file_name || file.name),
                            remote: Boolean(checkData.remote) && !forceDownload,
                            forced: forceDownload || Boolean(viewerUrl && fileKind === 'native'),
                            kind: fileKind
                        });
                    }

                    if (submitButton) {
                        submitButton.textContent = 'Accepting job...';
                    }

                    const formData = new FormData(form);
                    if (!formData.has('update_order')) {
                        formData.append('update_order', '1');
                    }

                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json().catch(function() {
                        return null;
                    });

                    if (!response.ok || !data || !data.success) {
                        throw new Error((data && data.message) || 'Print job update failed.');
                    }

                    const orderId = data.order_id || form.dataset.orderId;
                    setOrderProcessing(orderId);
                    document.querySelectorAll('[data-accept-download-form][data-order-id="' + orderId + '"]').forEach(function(matchingForm) {
                        matchingForm.remove();
                    });
                    showReadyAction(orderId);

                    if (submitButton) {
                        submitButton.textContent = 'Starting download...';
                    }

                    resolvedFiles.forEach(function(file, index) {
                        const baseDelay = index * 350;

                        function openNewTab(url) {
                            const link = document.createElement('a');
                            link.href = url;
                            link.target = '_blank';
                            link.rel = 'noopener';
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        }

                        function triggerDownload(url, name) {
                            // Same-origin proxy URL + attachment headers: saves
                            // without navigating away or opening a blank tab.
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = name;
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        }

                        // Viewable (doc/docx): viewer preview like PDF new-tab,
                        // plus auto-download of the actual file.
                        if (file.viewerUrl) {
                            window.setTimeout(function() {
                                openNewTab(file.viewerUrl);
                            }, baseDelay);
                            window.setTimeout(function() {
                                triggerDownload(file.downloadUrl, file.name);
                            }, baseDelay + 150);
                        } else if (file.forced) {
                            window.setTimeout(function() {
                                triggerDownload(file.downloadUrl, file.name);
                            }, baseDelay);
                        } else if (file.remote) {
                            window.setTimeout(function() {
                                openNewTab(file.downloadUrl);
                            }, baseDelay);
                        } else {
                            window.setTimeout(function() {
                                triggerDownload(file.downloadUrl, file.name);
                            }, baseDelay);
                        }
                    });

                    // Popup-blocker fallback: leave manual links in the toast area
                    // via console so owners can still open them if blocked.
                    if (resolvedFiles.some(function(f) { return f.viewerUrl; }) && window.console) {
                        try { console.info('[PrintEase] viewer+download opened', resolvedFiles); } catch (logError) { /* best-effort */ }
                    }

                    closeModal(form.closest('.order-modal') || activeModal);

                    window.setTimeout(function() {
                        const processingUrl = new URL('orders.php', window.location.href);
                        processingUrl.searchParams.set('status', 'processing');
                        processingUrl.searchParams.set('focus_order_id', orderId);
                        window.location.href = processingUrl.toString();
                    }, Math.max(1200, resolvedFiles.length * 350));

                    if (window.ownerShowToast) {
                        window.ownerShowToast('Print job accepted and downloads started.', 'success');
                    }
                } catch (error) {
                    form.dataset.downloadStarted = 'false';
                    form.classList.remove('is-loading');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Accept & Download';
                    }
                    if (window.ownerShowToast) {
                        window.ownerShowToast(error.message || 'Download failed. Please open the file preview link and download manually.', 'error');
                    }
                }
            });
        });

    })();
</script>

<?php ownerLayoutEnd(); ?>
