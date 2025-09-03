<?php
// store_job.php
header('Content-Type: application/json');
require_once 'connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/PHP-Esqify-Project/config.php';

$result = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Only accept POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['process_type'] ?? '';

    if ($type === 'job_details') {
        $title = $_POST['title'] ?? '';
        $industry = $_POST['industry'] ?? '';
        $speciality = $_POST['speciality'] ?? '';
        $practice_area = $_POST['practice_area'] ?? '';
        $job_city = $_POST['job_city'] ?? '';
        $job_state = $_POST['job_state'] ?? '';
        $position = $_POST['position'] ?? '';
        $owner = $_POST['owner'] ?? '';
        $description = $_POST['description'] ?? '';
        $firm = $_POST['firm'] ?? '';
        $salary = $_POST['salary'] ?? '';
        $posted_date = date('Y-m-d H:i:s');

        // Build raw SQL query
        $query = "
            INSERT INTO eq_jobs (
                title, job_type, industry, speciality, practice_area,
                job_city, job_state, position, owner, descriptions,
                firm, salary, posted_date
            ) VALUES (
                '$title', 'regular', '$industry', '$speciality', '$practice_area',
                '$job_city', '$job_state', '$position', '$owner', '$description',
                '$firm', '$salary', '$posted_date'
            )
        ";

        // Run the query
        if (mysqli_query($conn, $query)) {
            $result['status'] = true;
            $result['message'] = 'Job successfully saved.';
            $result['data'] = ['id' => mysqli_insert_id($conn)];
        } else {
            $result['message'] = 'Error inserting job: ' . mysqli_error($conn);
        }
    } else {
        $result['message'] = 'Invalid process type.';
    }
} else {
    $result['message'] = 'Invalid request method.';
}

echo json_encode($result);


?>




