<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

checkRole("customer");

validateCsrf();

function ensureCustomerFavoriteShopsTable(mysqli $conn): void
{
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS customer_favorite_shops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        shop_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_customer_shop (customer_id, shop_id),
        KEY idx_customer_favorites_customer (customer_id),
        KEY idx_customer_favorites_shop (shop_id)
    )");
}

ensureCustomerFavoriteShopsTable($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . "frontend/user/customer/explore.php");
}

$customer_id = (int) ($_SESSION['user_id'] ?? 0);
$shop_id = (int) ($_POST['shop_id'] ?? 0);
$intent = (string) ($_POST['intent'] ?? 'toggle');
$return_to = (string) ($_POST['return_to'] ?? '');

$fallback = BASE_URL . "frontend/user/customer/explore.php";
$return_url = $fallback;
if ($return_to !== '') {
    $parts = parse_url($return_to);
    $path = (string) ($parts['path'] ?? '');
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    if ($path !== '' && !preg_match('/^https?:\/\//i', $return_to) && str_contains($path, '/frontend/user/customer/')) {
        $return_url = $return_to;
    } elseif ($path === '' && str_starts_with($return_to, 'explore.php')) {
        $return_url = BASE_URL . "frontend/user/customer/" . $return_to;
    } elseif (str_starts_with($path, 'explore.php')) {
        $return_url = BASE_URL . "frontend/user/customer/" . $path . $query;
    }
}

if ($customer_id <= 0 || $shop_id <= 0) {
    setToast("Could not update favorite shop.", "warning");
    redirect($return_url);
}

$shop_stmt = mysqli_prepare($conn, "SELECT shop_id FROM print_shops WHERE shop_id = ? AND permit_status = 'verified' LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $shop_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    setToast("This shop is no longer available.", "warning");
    redirect($return_url);
}

if ($intent === 'remove') {
    $stmt = mysqli_prepare($conn, "DELETE FROM customer_favorite_shops WHERE customer_id = ? AND shop_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $customer_id, $shop_id);
    mysqli_stmt_execute($stmt);
    setToast("Removed from favorites.", "success");
    redirect($return_url);
}

if ($intent === 'add') {
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO customer_favorite_shops (customer_id, shop_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $customer_id, $shop_id);
    mysqli_stmt_execute($stmt);
    setToast("Added to favorites.", "success");
    redirect($return_url);
}

$check_stmt = mysqli_prepare($conn, "SELECT id FROM customer_favorite_shops WHERE customer_id = ? AND shop_id = ? LIMIT 1");
mysqli_stmt_bind_param($check_stmt, "ii", $customer_id, $shop_id);
mysqli_stmt_execute($check_stmt);
$favorite = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

if ($favorite) {
    $stmt = mysqli_prepare($conn, "DELETE FROM customer_favorite_shops WHERE customer_id = ? AND shop_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $customer_id, $shop_id);
    mysqli_stmt_execute($stmt);
    setToast("Removed from favorites.", "success");
} else {
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO customer_favorite_shops (customer_id, shop_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $customer_id, $shop_id);
    mysqli_stmt_execute($stmt);
    setToast("Added to favorites.", "success");
}

redirect($return_url);
?>
