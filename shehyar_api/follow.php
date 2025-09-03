<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'data' => null];

$follower_id = $_POST['follower_id'] ?? null;
$followed_id = $_POST['followed_id'] ?? null;

if (!$follower_id || !$followed_id) {
    $response['message'] = 'Both follower_id and followed_id are required.';
    echo json_encode($response);
    exit;
}

if ($follower_id == $followed_id) {
    $response['message'] = 'You cannot follow yourself.';
    echo json_encode($response);
    exit;
}

try {
    require_once 'connection.php'; // your PDO $pdo

    // Insert follow relationship with created_at and updated_at timestamps
    // Use NOW() for current timestamp
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO follows (follower_id, followed_id, created_at, updated_at) 
        VALUES (?, ?, NOW(), NOW())
    ");
    $stmt->execute([$follower_id, $followed_id]);

    $response['status'] = true;
    $response['message'] = 'Followed successfully.';
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
