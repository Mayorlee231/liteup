<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$walletBalance = (float) ($stmt->get_result()->fetch_assoc()['balance'] ?? 0);

$recentStmt = $conn->prepare('SELECT type, details, amount, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$recentStmt->bind_param('i', $userId);
$recentStmt->execute();
$recentTransactions = $recentStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        :root { --ink: #102820; --leaf: #147d59; --mint: #d9f3e8; --paper: #f4f7f3; }
        body { background: var(--paper); color: var(--ink); }
        .dashboard-main { flex: 1; }
        .welcome-band { background: var(--ink); color: white; border-radius: 12px; padding: clamp(1.5rem, 4vw, 3rem); }
        .wallet-panel { background: var(--leaf); color: white; border-radius: 10px; min-height: 100%; }
        .service-link { display: block; height: 100%; padding: 1.25rem; color: var(--ink); text-decoration: none; background: white; border: 1px solid #dce6df; border-radius: 8px; transition: transform .18s ease, border-color .18s ease; }
        .service-link:hover { color: var(--ink); border-color: var(--leaf); transform: translateY(-3px); }
        .service-icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 10px; color: var(--leaf); background: var(--mint); font-size: 1.35rem; }
        .section-label { color: #5d7166; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
        .balance-amount { font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 700; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration: .01ms !important; } }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>
<main class="dashboard-main">
    <div class="container py-4 py-lg-5">
        <section class="welcome-band mb-4 mb-lg-5" aria-labelledby="welcome-title">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <p class="section-label text-white-50 mb-2">Your LiteUp account</p>
                    <h1 id="welcome-title" class="display-6 fw-bold">Power your day, <?= htmlspecialchars(explode(' ', trim((string) $_SESSION['full_name']))[0] ?: 'there', ENT_QUOTES, 'UTF-8') ?>.</h1>
                    <p class="mb-0 text-white-50">Manage your wallet and everyday utility requests from one place.</p>
                </div>
                <div class="col-lg-5">
                    <div class="wallet-panel p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><div class="text-white-50 small">Available wallet balance</div><div id="balanceAmount" class="balance-amount">₦<?= number_format($walletBalance, 2) ?></div></div>
                            <button id="toggleBalance" class="btn btn-sm btn-outline-light" type="button" aria-label="Hide wallet balance" title="Hide wallet balance">Hide</button>
                        </div>
                        <a href="fund_wallet.php" class="btn btn-light mt-3">Add funds</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5" aria-labelledby="services-title">
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div><p class="section-label mb-1">Services</p><h2 id="services-title" class="h3 mb-0">What do you need today?</h2></div>
                <a href="orders.php" class="link-success fw-semibold">Track requests</a>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3"><a class="service-link" href="buy_electricity.php"><span class="service-icon" aria-hidden="true">⚡</span><h3 class="h5 mt-3">Electricity</h3><p class="text-secondary small mb-0">Create a meter payment request.</p></a></div>
                <div class="col-sm-6 col-xl-3"><a class="service-link" href="buy_airtime.php"><span class="service-icon" aria-hidden="true">◉</span><h3 class="h5 mt-3">Airtime</h3><p class="text-secondary small mb-0">Request a mobile top-up.</p></a></div>
                <div class="col-sm-6 col-xl-3"><a class="service-link" href="buy_data.php"><span class="service-icon" aria-hidden="true">▤</span><h3 class="h5 mt-3">Mobile data</h3><p class="text-secondary small mb-0">Choose a supported network bundle.</p></a></div>
                <div class="col-sm-6 col-xl-3"><a class="service-link" href="tamper_history.php"><span class="service-icon" aria-hidden="true">⌁</span><h3 class="h5 mt-3">Tamper requests</h3><p class="text-secondary small mb-0">Review your request status.</p></a></div>
            </div>
            <p class="small text-secondary mt-3 mb-0">Cable TV is not available until a provider is connected. Service requests are manually fulfilled by operations.</p>
        </section>

        <section aria-labelledby="activity-title">
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div><p class="section-label mb-1">Recent activity</p><h2 id="activity-title" class="h3 mb-0">Latest wallet transactions</h2></div>
                <a href="transactions.php" class="link-success fw-semibold">All transactions</a>
            </div>
            <div class="bg-white border rounded-3 overflow-hidden">
                <?php if ($recentTransactions->num_rows === 0): ?>
                    <p class="text-secondary p-4 mb-0">No transactions yet.</p>
                <?php else: ?>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Type</th><th>Details</th><th>Date</th><th class="text-end">Amount</th></tr></thead><tbody>
                    <?php while ($transaction = $recentTransactions->fetch_assoc()): ?>
                        <tr><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $transaction['type'])), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $transaction['details'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $transaction['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end">₦<?= number_format((float) $transaction['amount'], 2) ?></td></tr>
                    <?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script src="../asset/js/dashboard.js" defer></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
