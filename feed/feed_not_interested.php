<?php
require_once 'connection.php'; // $pdo should be defined here
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$feedId = $_POST['feed_id'] ?? null;

if (!$feedId) {
    echo json_encode(['success' => false, 'message' => 'feed_id is required']);
    exit;
}

try {
    // Check if feed exists
    $stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $feedId]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feed) {
        echo json_encode(['success' => false, 'message' => 'Feed not found']);
        exit;
    }

    // Check if already marked as not interested
    $checkStmt = $pdo->prepare("SELECT * FROM feed_not_interested WHERE feed_id = :feed_id AND user_id = :user_id LIMIT 1");
    $checkStmt->execute([':feed_id' => $feedId, ':user_id' => $userId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Remove "not interested"
        $deleteStmt = $pdo->prepare("DELETE FROM feed_not_interested WHERE id = :id");
        $deleteStmt->execute([':id' => $existing['id']]);

        echo json_encode(['status' => 'interested']);
    } else {
        // Mark as "not interested"
        $insertStmt = $pdo->prepare("INSERT INTO feed_not_interested (feed_id, user_id, created_at) VALUES (:feed_id, :user_id, NOW())");
        $insertStmt->execute([':feed_id' => $feedId, ':user_id' => $userId]);

        echo json_encode(['status' => 'notinterested']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
