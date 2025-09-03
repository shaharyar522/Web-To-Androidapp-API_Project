<?php
require_once 'connection.php'; // your PDO $pdo instance
session_start();
date_default_timezone_set('UTC');
header('Content-Type: application/json');

// Simulate auth
$userId = $_SESSION['user_id'] ?? null;
$firstName = $_SESSION['first_name'] ?? 'User';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate required POST data
$feedId = $_POST['feed_id'] ?? null;
$shareHeading = $_POST['share_post_heading'] ?? '';

if (!$feedId) {
    echo json_encode(['success' => false, 'message' => 'feed_id is required']);
    exit;
}

try {
    // Find original feed
    $stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $feedId]);
    $originalFeed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalFeed) {
        echo json_encode(['success' => false, 'message' => 'Original feed not found']);
        exit;
    }

    // Get sharer's full name or username (optional)
    $userStmt = $pdo->prepare("SELECT first_name, username FROM users WHERE id = :id");
    $userStmt->execute([':id' => $userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    $firstName = $userData['first_name'] ?? 'User';
    $username = $userData['username'] ?? '';

    // Create shared post
    $insert = $pdo->prepare("
        INSERT INTO feeds (descriptions, posted_date, owner, is_shared, shared_from_id)
        VALUES (:desc, :date, :owner, :is_shared, :shared_from_id)
    ");
    $insert->execute([
        ':desc' => $shareHeading,
        ':date' => date('Y-m-d H:i:s'),
        ':owner' => $userId,
        ':is_shared' => 1,
        ':shared_from_id' => $feedId
    ]);

    // Add notification if not same user
    if ($originalFeed['owner'] != $userId) {
        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (:user_id, :title, :message, :type, NOW())
        ");

        $messageText = "$firstName (@$username) shared your feed post.";

        $notif->execute([
            ':user_id' => $originalFeed['owner'],
            ':title' => 'Someone shared your post',
            ':message' => $messageText,
            ':type' => 'share'
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Post shared successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to share post: ' . $e->getMessage()]);
}
