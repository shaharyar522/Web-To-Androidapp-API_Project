<?php
header('Content-Type: application/json');
require_once 'connection.php'; // This should define $pdo (a PDO instance)

$response = [
    'status' => false,
    'message' => '',
    'data' => null
];

// Ensure only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

// Required parameters
$process_type = $_POST['process_type'] ?? '';
$job_id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Validate job ID
if ($job_id <= 0) {
    $response['message'] = 'Invalid or missing job ID.';
    echo json_encode($response);
    exit;
}

// ==============================
// 1️⃣ FETCH REFERRAL JOB DETAILS
// ==============================
if ($process_type === 'edit') {
    try {
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
        $stmt->execute([':id' => $job_id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $response['message'] = 'Referral job not found.';
        } else {
            // Replace IDs with human-readable titles
            $job['industry'] = $job['industry_name'];
            $job['speciality'] = $job['speciality_name'];
            $job['practice_area'] = $job['practice_area_name'];
            $job['job_state'] = $job['job_state_name'];
            $job['job_city'] = $job['job_city_name'];

            unset(
                $job['industry_name'],
                $job['speciality_name'],
                $job['practice_area_name'],
                $job['job_state_name'],
                $job['job_city_name']
            );

            $response['status'] = true;
            $response['message'] = 'Referral job details fetched successfully.';
            $response['data'] = $job;
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// ==============================
// 2️⃣ UPDATE REFERRAL JOB DETAILS
// ==============================
if ($process_type === 'update') {
    try {
        // Check that it's a referral job
        $checkStmt = $pdo->prepare("SELECT id FROM eq_jobs WHERE id = :id AND job_type = 'referral' LIMIT 1");
        $checkStmt->execute([':id' => $job_id]);

        if ($checkStmt->rowCount() === 0) {
            $response['message'] = 'Referral job not found.';
            echo json_encode($response);
            exit;
        }

        // Prepare update query
        $sql = "UPDATE eq_jobs SET 
                    title = :title,
                    industry = :industry,
                    speciality = :speciality,
                    practice_area = :practice_area,
                    job_city = :job_city,
                    job_state = :job_state,
                    position = :position,
                    owner = :owner,
                    descriptions = :description,
                    firm = :firm,
                    is_active = :is_active,
                    salary = :salary
                WHERE id = :id AND job_type = 'referral'";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':title' => $_POST['title'] ?? '',
            ':industry' => $_POST['industry'] ?? '',
            ':speciality' => $_POST['speciality'] ?? '',
            ':practice_area' => $_POST['practice_area'] ?? '',
            ':job_city' => $_POST['job_city'] ?? '',
            ':job_state' => $_POST['job_state'] ?? '',
            ':position' => $_POST['position'] ?? '',
            ':owner' => $_POST['owner'] ?? '',
            ':description' => $_POST['description'] ?? '',
            ':firm' => $_POST['firm'] ?? '',
            ':is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0,
            ':salary' => $_POST['salary'] ?? '',
            ':id' => $job_id
        ]);

        // Fetch updated record
        $stmt = $pdo->prepare("SELECT * FROM eq_jobs WHERE id = :id AND job_type = 'referral' LIMIT 1");
        $stmt->execute([':id' => $job_id]);
        $updatedJob = $stmt->fetch(PDO::FETCH_ASSOC);

        $response['status'] = true;
        $response['message'] = 'Referral job updated successfully.';
        $response['data'] = $updatedJob;

    } catch (PDOException $e) {
        $response['message'] = 'Update failed: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// ==============================
// 🔴 Fallback for unknown process
// ==============================
$response['message'] = 'Invalid process type. Use "edit" or "update" only.';
echo json_encode($response);
exit;
