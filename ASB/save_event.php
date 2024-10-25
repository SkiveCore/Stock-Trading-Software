<?php
require '../includes/db_connect.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$event_date = $input['event_date'];
$event_type = $input['event_type'];
$open_time = !empty($input['open_time']) ? $input['open_time'] : null;
$close_time = !empty($input['close_time']) ? $input['close_time'] : null;
$event_description = !empty($input['event_description']) ? $input['event_description'] : null;
$is_open = ($event_type === 'holiday' || $event_type === 'early_closure') ? 0 : 1;

$sql = "INSERT INTO market_calendar (event_date, open_time, close_time, is_open, event_type, event_description) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssiss", $event_date, $open_time, $close_time, $is_open, $event_type, $event_description);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>