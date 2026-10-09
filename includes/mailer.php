<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/env.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function send_verification_code(string $email, string $name, string $code, string $purpose): bool
{
    $env = liteup_env();
    $mailUsername = $env['MAIL_USERNAME'] ?? $env['MAIL_EMAIL'] ?? $env['USER_EMAIL'] ?? '';
    $mailPassword = $env['MAIL_PASSWORD'] ?? $env['USER_PASSWORD'] ?? '';
    if (empty($env['MAIL_HOST']) || empty($mailUsername) || empty($mailPassword) || empty($env['MAIL_FROM_ADDRESS'])) {
        error_log('LiteUp email verification is not configured.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $env['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = str_replace(' ', '', $mailPassword);
        $mail->Port = (int) ($env['MAIL_PORT'] ?? 587);
        $mail->SMTPSecure = strtolower((string) ($env['MAIL_ENCRYPTION'] ?? 'tls')) === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($env['MAIL_FROM_ADDRESS'], $env['MAIL_FROM_NAME'] ?? 'LiteUp');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = $purpose === 'registration' ? 'Verify your LiteUp account' : 'Your LiteUp sign-in code';
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<p>Your LiteUp verification code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . $safeCode . '</p><p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>';
        $mail->AltBody = "Your LiteUp verification code is {$code}. It expires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('LiteUp verification email failed: ' . $exception->getMessage());
        return false;
    }
}
