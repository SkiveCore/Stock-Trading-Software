<?php
session_start();
require 'includes/db_connect.php';
require_once __DIR__ . '/vendor/phpgangsta/googleauthenticator/PHPGangsta/GoogleAuthenticator.php';

$gAuth = new PHPGangsta_GoogleAuthenticator();
$errors = [];
$email = '';
define('RECAPTCHA_SITE_KEY', '6Ldb1m8qAAAAAAf_ix7ImWElgfHf9JqGA_FiCMzZ');
define('RECAPTCHA_SECRET_KEY', '6Ldb1m8qAAAAAGnl3qjrWjuAVBPnUpMvg53BO3WD');
function generateCaptcha() {
    $num1 = rand(1, 9);
    $num2 = rand(1, 9);
    $_SESSION['fallback_captcha_answer'] = $num1 + $num2;
    return "What is $num1 + $num2?";
}
function verifyCaptcha($captchaResponse) {
    if (isset($_POST['fallback_captcha'])) {
        return $_POST['fallback_captcha'] == $_SESSION['fallback_captcha_answer'];
    } else {
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . RECAPTCHA_SECRET_KEY . "&response=" . $captchaResponse);
        $responseKeys = json_decode($response, true);
        return $responseKeys['success'] ?? false;
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $captchaResponse = $_POST['g-recaptcha-response'] ?? '';
    
    if (!$captchaResponse && !isset($_POST['fallback_captcha'])) {
        $errors[] = "CAPTCHA validation failed. Please try again.";
    } elseif (!verifyCaptcha($captchaResponse)) {
        $errors[] = "CAPTCHA validation failed. Please try again.";
    }

    if (empty($errors) && isset($_SESSION['two_factor_pending'], $_POST['otp_code']) && $_SESSION['two_factor_pending']) {
        $otpCode = $_POST['otp_code'];
        $isCodeValid = $gAuth->verifyCode($_SESSION['two_fa_secret'], $otpCode, 2);

        if ($isCodeValid) {
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['email'] = $_SESSION['temp_email'];
            $_SESSION['first_name'] = $_SESSION['temp_first_name'];
            $_SESSION['last_name'] = $_SESSION['temp_last_name'];
            $_SESSION['is_admin'] = $_SESSION['temp_is_admin'];
            unset($_SESSION['two_factor_pending'], $_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['temp_first_name'], $_SESSION['temp_last_name'], $_SESSION['two_fa_secret'], $_SESSION['temp_is_admin']);
            
            header('Location: index.php');
            exit();
        } else {
            $errors[] = 'Invalid 2FA code, please try again.';
        }
    } elseif (empty($errors)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($password)) {
            $errors[] = 'Password is required.';
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("SELECT id, first_name, last_name, password_hash, is_verified, two_factor_enabled, two_fa_secret, is_admin FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id, $first_name, $last_name, $password_hash, $is_verified, $two_factor_enabled, $two_fa_secret, $is_admin);
                    $stmt->fetch();

                    if (!$is_verified) {
                        $errors[] = 'Your account is not verified. Please check your email for the verification link.';
                    } elseif (password_verify($password, $password_hash)) {
                        if ($two_factor_enabled) {
                            $_SESSION['temp_user_id'] = $user_id;
                            $_SESSION['temp_email'] = $email;
                            $_SESSION['temp_first_name'] = $first_name;
                            $_SESSION['temp_last_name'] = $last_name;
                            $_SESSION['two_factor_pending'] = true;
                            $_SESSION['two_fa_secret'] = $two_fa_secret;
                            $_SESSION['temp_is_admin'] = $is_admin;
                        } else {
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['email'] = $email;
                            $_SESSION['first_name'] = $first_name;
                            $_SESSION['last_name'] = $last_name;
                            $_SESSION['is_admin'] = $is_admin;
                            header('Location: index.php');
                            exit();
                        }
                    } else {
                        $errors[] = 'Incorrect email or password.';
                    }
                } else {
                    $errors[] = 'Incorrect email or password.';
                }

                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log('Database error: ' . $e->getMessage());
                $errors[] = 'An error occurred. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
</head>
<body>
    <?php include "includes/header.php"; ?>
    <div class="container register-form">
        <h2 class="center-align">Login to ZNCTech</h2>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error-message"><?= htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['two_factor_pending']) && $_SESSION['two_factor_pending']): ?>
            <form action="login.php" method="POST" class="form-grid">
                <md-outlined-text-field label="Enter 2FA code from app" type="text" name="otp_code" class="full-width" required></md-outlined-text-field>
                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                <md-filled-button id="login-button" class="full-width" type="submit">Verify 2FA</md-filled-button>
            </form>
		    <noscript>
				<style>.form-grid { display: none; }</style>
				<div class="noscript-container">
					<p class="noscript-message">JavaScript Disabled: Please enter your 2FA code below to verify your login.</p>
					<form action="login.php" method="POST">
						<label for="otp_code">Enter 2FA Code:</label>
						<input type="text" name="otp_code" id="otp_code" required>
						<label for="fallback_captcha_answer"><?= generateCaptcha(); ?></label>
						<input type="number" name="fallback_captcha" id="fallback_captcha_answer" required>
						<button type="submit">Verify 2FA</button>
					</form>
				</div>
			</noscript>
        <?php else: ?>
            <form action="login.php" method="POST" class="form-grid">
                <md-outlined-text-field label="Email Address" type="email" name="email" id="email" class="full-width" required value="<?= htmlspecialchars($email); ?>"></md-outlined-text-field>
                <md-outlined-text-field label="Password" type="password" name="password" id="password" class="full-width" required>
                    <md-icon-button toggle slot="trailing-icon" type="button" onclick="togglePasswordVisibility('password')">
                        <md-icon>visibility</md-icon>
                        <md-icon slot="selected">visibility_off</md-icon>
                    </md-icon-button>
                </md-outlined-text-field>
                <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
                <md-filled-button id="login-button" class="full-width" type="submit">Login</md-filled-button>
            </form>

		
			<noscript>
				<style>.form-grid { display: none; }</style>
				<div class="noscript-container">
					<p class="noscript-message">JavaScript Disabled: Please solve the following question for security:</p>
					<form action="login.php" method="POST">
						<label for="email">Email Address:</label>
						<input type="email" name="email" id="email" required>
						<label for="password">Password:</label>
						<input type="password" name="password" id="password" required>
						<label for="fallback_captcha_answer"><?= generateCaptcha(); ?></label>
						<input type="number" name="fallback_captcha" id="fallback_captcha_answer" required>
						<button type="submit">Login</button>
					</form>
				</div>
			</noscript>
        <?php endif; ?>

        <p class="center-align">Don't have an account? <a href="register.php">Register here</a></p>
        <p class="center-align"><a href="forgot_password.php">Forgot Password?</a></p>
    </div>
    <?php include "includes/footer.php"; ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY; ?>"></script>
    <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('<?= RECAPTCHA_SITE_KEY; ?>', {action: 'login'}).then(function(token) {
                document.getElementById('g-recaptcha-response').value = token;
            });
        });

        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
