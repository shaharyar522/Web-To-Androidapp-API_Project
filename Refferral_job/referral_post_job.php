<?php
header('Content-Type: application/json');
require_once 'connection.php'; // This file must define $pdo (PDO instance)

// Default response
$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

// Fetch POST data with null coalescing operator
$title         = $_POST['title']         ?? '';
$industry      = $_POST['industry']      ?? '';
$speciality    = $_POST['speciality']    ?? '';
$practice_area = $_POST['practice_area'] ?? '';
$job_city      = $_POST['job_city']      ?? '';
$job_state     = $_POST['job_state']     ?? '';
$position      = $_POST['position']      ?? '';
$owner         = $_POST['owner']         ?? '';
$description   = $_POST['description']   ?? '';
$firm          = $_POST['firm']          ?? '';
$salary        = $_POST['salary']        ?? '';
$posted_date   = date('Y-m-d H:i:s');
$job_type      = 'referral'; // Hardcoded

// Basic validation (you can expand this as needed)
if (empty($title) || empty($owner) || empty($position)) {
    $response['message'] = 'Title, position, and owner are required.';
    echo json_encode($response);
    exit;
}

try {
    // Prepare SQL using named parameters
    $sql = "
        INSERT INTO eq_jobs (
            title, job_type, industry, speciality, practice_area,
            job_city, job_state, position, owner, descriptions,
            firm, salary, posted_date
        ) VALUES (
            :title, :job_type, :industry, :speciality, :practice_area,
            :job_city, :job_state, :position, :owner, :description,
            :firm, :salary, :posted_date
        )
    ";

    $stmt = $pdo->prepare($sql);

    // Execute with parameters
    $stmt->execute([
        ':title'         => $title,
        ':job_type'      => $job_type,
        ':industry'      => $industry,
        ':speciality'    => $speciality,
        ':practice_area' => $practice_area,
        ':job_city'      => $job_city,
        ':job_state'     => $job_state,
        ':position'      => $position,
        ':owner'         => $owner,
        ':description'   => $description,
        ':firm'          => $firm,
        ':salary'        => $salary,
        ':posted_date'   => $posted_date
    ]);

    // Success response
    $response['status'] = true;
    $response['message'] = 'Referral job successfully saved.';
    $response['data'] = ['job_id' => $pdo->lastInsertId()];
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response);
