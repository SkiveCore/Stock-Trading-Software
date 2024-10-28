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
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transaction_id <= 0) {
    $_SESSION['message'] = 'Invalid transaction ID.';
    $_SESSION['message_type'] = 'error';
    header("Location: transactions");
    exit();
}

$query = "SELECT * FROM user_stock_transactions WHERE id = ? AND user_id = ? AND status = 'pending'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = 'Transaction cannot be canceled or does not exist.';
    $_SESSION['message_type'] = 'error';
    header("Location: transactions");
    exit();
}

$update_query = "UPDATE user_stock_transactions SET status = 'cancelled' WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("i", $transaction_id);
if ($update_stmt->execute()) {
    $_SESSION['message'] = 'Transaction canceled successfully.';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Failed to cancel the transaction. Please try again.';
    $_SESSION['message_type'] = 'error';
}

header("Location: transactions");
exit();
?>
