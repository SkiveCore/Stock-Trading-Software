<?php
session_start();
require 'includes/db_connect.php';
require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt_select = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_select->bind_param("s", $email);
        $stmt_select->execute();
        $stmt_select->bind_result($user_id);
        $stmt_select->fetch();
        $stmt_select->close();
        if ($user_id) {
            $token = bin2hex(random_bytes(16));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
            $stmt_replace = $conn->prepare("REPLACE INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt_replace->bind_param("iss", $user_id, $token, $expiry);
            $stmt_replace->execute();
            $stmt_replace->close();
            $mail = new PHPMailer(true);
            try {
                $reset_link = "https://znctech.org/reset_password.php?token=$token";
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'donotreply@znctech.org';
                $mail->Password = 'rpnz cihj elzv ebmx';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
                $mail->setFrom('donotreply@znctech.org', 'ZNCTech');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'ZNCTech Password Reset';
                $email_body = file_get_contents('includes/reset_email_template.html');
                $email_body = str_replace('{{reset_link}}', htmlspecialchars($reset_link), $email_body);
                $mail->Body = $email_body;
                $mail->send();
                $success = 'A password reset link has been sent to your email.';
            } catch (Exception $e) {
                $errors[] = 'Unable to send reset email. Please try again later.';
            }
        } else {
            $errors[] = 'No account found with that email address.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>Forgot Password - ZNCTech</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="container register-form">
        <h2 class="center-align">Forgot Password</h2>
        <?php
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
            }
        }
        if (!empty($success)) {
            echo '<p class="success-message">' . htmlspecialchars($success) . '</p>';
        }
        ?>
        <form action="forgot_password.php" method="POST" class="form-grid">
            <md-outlined-text-field label="Email Address" type="email" name="email" required></md-outlined-text-field>
            <md-filled-button class="full-width" type="submit">Send Reset Link</md-filled-button>
        </form>
    </div>
    <?php include "includes/footer.php"; ?>
</body>
</html>
