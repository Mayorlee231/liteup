<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<style>
    .admin-main { min-height: calc(100vh - 72px); background: #f4f7fb; }
    .admin-nav .navbar-brand img { width: 130px; height: auto; }
    .admin-nav .nav-link { color: rgba(255,255,255,.82); }
    .admin-nav .nav-link:hover, .admin-nav .nav-link.active { color: #fff; }
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm admin-nav">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php" aria-label="LiteUp admin dashboard">
            <img src="../asset/images/liteup-logo.svg" alt="LiteUp">
        </a>
        <span class="badge text-bg-info me-3">Admin</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false" aria-label="Open admin navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavigation">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Overview</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_user.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="wallet_funding.php">Funding</a></li>
                <li class="nav-item"><a class="nav-link" href="service_orders.php">Service orders</a></li>
                <li class="nav-item"><a class="nav-link" href="tamper_code.php">Tamper Codes</a></li>
                <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
            </ul>
            <form method="post" action="../public/logout.php" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light btn-sm" type="submit">Logout</button>
            </form>
        </div>
    </div>
</nav>
