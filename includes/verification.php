<?php
require_once __DIR__ . '/mailer.php';

function issue_verification_code(mysqli $conn, int $userId, string $email, string $name, string $purpose): bool
{
    if (!in_array($purpose, ['registration', 'login'], true)) {
        return false;
    }

    $recent = $conn->prepare('SELECT id FROM verification_codes WHERE user_id = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1');
    $recent->bind_param('is', $userId, $purpose);
    $recent->execute();
    if ($recent->get_result()->fetch_assoc()) {
        return false;
    }

    $invalidate = $conn->prepare('UPDATE verification_codes SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL');
    $invalidate->bind_param('is', $userId, $purpose);
    $invalidate->execute();

    $code = (string) random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $insert = $conn->prepare('INSERT INTO verification_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)');
    $insert->bind_param('isss', $userId, $purpose, $codeHash, $expiresAt);
    if (!$insert->execute()) {
        return false;
    }

    if (send_verification_code($email, $name, $code, $purpose)) {
        return true;
    }

    $delete = $conn->prepare('UPDATE verification_codes SET used_at = NOW() WHERE id = ?');
    $verificationId = $insert->insert_id;
    $delete->bind_param('i', $verificationId);
    $delete->execute();
    return false;
}

function verify_code(mysqli $conn, int $userId, string $code, string $purpose): bool
{
    if (!preg_match('/^\d{6}$/', $code) || !in_array($purpose, ['registration', 'login'], true)) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, code_hash, attempts FROM verification_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->bind_param('is', $userId, $purpose);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        if (!$record || (int) $record['attempts'] >= 5) {
            $conn->commit();
            return false;
        }

        if (!password_verify($code, $record['code_hash'])) {
            $failed = $conn->prepare('UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ? AND attempts < 5');
            $failed->bind_param('i', $record['id']);
            $failed->execute();
            $conn->commit();
            return false;
        }

        $used = $conn->prepare('UPDATE verification_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $used->bind_param('i', $record['id']);
        $used->execute();
        $valid = $used->affected_rows === 1;
        $conn->commit();
        return $valid;
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function trusted_device_is_valid(mysqli $conn, int $userId, string $token): bool
{
    if ($token === '') {
        return false;
    }

    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id FROM trusted_devices WHERE user_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('is', $userId, $hash);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();
    if ($valid) {
        $update = $conn->prepare('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ?');
        $update->bind_param('i', $valid['id']);
        $update->execute();
    }
    return (bool) $valid;
}

function create_trusted_device(mysqli $conn, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    $stmt = $conn->prepare('INSERT INTO trusted_devices (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $hash, $expiresAt);
    $stmt->execute();
    return $token;
}
