<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? 0;
$stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$order_type = strtolower($_POST['order_type']);
$limit_price = isset($_POST['limit_price']) && is_numeric($_POST['limit_price']) ? floatval($_POST['limit_price']) : null;

if (!$user_id) {
    $_SESSION['message'] = "Please log in to sell stocks.";
    $_SESSION['message_type'] = "error";
    header("Location: /login.php");
    exit();
}

if ($quantity <= 0 || $stock_id <= 0) {
    $_SESSION['message'] = "Invalid stock or quantity specified.";
    $_SESSION['message_type'] = "error";
    header("Location: /");
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

$query = "SELECT ticker_symbol, current_price FROM stocks WHERE stock_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $stock_id);
$stmt->execute();
$result = $stmt->get_result();
$stock = $result->fetch_assoc();

if (!$stock) {
    $_SESSION['message'] = "Stock not found.";
    $_SESSION['message_type'] = "error";
    header("Location: /404.php");
    exit();
}

$ticker_symbol = $stock['ticker_symbol'];
$current_price = $stock['current_price'];

$holding_query = "
    SELECT 
        (COALESCE(SUM(CASE WHEN transaction_type = 'purchase' AND status = 'completed' THEN quantity ELSE 0 END), 0) -
         COALESCE(SUM(CASE WHEN transaction_type = 'sale' AND status = 'completed' THEN quantity ELSE 0 END), 0)) AS owned_quantity
    FROM 
        user_stock_transactions 
    WHERE 
        user_id = ? AND stock_id = ?
";
$holding_stmt = $conn->prepare($holding_query);
$holding_stmt->bind_param("ii", $user_id, $stock_id);
$holding_stmt->execute();
$holding_result = $holding_stmt->get_result();
$holding = $holding_result->fetch_assoc();
$owned_quantity = $holding['owned_quantity'] ?? 0;

if ($quantity > $owned_quantity) {
    $_SESSION['message'] = "You don't have enough shares to sell.";
    $_SESSION['message_type'] = "error";
    header("Location: /$ticker_symbol");
    exit();
}

$transaction_status = isMarketOpen($conn) ? 'completed' : 'pending';

if ($order_type === 'limit') {
    if ($limit_price === null || $limit_price <= 0) {
        $_SESSION['message'] = 'Error: Invalid limit price specified for limit order.';
        $_SESSION['message_type'] = 'error';
        header("Location: /$ticker_symbol");
        exit();
    }
    $transaction_status = 'pending';
}

$total_earnings = $quantity * $current_price;

$conn->begin_transaction();

try {
    if ($transaction_status === 'completed' && $order_type === 'market') {
        $update_balance_query = "UPDATE user_wallets SET balance = balance + ? WHERE user_id = ?";
        $update_balance_stmt = $conn->prepare($update_balance_query);
        $update_balance_stmt->bind_param("di", $total_earnings, $user_id);
        $update_balance_stmt->execute();
    }

    $transaction_query = "INSERT INTO user_stock_transactions (user_id, stock_id, quantity, price_per_share, transaction_type, status, order_type, limit_price) 
                          VALUES (?, ?, ?, ?, 'sale', ?, ?, ?)";
    $price_per_share = ($order_type === 'limit') ? $limit_price : $current_price;
    $transaction_stmt = $conn->prepare($transaction_query);
    $transaction_stmt->bind_param("iiidssd", $user_id, $stock_id, $quantity, $price_per_share, $transaction_status, $order_type, $limit_price);
    $transaction_stmt->execute();

    $conn->commit();

    if ($transaction_status === 'completed') {
        $_SESSION['message'] = "Successfully sold $quantity shares for $$total_earnings.";
        $_SESSION['message_type'] = "success";
    } elseif ($order_type === 'limit') {
        $_SESSION['message'] = "Limit sell order placed. It will be executed when the stock reaches $$limit_price or higher.";
        $_SESSION['message_type'] = "info";
    } else {
        $_SESSION['message'] = "Sell order placed successfully but is pending. The transaction will be processed when the market opens.";
        $_SESSION['message_type'] = "info";
    }

    header("Location: /$ticker_symbol");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Error: Sell order could not be completed. Please try again later.";
    $_SESSION['message_type'] = "error";
    header("Location: /$ticker_symbol");
    exit();
}

$conn->close();
?>
