<?php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method is allowed"]);
    exit();
}

require_once 'conn.php';
require_once 'config.php';

// ===== Get deal_id from JSON or form-data =====
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

    // ===== Decode JSON fields =====
    $deal['tags'] = !empty($deal['tags']) ? json_decode($deal['tags'], true) : [];
    $deal['photos'] = !empty($deal['photos']) ? json_decode($deal['photos'], true) : [];
    $deal['other_attorneys_ids'] = !empty($deal['other_attorneys']) ? json_decode($deal['other_attorneys'], true) : [];
    $deal['is_deleted'] = !is_null($deal['deleted_at']);

    // ===== Fetch other attorneys for main deal =====
    $other_attornies = [];
    if (!empty($deal['other_attorneys_ids'])) {
        $placeholders = implode(',', array_fill(0, count($deal['other_attorneys_ids']), '?'));
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)");
        $stmt->execute($deal['other_attorneys_ids']);
        $other_attornies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== Function to fetch related deals =====
    function fetchRelatedDeals($conn, $condition, $params, $exclude_id) {
        $sql = "
            SELECT * FROM deals 
            WHERE $condition
              AND id <> :exclude_id
              AND deleted_at IS NULL
              AND title IS NOT NULL
              AND descriptions IS NOT NULL
              AND photos IS NOT NULL
              AND amount IS NOT NULL
            ORDER BY posted_date DESC
            LIMIT 4
        ";
        $stmt = $conn->prepare($sql);
        $params['exclude_id'] = $exclude_id;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== Try practice_area match =====
    $deals = [];
    if (!empty($deal['practice_area'])) {
        $deals = fetchRelatedDeals($conn, "practice_area = :practice_area", [
            'practice_area' => $deal['practice_area']
        ], $deal_id);
    }

    // ===== If no results, try industry match =====
    if (empty($deals) && !empty($deal['industry'])) {
        $deals = fetchRelatedDeals($conn, "industry = :industry", [
            'industry' => $deal['industry']
        ], $deal_id);
    }

    // ===== If still no results, show any complete deals =====
    if (empty($deals)) {
        $deals = fetchRelatedDeals($conn, "1=1", [], $deal_id);
    }

    // ===== Decode fields for related deals =====
    foreach ($deals as &$rdeal) {
        $rdeal['tags'] = !empty($rdeal['tags']) ? json_decode($rdeal['tags'], true) : [];
        $rdeal['photos'] = !empty($rdeal['photos']) ? json_decode($rdeal['photos'], true) : [];
        $rdeal['other_attorneys_ids'] = !empty($rdeal['other_attorneys']) ? json_decode($rdeal['other_attorneys'], true) : [];
        $rdeal['is_deleted'] = !is_null($rdeal['deleted_at']);

        // Fetch other attorneys for related deal
        $rdeal['other_attornies'] = [];
        if (!empty($rdeal['other_attorneys_ids'])) {
            $placeholders = implode(',', array_fill(0, count($rdeal['other_attorneys_ids']), '?'));
            $stmt2 = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)");
            $stmt2->execute($rdeal['other_attorneys_ids']);
            $rdeal['other_attornies'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    unset($rdeal);

    // ===== Return response =====
    echo json_encode([
        "status" => true,
        "message" => "Deal details fetched successfully",
        "data" => [
            "deal" => $deal,
            "other_attornies" => $other_attornies,
            "deals" => $deals
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching deal: " . $e->getMessage()
    ]);
}
?>
