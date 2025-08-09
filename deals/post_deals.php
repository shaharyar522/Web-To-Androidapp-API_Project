<?php
header("Content-Type: application/json");

// ===== Only Allow POST =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method is allowed"]);
    exit();
}


// ===== Database Connection =====
require_once 'conn.php';

// ===== Read & Validate Input =====
$data = json_decode(file_get_contents("php://input"), true);

// Required fields
$required_fields = ["title", "owner", "state", "industry", "practice_area", "speciality"];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === "") {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields: " . implode(", ", $missing_fields)
    ]);
    exit();
}

// ===== Prepare Insert Query =====
$sql = "INSERT INTO deals 
(title, owner, state, industry, practice_area, speciality, descriptions, created_at) 
VALUES 
(:title, :owner, :state, :industry, :practice_area, :speciality, :descriptions, NOW())";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(":title", trim($data["title"]), PDO::PARAM_STR);
    $stmt->bindValue(":owner", intval($data["owner"]), PDO::PARAM_INT);
    $stmt->bindValue(":state", intval($data["state"]), PDO::PARAM_INT);
    $stmt->bindValue(":industry", intval($data["industry"]), PDO::PARAM_INT);
    $stmt->bindValue(":practice_area", intval($data["practice_area"]), PDO::PARAM_INT);
    $stmt->bindValue(":speciality", intval($data["speciality"]), PDO::PARAM_INT);
    $stmt->bindValue(":descriptions", isset($data["description"]) ? trim($data["descriptions"]) : null, PDO::PARAM_STR);

    $stmt->execute();

    echo json_encode([
        "status" => true,
        "message" => "Deal posted successfully",
        "deal_id" => $conn->lastInsertId()
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error posting deal: " . $e->getMessage()
    ]);
}
?>
