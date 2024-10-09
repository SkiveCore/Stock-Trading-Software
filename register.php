<!DOCTYPE html>
<html lang="en">
	<head>
    	<?php include "includes/metainfo.php"; ?>
		<title>ZNCTech - Register</title>
		<link rel="stylesheet" href="css/style.css">
	</head>
	<body>
		<?php include "includes/header.php"; ?>
		<div class="container register-form">
			<h2 class="center-align">Register for ZNCTech</h2>
			<?php
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
			}
			require 'includes/db_connect.php';
			require 'includes/PHPMailer/src/Exception.php';
			require 'includes/PHPMailer/src/PHPMailer.php';
			require 'includes/PHPMailer/src/SMTP.php';
			use PHPMailer\PHPMailer\PHPMailer;
			use PHPMailer\PHPMailer\Exception;
			$errors = [];
			$success_message = '';
			$first_name = '';
			$last_name = '';
			$email = '';
			$phone = '';
			$dob = '';
			if ($_SERVER['REQUEST_METHOD'] == 'POST') {
				$first_name = trim($_POST['first_name'] ?? '');
				$last_name = trim($_POST['last_name'] ?? '');
				$email = trim($_POST['email'] ?? '');
				$phone = trim($_POST['phone'] ?? '');
				$dob = trim($_POST['dob'] ?? '');
				$password = $_POST['password'] ?? '';
				$confirm_password = $_POST['confirm_password'] ?? '';
				if (empty($first_name)) {
					$errors[] = 'First name is required.';
				}
				if (empty($last_name)) {
					$errors[] = 'Last name is required.';
				}
				if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$errors[] = 'A valid email address is required.';
				}
				if (empty($phone) || !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
					$errors[] = 'A valid phone number is required.';
				}
				if (empty($dob)) {
					$errors[] = 'Date of birth is required.';
				}
				if (empty($password)) {
					$errors[] = 'Password is required.';
				} elseif ($password !== $confirm_password) {
					$errors[] = 'Passwords do not match.';
				} elseif (strlen($password) < 8) {
					$errors[] = 'Password must be at least 8 characters long.';
				}
				if (empty($errors)) {
					$password_hash = password_hash($password, PASSWORD_DEFAULT);
					$verification_token = bin2hex(random_bytes(16));
					$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, dob, password_hash, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
					$stmt->bind_param("sssssss", $first_name, $last_name, $email, $phone, $dob, $password_hash, $verification_token);
					$mail = new PHPMailer(true);
					try {
						$stmt->execute();
						$mail->isSMTP();
						$mail->Host = 'smtp.gmail.com';
						$mail->SMTPAuth = true;
						$mail->Port = 465;
						$mail->SMTPAuth = true;
						$mail->Username = 'donotreply@znctech.org';
						$mail->Password = 'rpnz cihj elzv ebmx';
						$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
						$mail->setFrom('donotreply@znctech.org', 'ZNCTech');
						$mail->addAddress($email, $first_name . ' ' . $last_name);
						$mail->isHTML(true);
						$mail->Subject = 'ZNCTech Email Verification';
						$verification_link = 'https://znctech.org/verify.php?token=' . $verification_token;
						$email_body = file_get_contents('includes/email_template.html');
						$email_body = str_replace('{{first_name}}', htmlspecialchars($first_name), $email_body);
						$email_body = str_replace('{{verification_link}}', htmlspecialchars($verification_link), $email_body);
						$mail->Body = $email_body;
						$mail->send();
						$success_message = 'Registration successful! Please check your email to verify your account.';
						$first_name = $last_name = $email = $phone = $dob = '';
						$stmt->close();
						$conn->close();
					} catch (mysqli_sql_exception $e) {
						if ($e->getCode() == 1062) {
							$errors[] = 'An account with this email already exists.';
						} else {
							$errors[] = 'An error occurred. Please try again later.';
						}
					} catch (Exception $e) {
						$errors[] = 'Unable to send verification email. Please try again later.' . $e;
					}
				}
			}
			// Display success message if set
			if ($success_message) {
				echo '<p class="success-message">' . $success_message . '</p>';
			} else {
				// Display errors if any
				if (!empty($errors)) {
					foreach ($errors as $error) {
						echo '<p class="error-message">' . htmlspecialchars($error) . '</p>';
					}
				}
			?>
			<form action="register.php" method="POST" class="form-grid">
				<md-outlined-text-field label="First Name" name="first_name" id="first_name" class="full-width" value="<?php echo htmlspecialchars($first_name); ?>" required></md-outlined-text-field>
				<md-outlined-text-field label="Last Name" name="last_name" id="last_name" class="full-width" value="<?php echo htmlspecialchars($last_name); ?>" required></md-outlined-text-field>
				<md-outlined-text-field label="Email Address" type="email" name="email" id="email" class="full-width" value="<?php echo htmlspecialchars($email); ?>" required></md-outlined-text-field>
				<md-outlined-text-field label="Phone Number" type="tel" name="phone" id="phone" class="full-width" value="<?php echo htmlspecialchars($phone); ?>" required></md-outlined-text-field>
				<md-outlined-text-field label="Date of Birth" type="date" name="dob" id="dob" class="full-width" value="<?php echo htmlspecialchars($dob); ?>" required></md-outlined-text-field>
				<md-outlined-text-field label="Password" type="password" name="password" id="password" class="full-width" required>
					<md-icon-button toggle slot="trailing-icon" type="button" onclick="togglePasswordVisibility('password')">
						<md-icon>visibility</md-icon>
						<md-icon slot="selected">visibility_off</md-icon>
					</md-icon-button>
				</md-outlined-text-field>
				<md-outlined-text-field label="Confirm Password" type="password"  name="confirm_password" id="confirm_password" class="full-width" required>
					<md-icon-button toggle slot="trailing-icon" type="button" onclick="togglePasswordVisibility('confirm_password')">
						<md-icon>visibility</md-icon>
						<md-icon slot="selected">visibility_off</md-icon>
					</md-icon-button>
				</md-outlined-text-field>
				<md-filled-button id="register-button" class="full-width" type="submit">Register</md-filled-button>
			</form>
			<?php
			}
			?>
		</div>
		<?php include "includes/footer.php"; ?>
		<script>
			function togglePasswordVisibility(fieldId) {
				const passwordField = document.getElementById(fieldId);
				passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
			}
		</script>
	</body>
</html>