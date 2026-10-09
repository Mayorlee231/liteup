<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/cloud_storage.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    exit('Authentication required.');
}

$stmt = $conn->prepare('SELECT profile_pic FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$reference = (string) ($stmt->get_result()->fetch_assoc()['profile_pic'] ?? '');
if ($reference === '') {
    http_response_code(404);
    exit('Image not found.');
}

$extension = strtolower(pathinfo(basename($reference), PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
if (!isset($types[$extension])) {
    http_response_code(404);
    exit('Image not found.');
}

try {
    $file = liteup_read_stored_file($reference);
} catch (Throwable $exception) {
    http_response_code(404);
    exit('Image not found.');
}

header('Content-Type: ' . $types[$extension]);
header('Content-Length: ' . strlen($file['body']));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $file['body'];
