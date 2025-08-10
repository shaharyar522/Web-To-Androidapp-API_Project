<?php
header("Content-Type: application/json");

// ===== Allow GET Only =====
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET method is allowed"]);
    exit();
}

// ===== Database Connection =====
$host = "localhost";
$db_name = "esqify_db";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Connection failed: " . $e->getMessage()]);
    exit();
}

// ===== Filters =====
$title = isset($_GET['title']) ? trim($_GET['title']) : null;
$state = isset($_GET['state_for_search']) ? trim($_GET['state_for_search']) : null;
$industries = isset($_GET['industries_for_search']) ? (array) $_GET['industries_for_search'] : [];
$practice_areas = isset($_GET['practice_areas_for_search']) ? (array) $_GET['practice_areas_for_search'] : [];
$specialities = isset($_GET['specialities_areas_for_search']) ? (array) $_GET['specialities_areas_for_search'] : [];
$sort_data = isset($_GET['sort_data']) ? trim($_GET['sort_data']) : null;

// ===== Pagination =====
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
$offset = ($page - 1) * $per_page;

// ===== Base Query =====
$sql = "SELECT u.id, u.first_name, u.last_name, u.law_firm, u.state_province,
               COUNT(d.id) AS deal_volume, 
               COALESCE(SUM(d.amount), 0) AS deal_total
        FROM users u
        INNER JOIN deals d ON d.user_id = u.id
        WHERE u.role_id = 3 
          AND u.deleted_at IS NULL
          AND d.deleted_at IS NULL";

// ===== Dynamic Filters =====
$params = [];

if (!empty($title)) {
    $sql .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.law_firm LIKE ?)";
    $params[] = "%$title%";
    $params[] = "%$title%";
}

if (!empty($state)) {
    $sql .= " AND u.state_province = ?";
    $params[] = $state;
}

if (!empty($industries)) {
    $placeholders = implode(',', array_fill(0, count($industries), '?'));
    $sql .= " AND u.industry IN ($placeholders)";
    $params = array_merge($params, $industries);
}

if (!empty($practice_areas)) {
    foreach ($practice_areas as $area) {
        $sql .= " AND JSON_CONTAINS(u.practice_area, ?)";
        $params[] = json_encode($area);
    }
}

if (!empty($specialities)) {
    foreach ($specialities as $area) {
        $sql .= " AND JSON_CONTAINS(u.speciality, ?)";
        $params[] = json_encode($area);
    }
}

$sql .= " GROUP BY u.id";

// ===== Sorting =====
if ($sort_data === 'deal_volume') {
    $sql .= " ORDER BY deal_volume DESC";
} elseif ($sort_data === 'deal_total') {
    $sql .= " ORDER BY deal_total DESC";
} elseif ($sort_data === 'latest') {
    $sql .= " ORDER BY u.id DESC";
} elseif ($sort_data === 'oldest') {
    $sql .= " ORDER BY u.id ASC";
} elseif ($sort_data === 'ascending') {
    $sql .= " ORDER BY u.first_name ASC";
} elseif ($sort_data === 'descending') {
    $sql .= " ORDER BY u.first_name DESC";
} else {
    $sql .= " ORDER BY u.id DESC";
}

// ===== Pagination Limit =====
$sql .= " LIMIT $per_page OFFSET $offset";

// ===== Execute Query =====
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Get Total Records =====
$count_sql = "SELECT COUNT(DISTINCT u.id) 
              FROM users u
              INNER JOIN deals d ON d.user_id = u.id
              WHERE u.role_id = 3 AND u.deleted_at IS NULL AND d.deleted_at IS NULL";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute();
$total = $count_stmt->fetchColumn();

// ===== Output =====
echo json_encode([
    "status" => true,
    "message" => "Leaderboard fetched successfully",
    "total" => intval($total),
    "per_page" => $per_page,
    "current_page" => $page,
    "last_page" => ceil($total / $per_page),
    "data" => $data
], JSON_PRETTY_PRINT);
