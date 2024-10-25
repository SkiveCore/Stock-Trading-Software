<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Error: You need to log in to perform this action.';
    $_SESSION['message_type'] = 'error';
    header("Location: /login.php");
    exit();
}
function redirectToPreviousPage($fallbackUrl = '/') {
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        header("Location: " . $fallbackUrl);
    }
    exit();
}
$user_id = $_SESSION['user_id'];
if (!isset($_POST['stock_id'], $_POST['quantity']) || !is_numeric($_POST['quantity'])) {
    $_SESSION['message'] = 'Error: Invalid request.';
    $_SESSION['message_type'] = 'error';
    redirectToPreviousPage();
    exit();
}
$stock_id = intval($_POST['stock_id']);
$quantity = intval($_POST['quantity']);
$query = "SELECT * FROM stocks WHERE stock_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $stock_id);
$stmt->execute();
$result = $stmt->get_result();
$stock = $result->fetch_assoc();
if (!$stock) {
    $_SESSION['message'] = 'Error: Stock not found.';
    $_SESSION['message_type'] = 'error';
    redirectToPreviousPage();
    exit();
}

$current_price = $stock['current_price'];
$ticker_symbol = $stock['ticker_symbol'];
$total_cost = $current_price * $quantity;
if ($quantity <= 0) {
    $_SESSION['message'] = 'Error: Invalid quantity value.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

// Fetch the user's wallet balance
$query = "SELECT balance FROM user_wallets WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();

if (!$wallet) {
    $_SESSION['message'] = 'Error: No wallet found for the user.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

$cash_balance = $wallet['balance'];

// Server-side validation: Ensure the user has sufficient funds (5% buffer)
if ($total_cost > ($cash_balance * 0.95)) {
    $_SESSION['message'] = 'Error: Insufficient funds to complete the purchase.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

// Deduct the total cost from the user's wallet balance
$new_balance = $cash_balance - $total_cost;

// Begin a transaction
$conn->begin_transaction();

try {
    // Update the wallet balance
    $update_query = "UPDATE user_wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("di", $new_balance, $user_id);
    $update_stmt->execute();

    // Insert the transaction record (log the purchase)
    $transaction_query = "INSERT INTO user_stock_transactions (user_id, stock_id, transaction_type, quantity, price_per_share, transaction_date, status) VALUES (?, ?, 'purchase', ?, ?, NOW(), 'completed')";
    $transaction_stmt = $conn->prepare($transaction_query);
    $transaction_stmt->bind_param("iiid", $user_id, $stock_id, $quantity, $current_price);
    $transaction_stmt->execute();

    // Commit the transaction
    $conn->commit();

    // Set success message
    $_SESSION['message'] = "Purchase successful! You bought $quantity shares at $$current_price each.";
    $_SESSION['message_type'] = 'success';
    header("Location: /$ticker_symbol");
    exit();

    // Close the prepared statements
    $update_stmt->close();
    $transaction_stmt->close();
} catch (Exception $e) {
    // Roll back the transaction if something goes wrong
    $conn->rollback();
    $_SESSION['message'] = 'Error: Purchase could not be completed. Please try again later.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

// Close the database connection
$conn->close();
?>
