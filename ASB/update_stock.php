<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$stock_id = intval($data['stock_id']);
$field = $conn->real_escape_string($data['field']);
$value = $conn->real_escape_string($data['value']);

$allowed_fields = ['pe_ratio', 'eps', 'dividend_yield', 'dividend_per_share', 'beta', 'revenue', 'net_income', 'operating_income', 'total_debt', 'price_to_sales_ratio', 'cash_flow_per_share', 'debt_to_equity_ratio', 'return_on_equity', 'total_assets', 'total_liabilities'];

if (in_array($field, $allowed_fields)) {
    $query = "UPDATE stocks SET $field = '$value' WHERE stock_id = $stock_id";
    if ($conn->query($query) === TRUE) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid field']);
}
?>
