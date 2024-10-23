<?php
require_once '../includes/db_connect.php';

$stock_id = intval($_GET['stock_id']);
$timeframe = $_GET['timeframe'] ?? '1d';

switch ($timeframe) {
    case '1d':
        $interval = '1 MINUTE';
        $limit = 1440;
        break;
    case '1w':
        $interval = '1 HOUR';
        $limit = 168;
        break;
    case '1m':
        $interval = '1 DAY';
        $limit = 30;
        break;
    case '3m':
        $interval = '1 DAY';
        $limit = 90;
        break;
    case 'ytd':
        $interval = '1 DAY';
        $limit = date('z');
        break;
    case '1y':
        $interval = '1 DAY';
        $limit = 365;
        break;
    case 'all':
    default:
        $interval = '1 DAY';
        $limit = 1000;
        break;
}
$query = "
    SELECT timestamp, price 
    FROM stock_price_history 
    WHERE stock_id = $stock_id 
    ORDER BY timestamp DESC
    LIMIT $limit;
";
$result = $conn->query($query);

$timestamps = [];
$prices = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timestamps[] = $row['timestamp'];
        $prices[] = $row['price'];
    }
    echo json_encode(['success' => true, 'timestamps' => array_reverse($timestamps), 'prices' => array_reverse($prices)]);
} else {
    echo json_encode(['success' => false, 'message' => 'No data found']);
}
?>
