<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
$target = trim((string) ($_GET['target'] ?? ''));

$targets = [
    'super_admin' => [
        'admin_users' => [
            'file' => __DIR__ . '/../../frontend/user/superadmin/manage_users.php',
            'params' => ['search', 'status'],
        ],
        'admin_shops' => [
            'file' => __DIR__ . '/../../frontend/user/superadmin/manage_print_shops.php',
            'params' => ['search', 'status'],
        ],
        'admin_activity' => [
            'file' => __DIR__ . '/../../frontend/user/superadmin/activity_logs.php',
            'params' => ['search', 'range', 'module'],
        ],
    ],
    'shop_owner' => [
        'owner_orders' => [
            'file' => __DIR__ . '/../../frontend/user/shop_owner/orders.php',
            'params' => ['order_code', 'status', 'page', 'focus_order_id', 'focus_order_code'],
        ],
        'owner_transactions' => [
            'file' => __DIR__ . '/../../frontend/user/shop_owner/transactions.php',
            'params' => ['q', 'date_filter'],
        ],
    ],
    'customer' => [
        'customer_explore' => [
            'file' => __DIR__ . '/../../frontend/user/customer/explore.php',
            'params' => ['view', 'search', 'shop_id'],
        ],
        'customer_orders' => [
            'file' => __DIR__ . '/../../frontend/user/customer/orders.php',
            'params' => ['order_code', 'status', 'focus_order_code'],
        ],
    ],
];

if (!isset($targets[$role][$target])) {
    echo json_encode(['success' => false, 'message' => 'Search target is not available.']);
    exit();
}

$config = $targets[$role][$target];
if (!is_file($config['file'])) {
    echo json_encode(['success' => false, 'message' => 'Search page was not found.']);
    exit();
}

$next_get = [];
foreach ($config['params'] as $param) {
    if (array_key_exists($param, $_GET)) {
        $next_get[$param] = is_array($_GET[$param]) ? '' : trim((string) $_GET[$param]);
    }
}
$_GET = $next_get;

ob_start();
try {
    include $config['file'];
    $page_html = ob_get_clean();
} catch (Throwable $error) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unable to render search results.']);
    exit();
}

$regions = [];
if (class_exists('DOMDocument')) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $page_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//*[@data-live-region]') as $node) {
        $name = $node->getAttribute('data-live-region');
        if ($name !== '') {
            $regions[$name] = $dom->saveHTML($node);
        }
    }
    libxml_clear_errors();
}

echo json_encode([
    'success' => !empty($regions),
    'regions' => $regions,
]);
?>
