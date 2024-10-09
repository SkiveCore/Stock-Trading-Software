<?php
session_start();
require 'includes/db_connect.php';
require_once __DIR__ . '/vendor/phpgangsta/googleauthenticator/PHPGangsta/GoogleAuthenticator.php'; // Include the 2FA library

$gAuth = new PHPGangsta_GoogleAuthenticator();
$email = '';
$errors = [];

// Step 1: Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Step 2: Check if OTP code submission is being handled
    if (isset($_SESSION['two_factor_pending']) && $_SESSION['two_factor_pending'] && isset($_POST['otp_code'])) {
        $otp_code = $_POST['otp_code'];
        $secret = $_SESSION['two_fa_secret'];
        
        // Step 3: Verify the OTP code
        $isCodeValid = $gAuth->verifyCode($secret, $otp_code, 2); // 2 = time window tolerance
        
        if ($isCodeValid) {
            // OTP is correct, now log in the user
            // Set all session variables, including first_name and last_name
            $_SESSION['user_id'] = $_SESSION['temp_user_id']; 
            $_SESSION['email'] = $_SESSION['temp_email']; 
            $_SESSION['first_name'] = $_SESSION['temp_first_name']; 
            $_SESSION['last_name'] = $_SESSION['temp_last_name'];
            
            // Clear the 2FA session data
            unset($_SESSION['two_factor_pending']);
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_first_name']);
            unset($_SESSION['temp_last_name']);
            unset($_SESSION['two_fa_secret']);
            
            header('Location: index.php');  // Redirect to index page
            exit();
        } else {
            $errors[] = 'Invalid 2FA code, please try again.';
        }
    } else {
        // Step 4: Handle email/password login submission
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
                $stmt = $conn->prepare("SELECT id, first_name, last_name, password_hash, is_verified, two_factor_enabled, two_fa_secret FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $first_name, $last_name, $password_hash, $is_verified, $two_factor_enabled, $two_fa_secret);
                    $stmt->fetch();

                    if (!$is_verified) {
                        $errors[] = 'Your account is not verified. Please check your email for the verification link.';
                    } elseif (password_verify($password, $password_hash)) {
                        // Step 5: Check if 2FA is enabled for this user
                        if ($two_factor_enabled) {
                            // 2FA is enabled, prompt for the OTP code
                            $_SESSION['temp_user_id'] = $user_id;      // Store user ID in a temporary session
                            $_SESSION['temp_email'] = $email;         // Store email in a temporary session
                            $_SESSION['temp_first_name'] = $first_name; // Store first name in a temporary session
                            $_SESSION['temp_last_name'] = $last_name;   // Store last name in a temporary session
                            $_SESSION['two_factor_pending'] = true;   // Set the flag for pending 2FA
                            $_SESSION['two_fa_secret'] = $two_fa_secret;
                        } else {
                            // No 2FA, log the user in
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['email'] = $email;
                            $_SESSION['first_name'] = $first_name;   // Set first name
                            $_SESSION['last_name'] = $last_name;     // Set last name
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
                $conn->close();
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
</head>
<body>

    <?php include "includes/header.php"; ?>

    <div class="container register-form">
        <h2 class="center-align">Login to ZNCTech</h2>
        <?php
        // Step 6: Display any success or error messages
        if (isset($_SESSION['success_message'])) {
            echo '<p class="success-message">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<p class="error-message">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
            }
        }
        ?>

        <!-- Step 7: Check if 2FA is pending, show OTP field -->
        <?php if (isset($_SESSION['two_factor_pending']) && $_SESSION['two_factor_pending']): ?>
            <form action="login.php" method="POST" class="form-grid">
                <md-outlined-text-field label="Enter 2FA code from app" type="text" name="otp_code" class="full-width" required></md-outlined-text-field>
                <md-filled-button id="login-button" class="full-width" type="submit">Verify 2FA</md-filled-button>
            </form>
        <?php else: ?>
            <!-- Regular login form -->
            <form action="login.php" method="POST" class="form-grid">
                <md-outlined-text-field label="Email Address" type="email" name="email" id="email" class="full-width" required value="<?php echo htmlspecialchars($email); ?>"></md-outlined-text-field>
                <md-outlined-text-field label="Password" type="password" name="password" id="password" class="full-width" required>
                    <md-icon-button toggle slot="trailing-icon" type="button" onclick="togglePasswordVisibility('password')">
                        <md-icon>visibility</md-icon>
                        <md-icon slot="selected">visibility_off</md-icon>
                    </md-icon-button>
                </md-outlined-text-field>
                <md-filled-button id="login-button" class="full-width" type="submit">Login</md-filled-button>
            </form>
        <?php endif; ?>
        
        <p class="center-align">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
    <?php include "includes/footer.php"; ?>
    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const iconButton = event.currentTarget;
            if (iconButton.selected) {
                passwordField.type = 'text';
            } else {
                passwordField.type = 'password';
            }
        }
    </script>

</body>
</html>
