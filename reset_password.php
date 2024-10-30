<?php
session_start();
require 'includes/db_connect.php';

$token = $_GET['token'] ?? '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $errors[] = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        $stmt_select = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt_select->bind_param("s", $token);
        $stmt_select->execute();
        $stmt_select->bind_result($user_id);
        $stmt_select->fetch();
        $stmt_select->close();

        if ($user_id) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt_update->bind_param("si", $password_hash, $user_id);
            $stmt_update->execute();
            $stmt_update->close();
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt_delete->bind_param("s", $token);
            $stmt_delete->execute();
            $stmt_delete->close();

            $success = 'Password reset successful. You can now <a href="login.php">login</a>.';
        } else {
            $errors[] = 'Invalid or expired token.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>Reset Password - ZNCTech</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="container register-form">
        <h2 class="center-align">Reset Password</h2>
        <?php
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
            }
        }
        if (!empty($success)) {
            echo '<p class="success-message">' . $success . '</p>';
        }
        ?>
        <form action="reset_password.php" method="POST" class="form-grid">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <md-outlined-text-field label="New Password" type="password" name="password" required></md-outlined-text-field>
            <md-outlined-text-field label="Confirm Password" type="password" name="confirm_password" required></md-outlined-text-field>
            <md-filled-button class="full-width" type="submit">Reset Password</md-filled-button>
        </form>
		<noscript>
            <style>.form-grid { display: none; }</style>
            <div class="noscript-container">
                <p class="noscript-message">JavaScript Disabled: Please enter your new password below.</p>
                <form action="reset_password.php" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <label for="password">New Password:</label>
                    <input type="password" name="password" id="password" required>
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <button type="submit">Reset Password</button>
                </form>
            </div>
        </noscript>
    </div>
    <?php include "includes/footer.php"; ?>
</body>
</html>
