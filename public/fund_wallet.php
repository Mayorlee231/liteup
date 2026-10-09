<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/cloud_storage.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$message = '';
$maxProofBytes = liteup_is_vercel_request() ? 4 * 1024 * 1024 : 5 * 1024 * 1024;
$maxProofMb = (int) ($maxProofBytes / 1024 / 1024);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rawAmount = trim((string) ($_POST['amount'] ?? ''));
    $amount = is_numeric($rawAmount) ? round((float) $rawAmount, 2) : 0;
    $proof = '';

    if ($amount <= 0 || $amount > 1000000 || !preg_match('/^\d+(\.\d{1,2})?$/', $rawAmount)) {
        $message = 'Enter a valid amount between NGN 1 and NGN 1,000,000.';
    } elseif (isset($_FILES['proof']) && $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE) {
        $proofFile = $_FILES['proof'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if ($proofFile['error'] !== UPLOAD_ERR_OK || $proofFile['size'] > $maxProofBytes || !is_uploaded_file($proofFile['tmp_name'])) {
            $message = 'Proof must be a valid JPG, PNG, WebP, or PDF file up to ' . $maxProofMb . ' MB.';
        } else {
            $mimeInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($mimeInfo, $proofFile['tmp_name']);
            finfo_close($mimeInfo);
            $imageInfo = str_starts_with($mimeType, 'image/') ? @getimagesize($proofFile['tmp_name']) : true;
            if (!isset($allowedTypes[$mimeType]) || !$imageInfo) {
                $message = 'Proof must be a valid JPG, PNG, WebP, or PDF file up to ' . $maxProofMb . ' MB.';
            } else {
                $objectKey = 'proofs/' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                $storedProof = liteup_store_uploaded_file($proofFile['tmp_name'], $objectKey, $mimeType);
                if ($storedProof === false) {
                    $message = 'Proof storage is unavailable. Please try again later.';
                } else {
                    $proof = $storedProof;
                }
            }
        }
    }

    if (!$message) {
        $stmt = $conn->prepare("INSERT INTO wallet_funding_requests (user_id, amount, proof, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("ids", $user_id, $amount, $proof);
        if ($stmt->execute()) {
            $message = "Funding request submitted successfully. Await admin approval.";
        } else {
            $message = "Error submitting funding request.";
            if ($proof) {
                liteup_delete_stored_file($proof);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fund Wallet - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<main class="container my-5">
    <h3>Fund Your Wallet</h3>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mt-4" style="max-width: 400px;">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount (NGN)</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" min="1" required>
        </div>
        <div class="mb-3">
            <label for="proof" class="form-label">Payment Proof <span class="text-secondary">(optional)</span></label>
            <input type="file" name="proof" id="proof" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
            <div class="form-text">JPG, PNG, WebP, or PDF. Maximum size: <?= $maxProofMb ?> MB.</div>
        </div>
        <button type="submit" class="btn btn-primary">Submit Funding Request</button>
        <a href="dashboard.php" class="btn btn-secondary ms-2">Back to Dashboard</a>
    </form>
</main>

<?php include '../includes/footer.php'; ?>

</body>
</html>
