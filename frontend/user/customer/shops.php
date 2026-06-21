<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";

$redirect_query = ['view' => 'all'];
if (trim((string) ($_GET['search'] ?? '')) !== '') {
    $redirect_query['search'] = trim((string) $_GET['search']);
}
header("Location: explore.php?" . http_build_query($redirect_query));
exit();

$customer_id = $_SESSION['user_id'];

$user_stmt = mysqli_prepare($conn, "SELECT account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $customer_id);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

if (($user['account_status'] ?? '') !== 'verified') {
    redirect("dashboard.php");
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.display_address, ps.landmark,
               ps.contact_number, ps.shop_logo, ps.shop_status, ps.latitude, ps.longitude,
               ps.weekday_open_time, ps.weekday_close_time, ps.weekend_open_time, ps.weekend_close_time,
               COUNT(ss.service_id) AS service_count,
               MIN(ss.price_per_page) AS starting_price
        FROM print_shops ps
        LEFT JOIN shop_services ss ON ps.shop_id = ss.shop_id AND ss.is_available = 1
        WHERE ps.permit_status = 'verified'
          AND ps.shop_status IN ('available', 'busy')
          AND ps.latitude IS NOT NULL
          AND ps.longitude IS NOT NULL";

$types = '';
$params = [];
if ($search !== '') {
    $like = "%$search%";
    $sql .= " AND (
                ps.shop_name LIKE ?
                OR ps.shop_address LIKE ?
                OR ps.display_address LIKE ?
                OR ps.landmark LIKE ?
            )";
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
}

$sql .= " GROUP BY ps.shop_id
          HAVING service_count > 0
          ORDER BY CASE ps.shop_status WHEN 'available' THEN 0 ELSE 1 END, ps.shop_name ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$shops = mysqli_stmt_get_result($stmt);

function customerShopShortAddress(array $shop): string
{
    $preferred = trim((string) ($shop['display_address'] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    $address_parts = explode(',', (string) ($shop['shop_address'] ?? ''));
    return trim($address_parts[0] ?? '') ?: 'Location not provided';
}

function customerShopTimeMinutes(?string $value): ?int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})/', (string) $value, $matches)) {
        return null;
    }

    return ((int) $matches[1] * 60) + (int) $matches[2];
}

function customerShopScheduleForDay(array $shop, int $day): array
{
    $weekend = in_array($day, [0, 6], true);

    return $weekend
        ? ['open' => $shop['weekend_open_time'] ?? '', 'close' => $shop['weekend_close_time'] ?? '']
        : ['open' => $shop['weekday_open_time'] ?? '', 'close' => $shop['weekday_close_time'] ?? ''];
}

function customerShopFormatTime(?string $value): string
{
    $minutes = customerShopTimeMinutes($value);
    if ($minutes === null) {
        return '';
    }

    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $display_hour = $hour % 12 ?: 12;

    return $display_hour . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT) . ($hour >= 12 ? ' PM' : ' AM');
}

function customerShopHoursLabel(array $shop): string
{
    $schedule = customerShopScheduleForDay($shop, (int) date('w'));
    if (empty($schedule['open']) || empty($schedule['close'])) {
        return 'Hours not set';
    }

    return customerShopFormatTime($schedule['open']) . ' - ' . customerShopFormatTime($schedule['close']);
}

function customerShopIsOpenNow(array $shop): bool
{
    $current = ((int) date('G') * 60) + (int) date('i');
    $today = customerShopScheduleForDay($shop, (int) date('w'));
    $today_open = customerShopTimeMinutes($today['open']);
    $today_close = customerShopTimeMinutes($today['close']);

    if ($today_open !== null && $today_close !== null) {
        if ($today_open === $today_close) {
            return true;
        }
        if ($today_close > $today_open && $current >= $today_open && $current < $today_close) {
            return true;
        }
        if ($today_close < $today_open && $current >= $today_open) {
            return true;
        }
    }

    $previous_day = ((int) date('w') + 6) % 7;
    $previous = customerShopScheduleForDay($shop, $previous_day);
    $previous_open = customerShopTimeMinutes($previous['open']);
    $previous_close = customerShopTimeMinutes($previous['close']);

    return $previous_open !== null && $previous_close !== null && $previous_close < $previous_open && $current < $previous_close;
}

