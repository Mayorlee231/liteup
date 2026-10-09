<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch all transactions for the user
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transaction History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        .type-label {
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>

<main class="container flex-grow-1 mt-5">
    <div class="card p-4">
        <h4 class="mb-4">Transaction History</h4>
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th>Amount (₦)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sn = 1;
                while ($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= (int) $sn++; ?></td>
                        <td class="type-label text-primary"><?= htmlspecialchars(ucfirst((string) $row['type']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $row['details']) ?></td>
                        <td><?= number_format($row['amount'], 2); ?></td>
                        <td><?= date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No transactions yet.</p>
        <?php endif; ?>
        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>

</body>
</html>

