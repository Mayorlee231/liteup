<?php
require_once __DIR__ . '/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

function require_admin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../public/dashboard.php');
        exit();
    }
}
?>