function customerShopMoney($value): string
{
    return is_numeric($value) ? '&#8369;' . number_format((float) $value, 2) : 'Price unavailable';
}

function customerShopDirectionsUrl(array $shop): string
{
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode((string) $shop['latitude'] . ',' . (string) $shop['longitude']);
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Print Shops</title>
    <?php renderCustomerHead(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-6xl mx-auto min-h-screen">

        <?php renderCustomerLayout(['title' => 'Find Print Shops', 'subtitle' => 'Choose your preferred shop for printing.']); ?>

        <main class="p-4 md:p-6">

            <form method="GET" class="customer-map-toolbar customer-shops-toolbar" role="search">
                <div class="customer-map-search">
                    <span class="customer-map-search-icon" aria-hidden="true"><?php echo customerIcon('search'); ?></span>
                <input type="text" name="search" value="<?php echo e($search); ?>"
                    placeholder="Search shop, street, or landmark"
                    aria-label="Search print shops">
                    <button type="submit">Search</button>
                </div>
            </form>

            <?php if (mysqli_num_rows($shops) == 0): ?>
                <section class="customer-map-empty" role="status">
                    <span><?php echo customerIcon('printer'); ?></span>
                    <h2>No print shops found</h2>
                    <p>Try another search or check the Map tab for nearby verified shops.</p>
                    <a href="shopLocation.php">Open Map</a>
                </section>
            <?php else: ?>
                <div class="customer-shops-grid">
                    <?php while ($shop = mysqli_fetch_assoc($shops)): ?>
                        <?php
                        $status = $shop['shop_status'] === 'available' ? 'available' : 'busy';
                        $statusText = $status === 'available' ? 'Available' : 'Busy';
                        $short_address = customerShopShortAddress($shop);
                        $landmark = trim((string) ($shop['landmark'] ?? ''));
                        $shop_logo = trim((string) ($shop['shop_logo'] ?? ''));
                        $is_open = customerShopIsOpenNow($shop);
                        $hours_label = customerShopHoursLabel($shop);
                        $contact = trim((string) ($shop['contact_number'] ?? '')) ?: 'No contact listed';
                        ?>
                        <article class="customer-map-shop-card customer-shops-card">
                            <div class="customer-map-shop-head">
                                <div class="customer-map-shop-logo">
                                    <?php if ($shop_logo !== ''): ?>
                                        <img src="<?php echo e(SHOP_LOGOS_URL . $shop_logo); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo customerIcon('printer'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="customer-map-shop-title">
                                    <h3><?php echo e($shop['shop_name']); ?></h3>
                                    <p><span aria-hidden="true">&#9679;</span><?php echo e($short_address); ?></p>
                                    <?php if ($landmark !== ''): ?><small><?php echo e($landmark); ?></small><?php endif; ?>
                                </div>
                                <span class="customer-map-status <?php echo e($status); ?>"><?php echo $statusText; ?></span>
                            </div>

                            <div class="customer-map-shop-facts">
                                <span><strong><?php echo $is_open ? 'Open now' : 'Closed now'; ?></strong><?php echo e($hours_label); ?></span>
                                <span><strong><?php echo (int) $shop['service_count']; ?> services</strong><?php echo customerShopMoney($shop['starting_price']); ?> start</span>
                                <span><strong>Contact</strong><?php echo e($contact); ?></span>
                            </div>

                            <div class="customer-map-shop-actions">
                                <a href="<?php echo BASE_URL; ?>frontend/user/customer/shopLocation.php?shop_id=<?php echo e($shop['shop_id']); ?>">View Map</a>
                                <a href="<?php echo e(customerShopDirectionsUrl($shop)); ?>" target="_blank" rel="noopener">Directions</a>
                                <a class="primary" href="<?php echo BASE_URL; ?>frontend/user/customer/place_order.php?shop_id=<?php echo e($shop['shop_id']); ?>">Order Now</a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <?php renderCustomerLayoutEnd('shops'); ?>

</body>

</html>
