<?php
header('Content-Type: application/json');

// Include DB connection
require_once 'connection.php';

// Pagination from GET params
$post_par_page = isset($_GET['post_par_page']) ? (int)$_GET['post_par_page'] : 9;
$people_par_page = isset($_GET['people_par_page']) ? (int)$_GET['people_par_page'] : 9;
$job_par_page = isset($_GET['job_par_page']) ? (int)$_GET['job_par_page'] : 9;

try {
    // Fetch recent posts (feeds)
    $stmt = $conn->prepare("
        SELECT f.id, f.title, f.descriptions, f.posted_date, 
               CONCAT(u.first_name, ' ', u.last_name) AS owner_name
        FROM feeds f
        JOIN users u ON u.id = f.owner
        WHERE f.deleted_at IS NULL
        ORDER BY f.posted_date DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $post_par_page, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch users (people)
    $stmt2 = $conn->prepare("
        SELECT id, username, email, CONCAT(first_name, ' ', last_name) AS full_name
        FROM users
        WHERE deleted_at IS NULL
        ORDER BY id DESC
        LIMIT :limit
    ");
    $stmt2->bindValue(':limit', $people_par_page, PDO::PARAM_INT);
    $stmt2->execute();
    $people = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Fetch jobs
    $stmt3 = $conn->prepare("
        SELECT j.id, j.title, j.owner, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
        FROM eq_jobs j
        JOIN users u ON u.id = j.owner
        WHERE j.deleted_at IS NULL
        ORDER BY j.posted_date DESC
        LIMIT :limit
    ");
    $stmt3->bindValue(':limit', $job_par_page, PDO::PARAM_INT);
    $stmt3->execute();
    $jobs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON
    echo json_encode([
        "status" => true,
        "posts" => $posts,
        "people" => $people,
        "jobs" => $jobs
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
