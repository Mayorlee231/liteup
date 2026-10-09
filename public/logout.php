<?php
require_once '../includes/security.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();

$trustedToken = (string) ($_COOKIE['liteup_trusted_device'] ?? '');
if ($trustedToken !== '' && isset($_SESSION['user_id'])) {
    $trustedHash = hash('sha256', $trustedToken);
    $revoke = $conn->prepare('DELETE FROM trusted_devices WHERE user_id = ? AND token_hash = ?');
    $revoke->bind_param('is', $_SESSION['user_id'], $trustedHash);
    $revoke->execute();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}

setcookie('liteup_trusted_device', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => liteup_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_destroy();
header('Location: login.php');
exit();
