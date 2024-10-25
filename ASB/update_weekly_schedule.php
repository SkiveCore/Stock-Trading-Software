<?php
require '../includes/db_connect.php';
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: /404.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM default_market_hours WHERE day_of_week = ?");
    $insertStmt = $conn->prepare("INSERT INTO default_market_hours (day_of_week, open_time, close_time, is_open) VALUES (?, ?, ?, ?)");
    $updateStmt = $conn->prepare("UPDATE default_market_hours SET open_time = ?, close_time = ?, is_open = ? WHERE day_of_week = ?");

    if (!$checkStmt || !$insertStmt || !$updateStmt) {
        echo "Error preparing statement: " . $conn->error;
        exit();
    }

    foreach ($daysOfWeek as $day) {
        $openTime = isset($_POST['open_time'][$day]) ? $_POST['open_time'][$day] : null;
        $closeTime = isset($_POST['close_time'][$day]) ? $_POST['close_time'][$day] : null;
        $isOpen = isset($_POST['is_open'][$day]) ? 1 : 0;
        $checkStmt->bind_param("s", $day);
        $checkStmt->execute();
        $checkStmt->store_result();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();

        if ($count > 0) {
            $updateStmt->bind_param("ssis", $openTime, $closeTime, $isOpen, $day);
            if (!$updateStmt->execute()) {
                echo "Error updating schedule for $day: " . $updateStmt->error;
                exit();
            }
        } else {
            $insertStmt->bind_param("sssi", $day, $openTime, $closeTime, $isOpen);
            if (!$insertStmt->execute()) {
                echo "Error inserting schedule for $day: " . $insertStmt->error;
                exit();
            }
        }
        $checkStmt->free_result();
    }
    $checkStmt->close();
    $insertStmt->close();
    $updateStmt->close();
    $conn->close();
    header("Location: index.php?section=market-schedule&update=success");
    exit();
} else {
    header('Location: /404.php');
    exit();
}
