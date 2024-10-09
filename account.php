<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require 'includes/db_connect.php';
require 'vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
require_once __DIR__ . '/vendor/phpgangsta/googleauthenticator/PHPGangsta/GoogleAuthenticator.php'; // Ensure the path is correct

$gAuth = new \PHPGangsta_GoogleAuthenticator();
$secret = $_SESSION['two_fa_secret'] ?? $gAuth->createSecret();
$_SESSION['two_fa_secret'] = $secret;

$user_id = $_SESSION['user_id'];

$messages = [];  // Initialize messages array for success and error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_phone'])) {
        $new_phone = $_POST['phone'];
        // Update the phone number in the database
        $query = "UPDATE users SET phone = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_phone, $user_id);
        $stmt->execute();

        // Push success message
        $messages[] = "<p class='success-message'>Phone number updated successfully!</p>";
    } elseif (isset($_POST['update_password'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['password'];

        // Step 1: Fetch the current password hash from the database
        $query = "SELECT password_hash FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->fetch();
        $stmt->close();

        // Step 2: Verify the old password
        if (password_verify($old_password, $password_hash)) {
            // Step 3: Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Step 4: Update the password in the database
            $query = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_password_hash, $user_id);
            $stmt->execute();

            // Push success message
            $messages[] = "<p class='success-message'>Password updated successfully!</p>";
        } else {
            // Push error message
            $messages[] = "<p class='error-message'>Old password is incorrect.</p>";
        }
    } elseif (isset($_POST['enable_2fa'])) {
        $otp_code = $_POST['otp_code'];
		// Verify the OTP code the user entered against the stored secret
		$secret = $_SESSION['two_fa_secret'];
		$isCodeValid = $gAuth->verifyCode($secret, $otp_code, 2); // 2 = 2 allowed time windows

		if ($isCodeValid) {
			// Mark 2FA as enabled in the database
			$query = "UPDATE users SET two_factor_enabled = 1, two_fa_secret = ? WHERE id = ?";
			$stmt = $conn->prepare($query);
			$stmt->bind_param("si", $secret, $user_id);
			$stmt->execute();

			// Success message
			$messages[] = "<p class='success-message'>Two-factor authentication has been enabled!</p>";
		} else {
			$messages[] = "<p class='error-message'>Invalid code, please try again.</p>";
		}
    }
}

// Fetch phone number and 2FA status from the database
$query = "SELECT phone, two_factor_enabled FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_phone = $row['phone'];
    $two_factor_enabled = $row['two_factor_enabled'];  // Check 2FA status from DB
}

$qrCode = new QrCode('otpauth://totp/ZNCTech:' . $_SESSION['email'] . '?secret=' . $secret . '&issuer=ZNCTech');
$qrCode->setSize(300);
$writer = new PngWriter();
$qrCodeImage = $writer->write($qrCode)->getDataUri();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Account Settings</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include "includes/header.php"; ?>

    <!-- Account settings content -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="container">
            <div class="account-settings-card">
                <h2 class="center-align">Account Settings</h2>

				<!-- Message box area -->
				<div class="message-box">
					<?php
					// Display all messages
					if (!empty($messages)) {
						foreach ($messages as $message) {
							echo $message;
						}
					}
					?>
				</div>

                <!-- Phone Number Update Form -->
                <form action="account.php" method="POST" class="form-grid">
                    <md-outlined-text-field label="Phone Number" type="text" name="phone" id="phone" class="full-width" required value="<?php echo htmlentities($user_phone); ?>"></md-outlined-text-field>
                    <md-filled-button type="submit" name="update_phone" class="full-width">Update Phone</md-filled-button>
                </form>

                <!-- Password Update Form -->
                <form action="account.php" method="POST" class="form-grid">
					<!-- Old Password Field -->
					<md-outlined-text-field label="Old Password" type="password" name="old_password" id="old_password" class="full-width" required></md-outlined-text-field>

					<!-- New Password Field -->
					<md-outlined-text-field label="New Password" type="password" name="password" id="password" class="full-width" required></md-outlined-text-field>

					<md-filled-button type="submit" name="update_password" class="full-width">Update Password</md-filled-button>
				</form>

                <!-- Two-Factor Authentication Form -->
                <form action="account.php" method="POST" class="form-grid">
                    <div class="form-group qr-section">
                        <label for="two_fa" class="qr-label">Two-Factor Authentication</label>
                        <?php if ($two_factor_enabled == 0): // Check 2FA status from the database ?>
                            <p>Scan this QR code with your favorite authenticator app:</p>
                            <img class="qr-code" src="<?php echo $qrCodeImage; ?>" alt="QR Code">
                            <md-outlined-text-field label="Enter code from app" type="text" name="otp_code" class="full-width"></md-outlined-text-field>
                            <md-filled-button type="submit" name="enable_2fa" class="full-width">Enable 2FA</md-filled-button>
                        <?php else: ?>
                            <p>Two-Factor Authentication is enabled.</p>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    <?php else:
        header('Location: index.php');
    endif; ?>

    <?php include "includes/footer.php"; ?>
</body>
</html>
