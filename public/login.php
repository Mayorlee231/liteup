<?php
require_once '../includes/security.php';
require_once '../includes/db.php';
require_once '../includes/verification.php';

$message = (string) ($_SESSION['success_message'] ?? '');
unset($_SESSION['success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $emailOrPhone = trim((string) ($_POST['email_or_phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (strlen($emailOrPhone) > 254 || strlen($password) > 128 || consume_login_attempt($emailOrPhone)) {
        $message = 'Invalid login details. Please try again later.';
    } else {
        // Prepare statement to fetch user by email or phone
        $stmt = $conn->prepare("SELECT id, full_name, email, phone, password, role, email_verified_at FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                clear_login_failures($emailOrPhone);
                if (empty($user['email_verified_at'])) {
                    $emailSent = issue_verification_code($conn, (int) $user['id'], $user['email'], $user['full_name'], 'registration');
                    $_SESSION['verification_user_id'] = (int) $user['id'];
                    $_SESSION['verification_email'] = $user['email'];
                    $_SESSION['verification_mail_error'] = !$emailSent;
                    header('Location: verify_email.php');
                    exit();
                }

                $trustedToken = (string) ($_COOKIE['liteup_trusted_device'] ?? '');
                if (!trusted_device_is_valid($conn, (int) $user['id'], $trustedToken)) {
                    if (!issue_verification_code($conn, (int) $user['id'], $user['email'], $user['full_name'], 'login')) {
                        $message = 'Unable to send a verification code. Check the email configuration.';
                    } else {
                        $_SESSION['pending_login_user_id'] = (int) $user['id'];
                        $_SESSION['pending_login_role'] = $user['role'];
                        $_SESSION['pending_login_name'] = $user['full_name'];
                        $_SESSION['pending_login_email'] = $user['email'];
                        header('Location: verify_login.php');
                        exit();
                    }
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: ' . ($user['role'] === 'admin' ? '../admin/dashboard.php' : 'dashboard.php'));
                    exit();
                }
            } else {
                $message = 'Invalid login details.';
            }
        } else {
            $message = 'Invalid login details.';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - LiteUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-card {
            max-width: 400px;
            margin: 1.5rem auto;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
            border-radius: 8px;
        }
    </style>
    <link href="../asset/css/liteup.css" rel="stylesheet">
</head>
<body class="auth-page d-flex flex-column min-vh-100">

<main class="auth-main">
<div class="text-center pt-4 pb-3">
    <a class="auth-brand" href="index.php" aria-label="LiteUp home"><img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="52"></a>
</div>
<div class="login-card">
    <h3 class="mb-4 text-center">Login to LiteUp</h3>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="email_or_phone" class="form-label">Email or Phone</label>
            <input type="text" class="form-control" id="email_or_phone" name="email_or_phone" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <p class="mt-3 text-center">Don't have an account? <a href="register.php">Register here</a></p>
</div>
 </main>
 <?php include '../includes/footer.php'; ?>
</body>
</html>
