<?php
require_once "../includes/security.php";
require_once "../includes/db.php";
require_once "../includes/verification.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (consume_login_attempt('registration')) {
        http_response_code(429);
        $errors[] = 'Too many registration attempts. Please wait before trying again.';
    }
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[\p{L}\p{M} .\'-]{2,100}$/u', $full_name)) $errors[] = "Enter a valid full name.";
    if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid email address up to 150 characters.";
    if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) $errors[] = "Enter a valid phone number.";
    $passwordError = validate_password($password);
    if ($passwordError) $errors[] = $passwordError;
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Unable to create an account with those details. Try signing in or use different details.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $phone, $password_hash);
            $stmt->execute();
            $user_id = (int) $stmt->insert_id;

            $walletStmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
            $walletStmt->bind_param("i", $user_id);
            $walletStmt->execute();
            $conn->commit();

            $emailSent = issue_verification_code($conn, $user_id, $email, $full_name, 'registration');
            $_SESSION['verification_user_id'] = $user_id;
            $_SESSION['verification_email'] = $email;
            $_SESSION['verification_mail_error'] = !$emailSent;
            header("Location: verify_email.php");
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('LiteUp registration failed: ' . $exception->getMessage());
            $errors[] = "Registration failed. Check the details and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Liteup - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        body {
            background: #f4f7f3;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .register-container {
            max-width: 420px;
            margin: auto;
            background: #fff;
            padding: 30px 25px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            margin-top: 60px;
            margin-bottom: 40px;
        }
        footer {
            background: #222;
            color: #bbb;
            text-align: center;
            padding: 15px 0;
            margin-top: auto;
        }
        .form-label {
            font-weight: 600;
            color: #444;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
    <link href="../asset/css/liteup.css" rel="stylesheet">
</head>
<body class="auth-page d-flex flex-column min-vh-100">

<div class="text-center pt-4">
    <a class="auth-brand" href="index.php" aria-label="LiteUp home"><img src="../asset/images/liteup-logo.svg" alt="LiteUp" height="52"></a>
</div>
<div class="register-container shadow-sm">
    <h2 class="mb-4 text-center text-primary">Create Account</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?=htmlspecialchars($e)?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?=isset($full_name) ? htmlspecialchars($full_name) : ''?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?=isset($email) ? htmlspecialchars($email) : ''?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?=isset($phone) ? htmlspecialchars($phone) : ''?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
            <div class="form-text">Use at least 8 characters, including uppercase, lowercase, a number, and a special character.</div>
        </div>
        <div class="mb-4">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100 shadow-sm">Register</button>
    </form>

    <p class="text-center mt-3 mb-0">Already have an account? <a href="login.php">Login here</a></p>
</div>

 <?php include '../includes/footer.php'; ?>
</body>
</html>
