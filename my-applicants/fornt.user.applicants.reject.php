<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "connection.php"; // adjust DB connection

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "success" => false,
            "message" => "Only POST method allowed"
        ]);
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST; // fallback for form-data
    }

    // ✅ Case 1: Update reject status if parameters exist
    if (!empty($input['id']) && isset($input['status'])) {
        $applicationId = intval($input['id']);
        $statusFlag = intval($input['status']);

        // match controller flow: rejected → "Rejected", otherwise → "Pending"
        $statusText = $statusFlag === 1 ? 'Rejected' : 'Pending';

        $sql = "UPDATE job_applications 
                SET is_rijected = :flag, status = :statusText 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(":flag", $statusFlag, PDO::PARAM_INT);
        $stmt->bindValue(":statusText", $statusText, PDO::PARAM_STR);
        $stmt->bindValue(":id", $applicationId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Rejected status updated successfully.",
                "application_id" => $applicationId,
                "is_rejected" => $statusFlag,
                "status" => $statusText
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to update rejected status"
            ]);
        }
        exit();
    }

    // ✅ Case 2: No params → return applicants list
    $perPage = isset($input['per_page']) ? intval($input['per_page']) : 10;
    $page = isset($input['page']) ? intval($input['page']) : 1;
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT id, applicant_id, job_id, is_shortlisted, is_rijected,
                   status, apply_date
            FROM job_applications
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count total
    $total = $conn->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();

    echo json_encode([
        "success" => true,
        "message" => "Applicants list",
        "total" => intval($total),
        "page" => $page,
        "per_page" => $perPage,
        "data" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
