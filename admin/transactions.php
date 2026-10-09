<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

// Fetch all transactions with user info
$sql = "SELECT t.id, t.user_id, t.type, t.details, t.amount, t.created_at, u.full_name, u.email
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Transactions - Admin - LiteUp</title>
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

<main class="admin-main flex-grow-1">
<div class="container py-5">
    <h3>All Transactions</h3>

    <?php if ($result->num_rows > 0): ?>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Email</th>
                <th>Type</th>
                <th>Details</th>
                <th>Amount (₦)</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= (int) $sn++; ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td class="type-label text-primary"><?= htmlspecialchars(ucfirst((string) $row['type']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['details']) ?></td>
                <td><?= number_format($row['amount'], 2) ?></td>
                <td><?= date("d M Y, h:i A", strtotime($row['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>No transactions found.</p>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary">Back to Admin Dashboard</a>
</div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
