<?php
require_once __DIR__ . "/session.php";

secureSession();

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/remember_auth.php";

function clearAuthenticatedSession($conn)
{
    rememberRevokeCurrentDevice($conn);
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function validateAuthenticatedSession($conn)
{
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0 || !isset($_SESSION['auth_version'])) {
        return false;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT role, account_status, auth_version FROM users WHERE user_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $user
        && !in_array(($user['account_status'] ?? ''), ['rejected', 'inactive'], true)
        && hash_equals((string) $user['role'], (string) ($_SESSION['role'] ?? ''))
        && (int) $user['auth_version'] === (int) $_SESSION['auth_version'];
}

function authIsAjaxRequest(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function authJsonResponse(bool $success, string $message, int $status_code): void
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);
    exit();
}

function authenticatedHomeUrl($conn)
{
    $role = $_SESSION['role'] ?? '';

    switch ($role) {
        case 'shop_owner':
            $user_id = (int) ($_SESSION['user_id'] ?? 0);
            $stmt = mysqli_prepare($conn, "SELECT shop_id FROM print_shops WHERE owner_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $shop = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($shop) === 0) {
                return BASE_URL . "frontend/user/shop_owner/shop_profile.php";
            }

            return BASE_URL . "frontend/user/shop_owner/dashboard.php";

        case 'super_admin':
            return BASE_URL . "frontend/user/superadmin/dashboard.php";

        case 'customer':
            $user_id = (int) ($_SESSION['user_id'] ?? 0);
            $stmt = mysqli_prepare($conn, "SELECT account_status FROM users WHERE user_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (($user['account_status'] ?? 'incomplete') === 'incomplete') {
                return BASE_URL . "frontend/user/customer/profile.php";
            }

            return BASE_URL . "frontend/user/customer/dashboard.php";

        default:
            return '';
    }
}

function redirectIfAuthenticated($conn)
{
    if (!$conn instanceof mysqli) {
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        rememberRestoreSession($conn);
    }

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    if (!validateAuthenticatedSession($conn)) {
        clearAuthenticatedSession($conn);
        return;
    }

    rememberRenewCurrentDevice($conn);
    $url = authenticatedHomeUrl($conn);

    if ($url !== '') {
        header("Location: " . $url);
        exit();
    }
}

function checkRole($required_role)
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        rememberRestoreSession($conn);
    }

    if (!isset($_SESSION['user_id']) || !validateAuthenticatedSession($conn)) {
        clearAuthenticatedSession($conn);
        if (authIsAjaxRequest()) {
            authJsonResponse(false, 'Authentication required.', 401);
        }
        header("Location: " . BASE_URL . "frontend/pages/login.php");
        exit();
    }

    rememberRenewCurrentDevice($conn);

    if (($_SESSION['role'] ?? '') !== $required_role) {
        if (authIsAjaxRequest()) {
            authJsonResponse(false, 'Access denied.', 403);
        }
        http_response_code(403);
        echo "Access denied.";
        exit();
    }
}
?>
