<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

// Expected POST params: follower_id, unfollowed_id
$follower_id = $_POST['follower_id'] ?? null;
$unfollowed_id = $_POST['unfollowed_id'] ?? null;

if (!$follower_id || !$unfollowed_id) {
    $response['message'] = 'Both follower_id and unfollowed_id are required.';
    echo json_encode($response);
    exit;
}

try {
    require_once 'connection.php'; // Your PDO $pdo

    // Delete the follow relation
    $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->execute([$follower_id, $unfollowed_id]);

    $response['status'] = true;
    $response['message'] = 'Unfollowed successfully.';
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
