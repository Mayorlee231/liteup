<?php
require_once '../includes/auth.php';   // Auth check - user must be logged in
require_once '../includes/db.php';     // Database connection
require_once '../includes/order_service.php';

$user_id = $_SESSION['user_id'];
$orderKey = bin2hex(random_bytes(32));
$message = '';

// Fetch user's wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $rawAmount = trim((string) ($_POST['amount'] ?? ''));
    $amount = is_numeric($rawAmount) ? round((float) $rawAmount, 2) : 0;
    $network = (string) ($_POST['network'] ?? '');

    if (!preg_match('/^\+?[0-9]{10,15}$/', $phone) || !in_array($network, ['MTN', 'GLO', 'AIRTEL', '9MOBILE'], true) || $amount < 50 || $amount > 1000000) {
        $message = "Please fill in all fields.";
    } else {
        try {
            $submission = create_wallet_order($conn, (int) $user_id, 'airtime', $phone, $network, $amount, (string) ($_POST['order_key'] ?? ''));
            $message = $submission['created']
                ? "Request #{$submission['id']} submitted. ₦" . number_format($amount, 2) . " is reserved while it awaits fulfillment."
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
    <title>Buy Airtime - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">

<?php include 'navbar.php'; ?>

<main class="container flex-grow-1 mt-5">
    <h3>Buy Airtime</h3>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="mb-3">
        <strong>Wallet Balance:</strong> ₦<?= number_format($balance, 2) ?>
    </div>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="order_key" value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="network" class="form-label">Select Network</label>
            <select class="form-select" name="network" id="network" required>
                <option value="" disabled selected>Select network</option>
                <option value="MTN">MTN</option>
                <option value="GLO">GLO</option>
                <option value="AIRTEL">AIRTEL</option>
                <option value="9MOBILE">9MOBILE</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g. 08012345678" required pattern="\d{10,11}">
        </div>

        <div class="mb-3">
            <label for="amount" class="form-label">Amount (₦)</label>
            <input type="number" class="form-control" id="amount" name="amount" min="50" step="0.01" required>
            <div class="form-text">Minimum ₦50. Requests are fulfilled manually until a provider is connected.</div>
        </div>

        <button type="submit" class="btn btn-primary">Submit airtime request</button>
        <a href="dashboard.php" class="btn btn-secondary ms-2">Back to Dashboard</a>
    </form>
</main>

 <?php include '../includes/footer.php'; ?>
</body>
</html>
