<?php
require_once '../includes/db_connect.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'];
$timeframe = $_GET['timeframe'] ?? 'all';
$portfolio_sql = "
    SELECT hsh.timestamp, SUM(ust.quantity * hsh.price) as portfolio_value
    FROM user_stock_transactions ust
    JOIN stock_price_history hsh ON ust.stock_id = hsh.stock_id 
    WHERE ust.user_id = ? AND hsh.timestamp <= NOW()
    GROUP BY hsh.timestamp
    ORDER BY hsh.timestamp ASC
";

$stmt = $conn->prepare($portfolio_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$portfolio_data = [];
while ($row = $result->fetch_assoc()) {
    $portfolio_data[] = [
        'timestamp' => $row['timestamp'],
        'portfolio_value' => (float)$row['portfolio_value'],
    ];
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $portfolio_data]);
?>
