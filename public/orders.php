<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM (
    SELECT id, user_id, CONCAT(UPPER(type), ' - ', network, ' - ', phone_number) AS details, amount, status, created_at, 'Mobile' AS category
    FROM airtime_data_orders
    UNION ALL
    SELECT id, user_id, CONCAT('Electricity - Meter ', meter_number) AS details, amount, status, created_at, 'Electricity' AS category
    FROM electricity_orders
) AS service_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My requests | LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<?php include 'navbar.php'; ?>
<main class="container py-5 flex-grow-1">
    <div class="d-flex justify-content-between align-items-end mb-4"><div><p class="text-uppercase small fw-bold text-secondary mb-1">Your activity</p><h1 class="h3 mb-1">Service requests</h1><p class="text-secondary mb-0">Pending requests are being reviewed. Failed requests are refunded to your wallet.</p></div><a href="dashboard.php" class="btn btn-outline-secondary">Dashboard</a></div>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Request</th><th>Service</th><th>Details</th><th>Status</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php if ($orders->num_rows === 0): ?><tr><td colspan="6" class="text-center text-secondary py-5">You have no service requests yet.</td></tr><?php endif; ?>
        <?php while ($order = $orders->fetch_assoc()): ?>
            <?php $badge = ['pending' => 'warning', 'completed' => 'success', 'failed' => 'danger'][$order['status']] ?? 'secondary'; ?>
            <tr><td>#<?= (int) $order['id'] ?></td><td><?= htmlspecialchars((string) $order['category'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $order['details'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst((string) $order['status']), ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars((string) $order['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end">₦<?= number_format((float) $order['amount'], 2) ?></td></tr>
        <?php endwhile; ?>
        </tbody>
    </table></div></div>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
