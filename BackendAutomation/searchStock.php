<?php
require_once '../includes/db_connect.php';

$query = isset($_GET['query']) ? htmlspecialchars($_GET['query']) : '';

if ($query) {
    $stmt = $conn->prepare("
        SELECT 
            ticker_symbol, 
            company_name, 
            current_price, 
            percentage_change 
        FROM stocks 
        WHERE ticker_symbol LIKE CONCAT(?, '%') OR company_name LIKE CONCAT('%', ?, '%') 
        LIMIT 10
    ");
    $stmt->bind_param("ss", $query, $query);
    $stmt->execute();
    $result = $stmt->get_result();
    $stocks = [];

    while ($row = $result->fetch_assoc()) {
        $stocks[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($stocks);
}
?>
