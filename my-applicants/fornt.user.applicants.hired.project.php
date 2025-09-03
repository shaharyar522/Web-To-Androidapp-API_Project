<?php
header("Content-Type: application/json");

// Database connection
require_once "connection.php"; // <-- update with your PDO connection

// Use Carbon (installed via Composer)
require_once __DIR__ . "/vendor/autoload.php";
use Carbon\Carbon;

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate
if (!isset($input['id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required parameter: id"
    ]);
    exit;
}

$id = (int)$input['id'];

try {
    // Fetch job application
    $stmt = $pdo->prepare("SELECT * FROM job_applications WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        echo json_encode([
            "success" => false,
            "message" => "Application not found"
        ]);
        exit;
    }

    // If not hired yet
    if (empty($application['hired_at'])) {
        $hiredAt = Carbon::now()->toDateTimeString();

        $update = $pdo->prepare("
            UPDATE job_applications 
            SET hired_at = :hired_at, status = 'Hired'
            WHERE id = :id
        ");
        $update->execute([
            'hired_at' => $hiredAt,
            'id'       => $id
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Applicant hired successfully.",
            "application_id" => $id,
            "status" => "Hired",
            "hired_at" => $hiredAt
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "message" => "Applicant already hired.",
            "application_id" => $id,
            "status" => $application['status'],
            "hired_at" => $application['hired_at']
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
