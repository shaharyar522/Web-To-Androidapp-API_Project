<?php
header("Content-Type: application/json");

// ===== Only Allow POST or PUT =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["status" => false, "message" => "Only POST or PUT method is allowed"]);
    exit();
}

// ===== Database Connection =====
require_once 'conn.php';


// ===== Get JSON Data =====
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit();
}

// ===== Required: Deal ID =====
$deal_id = isset($data['id']) ? intval($data['id']) : null;
if (!$deal_id) {
    echo json_encode(["status" => false, "message" => "Deal ID is required"]);
    exit();
}

// ===== Allowed fields for update =====
$allowedFields = [
    'title',
    'descriptions',
    'notes',
    'press_release_link',
    'tags',
    'photos',
    'amount',
    'owner',
    'firm',
    'posted_date',
    'status',
    'client',
    'industry',
    'company_name',
    'state',
    'city',
    'practice_area',
    'speciality',
    'other_attorneys'
];

// ===== Build SET part dynamically =====
$setParts = [];
$params = [];

foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) { // allow null values too
        $setParts[] = "$field = ?";
        $params[] = $data[$field];
    }
}

if (empty($setParts)) {
    echo json_encode(["status" => false, "message" => "No valid fields to update"]);
    exit();
}

$setSql = implode(", ", $setParts);

// ===== Check if deal exists =====
try {
    $checkSql = "SELECT COUNT(*) FROM deals WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$deal_id]);

    if ($checkStmt->fetchColumn() == 0) {
        echo json_encode(["status" => false, "message" => "Deal not found"]);
        exit();
    }

    // ===== Update Deal =====
    $params[] = $deal_id;
    $sql = "UPDATE deals SET $setSql, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => true, "message" => "Deal updated successfully"]);
    } else {
        echo json_encode(["status" => true, "message" => "No changes made"]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Error updating deal: " . $e->getMessage()]);
}
