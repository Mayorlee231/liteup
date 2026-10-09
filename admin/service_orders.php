<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/order_service.php';
require_admin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $kind = (string) ($_POST['kind'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    if (!$orderId || !in_array($kind, ['mobile', 'electricity'], true) || !in_array($action, ['complete', 'fail'], true)) {
        $error = 'Invalid service-order action.';
    } else {
        try {
            $newStatus = $action === 'complete' ? 'completed' : 'failed';
            transition_wallet_order($conn, $kind, $orderId, $newStatus);
            $message = $action === 'complete' ? 'Order marked completed.' : 'Order marked failed and customer refunded.';
        } catch (Throwable $exception) {
            error_log('Service order update failed: ' . $exception->getMessage());
            $safeErrors = [
                'This order is no longer pending.',
                'Customer wallet was not found; refund was not issued.',
                'Order status changed before this action completed.'
            ];
            $error = $exception instanceof RuntimeException && in_array($exception->getMessage(), $safeErrors, true)
                ? $exception->getMessage()
                : 'The order could not be updated. No changes were saved.';
        }
    }
}

$orders = $conn->query("SELECT * FROM (
    SELECT id, user_id, CONCAT(UPPER(type), ' - ', network, ' - ', phone_number) AS details, amount, status, created_at, 'mobile' AS kind
    FROM airtime_data_orders
    UNION ALL
    SELECT id, user_id, CONCAT('ELECTRICITY - Meter ', meter_number) AS details, amount, status, created_at, 'electricity' AS kind
    FROM electricity_orders
) AS service_orders WHERE status = 'pending' ORDER BY created_at ASC LIMIT 100");
?>
<?php include 'navbar.php'; ?>
<main class="admin-main flex-grow-1">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div><p class="text-uppercase small fw-bold text-secondary mb-1">Operations</p><h1 class="h3 mb-1">Service orders</h1><p class="text-secondary mb-0">Review each request, complete the service, or fail it to issue a one-time refund.</p></div>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to overview</a>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="card border-0 shadow-sm"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>Order</th><th>User</th><th>Request</th><th>Amount</th><th>Submitted</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?><tr><td colspan="6" class="text-center text-secondary py-5">No pending service orders.</td></tr><?php endif; ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int) $order['id'] ?></td><td><?= (int) $order['user_id'] ?></td>
                        <td><?= htmlspecialchars((string) $order['details'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="fw-semibold">₦<?= number_format((float) $order['amount'], 2) ?></td>
                        <td><?= htmlspecialchars((string) $order['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end text-nowrap">
                            <form method="post" class="d-inline" data-confirm="Mark this service as completed?">
                                <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="kind" value="<?= htmlspecialchars((string) $order['kind'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-success btn-sm" name="action" value="complete">Complete</button>
                            </form>
                            <form method="post" class="d-inline" data-confirm="Mark failed and refund ₦<?= number_format((float) $order['amount'], 2) ?> to the user?">
                                <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="kind" value="<?= htmlspecialchars((string) $order['kind'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-outline-danger btn-sm" name="action" value="fail">Fail + refund</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
