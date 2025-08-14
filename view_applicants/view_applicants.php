<?php
header('Content-Type: application/json');
include_once 'connection.php';

// Read POST form-data
$id            = isset($_POST['id']) ? intval($_POST['id']) : 0;
$owner_id      = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
$status        = isset($_POST['status']) ? trim($_POST['status']) : '';
$job_id        = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
$applicant_id  = isset($_POST['applicant_id']) ? intval($_POST['applicant_id']) : 0;

// Base SQL query
$sql = "
SELECT 
    ja.id,
    ja.owner_id,
    ja.applicant_id,
    ja.platform_percentage,
    ja.job_id,
    ja.apply_date,
    ja.status,
    ja.is_shortlisted,
    ja.is_rijected,
    ja.hired_at,
    ja.application_step,
    ja.accepted_date,
    ja.completed_at,
    ja.paid_amount,
    ja.payment_at,
    ja.owner_review,
    ja.owner_review_at,
    ja.employee_review,
    ja.employee_review_at,
    ja.created_at,
    ja.updated_at,
    u.username,
    u.first_name,
    u.last_name,
    CONCAT(u.first_name, ' ', u.last_name) AS applicant_name,
    u.email AS applicant_email,
    eq.title AS job_title,
    eq.descriptions AS job_description
FROM job_applications ja
JOIN users u ON u.id = ja.applicant_id
JOIN eq_jobs eq ON eq.id = ja.job_id
WHERE 1=1
";

// Apply filters dynamically
if ($id > 0) {
    $sql .= " AND ja.id = :id";
}
if ($owner_id > 0) {
    $sql .= " AND ja.owner_id = :owner_id";
}
if (!empty($status)) {
    $sql .= " AND ja.status = :status";
}
if ($job_id > 0) {
    $sql .= " AND ja.job_id = :job_id";
}
if ($applicant_id > 0) {
    $sql .= " AND ja.applicant_id = :applicant_id";
}

$sql .= " ORDER BY ja.created_at ASC";

try {
    $stmt = $pdo->prepare($sql);

    // Bind only provided parameters
    if ($id > 0) {
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    }
    if ($owner_id > 0) {
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    }
    if (!empty($status)) {
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    }
    if ($job_id > 0) {
        $stmt->bindParam(':job_id', $job_id, PDO::PARAM_INT);
    }
    if ($applicant_id > 0) {
        $stmt->bindParam(':applicant_id', $applicant_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "total" => count($applicants),
        "data" => $applicants
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
