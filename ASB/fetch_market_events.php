<?php
require '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $stmt = $conn->prepare("SELECT event_id, event_date, open_time, close_time, event_type, event_description FROM market_calendar WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Error preparing statement: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    $stmt->close();
    $conn->close();
    echo json_encode($events);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method']);
}
