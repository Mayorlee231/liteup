<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/verification.php';

function assert_verification_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$userId = 0;
try {
    $email = 'liteup-code-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $phone = '08' . random_int(100000000, 999999999);
    $fullName = 'Verification Test';
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $createUser = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, wallet_balance) VALUES (?, ?, ?, ?, 'user', 0)");
    $createUser->bind_param('ssss', $fullName, $email, $phone, $passwordHash);
    $createUser->execute();
    $userId = (int) $createUser->insert_id;

    $purpose = 'login';
    $wrongCodeHash = password_hash('492681', PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $insertCode = $conn->prepare('INSERT INTO verification_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)');
    $insertCode->bind_param('isss', $userId, $purpose, $wrongCodeHash, $expiresAt);
    $insertCode->execute();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        assert_verification_test(!verify_code($conn, $userId, '111111', 'login'), 'An incorrect code was accepted.');
    }
    assert_verification_test(!verify_code($conn, $userId, '492681', 'login'), 'A code was accepted after five failed guesses.');

    $invalidateCodes = $conn->prepare("UPDATE verification_codes SET used_at = NOW() WHERE user_id = ? AND purpose = 'login' AND used_at IS NULL");
    $invalidateCodes->bind_param('i', $userId);
    $invalidateCodes->execute();
    $rightCodeHash = password_hash('735204', PASSWORD_DEFAULT);
    $insertCode->bind_param('isss', $userId, $purpose, $rightCodeHash, $expiresAt);
    $insertCode->execute();
    assert_verification_test(verify_code($conn, $userId, '735204', 'login'), 'A valid code was not accepted.');
    assert_verification_test(!verify_code($conn, $userId, '735204', 'login'), 'A code was accepted more than once.');

    echo "PASS: invalid-code attempts are capped, valid code is single-use, and replay is rejected.\n";
} finally {
    if ($userId > 0) {
        $deleteCodes = $conn->prepare('DELETE FROM verification_codes WHERE user_id = ?');
        $deleteCodes->bind_param('i', $userId);
        $deleteCodes->execute();
        $deleteUser = $conn->prepare('DELETE FROM users WHERE id = ?');
        $deleteUser->bind_param('i', $userId);
        $deleteUser->execute();
    }
}
