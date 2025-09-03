<?php
header('Content-Type: application/json');
require_once 'connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/PHP-Esqify-Project/config.php';

$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Get job ID from GET or POST
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

// Validate ID
if ($id <= 0) {
    $response['message'] = 'Invalid job ID.';
    echo json_encode($response);
    exit;
}

// Fetch job with joins to get readable names, including job_state and job_city names
$jobQuery = mysqli_query($conn, "
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
    WHERE j.id = $id
    LIMIT 1
");

$job = mysqli_fetch_assoc($jobQuery);

// If job not found
if (!$job) {
    $response['message'] = 'Job not found.';
    echo json_encode($response);
    exit;
}

// Replace IDs with names for better clarity
$job['industry'] = $job['industry_name'];
$job['speciality'] = $job['speciality_name'];
$job['practice_area'] = $job['practice_area_name'];
$job['job_state'] = $job['job_state_name'];
$job['job_city'] = $job['job_city_name'];

// Optional: remove the extra helper fields
unset(
    $job['industry_name'],
    $job['speciality_name'],
    $job['practice_area_name'],
    $job['job_state_name'],
    $job['job_city_name'],
    $job['firm'] // just in case
);

// Final response
$response['status'] = true;
$response['message'] = 'Job data loaded.';
$response['data'] = $job;

echo json_encode($response);
?>



