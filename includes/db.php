<?php
require_once __DIR__ . '/env.php';
$env = liteup_env();

$isVercel = getenv('VERCEL') === '1';
$host = trim((string) ($env['LITEUP_DB_HOST'] ?? ''));
$db_user = (string) ($env['LITEUP_DB_USER'] ?? '');
$db_pass = (string) ($env['LITEUP_DB_PASSWORD'] ?? '');
$db_name = trim((string) ($env['LITEUP_DB_NAME'] ?? ''));
$port = (int) ($env['LITEUP_DB_PORT'] ?? ($isVercel ? 4000 : 3306));

if ($isVercel && ($host === '' || $db_user === '' || $db_pass === '' || $db_name === '' || strtolower($host) === 'localhost' || strtolower($db_user) === 'root')) {
    error_log('LiteUp cloud database configuration is incomplete or uses local defaults.');
    http_response_code(500);
    exit('The service is temporarily unavailable. Please try again later.');
}

$host = $host !== '' ? $host : 'localhost';
$db_user = $db_user !== '' ? $db_user : 'root';
$db_name = $db_name !== '' ? $db_name : 'liteup_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_init();
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 8);
    if ($isVercel || filter_var($env['LITEUP_DB_TLS'] ?? false, FILTER_VALIDATE_BOOL)) {
        $caFile = trim((string) ($env['LITEUP_DB_SSL_CA_FILE'] ?? ''));
        if ($caFile !== '') {
            mysqli_ssl_set($conn, null, null, $caFile, null, null);
        } else {
            mysqli_ssl_set($conn, null, null, null, null, null);
        }
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
        }
        $conn->real_connect($host, $db_user, $db_pass, $db_name, $port, null, MYSQLI_CLIENT_SSL);
    } else {
        $conn->real_connect($host, $db_user, $db_pass, $db_name, $port);
    }
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('LiteUp database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The service is temporarily unavailable. Please try again later.');
}
?>
