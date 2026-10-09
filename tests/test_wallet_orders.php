<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/order_service.php';

function assert_order_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$userId = 0;
try {
    $email = 'liteup-order-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $phone = '07' . random_int(100000000, 999999999);
    $name = 'Temporary Order Test';
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $createUser = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, wallet_balance) VALUES (?, ?, ?, ?, 'user', 0)");
    $createUser->bind_param('ssss', $name, $email, $phone, $password);
    $createUser->execute();
    $userId = (int) $createUser->insert_id;

    $createWallet = $conn->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 10000.00)');
    $createWallet->bind_param('i', $userId);
    $createWallet->execute();

    $mobileToken = bin2hex(random_bytes(32));
    $mobileSubmission = create_wallet_order($conn, $userId, 'data', $phone, 'TEST 500MB', 50.00, $mobileToken);
    $mobileOrderId = $mobileSubmission['id'];
    assert_order_test($mobileSubmission['created'], 'Initial order was not marked as newly created.');
    $replayedSubmission = create_wallet_order($conn, $userId, 'data', $phone, 'TEST 500MB', 50.00, $mobileToken);
    assert_order_test(!$replayedSubmission['created'] && $replayedSubmission['id'] === $mobileOrderId, 'Repeated order token did not return the original order.');
    $balanceStmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ?');
    $balanceStmt->bind_param('i', $userId);
    $balanceStmt->execute();
    assert_order_test((float) $balanceStmt->get_result()->fetch_assoc()['balance'] === 9950.0, 'Order creation did not debit the wallet.');

    transition_wallet_order($conn, 'mobile', $mobileOrderId, 'failed');
    $balanceStmt->execute();
    assert_order_test((float) $balanceStmt->get_result()->fetch_assoc()['balance'] === 10000.0, 'Failed order was not refunded.');

    $refundCount = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE user_id = ? AND type = 'refund'");
    $refundCount->bind_param('i', $userId);
    $refundCount->execute();
    assert_order_test((int) $refundCount->get_result()->fetch_assoc()['total'] === 1, 'Refund ledger entry was not written exactly once.');

    try {
        transition_wallet_order($conn, 'mobile', $mobileOrderId, 'failed');
        throw new RuntimeException('A previously failed order accepted a second refund transition.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'A previously failed order accepted a second refund transition.') {
            throw $exception;
        }
    }

    $electricSubmission = create_wallet_order($conn, $userId, 'electricity', '1234567890', '', 10.00, bin2hex(random_bytes(32)));
    $electricOrderId = $electricSubmission['id'];
    transition_wallet_order($conn, 'electricity', $electricOrderId, 'completed');
    $balanceStmt->execute();
    assert_order_test((float) $balanceStmt->get_result()->fetch_assoc()['balance'] === 9990.0, 'Completed order changed the refunded wallet unexpectedly.');

    $tamperToken = bin2hex(random_bytes(32));
    $tamperResult = create_tamper_code_order($conn, $userId, $tamperToken);
    $tamperReplay = create_tamper_code_order($conn, $userId, $tamperToken);
    assert_order_test($tamperResult['created'] && !$tamperReplay['created'] && $tamperResult['code'] === $tamperReplay['code'], 'Tamper-code replay did not return the original code.');
    $balanceStmt->execute();
    assert_order_test((float) $balanceStmt->get_result()->fetch_assoc()['balance'] === 4990.0, 'Tamper-code replay charged the wallet more than once.');

    echo "PASS: order debit, one-time failure refund, duplicate protection, and completion.\n";
} finally {
    if ($userId > 0) {
        $tables = ['airtime_data_orders', 'electricity_orders', 'transactions', 'wallet_funding_requests', 'tamper_code', 'tamper_codes', 'tamper_code_requests', 'wallet_transactions', 'wallet', 'wallets', 'trusted_devices', 'verification_codes'];
        foreach ($tables as $table) {
            $delete = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
            $delete->bind_param('i', $userId);
            $delete->execute();
        }
        $deleteUser = $conn->prepare('DELETE FROM users WHERE id = ?');
        $deleteUser->bind_param('i', $userId);
        $deleteUser->execute();
    }
}
