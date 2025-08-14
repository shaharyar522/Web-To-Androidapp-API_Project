<?php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method is allowed"]);
    exit();
}

require_once 'conn.php';
require_once 'config.php';

// ===== Get deal_id =====
$deal_id = null;
$rawInput = file_get_contents("php://input");
$jsonInput = json_decode($rawInput, true);

if (json_last_error() === JSON_ERROR_NONE && isset($jsonInput['id'])) {
    $deal_id = intval($jsonInput['id']);
} elseif (isset($_POST['id'])) {
    $deal_id = intval($_POST['id']);
}

if (!$deal_id) {
    echo json_encode(["status" => false, "message" => "Deal ID is required"]);
    exit();
}

try {
    // ===== Fetch main deal =====
    $stmt = $conn->prepare("SELECT * FROM deals WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $deal_id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        echo json_encode(["status" => false, "message" => "Deal not found"]);
        exit();
    }

    // Decode JSON fields for main deal
    $deal['tags'] = !empty($deal['tags']) ? json_decode($deal['tags'], true) : [];
    $deal['photos'] = !empty($deal['photos']) ? json_decode($deal['photos'], true) : [];
    $deal['is_deleted'] = !is_null($deal['deleted_at']);

    // ===== Return response (only main deal) =====
    echo json_encode([
        "status" => true,
        "message" => "Deal details fetched successfully",
        "data" => [
            "deal" => $deal
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching deal: " . $e->getMessage()
    ]);
}
?>
