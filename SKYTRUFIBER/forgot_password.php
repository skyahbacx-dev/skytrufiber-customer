<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = trim($_POST['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT full_name, account_number FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found in our records.']);
        exit;
    }

    $smtpUser = getenv('SMTP_USERNAME') ?: '';
    $smtpPass = getenv('SMTP_PASSWORD') ?: '';
    if (!$smtpUser || !$smtpPass) {
        throw new RuntimeException('SMTP is not configured.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
    $mail->setFrom($smtpUser, 'SkyTruFiber Support');
    $mail->addAddress($email, $user['full_name'] ?? '');
    $mail->isHTML(true);
    $mail->Subject = 'Your SkyTruFiber account login';
    $account = htmlspecialchars((string)$user['account_number'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars((string)$user['full_name'], ENT_QUOTES, 'UTF-8');
    $mail->Body = "<p>Hello <b>{$name}</b>,</p><p>Your account number (current login password) is: <strong>{$account}</strong></p><p>For your security, please contact support to change this legacy login method.</p><p>SkyTruFiber Support</p>";
    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Email sent! Please check your inbox.']);
} catch (Throwable $e) {
    error_log('Password assistance email failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Unable to send email right now. Please try again later.']);
}
