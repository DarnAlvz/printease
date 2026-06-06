<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

function dashboardCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function dashboardRows($conn, $sql)
{
    $rows = [];
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

function dashboardRelativeTime($datetime)
{
    if (empty($datetime)) {
        return 'Just now';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return e($datetime);
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = floor($diff / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function dashboardIcon($name)
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.4"></rect><rect x="14" y="3" width="7" height="7" rx="1.4"></rect><rect x="3" y="14" width="7" height="7" rx="1.4"></rect><rect x="14" y="14" width="7" height="7" rx="1.4"></rect>',
        'shop' => '<path d="M4 10h16l-1.2-5H5.2L4 10Z"></path><path d="M6 10v9h12v-9"></path><path d="M9 19v-5h6v5"></path><path d="M4 10c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2c0 1.1.9 2 2 2s2-.9 2-2"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>',
        'activity' => '<path d="M22 12h-4l-3 8L9 4l-3 8H2"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1.82V22a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.82-.33H2a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1.82V2a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 16 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.24.33.6.57 1 .6.45.04.91-.07 1.32-.33H22a2 2 0 1 1 0 4h-.09A1.65 1.65 0 0 0 19.4 15Z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
        'check' => '<path d="M20 6 9 17l-5-5"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'plus' => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        'chart' => '<path d="M3 3v18h18"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-3"></path>',
        'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"></path><path d="M5 3v4"></path><path d="M3 5h4"></path><path d="M19 17v4"></path><path d="M17 19h4"></path>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"></path><path d="M14 7h7v7"></path>',
        'bulb' => '<path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M8.5 14.5A6 6 0 1 1 15.5 14.5c-.7.47-1.5 1.35-1.5 2.5h-4c0-1.15-.8-2.03-1.5-2.5Z"></path>',
        'x' => '<circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path>',
    ];

    $paths = $icons[$name] ?? $icons['dashboard'];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function dashboardLinePoints($series, $width = 640, $height = 210)
{
    $max = max(1, max($series));
    $count = count($series);
    $points = [];

    foreach ($series as $index => $value) {
        $x = $count > 1 ? ($index / ($count - 1)) * $width : $width / 2;
        $y = $height - (($value / $max) * ($height - 22)) - 10;
        $points[] = round($x, 2) . ',' . round($y, 2);
    }

    return implode(' ', $points);
}

$total_shops = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops");
$total_customers = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'");
$active_users = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND account_status = 'verified'");
$pending_users = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND account_status = 'pending'");
$pending_permits = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'pending'");
$pending_approvals = $pending_users + $pending_permits;
$verified_shops = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'verified'");
$new_shops_today = dashboardCount($conn, "SELECT COUNT(*) AS total FROM print_shops WHERE DATE(created_at) = CURDATE()");
$new_users_week = dashboardCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role != 'super_admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$unread_notifications = dashboardCount($conn, "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0");

$pending_shops = dashboardRows($conn, "
    SELECT ps.shop_id, ps.shop_name, ps.shop_address, ps.business_permit_file, ps.created_at, u.full_name, u.email
    FROM print_shops ps
    JOIN users u ON ps.owner_id = u.user_id
    WHERE ps.permit_status = 'pending'
    ORDER BY ps.created_at DESC
    LIMIT 5
");

$pending_accounts = dashboardRows($conn, "
    SELECT u.user_id, u.full_name, u.email, u.role, u.created_at
    FROM users u
    WHERE u.role != 'super_admin' AND u.account_status = 'pending'
    ORDER BY u.created_at DESC
    LIMIT 5
");

$recent_activity = dashboardRows($conn, "
    SELECT al.action, al.module, al.created_at, u.full_name, u.role
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 5
");

$daily_users = [];
$daily_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $daily_users[$key] = 0;
    $daily_labels[$key] = date('D', strtotime($key));
}

$daily_result = mysqli_query($conn, "
    SELECT DATE(created_at) AS day_key, COUNT(*) AS total
    FROM users
    WHERE role != 'super_admin' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
if ($daily_result) {
    while ($row = mysqli_fetch_assoc($daily_result)) {
        if (isset($daily_users[$row['day_key']])) {
            $daily_users[$row['day_key']] = (int) $row['total'];
        }
    }
}

$weekly_shops = [];
$weekly_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $monday = date('Y-m-d', strtotime("monday this week -$i weeks"));
    $weekly_shops[$monday] = 0;
    $weekly_labels[$monday] = 'W' . date('W', strtotime($monday));
}

$weekly_result = mysqli_query($conn, "
    SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_key, COUNT(*) AS total
    FROM print_shops
    WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 WEEK)
    GROUP BY week_key
");
if ($weekly_result) {
    while ($row = mysqli_fetch_assoc($weekly_result)) {
        if (isset($weekly_shops[$row['week_key']])) {
            $weekly_shops[$row['week_key']] = (int) $row['total'];
        }
    }
}

$daily_values = array_values($daily_users);
$weekly_values = array_values($weekly_shops);
$max_weekly = max(1, max($weekly_values));
$admin_name = $_SESSION['full_name'] ?? 'Super Admin';
$admin_email = $_SESSION['email'] ?? 'admin@eprinting.com';
$admin_initials = strtoupper(substr($admin_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <style>
        :root {
            --navy: #000567;
            --blue: #007fbc;
            --cyan: #08b7d4;
            --sky: #c9f3fb;
            --panel: #eefdff;
            --line: #79ddf3;
            --green: #07b54c;
            --yellow: #f4ad00;
            --red: #f0000b;
            --muted: #526174;
            --ink: #030852;
            --soft: #f8fdff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f8fbfd;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .icon {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .admin-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 257px minmax(0, 1fr);
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--line);
            background: #fff;
        }

        .brand {
            height: 68px;
            display: flex;
            align-items: center;
            padding: 0 26px;
            border-bottom: 1px solid var(--line);
        }

        .brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .nav-list {
            display: grid;
            gap: 10px;
            padding: 22px 16px;
        }

        .nav-item {
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 17px;
            color: var(--navy);
            border: 1px solid transparent;
            border-radius: 8px;
            font-weight: 700;
        }

        .nav-item.active {
            background: #e8e8f0;
            border-color: #67b5ed;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--line);
            padding: 20px 16px 24px;
        }

        .logout {
            color: #ee0b28;
        }

        .main {
            min-width: 0;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            height: 68px;
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 0 32px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
        }

        .topbar h1 {
            margin: 0;
            font-size: 25px;
            line-height: 1;
            font-weight: 800;
            color: var(--navy);
        }

        .topbar-spacer {
            flex: 1;
        }

        .search-box {
            width: min(318px, 34vw);
            height: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            border-radius: 7px;
            background: #cbf2fa;
            color: #006aa7;
        }

        .search-box input {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--navy);
            font-size: 14px;
        }

        .search-box input::placeholder {
            color: #5f7d9c;
        }

        .notification {
            position: relative;
            width: 48px;
            height: 40px;
            display: grid;
            place-items: center;
            color: var(--navy);
            border-right: 1px solid var(--line);
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            right: 9px;
            min-width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #e1113c;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-copy {
            text-align: right;
            line-height: 1.25;
        }

        .admin-copy strong {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--navy);
        }

        .admin-copy span {
            font-size: 12px;
            color: #0065b1;
        }

        .avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: #0052a2;
            font-weight: 700;
        }

        .content {
            padding: 42px 38px 48px;
        }

        .flash {
            margin: 0 0 18px;
            padding: 12px 16px;
            border: 1px solid #7be3b0;
            border-radius: 8px;
            background: #edfff6;
            color: #08743a;
            font-weight: 700;
        }

        .section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .section-head h2 {
            margin: 0 0 10px;
            font-size: 30px;
            line-height: 1;
            color: var(--navy);
        }

        .section-head p,
        .muted {
            margin: 0;
            color: #425066;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 0 18px;
            border: 2px solid #00b2d5;
            border-radius: 7px;
            background: #fff;
            color: var(--navy);
            font-weight: 800;
            box-shadow: 0 2px 4px rgba(0, 82, 120, 0.18);
        }

        .btn.primary {
            border-color: var(--cyan);
            background: var(--cyan);
            color: #fff;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .metric-card {
            min-height: 218px;
            display: grid;
            grid-template-rows: auto 1fr auto;
            padding: 24px 24px 20px;
            border: 1px solid #99e7f7;
            border-radius: 14px;
            background: var(--panel);
            box-shadow: 0 2px 4px rgba(0, 83, 110, 0.18);
        }

        .metric-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: #fff;
        }

        .metric-icon.cyan {
            background: #08b5d6;
        }

        .metric-icon.blue {
            background: #087db6;
        }

        .metric-icon.green {
            background: #07ad46;
        }

        .metric-icon.yellow {
            background: var(--yellow);
        }

        .metric-change {
            margin-top: 15px;
            color: #00a94d;
            font-size: 14px;
            font-weight: 800;
        }

        .metric-change.warning {
            color: #e79300;
        }

        .metric-value {
            align-self: end;
            margin: 14px 0 2px;
            font-size: 38px;
            line-height: 1;
            color: var(--navy);
            font-weight: 900;
        }

        .metric-label {
            margin: 0;
            color: #1b3554;
            font-size: 15px;
        }

        .mini-bars {
            height: 32px;
            display: flex;
            align-items: flex-end;
            gap: 4px;
            margin-top: 14px;
        }

        .mini-bars span {
            flex: 1;
            min-width: 8px;
            border-radius: 4px 4px 0 0;
            background: var(--bar-color);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 83, 110, 0.12);
        }

        .chart-panel {
            min-height: 422px;
            padding: 26px 26px 24px;
        }

        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .panel-title h3 {
            margin: 0 0 6px;
            font-size: 21px;
            color: var(--navy);
        }

        .panel-title p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .tabs {
            display: flex;
            gap: 8px;
        }

        .tab {
            padding: 8px 13px;
            border-radius: 8px;
            background: #f0f0f2;
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
        }

        .tab.active {
            color: #fff;
            background: var(--cyan);
        }

        .line-chart {
            width: 100%;
            height: 282px;
            overflow: visible;
        }

        .axis-labels {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            color: #4c5c70;
            font-size: 12px;
            text-align: center;
            margin-top: 4px;
        }

        .bar-chart {
            height: 278px;
            display: grid;
            grid-template-columns: repeat(7, minmax(28px, 1fr));
            align-items: end;
            gap: 26px;
            padding: 8px 10px 0;
            border-left: 1px solid #8d99a8;
            border-bottom: 2px solid #8d99a8;
            background-image: linear-gradient(to bottom, rgba(193, 203, 213, .36) 1px, transparent 1px), linear-gradient(to right, rgba(193, 203, 213, .28) 1px, transparent 1px);
            background-size: 100% 65px, 16.66% 100%;
        }

        .bar-item {
            display: grid;
            align-items: end;
            height: 100%;
        }

        .bar {
            min-height: 8px;
            border-radius: 8px 8px 0 0;
            background: var(--blue);
        }

        .approval-panel,
        .activity-panel {
            padding: 26px 24px 24px;
            margin-bottom: 24px;
        }

        .approval-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .title-row {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .title-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #fff4b5;
            color: #e99c00;
        }

        .pill {
            padding: 7px 14px;
            border-radius: 999px;
            background: #fff3a6;
            color: #a56700;
            font-size: 14px;
            font-weight: 800;
        }

        .approval-list {
            display: grid;
            gap: 12px;
        }

        .approval-item {
            min-height: 98px;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 24px 18px 16px;
            border: 1px solid #93e6f6;
            border-radius: 10px;
            background: linear-gradient(90deg, #eafeff 0%, #ffffff 100%);
        }

        .approval-avatar {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #09a7ca;
            color: #fff;
            font-weight: 900;
            font-size: 18px;
        }

        .approval-copy {
            min-width: 0;
            flex: 1;
        }

        .approval-copy strong {
            display: block;
            color: #07113f;
            font-size: 16px;
        }

        .approval-copy span {
            display: block;
            margin-top: 3px;
            color: #344962;
            font-size: 14px;
        }

        .approval-copy small {
            display: block;
            margin-top: 5px;
            color: #526174;
            font-size: 12px;
        }

        .approval-actions {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .approval-btn {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 15px;
            border-radius: 7px;
            color: #fff;
            font-weight: 800;
        }

        .approval-btn.approve {
            background: #05a844;
        }

        .approval-btn.reject {
            background: var(--red);
        }

        .view-link {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            color: #4d596d;
        }

        .insights {
            margin-bottom: 24px;
            padding: 28px 24px 24px;
            color: #fff;
            border-radius: 14px;
            background: linear-gradient(105deg, #00076f 0%, #0088bf 100%);
            box-shadow: 0 3px 8px rgba(0, 22, 98, 0.2);
        }

        .insights h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 16px;
            font-size: 21px;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .insight-card {
            min-height: 84px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.12);
        }

        .insight-card strong {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .insight-card p {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
        }

        .activity-panel h3 {
            margin: 0 0 26px;
            font-size: 21px;
            color: var(--navy);
        }

        .activity-list {
            display: grid;
            gap: 20px;
        }

        .activity-item {
            min-height: 68px;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 16px;
        }

        .activity-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: #1478ff;
            background: #dbeafe;
        }

        .activity-copy {
            flex: 1;
            min-width: 0;
        }

        .activity-copy strong {
            display: block;
            color: #081038;
            font-size: 14px;
        }

        .activity-copy small {
            color: #40546e;
        }

        .activity-time {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #5b6679;
            font-size: 12px;
            white-space: nowrap;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #1e84ff;
        }

        .empty {
            padding: 28px;
            border: 1px dashed #9ee5f5;
            border-radius: 10px;
            color: #496178;
            background: #f7feff;
            text-align: center;
        }

        @media (max-width: 1180px) {
            .metrics-grid,
            .charts-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 820px) {
            .admin-shell {
                display: block;
            }

            .sidebar {
                position: static;
                height: auto;
            }

            .brand {
                justify-content: center;
            }

            .nav-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sidebar-footer {
                padding-top: 0;
            }

            .topbar {
                position: static;
                height: auto;
                align-items: stretch;
                flex-wrap: wrap;
                padding: 18px;
            }

            .topbar h1 {
                width: 100%;
            }

            .topbar-spacer {
                display: none;
            }

            .search-box {
                width: 100%;
            }

            .content {
                padding: 28px 18px;
            }

            .section-head,
            .approval-head,
            .approval-item {
                align-items: stretch;
                flex-direction: column;
            }

            .actions,
            .approval-actions {
                justify-content: flex-start;
            }

            .metrics-grid,
            .charts-grid,
            .insight-grid {
                grid-template-columns: 1fr;
            }

            .bar-chart {
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">
                <img src="<?php echo BASE_URL; ?>assets/images/printing-logo.png" alt="PrintEase">
            </div>

            <nav class="nav-list" aria-label="Super admin navigation">
                <a class="nav-item active" href="dashboard.php"><?php echo dashboardIcon('dashboard'); ?>Dashboard</a>
                <a class="nav-item" href="dashboard.php#pending-approvals"><?php echo dashboardIcon('shop'); ?>Manage Print Shops</a>
                <a class="nav-item" href="manage_users.php"><?php echo dashboardIcon('users'); ?>User Management</a>
                <a class="nav-item" href="reports.php"><?php echo dashboardIcon('report'); ?>Reports</a>
                <a class="nav-item" href="activity_logs.php"><?php echo dashboardIcon('activity'); ?>Activity Logs</a>
            </nav>

            <div class="sidebar-footer">
                <a class="nav-item logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php"><?php echo dashboardIcon('logout'); ?>Logout</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <h1>Super Admin Dashboard</h1>
                <div class="topbar-spacer"></div>
                <label class="search-box" aria-label="Search">
                    <?php echo dashboardIcon('search'); ?>
                    <input type="search" placeholder="Search...">
                </label>
                <div class="notification" title="<?php echo (int) $unread_notifications; ?> unread notifications">
                    <?php echo dashboardIcon('bell'); ?>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"></span>
                    <?php endif; ?>
                </div>
                <div class="admin-profile">
                    <div class="admin-copy">
                        <strong><?php echo e($admin_name); ?></strong>
                        <span><?php echo e($admin_email); ?></span>
                    </div>
                    <div class="avatar"><?php echo e($admin_initials); ?></div>
                </div>
            </header>

            <div class="content">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="flash"><?php echo e($_SESSION['message']); ?></div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <section class="section-head">
                    <div>
                        <h2>System Overview</h2>
                        <p>Monitor platform activity and manage users</p>
                    </div>
                    <div class="actions">
                        <a href="reports.php" class="btn primary"><?php echo dashboardIcon('chart'); ?>View Reports</a>
                    </div>
                </section>

                <section class="metrics-grid" aria-label="System metrics">
                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon cyan"><?php echo dashboardIcon('shop'); ?></span>
                            <span class="metric-change">+ <?php echo (int) $new_shops_today; ?> today</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $total_shops; ?></div>
                            <p class="metric-label">Total Print Shops</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#02a9c7">
                            <?php foreach ([9, 13, 11, 18, 14, 21, 24, 20, 28, 36] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon blue"><?php echo dashboardIcon('users'); ?></span>
                            <span class="metric-change">+ <?php echo (int) $new_users_week; ?> week</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $total_customers; ?></div>
                            <p class="metric-label">Total Customers</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#057eb8">
                            <?php foreach ([12, 15, 13, 19, 23, 22, 27, 30, 28, 33] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon green"><?php echo dashboardIcon('check'); ?></span>
                            <span class="metric-change"><?php echo (int) $verified_shops; ?> verified shops</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $active_users; ?></div>
                            <p class="metric-label">Active Users</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#08ba4e">
                            <?php foreach ([18, 22, 21, 25, 27, 24, 28, 31, 29, 33] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="metric-card">
                        <div class="metric-top">
                            <span class="metric-icon yellow"><?php echo dashboardIcon('clock'); ?></span>
                            <span class="metric-change warning"><?php echo dashboardIcon('clock'); ?> Needs Action</span>
                        </div>
                        <div>
                            <div class="metric-value"><?php echo (int) $pending_approvals; ?></div>
                            <p class="metric-label">Pending Approvals</p>
                        </div>
                        <div class="mini-bars" style="--bar-color:#f4ad00">
                            <?php foreach ([16, 27, 20, 33, 28, 21, 17, 26, 32, 22] as $height): ?>
                                <span style="height: <?php echo $height; ?>px"></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="charts-grid">
                    <article class="panel chart-panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <h3>User Growth</h3>
                                <p>Track user registration trends</p>
                            </div>
                            <div class="tabs">
                                <span class="tab active">Daily</span>
                                <span class="tab">Weekly</span>
                                <span class="tab">Monthly</span>
                            </div>
                        </div>
                        <svg class="line-chart" viewBox="0 0 700 260" preserveAspectRatio="none" role="img" aria-label="Daily user growth chart">
                            <defs>
                                <pattern id="grid" width="110" height="52" patternUnits="userSpaceOnUse">
                                    <path d="M110 0H0V52" fill="none" stroke="#d9e2ec" stroke-width="1" stroke-dasharray="3 3"></path>
                                </pattern>
                            </defs>
                            <rect x="40" y="0" width="640" height="220" fill="url(#grid)"></rect>
                            <path d="M40 0V220H680" fill="none" stroke="#8d99a8" stroke-width="1.5"></path>
                            <polyline points="<?php echo dashboardLinePoints($daily_values); ?>" fill="none" stroke="#08b7d4" stroke-width="4" transform="translate(40 0)"></polyline>
                            <?php
                            $point_pairs = explode(' ', dashboardLinePoints($daily_values));
                            foreach ($point_pairs as $pair):
                                [$x, $y] = explode(',', $pair);
                            ?>
                                <circle cx="<?php echo 40 + (float) $x; ?>" cy="<?php echo (float) $y; ?>" r="5" fill="#08b7d4" stroke="#fff" stroke-width="2"></circle>
                            <?php endforeach; ?>
                        </svg>
                        <div class="axis-labels">
                            <?php foreach ($daily_labels as $label): ?>
                                <span><?php echo e($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="panel chart-panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <h3>Shop Registrations</h3>
                                <p>New shops per week</p>
                            </div>
                        </div>
                        <div class="bar-chart" role="img" aria-label="Weekly shop registration chart">
                            <?php foreach ($weekly_values as $value): ?>
                                <div class="bar-item">
                                    <span class="bar" style="height: <?php echo max(8, round(($value / $max_weekly) * 230)); ?>px" title="<?php echo (int) $value; ?> shops"></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="axis-labels">
                            <?php foreach ($weekly_labels as $label): ?>
                                <span><?php echo e($label); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section id="pending-approvals" class="panel approval-panel">
                    <div class="approval-head">
                        <div class="title-row">
                            <span class="title-icon"><?php echo dashboardIcon('clock'); ?></span>
                            <div class="panel-title">
                                <h3>Pending Approvals</h3>
                                <p>Review and approve new registrations</p>
                            </div>
                        </div>
                        <span class="pill"><?php echo (int) $pending_approvals; ?> Pending</span>
                    </div>

                    <div class="approval-list">
                        <?php foreach ($pending_shops as $shop): ?>
                            <article class="approval-item">
                                <div class="approval-avatar"><?php echo e(strtoupper(substr($shop['shop_name'], 0, 1))); ?></div>
                                <div class="approval-copy">
                                    <strong><?php echo e($shop['shop_name']); ?></strong>
                                    <span><?php echo e($shop['full_name']); ?></span>
                                    <small>Registered: <?php echo e(date('Y-m-d', strtotime($shop['created_at']))); ?></small>
                                </div>
                                <div class="approval-actions">
                                    <a class="approval-btn approve" href="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php?shop_id=<?php echo (int) $shop['shop_id']; ?>&status=verified"><?php echo dashboardIcon('check'); ?>Approve</a>
                                    <a class="approval-btn reject" href="<?php echo BASE_URL; ?>backend/actions/update_permit_status.php?shop_id=<?php echo (int) $shop['shop_id']; ?>&status=rejected"><?php echo dashboardIcon('x'); ?>Reject</a>
                                    <?php if (!empty($shop['business_permit_file'])): ?>
                                        <a class="view-link" href="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" target="_blank" rel="noopener" title="View permit"><?php echo dashboardIcon('eye'); ?></a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($pending_accounts as $account): ?>
                            <article class="approval-item">
                                <div class="approval-avatar"><?php echo e(strtoupper(substr($account['full_name'], 0, 1))); ?></div>
                                <div class="approval-copy">
                                    <strong><?php echo e($account['full_name']); ?></strong>
                                    <span><?php echo e(ucfirst(str_replace('_', ' ', $account['role']))); ?> account</span>
                                    <small>Registered: <?php echo e(date('Y-m-d', strtotime($account['created_at']))); ?></small>
                                </div>
                                <div class="approval-actions">
                                    <a class="btn" href="manage_users.php"><?php echo dashboardIcon('users'); ?>Manage User</a>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php if (empty($pending_shops) && empty($pending_accounts)): ?>
                            <div class="empty">No pending approvals right now.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="insights">
                    <h3><?php echo dashboardIcon('bulb'); ?>Key Insights</h3>
                    <div class="insight-grid">
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('sparkle'); ?>New Shops</strong>
                            <p><?php echo (int) $new_shops_today; ?> new shop<?php echo $new_shops_today === 1 ? '' : 's'; ?> registered today</p>
                        </article>
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('trend'); ?>Growth Rate</strong>
                            <p><?php echo (int) array_sum($daily_values); ?> new user<?php echo array_sum($daily_values) === 1 ? '' : 's'; ?> in the last 7 days</p>
                        </article>
                        <article class="insight-card">
                            <strong><?php echo dashboardIcon('activity'); ?>Platform Activity</strong>
                            <p><?php echo count($recent_activity); ?> latest activity item<?php echo count($recent_activity) === 1 ? '' : 's'; ?> available</p>
                        </article>
                    </div>
                </section>

                <section class="panel activity-panel">
                    <h3>Recent Activity</h3>
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty">No recent activity yet.</div>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recent_activity as $index => $activity): ?>
                                <article class="activity-item">
                                    <span class="activity-icon"><?php echo dashboardIcon($index === 0 ? 'shop' : ($index === 1 ? 'clock' : 'activity')); ?></span>
                                    <div class="activity-copy">
                                        <strong><?php echo e($activity['action']); ?></strong>
                                        <small><?php echo e($activity['module']); ?> by <?php echo e($activity['full_name']); ?></small>
                                    </div>
                                    <div class="activity-time">
                                        <span><?php echo e(dashboardRelativeTime($activity['created_at'])); ?></span>
                                        <span class="dot"></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>

</html>
