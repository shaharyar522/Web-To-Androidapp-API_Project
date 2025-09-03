<?php
header('Content-Type: application/json');
require_once 'connection.php'; // Ensure this defines $pdo (PDO instance)

$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. POST required.';
    echo json_encode($response);
    exit;
}

// Get job ID from POST
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $response['message'] = 'Invalid job ID.';
    echo json_encode($response);
    exit;
}

// Get process_type from POST
$type = $_POST['process_type'] ?? '';

try {
    if ($type === 'get_job') {
        // Fetch referral job details by ID with joined readable fields
        $stmt = $pdo->prepare("
            SELECT 
                j.*, 
                i.title AS industry_name, 
                s.title AS speciality_name, 
                p.title AS practice_area_name,
                st.name AS job_state_name,
                c.name AS job_city_name
            FROM eq_jobs j
            LEFT JOIN industrys i ON j.industry = i.id
            LEFT JOIN specialties s ON j.speciality = s.id
            LEFT JOIN practice_areas p ON j.practice_area = p.id
            LEFT JOIN states st ON j.job_state = st.id
            LEFT JOIN citys c ON j.job_city = c.id
            WHERE j.id = :id AND j.job_type = 'referral'
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $response['message'] = 'Referral job not found.';
            echo json_encode($response);
            exit;
        }

        // Replace IDs with readable names
        $job['industry'] = $job['industry_name'];
        $job['speciality'] = $job['speciality_name'];
        $job['practice_area'] = $job['practice_area_name'];
        $job['job_state'] = $job['job_state_name'];
        $job['job_city'] = $job['job_city_name'];

        // Remove extra joined fields
        unset(
            $job['industry_name'],
            $job['speciality_name'],
            $job['practice_area_name'],
            $job['job_state_name'],
            $job['job_city_name']
        );

        $response['status'] = true;
        $response['message'] = 'Referral job data loaded.';
        $response['data'] = $job;
    } else {
        $response['message'] = 'Invalid process type.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
