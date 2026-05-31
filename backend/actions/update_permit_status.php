<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";
checkRole("super_admin");

if (isset($_GET['shop_id']) && isset($_GET['status'])) {
    $shop_id = intval($_GET['shop_id']);
    $status = trim($_GET['status']);

    $allowed_status = ['pending', 'verified', 'rejected'];

    if (!in_array($status, $allowed_status)) {
        setMessage("Invalid permit status specified.");
        redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
    }

    // First get owner info (before possible status conflict)
    $owner_sql = "SELECT owner_id, shop_name FROM print_shops WHERE shop_id = ?";
    $owner_stmt = mysqli_prepare($conn, $owner_sql);
    mysqli_stmt_bind_param($owner_stmt, "i", $shop_id);
    mysqli_stmt_execute($owner_stmt);
    $owner_result = mysqli_stmt_get_result($owner_stmt);
    $shop = mysqli_fetch_assoc($owner_result);

    if (!$shop) {
        setMessage("Shop not found.");
        redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
    }

    $sql = "UPDATE print_shops SET permit_status = ? WHERE shop_id = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "si", $status, $shop_id);

    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_affected_rows($conn);

        $status_message = "Your business permit for \"" . $shop['shop_name'] . "\" has been " . $status . ".";
        sendNotification($conn, $shop['owner_id'], $status_message);
        logActivity($conn, $_SESSION['user_id'], "Updated permit #$shop_id (shop: {$shop['shop_name']}) to $status", "Permit Management");

        if ($affected > 0) {
            setMessage("Permit for \"" . $shop['shop_name'] . "\" has been set to: " . ucfirst($status) . ".");
        } else {
            setMessage("Permit status was already set to: " . ucfirst($status) . ".");
        }
    } else {
        setMessage("Database error: Failed to update permit status.");
    }

    redirect(BASE_URL . "frontend/user/superadmin/dashboard.php");
} else {
    echo "Missing shop ID or status.";
}
?>
