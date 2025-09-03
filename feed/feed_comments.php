<?php
require_once 'connection.php'; // $pdo PDO instance
session_start();
header('Content-Type: application/json');

// Simulate authentication (replace with your actual auth logic)
$userId = $_SESSION['user_id'] ?? null;
$firstName = $_SESSION['first_name'] ?? 'User'; // fallback if username not found

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate inputs
$feedId = $_POST['feed_id'] ?? null;
$commentText = $_POST['comment'] ?? null;
$commentId = $_POST['comment_id'] ?? null;
$image = $_FILES['image'] ?? null;

if (!$feedId) {
    echo json_encode(['success' => false, 'message' => 'feed_id is required']);
    exit;
}

// Validate comment length if set
if ($commentText !== null && strlen($commentText) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Comment too long (max 1000 characters)']);
    exit;
}

// Validate feed exists
$stmt = $pdo->prepare("SELECT * FROM feeds WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $feedId]);
$feed = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$feed) {
    echo json_encode(['success' => false, 'message' => 'Feed not found']);
    exit;
}

// Handle image upload if exists
$imagePath = null;
if ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($image['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Image upload error']);
        exit;
    }

    if ($image['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image size exceeds 2MB']);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
    if (!in_array($image['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image type']);
        exit;
    }

    $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/comment-images/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($image['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded image']);
        exit;
    }

    $imagePath = $filename;
}

// Prepare data array for insertion
$data = [
    'feed_id' => $feedId,
    'user_id' => $userId,
    'comment' => $commentText,
    'image_path' => $imagePath,
];

try {
    if ($commentId) {
        // Insert reply
        $sql = "INSERT INTO comment_replies (feed_id, user_id, comment, comment_id, image_path, created_at)
                VALUES (:feed_id, :user_id, :comment, :comment_id, :image_path, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':feed_id' => $data['feed_id'],
            ':user_id' => $data['user_id'],
            ':comment' => $data['comment'],
            ':comment_id' => $commentId,
            ':image_path' => $data['image_path'],
        ]);
    } else {
        // Insert top-level comment
        $sql = "INSERT INTO feed_comments (feed_id, user_id, comment, image_path, created_at)
                VALUES (:feed_id, :user_id, :comment, :image_path, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':feed_id' => $data['feed_id'],
            ':user_id' => $data['user_id'],
            ':comment' => $data['comment'],
            ':image_path' => $data['image_path'],
        ]);
    }

    // Fetch username for notification message
    $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $username = $user['username'] ?? $firstName;

    // Send notification if commenter is not feed owner
    if ($feed['owner'] != $userId) {
        $notifSql = "INSERT INTO notifications 
            (user_id, title, message, type, created_at)
            VALUES (:user_id, :title, :message, :type, NOW())";

        $notifStmt = $pdo->prepare($notifSql);
        $notifStmt->execute([
            ':user_id' => $feed['owner'],
            ':title' => 'Someone commented on your post',
            ':message' => "$username commented on your post.",
            ':type' => 'comment'
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Comment posted successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
