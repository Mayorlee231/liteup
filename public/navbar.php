<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$full_name = '';
$profile_pic = '';

if ($user_id) {
    $stmt = $conn->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($full_name, $profile_pic);
    $stmt->fetch();
    $stmt->close();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php" aria-label="LiteUp home">
            <img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="38">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="buy_electricity.php">Electricity</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="buy_airtime.php">Airtime</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="buy_data.php">Data</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="fund_wallet.php">Wallet</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="generate_tamper.php">Tamper Code</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="transactions.php">Transactions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">My Requests</a>
                </li>
            </ul>
            <?php if ($user_id): ?>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle text-info d-flex align-items-center gap-2 border-0 bg-transparent" type="button" id="navbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($profile_pic): ?>
                                <img src="media.php" alt="" class="rounded-circle" width="30" height="30" style="object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($full_name); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="logout.php" class="m-0">
                                    <?= csrf_field() ?>
                                    <button class="dropdown-item text-danger" type="submit">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-info">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
