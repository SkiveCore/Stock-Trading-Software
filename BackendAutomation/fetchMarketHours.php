<?php
require_once '../includes/db_connect.php';
$query = "SELECT day_of_week, open_time, close_time, is_open FROM default_market_hours WHERE is_open = 1";
$result = $conn->query($query);

$market_hours = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $market_hours[] = $row;
    }
}

echo json_encode(['success' => true, 'market_hours' => $market_hours]);
?>