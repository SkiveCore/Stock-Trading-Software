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
if (!isset($_POST['stock_id'], $_POST['quantity'], $_POST['order_type']) || !is_numeric($_POST['quantity'])) {
    $_SESSION['message'] = 'Error: Invalid request.';
    $_SESSION['message_type'] = 'error';
    redirectToPreviousPage();
    exit();
}

$stock_id = intval($_POST['stock_id']);
$quantity = intval($_POST['quantity']);
$order_type = strtolower($_POST['order_type']);
$limit_price = isset($_POST['limit_price']) && is_numeric($_POST['limit_price']) ? floatval($_POST['limit_price']) : null;

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

if ($order_type === 'market' && $total_cost > ($cash_balance * 0.95)) {
    $_SESSION['message'] = 'Error: Insufficient funds to complete the purchase.';
    $_SESSION['message_type'] = 'error';
    header("Location: /$ticker_symbol");
    exit();
}

if ($order_type === 'limit') {
    if ($limit_price === null || $limit_price <= 0) {
        $_SESSION['message'] = 'Error: Invalid limit price specified for limit order.';
        $_SESSION['message_type'] = 'error';
        redirectToPreviousPage();
        exit();
    }
    $transaction_status = 'pending';
} else {
    $transaction_status = isMarketOpen($conn) ? 'completed' : 'pending';
}

$conn->begin_transaction();

try {
    if ($transaction_status === 'completed' && $order_type === 'Market') {
        $new_balance = $cash_balance - $total_cost;
        $update_query = "UPDATE user_wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("di", $new_balance, $user_id);
        $update_stmt->execute();
    }
    $transaction_query = "INSERT INTO user_stock_transactions (user_id, stock_id, transaction_type, quantity, price_per_share, transaction_date, status, order_type, limit_price) 
                          VALUES (?, ?, 'purchase', ?, ?, NOW(), ?, ?, ?)";
    $price_per_share = ($order_type === 'Limit') ? $limit_price : $current_price;
    $transaction_stmt = $conn->prepare($transaction_query);
    $transaction_stmt->bind_param("iiidssd", $user_id, $stock_id, $quantity, $price_per_share, $transaction_status, $order_type, $limit_price);
    $transaction_stmt->execute();

    $conn->commit();

    if ($transaction_status === 'completed') {
        $_SESSION['message'] = "Purchase successful! You bought $quantity shares at $$current_price each.";
        $_SESSION['message_type'] = 'success';
    } elseif ($order_type === 'Limit') {
        $_SESSION['message'] = "Limit order placed. It will be executed when the stock reaches $$limit_price or lower.";
        $_SESSION['message_type'] = 'info';
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
