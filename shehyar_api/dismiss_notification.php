<?php
require_once 'connection.php'; // $pdo PDO instance
session_start();
header('Content-Type: application/json');

// Hardcoded user_id for testing
$userId = 24;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get notification ID to dismiss from POST
    $notifId = $_POST['notification_id'] ?? null;

    if (!$notifId) {
        echo json_encode(['success' => false, 'message' => 'notification_id is required']);
        exit;
    }

    try {
        // Update only this notification to mark as read/dismissed
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notifId, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notification dismissed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found or already dismissed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
