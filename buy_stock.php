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

function isMarketOpen($conn) {
    $day_of_week = date('l');
    $current_time = date('H:i:s');
    $query = "SELECT * FROM default_market_hours WHERE day_of_week = ? AND is_open = 1 AND open_time <= ? AND close_time >= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $day_of_week, $current_time, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
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

if ($total_cost > ($cash_balance * 0.95)) {
    $_SESSION['message'] = 'Error: Insufficient funds to complete the purchase.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

$transaction_status = isMarketOpen($conn) ? 'completed' : 'pending';

$conn->begin_transaction();

try {
    if ($transaction_status === 'completed') {
        $new_balance = $cash_balance - $total_cost;
        $update_query = "UPDATE user_wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("di", $new_balance, $user_id);
        $update_stmt->execute();
    }

    $transaction_query = "INSERT INTO user_stock_transactions (user_id, stock_id, transaction_type, quantity, price_per_share, transaction_date, status) VALUES (?, ?, 'purchase', ?, ?, NOW(), ?)";
    $transaction_stmt = $conn->prepare($transaction_query);
    $transaction_stmt->bind_param("iiids", $user_id, $stock_id, $quantity, $current_price, $transaction_status);
    $transaction_stmt->execute();

    $conn->commit();

    if ($transaction_status === 'completed') {
        $_SESSION['message'] = "Purchase successful! You bought $quantity shares at $$current_price each.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Purchase placed successfully but is pending. The transaction will be processed when the market opens.";
        $_SESSION['message_type'] = 'info';
    }

    header("Location: /$ticker_symbol");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = 'Error: Purchase could not be completed. Please try again later.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

$conn->close();
?>
