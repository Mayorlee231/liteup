<?php
function create_wallet_order(mysqli $conn, int $userId, string $kind, string $recipient, string $network, float $amount, string $idempotencyKey): array
{
    if (!in_array($kind, ['airtime', 'data', 'electricity'], true) || $amount <= 0 || !preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)) {
        throw new InvalidArgumentException('Invalid order details.');
    }

    $conn->begin_transaction();
    try {
        $table = $kind === 'electricity' ? 'electricity_orders' : 'airtime_data_orders';
        $existing = $conn->prepare("SELECT id, user_id FROM `$table` WHERE idempotency_key = ? LIMIT 1 FOR UPDATE");
        $existing->bind_param('s', $idempotencyKey);
        $existing->execute();
        $existingOrder = $existing->get_result()->fetch_assoc();
        if ($existingOrder) {
            if ((int) $existingOrder['user_id'] !== $userId) {
                throw new RuntimeException('This order token cannot be reused.');
            }
            $conn->commit();
            return ['id' => (int) $existingOrder['id'], 'created' => false];
        }

        $debit = $conn->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND balance >= ?');
        $debit->bind_param('did', $amount, $userId, $amount);
        $debit->execute();
        if ($debit->affected_rows !== 1) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        if ($kind === 'electricity') {
            $insert = $conn->prepare("INSERT INTO electricity_orders (user_id, meter_number, amount, status, idempotency_key) VALUES (?, ?, ?, 'pending', ?)");
            $insert->bind_param('isds', $userId, $recipient, $amount, $idempotencyKey);
        } else {
            $insert = $conn->prepare("INSERT INTO airtime_data_orders (user_id, phone_number, network, type, amount, status, idempotency_key) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $insert->bind_param('isssds', $userId, $recipient, $network, $kind, $amount, $idempotencyKey);
        }
        $insert->execute();
        $orderId = (int) $insert->insert_id;

        $type = 'utility_purchase';
        $details = ucfirst($kind) . ' request #' . $orderId . ' submitted for manual fulfillment';
        $ledger = $conn->prepare('INSERT INTO transactions (user_id, type, amount, details) VALUES (?, ?, ?, ?)');
        $ledger->bind_param('isds', $userId, $type, $amount, $details);
        $ledger->execute();
        $conn->commit();
        return ['id' => $orderId, 'created' => true];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function transition_wallet_order(mysqli $conn, string $kind, int $orderId, string $newStatus): void
{
    $tables = ['mobile' => 'airtime_data_orders', 'electricity' => 'electricity_orders'];
    if (!isset($tables[$kind]) || !in_array($newStatus, ['completed', 'failed'], true) || $orderId < 1) {
        throw new InvalidArgumentException('Invalid order transition.');
    }

    $table = $tables[$kind];
    $conn->begin_transaction();
    try {
        $select = $conn->prepare("SELECT user_id, amount, status FROM `$table` WHERE id = ? FOR UPDATE");
        $select->bind_param('i', $orderId);
        $select->execute();
        $order = $select->get_result()->fetch_assoc();
        if (!$order || $order['status'] !== 'pending') {
            throw new RuntimeException('This order is no longer pending.');
        }

        if ($newStatus === 'failed') {
            $userId = (int) $order['user_id'];
            $amount = (float) $order['amount'];
            $wallet = $conn->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
            $wallet->bind_param('di', $amount, $userId);
            $wallet->execute();
            if ($wallet->affected_rows !== 1) {
                throw new RuntimeException('Customer wallet was not found; refund was not issued.');
            }

            $type = 'refund';
            $details = 'Refund for failed ' . $kind . ' order #' . $orderId;
            $ledger = $conn->prepare('INSERT INTO transactions (user_id, type, amount, details) VALUES (?, ?, ?, ?)');
            $ledger->bind_param('isds', $userId, $type, $amount, $details);
            $ledger->execute();
        }

        $update = $conn->prepare("UPDATE `$table` SET status = ? WHERE id = ? AND status = 'pending'");
        $update->bind_param('si', $newStatus, $orderId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Order status changed before this action completed.');
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function create_tamper_code_order(mysqli $conn, int $userId, string $idempotencyKey): array
{
    if ($userId < 1 || !preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)) {
        throw new InvalidArgumentException('Invalid tamper-code request.');
    }

    $conn->begin_transaction();
    try {
        $existing = $conn->prepare('SELECT code FROM tamper_codes WHERE user_id = ? AND idempotency_key = ? LIMIT 1 FOR UPDATE');
        $existing->bind_param('is', $userId, $idempotencyKey);
        $existing->execute();
        $existingCode = $existing->get_result()->fetch_assoc();
        if ($existingCode) {
            $conn->commit();
            return ['code' => (string) $existingCode['code'], 'created' => false];
        }

        $wallet = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $wallet->bind_param('i', $userId);
        $wallet->execute();
        $balance = $wallet->get_result()->fetch_assoc();
        if (!$balance || (float) $balance['balance'] < 5000) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        $fee = 5000.00;
        $debit = $conn->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND balance >= ?');
        $debit->bind_param('did', $fee, $userId, $fee);
        $debit->execute();
        if ($debit->affected_rows !== 1) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        do {
            $code = strtoupper(bin2hex(random_bytes(6)));
            $codeCheck = $conn->prepare('SELECT id FROM tamper_codes WHERE code = ? LIMIT 1');
            $codeCheck->bind_param('s', $code);
            $codeCheck->execute();
            $codeCheck->store_result();
            $exists = $codeCheck->num_rows > 0;
            $codeCheck->close();
        } while ($exists);

        $insert = $conn->prepare("INSERT INTO tamper_codes (user_id, code, idempotency_key, status) VALUES (?, ?, ?, 'pending')");
        $insert->bind_param('iss', $userId, $code, $idempotencyKey);
        $insert->execute();

        $transactionAmount = $fee;
        $type = 'debit';
        $details = 'Tamper code generation fee';
        $ledger = $conn->prepare('INSERT INTO wallet_transactions (user_id, amount, type, details) VALUES (?, ?, ?, ?)');
        $ledger->bind_param('idss', $userId, $transactionAmount, $type, $details);
        $ledger->execute();
        $conn->commit();
        return ['code' => $code, 'created' => true];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}
