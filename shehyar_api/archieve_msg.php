<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

// Simulate authentication (user_id = 1 if not set)
$session_user_id = $_SESSION['user_id'] ?? 1;

// Optional override to act as sender
$acting_user_id = $_POST['sender_id'] ?? $session_user_id;

// Get input
$receiver_id = $_POST['receiver_id'] ?? null;
$is_archive = isset($_POST['is_archive']) ? (int)$_POST['is_archive'] : null;

// Validate input
if (!$receiver_id || !in_array($is_archive, [0, 1])) {
    echo json_encode(['status' => false, 'message' => 'receiver_id and is_archive (0 or 1) are required']);
    exit;
}

try {
    // Check if receiver exists
    $checkReceiver = $pdo->prepare("SELECT id FROM users WHERE id = :receiver_id");
    $checkReceiver->execute(['receiver_id' => $receiver_id]);
    if (!$checkReceiver->fetch()) {
        echo json_encode(['status' => false, 'message' => 'Receiver user not found']);
        exit;
    }

    // Fetch the chat between the two users
    $stmt = $pdo->prepare("
        SELECT * FROM chats
        WHERE (sender_id = :user_id AND receiver_id = :receiver_id)
           OR (sender_id = :receiver_id AND receiver_id = :user_id)
        LIMIT 1
    ");
    $stmt->execute([
        'user_id' => $acting_user_id,
        'receiver_id' => $receiver_id
    ]);

    $chat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chat) {
        echo json_encode(['status' => false, 'message' => 'Chat not found']);
        exit;
    }

    // Determine archive field
    if ((int)$chat['sender_id'] === (int)$acting_user_id) {
        $field = 'is_archived_by_sender';
    } elseif ((int)$chat['receiver_id'] === (int)$acting_user_id) {
        $field = 'is_archived_by_receiver';
    } else {
        echo json_encode(['status' => false, 'message' => 'You are not part of this chat']);
        exit;
    }

    // Update archive flag
    $updateStmt = $pdo->prepare("UPDATE chats SET $field = :is_archive WHERE id = :chat_id");
    $updateStmt->execute([
        'is_archive' => $is_archive,
        'chat_id' => $chat['id']
    ]);

    // Optionally log this change (optional feature)
    // $pdo->prepare("INSERT INTO archive_logs (chat_id, user_id, field, action, created_at) VALUES (:chat_id, :user_id, :field, :action, NOW())")
    //     ->execute([
    //         'chat_id' => $chat['id'],
    //         'user_id' => $acting_user_id,
    //         'field' => $field,
    //         'action' => $is_archive ? 'archive' : 'unarchive'
    //     ]);

    echo json_encode([
        'status' => true,
        'message' => $is_archive
            ? "Chat archived successfully as " . ($field === 'is_archived_by_sender' ? 'sender' : 'receiver')
            : "Chat unarchived successfully as " . ($field === 'is_archived_by_sender' ? 'sender' : 'receiver')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
