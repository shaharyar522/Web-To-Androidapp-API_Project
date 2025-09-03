<?php
// fornt.user.applicants.hired.project.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "connection.php"; // your PDO connection

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "message" => "Only POST method allowed"]);
        exit();
    }

    // read input (JSON or form-data)
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;

    // CASE 1: If application_id provided -> perform hire logic (matches controller->project($id))
    if (!empty($input['application_id'])) {
        $applicationId = intval($input['application_id']);

        // Fetch existing application
        $stmt = $conn->prepare("SELECT * FROM job_applications WHERE id = :id LIMIT 1");
        $stmt->bindValue(":id", $applicationId, PDO::PARAM_INT);
        $stmt->execute();
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            echo json_encode(["success" => false, "message" => "Application not found"]);
            exit();
        }

        // If hired_at is NULL -> set hired_at, set status='Hired' and save
        if (empty($application['hired_at'])) {
            $now = date('Y-m-d H:i:s');

            $updateSql = "UPDATE job_applications 
                          SET hired_at = :hired_at, status = :status 
                          WHERE id = :id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindValue(":hired_at", $now, PDO::PARAM_STR);
            $updateStmt->bindValue(":status", "Hired", PDO::PARAM_STR);
            $updateStmt->bindValue(":id", $applicationId, PDO::PARAM_INT);
            $ok = $updateStmt->execute();

            if (!$ok) {
                echo json_encode(["success" => false, "message" => "Failed to mark application as hired"]);
                exit();
            }

            // Optional: load owner name and job title for notification text
            // safe left joins to fetch owner first_name and job title
            $joinSql = "
                SELECT ja.*, u.first_name AS owner_first_name, j.title AS job_title
                FROM job_applications ja
                LEFT JOIN users u ON u.id = ja.owner_id
                LEFT JOIN eq_jobs j ON j.id = ja.job_id
                WHERE ja.id = :id
                LIMIT 1
            ";
            $joinStmt = $conn->prepare($joinSql);
            $joinStmt->bindValue(":id", $applicationId, PDO::PARAM_INT);
            $joinStmt->execute();
            $updatedApp = $joinStmt->fetch(PDO::FETCH_ASSOC);

            // PLACEHOLDER: call your addNotification function here (controller used addNotification)
            // Example (uncomment & replace with your actual function if available):
            // addNotification(
            //     $updatedApp['applicant_id'],
            //     'You are hired for a job',
            //     $updatedApp['owner_first_name'] . ' hired you for ' . $updatedApp['job_title'],
            //     'job_application',
            //     $authUserId, // supply auth()->id() or current user id here
            //     'JobApplication',
            //     $applicationId
            // );

            // Return updated application data
            echo json_encode([
                "success" => true,
                "message" => "Application marked as hired.",
                "application" => $updatedApp
            ]);
            exit();
        }

        // If already hired, return the current application (no change)
        // Fetch joined data for response
        $joinSql = "
            SELECT ja.*, u.first_name AS owner_first_name, j.title AS job_title
            FROM job_applications ja
            LEFT JOIN users u ON u.id = ja.owner_id
            LEFT JOIN eq_jobs j ON j.id = ja.job_id
            WHERE ja.id = :id
            LIMIT 1
        ";
        $joinStmt = $conn->prepare($joinSql);
        $joinStmt->bindValue(":id", $applicationId, PDO::PARAM_INT);
        $joinStmt->execute();
        $existing = $joinStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "message" => "Application already hired.",
            "application" => $existing
        ]);
        exit();
    }

    // CASE 2: No application_id -> return paginated applicants list (same as index listing behavior)
    $perPage = isset($input['per_page']) ? max(1, intval($input['per_page'])) : 10;
    $page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
    $offset = ($page - 1) * $perPage;

    // Basic listing (you can add filters later to match index() filters)
    $listSql = "
        SELECT id, applicant_id, job_id, is_shortlisted, is_rijected, hired_at, status, apply_date
        FROM job_applications
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ";
    $listStmt = $conn->prepare($listSql);
    $listStmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $apps = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // total count
    $total = (int)$conn->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();

    echo json_encode([
        "success" => true,
        "message" => "Applicants list",
        "total" => $total,
        "page" => $page,
        "per_page" => $perPage,
        "data" => $apps
    ]);
    exit();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}
