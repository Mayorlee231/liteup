<?php
require_once __DIR__ . '/env.php';

function liteup_cloud_storage_enabled(): bool
{
    return getenv('VERCEL') === '1';
}

function liteup_storage_endpoint(): string
{
    $env = liteup_env();
    $endpoint = trim((string) ($env['LITEUP_STORAGE_API_URL'] ?? ''));
    if ($endpoint === '') {
        $host = trim((string) ($env['VERCEL_URL'] ?? ''));
        if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host)) {
            throw new RuntimeException('Cloud file storage endpoint is not configured.');
        }
        $endpoint = 'https://' . $host . '/api/blob';
    }
    $parts = parse_url($endpoint);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])) {
        throw new RuntimeException('Cloud file storage endpoint is invalid.');
    }
    if (($parts['scheme'] ?? '') !== 'https' && !in_array($parts['host'], ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Cloud file storage requires HTTPS.');
    }
    return $endpoint;
}

function liteup_blob_key_is_valid(string $key): bool
{
    return (bool) preg_match('#^(?:profiles/[a-f0-9]{32}\.(?:jpg|png|gif|webp)|proofs/[a-f0-9]{32}\.(?:jpg|png|webp|pdf))$#', $key);
}

/** @return array{status:int, body:string, content_type:string} */
function liteup_blob_request(string $method, string $key, ?string $body = null, string $contentType = ''): array
{
    if (!liteup_blob_key_is_valid($key)) {
        throw new RuntimeException('Invalid cloud file reference.');
    }
    $env = liteup_env();
    $token = (string) ($env['LITEUP_STORAGE_SERVICE_TOKEN'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Cloud file storage is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for cloud file storage.');
    }

    $headers = [
        'X-LiteUp-Storage-Token: ' . $token,
        'X-LiteUp-Storage-Key: ' . $key,
    ];
    if ($contentType !== '') {
        $headers[] = 'X-LiteUp-Storage-Content-Type: ' . $contentType;
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/octet-stream';
    }
    $curl = curl_init(liteup_storage_endpoint());
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body ?? '');
    }
    $response = curl_exec($curl);
    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('LiteUp cloud storage connection failed: ' . $error);
        throw new RuntimeException('Cloud file storage is temporarily unavailable.');
    }
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $responseType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if ($status < 200 || $status >= 300) {
        error_log('LiteUp cloud storage returned HTTP ' . $status . '.');
        throw new RuntimeException('Cloud file storage is temporarily unavailable.');
    }
    return ['status' => $status, 'body' => (string) $response, 'content_type' => $responseType];
}

function liteup_store_uploaded_file(string $temporaryPath, string $objectKey, string $contentType): string|false
{
    if (liteup_cloud_storage_enabled()) {
        $bytes = file_get_contents($temporaryPath);
        if ($bytes === false) {
            return false;
        }
        try {
            $response = liteup_blob_request('POST', $objectKey, $bytes, $contentType);
            $result = json_decode($response['body'], true);
            return is_array($result) && ($result['pathname'] ?? '') === $objectKey ? $objectKey : false;
        } catch (Throwable $exception) {
            error_log('LiteUp cloud file upload failed: ' . $exception->getMessage());
            return false;
        }
    }

    $proof = str_starts_with($objectKey, 'proofs/');
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . ($proof ? DIRECTORY_SEPARATOR . 'proofs' : '');
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        return false;
    }
    $filename = basename($objectKey);
    return move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename) ? $filename : false;
}

function liteup_delete_stored_file(string $reference): void
{
    if (str_contains($reference, '/')) {
        try {
            liteup_blob_request('DELETE', $reference);
        } catch (Throwable $exception) {
            error_log('LiteUp cloud file cleanup failed.');
        }
        return;
    }
    if ($reference === '' || basename($reference) !== $reference) {
        return;
    }
    $proofPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'proofs' . DIRECTORY_SEPARATOR . $reference;
    $profilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $reference;
    foreach ([$proofPath, $profilePath] as $path) {
        if (is_file($path)) {
            unlink($path);
            return;
        }
    }
}

/** @return array{body:string, content_type:string} */
function liteup_read_stored_file(string $reference): array
{
    if (str_contains($reference, '/')) {
        $result = liteup_blob_request('GET', $reference);
        return ['body' => $result['body'], 'content_type' => $result['content_type']];
    }
    if ($reference === '' || basename($reference) !== $reference) {
        throw new RuntimeException('File not found.');
    }
    $isProof = str_starts_with($reference, 'proof-');
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . ($isProof ? DIRECTORY_SEPARATOR . 'proofs' : '') . DIRECTORY_SEPARATOR . $reference;
    if (!is_file($path)) {
        // Keep compatibility with older local proof names stored without a prefix.
        $alternate = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'proofs' . DIRECTORY_SEPARATOR . $reference;
        if (!is_file($alternate)) {
            throw new RuntimeException('File not found.');
        }
        $path = $alternate;
    }
    $body = file_get_contents($path);
    if ($body === false) {
        throw new RuntimeException('File not found.');
    }
    return ['body' => $body, 'content_type' => mime_content_type($path) ?: 'application/octet-stream'];
}
