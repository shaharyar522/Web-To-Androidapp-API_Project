<?php
header('Content-Type: application/json');
require_once 'connection.php'; // This should define $pdo (PDO instance)

$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

// Get and validate user ID
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$type = $_POST['process_type'] ?? '';

if ($id <= 0) {
    $response['message'] = 'Invalid user ID.';
    echo json_encode($response);
    exit;
}

if ($type !== 'edit_profile') {
    $response['message'] = 'Invalid or missing process_type.';
    echo json_encode($response);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit;
    }

    // Sanitize inputs
    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $bio             = trim($_POST['bio'] ?? '');
    $location        = trim($_POST['location'] ?? '');
    $practice_area   = trim($_POST['practice_area'] ?? '[]'); // JSON string
    $speciality      = trim($_POST['speciality'] ?? '[]');    // JSON string
    $profile_picture = trim($_POST['profile_picture'] ?? '');

    // Check if username is taken by someone else
    if (!empty($username)) {
        $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkUsername->execute([$username, $id]);

        if ($checkUsername->rowCount() > 0) {
            $response['message'] = 'Username already taken. Please choose another.';
            echo json_encode($response);
            exit;
        }
    }

    // Update user record
    $updateQuery = "
        UPDATE users SET 
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            username = :username,
            bio = :bio,
            location = :location,
            practice_area = :practice_area,
            speciality = :speciality,
            profile_picture = :profile_picture
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute([
        ':first_name'      => $first_name,
        ':last_name'       => $last_name,
        ':email'           => $email,
        ':username'        => $username,
        ':bio'             => $bio,
        ':location'        => $location,
        ':practice_area'   => $practice_area,
        ':speciality'      => $speciality,
        ':profile_picture' => $profile_picture,
        ':id'              => $id
    ]);

    // Fetch updated user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['status'] = true;
    $response['message'] = 'Profile updated successfully.';
    $response['data'] = $updatedUser;

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
