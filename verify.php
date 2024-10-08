<?php
session_start();
require 'includes/db_connect.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$verification_token = $_GET['token'] ?? '';
if (empty($verification_token)) {
    $_SESSION['error_message'] = 'Invalid verification link.';
    header('Location: login.php');
    exit();
}
try {
    $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $verification_token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $_SESSION['error_message'] = 'Invalid or expired verification link.';
        header('Location: login.php');
        exit();
    }
    $stmt->bind_result($user_id, $is_verified);
    $stmt->fetch();
    if ($is_verified) {
        $_SESSION['success_message'] = 'Your account is already verified. Please log in.';
        header('Location: login.php');
        exit();
    }
    $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $_SESSION['success_message'] = 'Your account has been verified successfully. Please log in.';
    header('Location: login.php');
    exit();
} catch (mysqli_sql_exception $e) {
    error_log('Database error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred during verification. Please try again later.';
    header('Location: login.php');
    exit();
}
$stmt->close();
if (isset($update_stmt)) {
    $update_stmt->close();
}
$conn->close();
?>
