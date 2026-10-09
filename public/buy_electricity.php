<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/order_service.php';



$user_id = $_SESSION['user_id'];
$orderKey = bin2hex(random_bytes(32));
$message = '';
$error = '';

// Fetch user wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$balance = 0;
if ($row = $result->fetch_assoc()) {
    $balance = $row['balance'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $meter_number = trim((string) ($_POST['meter_number'] ?? ''));
    $rawAmount = trim((string) ($_POST['amount'] ?? ''));
    $amount = is_numeric($rawAmount) ? round((float) $rawAmount, 2) : 0;

    if (!preg_match('/^[0-9]{6,20}$/', $meter_number) || $amount <= 0 || $amount > 1000000) {
        $error = "Please enter a valid amount.";
    } else {
        try {
            $submission = create_wallet_order($conn, (int) $user_id, 'electricity', $meter_number, '', $amount, (string) ($_POST['order_key'] ?? ''));
            $message = $submission['created']
                ? "Request #{$submission['id']} submitted. ₦" . number_format($amount, 2) . " is reserved while it awaits fulfillment."
                : "This form was already submitted as request #{$submission['id']}. Your wallet was not charged again.";
            if ($submission['created']) {
                $balance -= $amount;
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException && $exception->getMessage() === 'Insufficient wallet balance.'
                ? $exception->getMessage()
                : 'Request could not be submitted. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Buy Electricity - LiteUp</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"></head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<main class="container my-5" style="max-width: 500px;">
    <h3>Buy Electricity</h3>

    <p><strong>Wallet Balance:</strong> ₦<?= number_format($balance, 2) ?></p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="order_key" value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="meter_number" class="form-label">Meter Number</label>
            <input type="text" name="meter_number" id="meter_number" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount (₦)</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" min="1" required>
        </div>
        <p class="small text-secondary">Requests are fulfilled manually until a provider is connected. Failed requests are refunded.</p>
        <button type="submit" class="btn btn-primary">Submit electricity request</button>
        <a href="dashboard.php" class="btn btn-secondary ms-2">Back to Dashboard</a>
    </form>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
