<?php
require_once '../includes/security.php';
require_once '../includes/db.php';
require_once '../includes/verification.php';

$userId = (int) ($_SESSION['verification_user_id'] ?? 0);
if ($userId < 1) {
    header('Location: register.php');
    exit();
}

$message = '';
$error = '';
$verificationEmail = (string) ($_SESSION['verification_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['resend_code'])) {
        $stmt = $conn->prepare('SELECT email, full_name FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && issue_verification_code($conn, $userId, $user['email'], $user['full_name'], 'registration')) {
            $message = 'A new verification code was sent.';
        } else {
            $error = 'The verification email could not be sent. Check your SMTP settings.';
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!verify_code($conn, $userId, $code, 'registration')) {
            $error = 'The code is invalid, expired, or has reached its attempt limit.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            unset($_SESSION['verification_user_id'], $_SESSION['verification_email'], $_SESSION['verification_mail_error']);
            $_SESSION['success_message'] = 'Email verified. You can now sign in.';
            header('Location: login.php');
            exit();
        }
    }
}

if (!empty($_SESSION['verification_mail_error'])) {
    $error = 'The code could not be sent. Configure Gmail SMTP in .env, then request a new code.';
    unset($_SESSION['verification_mail_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="../asset/css/liteup.css" rel="stylesheet">
</head>
<body class="auth-page d-flex flex-column min-vh-100">
<div class="text-center pt-4"><a class="auth-brand" href="index.php"><img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="52"></a></div>
<main class="container my-auto" style="max-width: 460px;">
    <div class="card auth-card border-0 p-4">
        <h2 class="h4 mb-2">Verify your email</h2>
        <p class="text-secondary">Enter the 6-digit code sent to <?= htmlspecialchars($verificationEmail) ?>.</p>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="code" class="form-label">Verification code</label>
            <input type="text" id="code" name="code" class="form-control form-control-lg text-center mb-3" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
            <button class="btn btn-primary w-100" type="submit">Verify email</button>
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
