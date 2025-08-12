<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'conn.php'; // your DB connection

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method. Use POST only."
    ]);
    exit();
}

// Get input JSON
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON input."
    ]);
    exit();
}

function getParam($key, $default = null) {
    global $input;
    return isset($input[$key]) && $input[$key] !== '' ? trim($input[$key]) : $default;
}

// Required fields
$title = getParam('title');
$descriptions = getParam('descriptions');
$user_id = getParam('user_id'); // owner id (assume authenticated user id or passed)

if (!$title || !$descriptions || !$user_id || !is_numeric($user_id)) {
    echo json_encode([
        "status" => false,
        "message" => "Missing or invalid required parameters: title, descriptions, user_id."
    ]);
    exit();
}

// Prepare insert
$sql = "INSERT INTO deal_sheets (title, descriptions, user_id, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);

try {
    $stmt->execute([$title, $descriptions, (int)$user_id]);
    $insertedId = $conn->lastInsertId();

    // Fetch inserted record for response
    $sqlFetch = "SELECT ds.id, ds.title, ds.descriptions, ds.user_id, u.first_name, u.last_name, ds.created_at 
                 FROM deal_sheets ds
                 LEFT JOIN users u ON ds.user_id = u.id
                 WHERE ds.id = ?";
    $stmtFetch = $conn->prepare($sqlFetch);
    $stmtFetch->execute([$insertedId]);
    $data = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "Deal sheet created successfully.",
        "data" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
