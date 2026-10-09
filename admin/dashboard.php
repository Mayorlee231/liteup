<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if user is admin, redirect if not
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

// Fetch counts
$userCount = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$pendingFundingCount = $conn->query("SELECT COUNT(*) FROM wallet_funding_requests WHERE status = 'pending'")->fetch_row()[0];
$pendingServiceCount = (int) $conn->query("SELECT (SELECT COUNT(*) FROM airtime_data_orders WHERE status = 'pending') + (SELECT COUNT(*) FROM electricity_orders WHERE status = 'pending')")->fetch_row()[0];
$tamperCodeCount = $conn->query("SELECT COUNT(*) FROM tamper_code_requests WHERE status = 'pending'")->fetch_row()[0];
$transactionCount = $conn->query("SELECT COUNT(*) FROM transactions")->fetch_row()[0];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">
<?php include 'navbar.php'; ?>

<main class="admin-main flex-grow-1">
<div class="container py-5">
    <h3>Admin Dashboard</h3>
    <div class="row text-center">
        <div class="col-md-3 mb-3">
            <div class="card p-3 bg-primary text-white">
                <h5>Users</h5>
                <h2><?= (int) $userCount ?></h2>
                <a href="manage_user.php" class="btn btn-light btn-sm mt-2">Manage Users</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card p-3 bg-warning text-dark">
                <h5>Pending Wallet Funding</h5>
                <h2><?= (int) $pendingFundingCount ?></h2>
                <a href="wallet_funding.php" class="btn btn-dark btn-sm mt-2">View Requests</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card p-3 text-white" style="background:#147d59;">
                <h5>Pending Service Orders</h5>
                <h2><?= (int) $pendingServiceCount ?></h2>
                <a href="service_orders.php" class="btn btn-light btn-sm mt-2">Review orders</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card p-3 bg-success text-white">
                <h5>Pending Tamper Requests</h5>
                <h2><?= (int) $tamperCodeCount ?></h2>
                <a href="tamper_code.php" class="btn btn-light btn-sm mt-2">Manage Codes</a>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card p-3 bg-info text-white">
                <h5>Transactions</h5>
                <h2><?= (int) $transactionCount ?></h2>
                <a href="transactions.php" class="btn btn-light btn-sm mt-2">View Transactions</a>
            </div>
        </div>
    </div>
</div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
