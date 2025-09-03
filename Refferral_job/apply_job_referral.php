<?php
header('Content-Type: application/json');

// ===== Only Allow POST =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method is allowed"]);
    exit();
}

// ===== Database Connection =====
require_once 'connection.php'; // $pdo must be defined here

// ===== Get POST params =====
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 1;
$job_id  = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if (!$user_id || !$job_id) {
    echo json_encode(['status' => false, 'message' => 'Missing user_id or job_id.']);
    exit;
}

try {
    // ===== Check if job exists and is of type 'referral' =====
    $stmt = $pdo->prepare("SELECT * FROM eq_jobs WHERE id = :job_id AND LOWER(job_type) = 'referral' LIMIT 1");
    $stmt->execute([':job_id' => $job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode(['status' => false, 'message' => 'Referral job not found or invalid.']);
        exit;
    }

    $owner_id = (int)$job['owner'];

    // ===== Check if user already applied =====
    $check = $pdo->prepare("SELECT id FROM job_applications WHERE applicant_id = :user_id AND job_id = :job_id");
    $check->execute([':user_id' => $user_id, ':job_id' => $job_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'status' => false,
            'message' => 'You have already applied to this referral job.',
            'application_id' => $existing['id']
        ]);
        exit;
    }

    // ===== Insert Application =====
    $apply_date = date('Y-m-d H:i:s');
    $insert = $pdo->prepare("
        INSERT INTO job_applications (owner_id, applicant_id, job_id, apply_date)
        VALUES (:owner_id, :user_id, :job_id, :apply_date)
    ");
    $insert->execute([
        ':owner_id' => $owner_id,
        ':user_id' => $user_id,
        ':job_id' => $job_id,
        ':apply_date' => $apply_date
    ]);

    echo json_encode([
        'status' => true,
        'message' => 'Application submitted successfully!',
        'data' => [
            'application_id' => $pdo->lastInsertId(),
            'job_id' => $job_id,
            'user_id' => $user_id,
            'apply_date' => $apply_date
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
