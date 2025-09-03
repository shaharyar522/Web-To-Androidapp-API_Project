<?php
require_once 'connection.php'; // your PDO $pdo instance
session_start();
header('Content-Type: application/json');

// Simulated auth check
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Use POST for everything
$action = $_POST['action'] ?? null;
$feedId = $_POST['feed_id'] ?? null;

if (!$feedId || !in_array($action, ['like', 'notinterested'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check feed exists
$stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $feedId]);
$feed = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$feed) {
    echo json_encode(['success' => false, 'message' => 'Feed not found']);
    exit;
}

try {
    if ($action === 'like') {
        // Check if already liked
        $check = $pdo->prepare("SELECT id FROM feed_likes WHERE feed_id = :feed_id AND user_id = :user_id");
        $check->execute([':feed_id' => $feedId, ':user_id' => $userId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Unlike
            $delete = $pdo->prepare("DELETE FROM feed_likes WHERE id = :id");
            $delete->execute([':id' => $existing['id']]);
            echo json_encode(['status' => 'unliked']);
        } else {
            // Like
            $insert = $pdo->prepare("INSERT INTO feed_likes (feed_id, user_id, created_at) VALUES (:feed_id, :user_id, NOW())");
            $insert->execute([':feed_id' => $feedId, ':user_id' => $userId]);

            // Send notification
            if ($feed['owner'] != $userId) {
                $userInfo = $pdo->prepare("SELECT first_name FROM users WHERE id = :id");
                $userInfo->execute([':id' => $userId]);
                $user = $userInfo->fetch(PDO::FETCH_ASSOC);
                $firstName = $user['first_name'] ?? 'User';

                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at)
                                        VALUES (:user_id, :title, :message, :type, NOW())");
                $notif->execute([
                    ':user_id' => $feed['owner'],
                    ':title' => 'Someone liked your post',
                    ':message' => "$firstName liked your post.",
                    ':type' => 'like',
                ]);
            }
            echo json_encode(['status' => 'liked']);
        }
    } elseif ($action === 'notinterested') {
        // Check if already marked
        $check = $pdo->prepare("SELECT id FROM feed_not_interested WHERE feed_id = :feed_id AND user_id = :user_id");
        $check->execute([':feed_id' => $feedId, ':user_id' => $userId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Remove mark
            $delete = $pdo->prepare("DELETE FROM feed_not_interested WHERE id = :id");
            $delete->execute([':id' => $existing['id']]);
            echo json_encode(['status' => 'interested']);
        } else {
            // Add mark
            $insert = $pdo->prepare("INSERT INTO feed_not_interested (feed_id, user_id, created_at) VALUES (:feed_id, :user_id, NOW())");
            $insert->execute([':feed_id' => $feedId, ':user_id' => $userId]);
            echo json_encode(['status' => 'notinterested']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
