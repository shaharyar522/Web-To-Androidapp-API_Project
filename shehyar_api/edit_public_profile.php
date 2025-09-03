<?php
header('Content-Type: application/json');
require_once 'connection.php'; // Assumes $pdo is configured with ERRMODE_EXCEPTION

$response = ['status' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response);
    exit;
}

// Required for identifying the user
$username = $_POST['username'] ?? '';
if (empty($username)) {
    $response['message'] = 'Username is required.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch the user by username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit;
    }

    $userId = $user['id'];

    // Fields that can be updated
    $fields = [
        'full_name'       => $_POST['full_name'] ?? null,
        'email'           => $_POST['email'] ?? null,
        'bio'             => $_POST['bio'] ?? null,
        'location'        => $_POST['location'] ?? null,
        'practice_area'   => $_POST['practice_area'] ?? null, // JSON string or array
        'speciality'      => $_POST['speciality'] ?? null,    // JSON string or array
        'profile_picture' => $_POST['profile_picture'] ?? null
    ];

    // Prepare dynamic SQL
    $updateFields = [];
    $values = [];

    foreach ($fields as $key => $value) {
        if ($value !== null) {
            $updateFields[] = "$key = ?";
            $values[] = is_array($value) ? json_encode($value) : $value;
        }
    }

    if (empty($updateFields)) {
        $response['message'] = 'No valid fields to update.';
        echo json_encode($response);
        exit;
    }

    // Add user ID to the values array
    $values[] = $userId;

    // Final SQL
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    // Fetch updated user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['status'] = true;
    $response['message'] = 'Profile updated successfully.';
    $response['data'] = $updatedUser;

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
