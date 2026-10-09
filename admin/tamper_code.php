<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../public/dashboard.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();
    $actionError = '';
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'declined';
        $stmt = $conn->prepare("UPDATE tamper_code_requests SET status = ? WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('si', $status, $id);
        try {
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Tamper request moderation failed: ' . $exception->getMessage());
            $actionError = 'The request could not be updated. Please try again.';
        }
    }
}
?>

<?php include 'navbar.php'; ?>

<main class="admin-main flex-grow-1">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Tamper Code Requests</h3>
            <p class="text-secondary mb-0">Approve or decline pending tamper-code requests.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
    </div>
    <?php if (!empty($actionError)): ?><div class="alert alert-danger"><?= htmlspecialchars($actionError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="card border-0 shadow-sm">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Meter Number</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $requests = $conn->query("SELECT * FROM tamper_code_requests WHERE status = 'pending' ORDER BY created_at ASC");
            if ($requests->num_rows === 0):
            ?>
                <tr><td colspan="5" class="text-center text-secondary py-5">No pending tamper-code requests.</td></tr>
            <?php endif;
            while ($row = $requests->fetch_assoc()) {
                ?>
                <tr>
                    <td><?= (int) $row['user_id'] ?></td>
                    <td><?= htmlspecialchars((string) $row['meter_number']) ?></td>
                    <td><span class="badge text-bg-warning">Pending</span></td>
                    <td><?= htmlspecialchars((string) $row['created_at']) ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm">Decline</button>
                        </form>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
</main>

<?php include '../includes/footer.php'; ?>
