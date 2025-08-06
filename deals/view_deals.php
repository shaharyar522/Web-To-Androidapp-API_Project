<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once("../config/db.php");

try {
    // Get optional filters
    $title         = isset($_GET['title']) ? trim($_GET['title']) : null;
    $tags          = isset($_GET['tags']) ? trim($_GET['tags']) : null;
    $practice_area = isset($_GET['practice_area']) ? trim($_GET['practice_area']) : null;

    // Base query
    $query = "SELECT 
                id, title, descriptions, notes, press_release_link, tags, photos, 
                amount, owner, firm, posted_date, other_attorneys, client,
                industry, company_name, state, city, practice_area, speciality, 
                status, created_at, updated_at 
              FROM deals 
              WHERE deleted_at IS NULL";

    // Add filters dynamically
    $conditions = [];
    $params = [];

    if (!empty($title)) {
        $conditions[] = "title LIKE :title";
        $params[':title'] = "%$title%";
    }

    if (!empty($tags)) {
        $conditions[] = "tags LIKE :tags";
        $params[':tags'] = "%$tags%";
    }

    if (!empty($practice_area)) {
        $conditions[] = "practice_area LIKE :practice_area";
        $params[':practice_area'] = "%$practice_area%";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY created_at DESC";

    // Prepare and execute
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "Deals fetched successfully",
        "data" => $deals
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch deals: " . $e->getMessage()
    ]);
}
?>
