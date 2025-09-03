<?php
// Set content type to JSON
header('Content-Type: application/json');
require_once 'connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/PHP-Esqify-Project/config.php';

// Fake user authentication (replace with your actual logic)
$user_id = 123; // Assume logged-in user ID, or get it from session/cookie/token

if (!$user_id) {
    echo json_encode(['status' => false, 'message' => 'You have to login first!']);
    exit;
}

// Get job_id from POST
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if (!$job_id) {
    echo json_encode(['status' => false, 'message' => 'Job ID not found!']);
    exit;
}

// Check if job exists
$jobQuery = $conn->query("SELECT * FROM eq_jobs WHERE id = $job_id");
if (!$jobQuery || $jobQuery->num_rows == 0) {
    echo json_encode(['status' => false, 'message' => 'Job not found!']);
    exit;
}
$job = $jobQuery->fetch_assoc();

try {
    $owner_id = (int) $job['owner']; // Assuming `owner` is the column name
    $apply_date = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO job_applications (owner_id, applicant_id, job_id, apply_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $owner_id, $user_id, $job_id, $apply_date);
    $stmt->execute();

    echo json_encode(['status' => true, 'message' => 'Application submitted successfully!']);
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Something went wrong while applying. Please try again later. ' . $e->getMessage()
    ]);
}
?>
