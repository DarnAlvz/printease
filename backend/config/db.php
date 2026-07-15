<?php
require_once __DIR__ . "/env.php";

$host = envValue("DB_HOST", "localhost");
$username = envValue("DB_USER", "root");
$password = envValue("DB_PASS", "");
$dbname = envValue("DB_NAME", "printease_db");
$app_env = strtolower((string) envValue("APP_ENV", "local"));

$db_connection_optional = defined('DB_CONNECTION_OPTIONAL') && DB_CONNECTION_OPTIONAL === true;
$conn = null;

try {
    $conn = @new mysqli($host, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new mysqli_sql_exception($conn->connect_error, $conn->connect_errno);
    }
} catch (mysqli_sql_exception $exception) {
    if (!$db_connection_optional) {
        error_log("[PrintEase] Database connection failed: " . $exception->getMessage());
        die("A system error occurred. Please try again later.");
    }
}
?>
