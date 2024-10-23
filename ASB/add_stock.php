<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /404.php');
    exit();
}
require_once '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $ticker_symbol = $conn->real_escape_string($_POST['ticker_symbol']);
    $current_price = floatval($conn->real_escape_string($_POST['current_price']));
    $outstanding_shares = intval($conn->real_escape_string($_POST['outstanding_shares']));

    // Calculate market cap
    $market_cap = $current_price * $outstanding_shares;

    // Set other initial values
    $issued_shares = $outstanding_shares; // Initially, issued shares = outstanding shares
    $opening_price = $current_price;
    $high_price = $current_price;
    $low_price = $current_price;
    $fifty_two_week_high = $current_price;
    $fifty_two_week_low = $current_price;
    $previous_close = $current_price;
    $treasury_shares = 0; // Initially zero

    // Insert the new stock into the database
    $query = "INSERT INTO stocks (
        company_name, ticker_symbol, current_price, market_cap, outstanding_shares, 
        issued_shares, opening_price, high_price, low_price, fifty_two_week_high, 
        fifty_two_week_low, previous_close, treasury_shares
    ) VALUES (
        '$company_name', '$ticker_symbol', '$current_price', '$market_cap', '$outstanding_shares', 
        '$issued_shares', '$opening_price', '$high_price', '$low_price', '$fifty_two_week_high', 
        '$fifty_two_week_low', '$previous_close', '$treasury_shares'
    )";

    if ($conn->query($query) === TRUE) {
        header('Location: /asb/index.php?success=1');
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}
?>
