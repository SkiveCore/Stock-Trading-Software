<?php
session_start();
require 'includes/db_connect.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
            $stmt = $conn->prepare("SELECT id, first_name, last_name, password_hash, is_verified FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $first_name, $last_name, $password_hash, $is_verified);
                $stmt->fetch();
                if (!$is_verified) {
                    $errors[] = 'Your account is not verified. Please check your email for the verification link.';
                } else {
                    if (password_verify($password, $password_hash)) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $_SESSION['email'] = $email;
                        header('Location: index.php');
                        exit();
                    } else {
                        $errors[] = 'Incorrect email or password.';
                    }
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
