<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = intval($data['notification_id'] ?? 0);
    
    if ($notification_id > 0) {
        $update_query = "UPDATE Notifications SET Is_Read = TRUE, Read_At = NOW() WHERE Notification_ID = $notification_id";
        if (execute_query($update_query)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
