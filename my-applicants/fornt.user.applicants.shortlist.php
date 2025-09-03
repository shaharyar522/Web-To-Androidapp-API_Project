<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "connection.php"; // database connection

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
        exit();
    }

    // ✅ Read input
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST; // fallback for form-data
    }

    // ✅ CASE 1: If application_id is provided → update shortlist
    if (!empty($input['application_id'])) {
        $applicationId = intval($input['application_id']);
        $shortlisted = isset($input['is_shortlisted']) ? intval($input['is_shortlisted']) : 1;

        $sql = "UPDATE job_applications SET is_shortlisted = :shortlisted WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(":shortlisted", $shortlisted, PDO::PARAM_INT);
        $stmt->bindValue(":id", $applicationId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => $shortlisted ? "Applicant shortlisted successfully" : "Applicant removed from shortlist",
                "application_id" => $applicationId,
                "is_shortlisted" => $shortlisted
            ]);
        } else {
            echo json_encode(["status" => false, "message" => "Failed to update shortlist"]);
        }
        exit();
    }

    // ✅ CASE 2: If application_id is missing → return all applicants
    $sql = "SELECT id, applicant_id, job_id, is_shortlisted, is_rijected, status, apply_date 
            FROM job_applications ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "All applicants list",
        "total" => count($applicants),
        "data" => $applicants
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
