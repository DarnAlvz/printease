<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";
require_once __DIR__ . "/../../components/notifications.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";

requireVerifiedStatus($conn);

$customer_id = $_SESSION['user_id'];

$notifications = getUserNotifications($conn, $customer_id);
$unread_count = getUnreadNotificationCount($conn, $customer_id);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications</title>
    <?php renderCustomerHead(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head>

<body class="customer-body customer-notification-page bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

<div class="max-w-md md:max-w-5xl mx-auto min-h-screen">
    <?php renderCustomerLayout(['title' => 'Notifications', 'subtitle' => 'View your order updates.']); ?>

    <main class="customer-notification-main p-4 md:p-6">
        <?php renderNotificationCenter($notifications, ['role' => 'customer', 'unread_count' => $unread_count, 'empty_text' => "You're all caught up."]); ?>
    </main>
</div>

    <?php renderCustomerLayoutEnd(); ?>

</body>
</html>
