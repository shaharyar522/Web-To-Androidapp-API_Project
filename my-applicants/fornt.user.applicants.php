<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "connection.php"; // adjust path if needed

try {
    // ✅ Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
        exit();
    }

    // ✅ Get input (JSON or form-data)
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST; // fallback
    }

    $params = [];
    $where = " WHERE 1=1 "; // base condition always true

    // ✅ job_id is optional
    if (!empty($input['job_id'])) {
        $where .= " AND job_id = :job_id ";
        $params[':job_id'] = intval($input['job_id']);
    }

    // ✅ Filters (optional)
    if (!empty($input['name'])) {
        $where .= " AND applicant_id IN (
            SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) LIKE :name
        )";
        $params[':name'] = "%" . $input['name'] . "%";
    }

    if (!empty($input['email'])) {
        $where .= " AND applicant_id IN (
            SELECT id FROM users WHERE email = :email
        )";
        $params[':email'] = $input['email'];
    }

    if (!empty($input['phone'])) {
        $where .= " AND applicant_id IN (
            SELECT id FROM users WHERE phone = :phone
        )";
        $params[':phone'] = $input['phone'];
    }

    if (isset($input['shortlisted'])) {
        $where .= " AND is_shortlisted = :shortlisted ";
        $params[':shortlisted'] = $input['shortlisted'];
    }

    if (isset($input['rejected'])) {
        $where .= " AND is_rijected = :rejected ";
        $params[':rejected'] = $input['rejected'];
    }

    if (!empty($input['status'])) {
        $where .= " AND status = :status ";
        $params[':status'] = $input['status'];
    }

    if (!empty($input['from_date']) && !empty($input['to_date'])) {
        $where .= " AND apply_date BETWEEN :from_date AND :to_date ";
        $params[':from_date'] = $input['from_date'] . " 00:00:00";
        $params[':to_date']   = $input['to_date'] . " 23:59:59";
    } elseif (!empty($input['to_date'])) {
        $where .= " AND apply_date <= :to_date ";
        $params[':to_date'] = $input['to_date'] . " 23:59:59";
    }

    // ✅ Sorting
    $sortField = "id";
    $sortOrder = "DESC";
    if (!empty($input['sort_data'])) {
        switch ($input['sort_data']) {
            case "oldest":
                $sortOrder = "ASC";
                break;
            case "ascending":
                $sortField = "title"; $sortOrder = "ASC";
                break;
            case "descending":
                $sortField = "title"; $sortOrder = "DESC";
                break;
        }
    }

    // ✅ Pagination
    $perPage = !empty($input['par_page']) ? intval($input['par_page']) : 10;
    $page = !empty($input['page']) ? intval($input['page']) : 1;
    $offset = ($page - 1) * $perPage;

    // ✅ Count total
    $countSql = "SELECT COUNT(*) FROM job_applications $where";
    $stmtCount = $conn->prepare($countSql);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // ✅ Fetch applicants
    $sql = "SELECT * FROM job_applications $where ORDER BY $sortField $sortOrder LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();

    $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "total" => $total,
        "per_page" => $perPage,
        "current_page" => $page,
        "data" => $applicants
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
