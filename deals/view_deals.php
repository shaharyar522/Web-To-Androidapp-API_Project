<?php


header("Content-Type: application/json");

// ===== Database Connection =====
require_once 'conn.php';
require_once 'config.php';

// ===== Request Parameters =====
$title = isset($_GET['title']) ? trim($_GET['title']) : null;
$state_for_search = isset($_GET['state_for_search']) ? trim($_GET['state_for_search']) : null;
$bar_for_search = isset($_GET['bar_for_search']) ? trim($_GET['bar_for_search']) : null;

$industries_for_search = isset($_GET['industries_for_search']) ? (array) $_GET['industries_for_search'] : [];
$practice_areas_for_search = isset($_GET['practice_areas_for_search']) ? (array) $_GET['practice_areas_for_search'] : [];
$specialities_areas_for_search = isset($_GET['specialities_areas_for_search']) ? (array) $_GET['specialities_areas_for_search'] : [];

$sort = isset($_GET['sort_data']) ? $_GET['sort_data'] : null;
$per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max($page, 1);
$offset = ($page - 1) * $per_page;

// ===== Base Query =====
$sql_base = "FROM deals d
    LEFT JOIN users u ON u.id = d.owner
    WHERE d.deleted_at IS NULL";

$params = [];

// ===== Filters =====
if (!empty($title)) {
    $sql_base .= " AND (d.title LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params[] = "%{$title}%";
    $params[] = "%{$title}%";
}

if (!empty($state_for_search)) {
    $sql_base .= " AND d.state = ?";
    $params[] = $state_for_search;
}

if (!empty($bar_for_search)) {
    $sql_base .= " AND u.bar = ?";
    $params[] = $bar_for_search;
}

if (!empty($industries_for_search)) {
    $placeholders = implode(',', array_fill(0, count($industries_for_search), '?'));
    $sql_base .= " AND d.industry IN ($placeholders)";
    $params = array_merge($params, $industries_for_search);
}

if (!empty($practice_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($practice_areas_for_search), '?'));
    $sql_base .= " AND d.practice_area IN ($placeholders)";
    $params = array_merge($params, $practice_areas_for_search);
}

if (!empty($specialities_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($specialities_areas_for_search), '?'));
    $sql_base .= " AND d.speciality IN ($placeholders)";
    $params = array_merge($params, $specialities_areas_for_search);
}

// ===== Count Query =====
try {
    $count_sql = "SELECT COUNT(DISTINCT d.id) as total " . $sql_base;
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching count: " . $e->getMessage()
    ]);
    exit();
}

// ===== No records found =====
if ($total == 0) {
    $reason = "There are no records matching that search.";
    if (!empty($industries_for_search)) $reason = "No deals found for selected industry.";
    elseif (!empty($practice_areas_for_search)) $reason = "No deals found for selected practice area.";
    elseif (!empty($specialities_areas_for_search)) $reason = "No deals found for selected speciality.";
    elseif (!empty($state_for_search)) $reason = "No deals found for selected state.";
    elseif (!empty($bar_for_search)) $reason = "No deals found for selected bar.";

    echo json_encode([
        "status" => false,
        "message" => $reason,
        "page" => $page,
        "per_page" => $per_page,
        "total" => 0,
        "total_pages" => 0,
        "data" => []
    ]);
    exit();
}

// ===== Sorting =====
$sortOptions = [
    'latest'     => "d.id DESC",
    'oldest'     => "d.id ASC",
    'ascending'  => "d.title ASC",
    'descending' => "d.title DESC"
];
$orderBy = $sortOptions[$sort] ?? "d.id DESC";

// ===== Data Query =====
$sql = "SELECT d.*,
               CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
               u.email AS owner_email
        " . $sql_base . "
        GROUP BY d.id
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$params_with_limit = array_merge($params, [$per_page, $offset]);

try {
    $stmt = $conn->prepare($sql);
    foreach ($params_with_limit as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "Deals fetched successfully",
        "page" => $page,
        "per_page" => $per_page,
        "total" => (int)$total,
        "total_pages" => ceil($total / $per_page),
        "data" => $deals
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Error fetching deals: " . $e->getMessage()]);
}
?>
