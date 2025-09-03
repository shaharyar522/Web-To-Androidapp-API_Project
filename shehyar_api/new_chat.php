<?php
session_start();
require_once 'connection.php';

// Use user_id from session or fallback to 1 temporarily
$user_id = $_SESSION['user_id'] ?? 1;

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input
$receiver_id = $_POST['receiver_id'] ?? null;
$message_text = $_POST['message'] ?? null;

// Validate required fields
if (!$receiver_id || !$message_text) {
    echo json_encode(['status' => false, 'message' => 'receiver_id and message are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if chat exists
    $stmt = $pdo->prepare("
        SELECT * FROM chats
        WHERE (sender_id = :user AND receiver_id = :receiver)
           OR (sender_id = :receiver AND receiver_id = :user)
        LIMIT 1
    ");
    $stmt->execute(['user' => $user_id, 'receiver' => $receiver_id]);
    $chat = $stmt->fetch();

    if (!$chat) {
        // Create new chat
        $stmt = $pdo->prepare("INSERT INTO chats (sender_id, receiver_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $receiver_id]);
        $chat_id = $pdo->lastInsertId();
    } else {
        $chat_id = $chat['id'];
    }

    // Handle file upload (optional)
    $uploadDir = __DIR__ . '/uploads/message-file/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $file = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['video'] ?? null;
    $filename = null;
    $message_type = 'text';

    if ($file && is_uploaded_file($file['tmp_name'])) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $mime = mime_content_type($file['tmp_name']);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            if (strpos($mime, 'image') === 0) {
                $message_type = 'image';
            } elseif (strpos($mime, 'video') === 0) {
                $message_type = 'video';
            } else {
                $message_type = 'file';
            }
        }
    }

    // Save message
    $stmt = $pdo->prepare("
        INSERT INTO messages (chat_id, sender_id, receiver_id, message_text, file_path, message_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $chat_id,
        $user_id,
        $receiver_id,
        $message_text,
        $filename,
        $message_type
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Message sent successfully',
        'data' => [
            'chat_id' => $chat_id,
            'sender_id' => $user_id,
            'receiver_id' => $receiver_id,
            'message_text' => $message_text,
            'file_path' => $filename,
            'message_type' => $message_type
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
