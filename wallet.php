<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'includes/db_connect.php';
$user_id = $_SESSION['user_id'];
$sql = "SELECT balance FROM user_wallets WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();
if ($wallet) {
    $balance = $wallet['balance'];
} else {
    $balance = 0.00;
}
$encryption_key = getenv('AES_ENCRYPTION_KEY');
$sql_methods = "SELECT id, payment_type, card_holder_name, AES_DECRYPT(card_number, ?) AS card_number, card_expiry 
                FROM user_payment_methods 
                WHERE user_id = ? AND is_anonymized = 0";
$stmt_methods = $conn->prepare($sql_methods);
$stmt_methods->bind_param('si', $encryption_key, $user_id);
$stmt_methods->execute();
$payment_methods = $stmt_methods->get_result();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['delete_method'])) {
		$method_id = $_POST['method_id'];

		// Anonymize the payment method
		$sql_anonymize = "UPDATE user_payment_methods 
						  SET card_holder_name = 'Deleted', 
							  card_number = AES_ENCRYPT('0000000000000000', ?), 
							  card_expiry = '1970-01-01', 
							  is_anonymized = 1 
						  WHERE id = ? AND user_id = ?";
		$stmt_anonymize = $conn->prepare($sql_anonymize);
		$stmt_anonymize->bind_param('sii', $encryption_key, $method_id, $user_id);

		if ($stmt_anonymize->execute()) {
			header('Location: wallet.php');
			exit();
		} else {
			die("Error anonymizing payment method: " . $stmt_anonymize->error);
		}
	} elseif (isset($_POST['add_method'])) {
		$payment_type = $_POST['payment_type'];
		$card_holder_name = $_POST['card_holder_name'];
		$card_number = str_replace(' ', '', $_POST['card_number']);
		$card_expiry = $_POST['card_expiry'];
		$card_cvv = $_POST['card_cvv'];
		if ($payment_type === 'credit_card' || $payment_type === 'debit_card') {
			if (preg_match('/^4[0-9]{12,15}$/', $card_number)) {
				$payment_type = 'Visa';
			} elseif (preg_match('/^5[1-5][0-9]{14}$/', $card_number)) {
				$payment_type = 'Mastercard';
			} elseif (preg_match('/^3[47][0-9]{13}$/', $card_number)) {
				$payment_type = 'Amex';
			} elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $card_number)) {
				$payment_type = 'Discover';
			} else {
				$payment_type = 'Unknown Card';
			}
		} elseif ($payment_type === 'bank_account') {
			$payment_type = 'Bank Account';
		}
		$expiry_parts = explode('/', $card_expiry);
		if (count($expiry_parts) === 2) {
			$month = $expiry_parts[0];
			$year = '20' . $expiry_parts[1];
			$formatted_expiry = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
		} else {
			die("Invalid expiry date format.");
		}

		$sql_insert = "INSERT INTO user_payment_methods (user_id, payment_type, card_holder_name, card_number, card_expiry, card_cvv) 
					   VALUES (?, ?, ?, AES_ENCRYPT(?, ?), ?, AES_ENCRYPT(?, ?))";
		$stmt_insert = $conn->prepare($sql_insert);
		if (!$stmt_insert) {
			die("Error preparing statement: " . $conn->error);
		}
		$stmt_insert->bind_param('isssssss', $user_id, $payment_type, $card_holder_name, $card_number, $encryption_key, $formatted_expiry, $card_cvv, $encryption_key);
		if ($stmt_insert->execute()) {
			header('Location: wallet.php');
			exit();
		} else {
			die("Error executing statement: " . $stmt_insert->error);
		}
	} elseif (isset($_POST['load_wallet'])) {
		$amount = $_POST['amount'];
		$payment_method_id = $_POST['payment_method'];
		$sql_check_wallet = "SELECT id FROM user_wallets WHERE user_id = ?";
		$stmt_check_wallet = $conn->prepare($sql_check_wallet);
		$stmt_check_wallet->bind_param('i', $user_id);
		$stmt_check_wallet->execute();
		$result_check_wallet = $stmt_check_wallet->get_result();

		if ($result_check_wallet->num_rows > 0) {
			$sql_update_wallet = "UPDATE user_wallets SET balance = balance + ? WHERE user_id = ?";
			$stmt_update_wallet = $conn->prepare($sql_update_wallet);
			$stmt_update_wallet->bind_param('di', $amount, $user_id);
			if ($stmt_update_wallet->execute()) {
				$transaction_type = 'deposit';
				$sql_insert_transaction = "INSERT INTO user_bank_transactions (user_id, transaction_type, amount, bank_method_id) 
										   VALUES (?, ?, ?, ?)";
				$stmt_insert_transaction = $conn->prepare($sql_insert_transaction);
				if (!$stmt_insert_transaction) {
					die("Error preparing statement for transaction: " . $conn->error);
				}
				$stmt_insert_transaction->bind_param('isdi', $user_id, $transaction_type, $amount, $payment_method_id);
				if ($stmt_insert_transaction->execute()) {
					header('Location: wallet.php');
					exit();
				} else {
					die("Error executing transaction statement: " . $stmt_insert_transaction->error);
				}
			} else {
				die("Error executing wallet update: " . $stmt_update_wallet->error);
			}
		} else {
			$currency = 'USD';
			$sql_create_wallet = "INSERT INTO user_wallets (user_id, balance, currency) VALUES (?, ?, ?)";
			$stmt_create_wallet = $conn->prepare($sql_create_wallet);
			$stmt_create_wallet->bind_param('ids', $user_id, $amount, $currency);
			if ($stmt_create_wallet->execute()) {
				$transaction_type = 'deposit';
				$sql_insert_transaction = "INSERT INTO user_bank_transactions (user_id, transaction_type, amount, bank_method_id) 
										   VALUES (?, ?, ?, ?)";
				$stmt_insert_transaction = $conn->prepare($sql_insert_transaction);
				if (!$stmt_insert_transaction) {
					die("Error preparing statement for transaction: " . $conn->error);
				}
				$stmt_insert_transaction->bind_param('isdi', $user_id, $transaction_type, $amount, $payment_method_id);
				if ($stmt_insert_transaction->execute()) {
					header('Location: wallet.php');
					exit();
				} else {
					die("Error executing transaction statement: " . $stmt_insert_transaction->error);
				}
			} else {
				die("Error creating wallet: " . $stmt_create_wallet->error);
			}
		}
	}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/metainfo.php"; ?>
    <title>ZNCTech - Wallet</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/wallet.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include "includes/header.php"; ?>
	<div class="wallet-container">
        <h1>My Wallet</h1>
        <p class="wallet-balance">Current Balance: $<?php echo number_format((float)$balance, 2); ?></p>

        <h2>Load Wallet</h2>
        <form action="wallet.php" method="post" class="wallet-form">
            <label for="amount">Amount:</label>
            <input type="number" name="amount" step="0.01" min="0.01" required>
            <label for="payment_method">Select Payment Method:</label>
            <select name="payment_method" required>
                <option value="">Select</option>
                <?php
                $payment_methods->data_seek(0);
                while ($method = $payment_methods->fetch_assoc()): ?>
                    <option value="<?php echo $method['id']; ?>"><?php echo '**** **** **** ' . substr($method['card_number'], -4); ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" name="load_wallet">Load Wallet</button>
        </form>

        <h2>Payment Methods</h2>
        <table class="payment-methods-table">
            <tr>
                <th>Type</th>
                <th>Card Holder Name</th>
                <th>Card Number</th>
                <th>Expiry Date</th>
                <th>Actions</th>
            </tr>
            <?php $payment_methods->data_seek(0);
			while ($method = $payment_methods->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($method['payment_type']); ?></td>
                <td><?php echo htmlspecialchars($method['card_holder_name']); ?></td>
                <td><?php echo '**** **** **** ' . substr($method['card_number'], -4); ?></td>
                <td><?php echo htmlspecialchars($method['card_expiry']); ?></td>
                <td>
                    <form action="wallet.php" method="post" style="display:inline;">
                        <input type="hidden" name="method_id" value="<?php echo $method['id']; ?>">
                        <button type="submit" name="delete_method" class="delete-button">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h2><button id="toggle-add-method">Add Payment Method</button></h2>
        <form action="wallet.php" method="post" class="wallet-form" id="add-method-form" style="display: none;">
            <label for="payment_type">Payment Type:</label>
            <select name="payment_type" id="payment_type" required>
                <option value="credit_card">Credit Card</option>
                <option value="debit_card">Debit Card</option>
                <option value="bank_account">Bank Account</option>
            </select>
            <label for="card_holder_name">Card Holder Name:</label>
            <input type="text" name="card_holder_name" id="card_holder_name" required>
            <label for="card_number">Card Number:</label>
            <div class="card-input-wrapper">
                <input type="text" name="card_number" id="card_number" required placeholder="1234 5678 9012 3456">
                <i class="fas fa-credit-card card-icon" id="card_logo" style="display: none;"></i>
            </div>
            <small id="card_type" class="card-message"></small>
            <label for="card_expiry">Expiry Date (MM/YY):</label>
            <input type="text" name="card_expiry" id="card_expiry" placeholder="MM/YY" required>
            <label for="card_cvv">CVV:</label>
            <input type="password" name="card_cvv" id="card_cvv" maxlength="4" placeholder="123" required>
            <button type="submit" name="add_method">Add Payment Method</button>
        </form>
    </div>
	<script>
document.addEventListener('DOMContentLoaded', function () {
	const toggleButton = document.getElementById('toggle-add-method');
	const addMethodForm = document.getElementById('add-method-form');

	toggleButton.addEventListener('click', function () {
		if (addMethodForm.style.display === 'none') {
			addMethodForm.style.display = 'block';
		} else {
			addMethodForm.style.display = 'none';
		}
	});
    const cardNumberInput = document.getElementById('card_number');
    const cardTypeDisplay = document.getElementById('card_type');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCVVInput = document.getElementById('card_cvv');
    const cardLogo = document.getElementById('card_logo');
    const cardPatterns = {
        visa: { pattern: /^4[0-9]{0,15}$/, iconClass: 'fab fa-cc-visa' },
        mastercard: { pattern: /^5[1-5][0-9]{0,14}$/, iconClass: 'fab fa-cc-mastercard' },
        amex: { pattern: /^3[47][0-9]{0,13}$/, iconClass: 'fab fa-cc-amex' },
        discover: { pattern: /^6(?:011|5[0-9]{2})[0-9]{0,12}$/, iconClass: 'fab fa-cc-discover' }
    };
	const paymentTypeSelect = document.getElementById('payment_type');
	cardNumberInput.addEventListener('input', function () {
		const cardNumber = cardNumberInput.value.replace(/\D/g, '');
		cardNumberInput.value = cardNumber.replace(/(\d{4})(?=\d)/g, '$1 ');
		let cardType = '';
		let isValidCard = false;
		for (let type in cardPatterns) {
			if (cardPatterns[type].pattern.test(cardNumber)) {
				cardType = type.charAt(0).toUpperCase() + type.slice(1);
				cardLogo.className = `${cardPatterns[type].iconClass} card-icon`;
				cardLogo.style.display = 'inline';
				isValidCard = cardNumber.length === 16 || (type === 'amex' && cardNumber.length === 15);
				if (paymentTypeSelect) {
					paymentTypeSelect.value = 'credit_card';
				}
				break;
			}
		}
		if (cardType) {
			cardTypeDisplay.textContent = `Card Type: ${cardType}`;
			cardTypeDisplay.classList.remove('invalid-card');
		} else {
			cardTypeDisplay.textContent = 'Card type not recognized';
			cardLogo.style.display = 'none';
		}
		if (!isValidCard && cardNumber.length >= 12) {
			cardTypeDisplay.textContent = 'Invalid card number';
			cardTypeDisplay.classList.add('invalid-card');
		}
	});
	cardExpiryInput.addEventListener('input', function () {
		let value = cardExpiryInput.value.replace(/\D/g, '');
		if (value.length >= 2) {
			value = value.substring(0, 2) + '/' + value.substring(2);
		}
		cardExpiryInput.value = value;
		const isValidFormat = /^(0[1-9]|1[0-2])\/\d{2}$/.test(value);
		if (!isValidFormat) {
			cardExpiryInput.setCustomValidity('Invalid expiry date format');
		} else {
			const [month, year] = value.split('/');
			const expiryDate = new Date(`20${year}`, month - 1);
			const currentDate = new Date();
			if (expiryDate < currentDate) {
				cardExpiryInput.setCustomValidity('Card expiry date cannot be in the past');
			} else {
				cardExpiryInput.setCustomValidity('');
			}
		}
	});
    cardCVVInput.addEventListener('input', function () {
        const cvv = cardCVVInput.value;
        const cardNumber = cardNumberInput.value.replace(/\D/g, '');
        const isAmex = /^3[47]/.test(cardNumber);
        const isValidCVV = isAmex ? /^\d{4}$/.test(cvv) : /^\d{3}$/.test(cvv);
        if (!isValidCVV) {
            cardCVVInput.setCustomValidity('Invalid CVV');
        } else {
            cardCVVInput.setCustomValidity('');
        }
    });
});
	</script>
	
	
	<?php
	include "includes/footer.php";
	?>

</body>
</html>
