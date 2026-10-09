
<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/order_service.php';

// Your code continues here...


$user_id = $_SESSION['user_id'];
$orderKey = bin2hex(random_bytes(32));
$message = "";

// Get wallet balance
$wallet = 0;
$walletStmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ?');
$walletStmt->bind_param('i', $user_id);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
if ($walletResult && $walletResult->num_rows > 0) {
    $wallet = $walletResult->fetch_assoc()['balance'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $network = (string) ($_POST['network'] ?? '');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $volume = (string) ($_POST['volume'] ?? '');
    $prices = ['500MB' => 150, '1GB' => 300, '2GB' => 500, '5GB' => 1000];
    $amount = $prices[$volume] ?? 0;

    if (!in_array($network, ['MTN', 'Airtel', 'Glo', '9mobile'], true) || !preg_match('/^\+?[0-9]{10,15}$/', $phone) || !$amount) {
        $message = "Please provide valid purchase details.";
    } else {
        try {
            $submission = create_wallet_order($conn, (int) $user_id, 'data', $phone, $network . ' ' . $volume, (float) $amount, (string) ($_POST['order_key'] ?? ''));
            $message = $submission['created']
                ? "Request #{$submission['id']} submitted for $volume. ₦" . number_format((float) $amount, 2) . " is reserved while it awaits fulfillment."
                : "This form was already submitted as request #{$submission['id']}. Your wallet was not charged again.";
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException && $exception->getMessage() === 'Insufficient wallet balance.'
                ? $exception->getMessage()
                : 'Request could not be submitted. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Buy Data</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>

<main class="container flex-grow-1 mt-5">
    <div class="card p-4">
        <h4 class="mb-4">Buy Data</h4>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="order_key" value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="network" class="form-label">Select Network</label>
                <select name="network" class="form-control" required>
                    <option value="">-- Select --</option>
                    <option value="MTN">MTN</option>
                    <option value="Airtel">Airtel</option>
                    <option value="Glo">Glo</option>
                    <option value="9mobile">9mobile</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="volume" class="form-label">Data Volume</label>
                <select name="volume" id="volume" class="form-control" required>
                    <option value="">-- Select --</option>
                    <option value="500MB">500MB</option>
                    <option value="1GB">1GB</option>
                    <option value="2GB">2GB</option>
                    <option value="5GB">5GB</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="amount" class="form-label">Amount (₦)</label>
                <input type="number" name="amount" id="amount" class="form-control" readonly>
            </div>
            <p class="small text-secondary">Requests are fulfilled manually until a provider is connected. Failed requests are refunded.</p>
            <button type="submit" class="btn btn-primary">Buy Data</button>
        <a href="dashboard.php" class="btn btn-secondary ms-2">Back to Dashboard</a>
        </form>
        
    </div>
</main>
<script src="../asset/js/buy-data.js" defer></script>
 <?php include '../includes/footer.php'; ?>
</body>
</html>
