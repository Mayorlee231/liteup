<?php
require_once '../includes/auth.php';
require_once "../includes/db.php";
require_once '../includes/order_service.php';

$user_id = $_SESSION['user_id'];
$orderKey = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['order_key'] ?? ''))
    : bin2hex(random_bytes(32));

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!preg_match('/^[a-f0-9]{64}$/', $orderKey)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
    try {
        $result = create_tamper_code_order($conn, (int) $user_id, $orderKey);
        $message = $result['created']
            ? 'Tamper code generated: ' . $result['code'] . '. ₦5,000 was deducted.'
            : 'This form was already submitted. Your existing code is ' . $result['code'] . '; you were not charged again.';
    } catch (Throwable $exception) {
        error_log('Tamper code generation failed: ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException && $exception->getMessage() === 'Insufficient wallet balance.'
            ? 'You need at least ₦5,000 in your wallet to generate a code.'
            : 'The request could not be completed. No funds were deducted.';
    }
    }
}

// Fetch wallet balance for display
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

// Fetch all tamper codes for user
$stmt = $conn->prepare("SELECT code, status, created_at FROM tamper_codes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$all_tamper_codes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Generate Tamper Code - Liteup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>

<div class="container flex-grow-1 mt-5" style="max-width: 700px;">
    <button class="btn btn-secondary mb-3" type="button" data-history-back>← Back</button>
    <h1>Generate Tamper Code</h1>
    <p>Wallet Balance: <strong>₦<?=number_format($balance ?? 0, 2)?></strong></p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" data-confirm="Are you sure you want to generate a tamper code and pay ₦5,000?">
        <?= csrf_field() ?>
        <input type="hidden" name="order_key" value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-primary mb-4">Generate Tamper Code (₦5000)</button>
    </form>

    <h3>Your Tamper Code History</h3>

    <?php if (empty($all_tamper_codes)): ?>
        <p>No tamper codes generated yet.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Requested At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_tamper_codes as $tc): ?>
                <tr>
                    <td><?=htmlspecialchars($tc['code'])?></td>
                    <td class="text-capitalize"><?=htmlspecialchars($tc['status'])?></td>
                    <td><?=date('Y-m-d H:i', strtotime($tc['created_at']))?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

 <?php include '../includes/footer.php'; ?></body>
</html>
