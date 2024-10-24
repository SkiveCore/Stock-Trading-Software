<?php
require_once '../includes/db_connect.php';

$stock_id = intval($_GET['stock_id']);

$query = "
    SELECT timestamp, price 
    FROM stock_price_history 
    WHERE stock_id = $stock_id 
    ORDER BY timestamp ASC
";

$result = $conn->query($query);

$timestamps = [];
$prices = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = $row['timestamp'];
        $prices[] = $row['price'];
    }
    echo json_encode(['success' => true, 'timestamps' => $timestamps, 'prices' => $prices]);
} else {
    echo json_encode(['success' => false, 'message' => 'No data found']);
}
?>
