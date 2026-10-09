<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/cloud_storage.php';
require_admin();

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) {
    http_response_code(404);
    exit('Proof not found.');
}

$stmt = $conn->prepare('SELECT proof FROM wallet_funding_requests WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $requestId);
$stmt->execute();
$reference = (string) ($stmt->get_result()->fetch_assoc()['proof'] ?? '');
$extension = strtolower(pathinfo(basename($reference), PATHINFO_EXTENSION));
$contentTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
if ($reference === '' || !isset($contentTypes[$extension])) {
    http_response_code(404);
    exit('Proof not found.');
}

try {
    $file = liteup_read_stored_file($reference);
} catch (Throwable $exception) {
    http_response_code(404);
    exit('Proof not found.');
}

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . strlen($file['body']));
header('Content-Disposition: attachment; filename="funding-proof-' . (int) $requestId . '.' . $extension . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $file['body'];
