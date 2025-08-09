<?php
header("Content-Type: application/json");

// ===== Only Allow GET =====
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET method is allowed"]);
    exit();
}

require_once 'conn.php';

// ===== Get deal ID from query param =====
$deal_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$deal_id) {
    echo json_encode(["status" => false, "message" => "Deal ID is required"]);
    exit();
}

try {
    // Fetch the deal details without filtering deleted_at
    $sql = "SELECT * FROM deals WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$deal_id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        echo json_encode(["status" => false, "message" => "Deal not found"]);
        exit();
    }

    // Optional: add a flag indicating if the deal is deleted
    $deal['is_deleted'] = !is_null($deal['deleted_at']);

    echo json_encode([
        "status" => true,
        "message" => "Deal details fetched successfully",
        "data" => $deal
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Error fetching deal: " . $e->getMessage()]);
}
?>
