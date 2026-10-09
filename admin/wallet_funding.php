<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';

    if (!$requestId || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid funding request action.';
    } else {
        $conn->begin_transaction();
        try {
            $requestStmt = $conn->prepare("SELECT user_id, amount FROM wallet_funding_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
            $requestStmt->bind_param('i', $requestId);
            $requestStmt->execute();
            $request = $requestStmt->get_result()->fetch_assoc();
            if (!$request) {
                throw new RuntimeException('This request has already been processed.');
            }

            $status = $action === 'approve' ? 'approved' : 'rejected';
            if ($action === 'approve') {
                $fundingAmount = (float) $request['amount'];
                $fundingUserId = (int) $request['user_id'];
                $walletStmt = $conn->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
                $walletStmt->bind_param('di', $fundingAmount, $fundingUserId);
                $walletStmt->execute();
                if ($walletStmt->affected_rows !== 1) {
                    throw new RuntimeException('The user wallet could not be found.');
                }

                $type = 'deposit';
                $details = 'Wallet funding approved';
                $transactionStmt = $conn->prepare('INSERT INTO transactions (user_id, type, amount, details) VALUES (?, ?, ?, ?)');
                $transactionStmt->bind_param('isds', $fundingUserId, $type, $fundingAmount, $details);
                $transactionStmt->execute();
            }

            $updateStmt = $conn->prepare('UPDATE wallet_funding_requests SET status = ? WHERE id = ?');
            $updateStmt->bind_param('si', $status, $requestId);
            $updateStmt->execute();
            $conn->commit();
            $message = $action === 'approve' ? 'Funding approved and wallet credited.' : 'Funding request rejected.';
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Wallet funding review failed: ' . $exception->getMessage());
            $error = $exception instanceof RuntimeException && $exception->getMessage() === 'This request has already been processed.'
                ? $exception->getMessage()
                : 'The request could not be processed. No changes were saved.';
        }
    }
}
?>

<?php include 'navbar.php'; ?>

<main class="admin-main flex-grow-1">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Wallet Funding Requests</h3>
            <p class="text-secondary mb-0">Review pending deposits and credit approved wallets.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card border-0 shadow-sm">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Amount</th>
                <th>Proof</th>
                <th>Requested</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $requests = $conn->query("SELECT * FROM wallet_funding_requests WHERE status = 'pending' ORDER BY created_at ASC");
            if ($requests->num_rows === 0):
            ?>
                <tr><td colspan="5" class="text-center text-secondary py-5">No pending funding requests.</td></tr>
            <?php endif;
            while ($row = $requests->fetch_assoc()) {
                ?>
                <tr>
                    <td><?= (int) $row['user_id'] ?></td>
                    <td class="fw-semibold">₦<?= number_format((float) $row['amount'], 2) ?></td>
                    <td><?php if (!empty($row['proof'])): ?><a href="download_proof.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">View proof</a><?php else: ?><span class="text-secondary">Not provided</span><?php endif; ?></td>
                    <td><?= htmlspecialchars((string) $row['created_at']) ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline" data-confirm="Approve this funding request and credit the wallet?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form method="post" class="d-inline" data-confirm="Reject this funding request?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
</main>

<?php include '../includes/footer.php'; ?>
