<?php
// view_referral_job.php

header('Content-Type: application/json');
include_once 'conn.php'; // your PDO connection

if($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Optional user_id filter
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    try {
        if ($user_id > 0) {
            // Fetch jobs referred to this user
            $sql = "SELECT j.id, j.title, j.job_type, j.descriptions, j.notes, j.owner, 
                           j.firm, j.industry, j.speciality, j.practice_area, j.job_city,
                           j.job_state, j.position, j.salary, j.tags, j.referred_by,
                           j.posted_date, j.representative, j.status, j.is_active
                    FROM eq_jobs j
                    WHERE j.referred_by = :user_id AND j.is_active = 1
                    ORDER BY j.posted_date DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        } else {
            // Fetch all active jobs
            $sql = "SELECT j.id, j.title, j.job_type, j.descriptions, j.notes, j.owner, 
                           j.firm, j.industry, j.speciality, j.practice_area, j.job_city,
                           j.job_state, j.position, j.salary, j.tags, j.referred_by,
                           j.posted_date, j.representative, j.status, j.is_active
                    FROM eq_jobs j
                    WHERE j.is_active = 1
                    ORDER BY j.posted_date DESC";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($jobs) {
            echo json_encode([
                'status' => true,
                'data' => $jobs
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => 'No jobs found.'
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'status' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>
