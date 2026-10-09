<?php
require_once '../includes/security.php';
require_once '../includes/db.php';
require_once '../includes/verification.php';

$userId = (int) ($_SESSION['pending_login_user_id'] ?? 0);
if ($userId < 1) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$email = (string) ($_SESSION['pending_login_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['resend_code'])) {
        $stmt = $conn->prepare('SELECT email, full_name FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $message = $user && issue_verification_code($conn, $userId, $user['email'], $user['full_name'], 'login')
            ? 'A new sign-in code was sent.'
            : '';
        if (!$message) {
            $error = 'The verification email could not be sent. Check your SMTP settings.';
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!verify_code($conn, $userId, $code, 'login')) {
            $error = 'The code is invalid, expired, or has reached its attempt limit.';
        } else {
            $token = create_trusted_device($conn, $userId);
            $secure = liteup_request_is_https();
            setcookie('liteup_trusted_device', $token, [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['full_name'] = $_SESSION['pending_login_name'] ?? '';
            $_SESSION['role'] = $_SESSION['pending_login_role'] ?? 'user';
            $role = $_SESSION['role'];
            unset($_SESSION['pending_login_user_id'], $_SESSION['pending_login_role'], $_SESSION['pending_login_name'], $_SESSION['pending_login_email']);
            header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : 'dashboard.php'));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Sign-in - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="../asset/css/liteup.css" rel="stylesheet">
</head>
<body class="auth-page d-flex flex-column min-vh-100">
<div class="text-center pt-4"><a class="auth-brand" href="index.php"><img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="52"></a></div>
<main class="container my-auto" style="max-width: 460px;">
    <div class="card auth-card border-0 p-4">
        <h2 class="h4 mb-2">Verify this sign-in</h2>
        <p class="text-secondary">Enter the 6-digit code sent to <?= htmlspecialchars($email) ?>. This device will be remembered for 30 days.</p>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="code" class="form-label">Verification code</label>
            <input type="text" id="code" name="code" class="form-control form-control-lg text-center mb-3" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn btn-primary w-100" type="submit">Verify sign-in</button>
        </form>
        <form method="post" class="mt-3">
            <?= csrf_field() ?>
            <button class="btn btn-link w-100" type="submit" name="resend_code" value="1">Send a new code</button>
        </form>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
