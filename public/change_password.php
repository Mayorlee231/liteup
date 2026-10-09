<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    $passwordError = validate_password($new_password);
    if ($passwordError) {
        $error = $passwordError;
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Get current password hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($db_password);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current_password, $db_password)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user_id);

            if ($update->execute()) {
                $success = "Password changed successfully!";
                $deleteDevices = $conn->prepare('DELETE FROM trusted_devices WHERE user_id = ?');
                $deleteDevices->bind_param('i', $user_id);
                $deleteDevices->execute();
                session_regenerate_id(true);
            } else {
                $error = "Error updating password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="d-flex flex-column min-vh-100">

<?php include 'navbar.php'; ?>

<main class="container flex-grow-1 mt-5">
    <div class="card p-4">
        <h4>Change Password</h4>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
                <div class="form-text">Use at least 8 characters, including uppercase, lowercase, a number, and a special character.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
            <a href="profile.php" class="btn btn-secondary ms-2">Back to Profile</a>
        </form>
    </div>
</main>
 <?php include '../includes/footer.php'; ?>
</body>
</html>
