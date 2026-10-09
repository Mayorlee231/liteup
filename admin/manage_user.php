<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();

$message = (string) ($_SESSION['admin_message'] ?? '');
$error = (string) ($_SESSION['admin_error'] ?? '');
unset($_SESSION['admin_message'], $_SESSION['admin_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id) {
        $conn->begin_transaction();
        try {
            $userStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'user' FOR UPDATE");
            $userStmt->bind_param('i', $id);
            $userStmt->execute();
            if (!$userStmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('User not found or cannot be deleted.');
            }

            $dependentTables = [
                'airtime_data_orders', 'airtime_orders', 'electricity_orders',
                'tamper_code', 'tamper_codes', 'tamper_code_requests',
                'transactions', 'trusted_devices', 'verification_codes',
                'wallet', 'wallets', 'wallet_funding_requests', 'wallet_transactions'
            ];
            foreach ($dependentTables as $table) {
                $deleteChild = $conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
                $deleteChild->bind_param('i', $id);
                $deleteChild->execute();
            }

            $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            $deleteUser->bind_param('i', $id);
            $deleteUser->execute();
            if ($deleteUser->affected_rows !== 1) {
                throw new RuntimeException('The user could not be deleted.');
            }
            $conn->commit();
            $_SESSION['admin_message'] = 'User and associated records deleted successfully.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $_SESSION['admin_error'] = 'User deletion failed. No records were changed.';
        }
    }
    header("Location: manage_user.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_id'])) {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'promote_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id) {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role = 'user'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header("Location: manage_user.php");
    exit();
}
?>

<?php include 'navbar.php'; ?>

<main class="admin-main flex-grow-1">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Manage Users</h3>
                <p class="text-secondary mb-0">Review user accounts and wallet balances.</p>
            </div>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Wallet</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $users = $conn->query("SELECT u.*, COALESCE(w.balance, 0) AS wallet_balance FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.role = 'user'"); ?>
                        <?php while ($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= htmlspecialchars((string) $row['full_name']) ?></td>
                                <td><?= htmlspecialchars((string) $row['email']) ?></td>
                                <td><?= htmlspecialchars((string) $row['phone']) ?></td>
                                <td>₦<?= number_format((float) $row['wallet_balance'], 2) ?></td>
                                <td>
                                    <form method="post" class="d-inline" data-confirm="Give this user administrator access?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="promote_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Make admin</button>
                                    </form>
                                    <form method="post" class="d-inline" data-confirm="Delete user?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
