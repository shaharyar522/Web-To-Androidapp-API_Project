<?php
header("Content-Type: application/json");

// Enable error reporting (for debugging only; disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dev_mode = true; // Set to false in production

// ===== Database Connection =====
require_once 'conn.php';
require_once 'config.php';

// ===== Request Parameters =====
$title = isset($_GET['title']) ? trim($_GET['title']) : null;
$state_for_search = isset($_GET['state_for_search']) ? trim($_GET['state_for_search']) : null;

$industries_for_search = isset($_GET['industries_for_search']) ? (array) $_GET['industries_for_search'] : [];
$practice_areas_for_search = isset($_GET['practice_areas_for_search']) ? (array) $_GET['practice_areas_for_search'] : [];
$specialities_areas_for_search = isset($_GET['specialities_areas_for_search']) ? (array) $_GET['specialities_areas_for_search'] : [];

$sort = isset($_GET['sort_data']) ? $_GET['sort_data'] : null;

$per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max($page, 1);
$offset = ($page - 1) * $per_page;

// ===== Base Query =====
$sql_base = "FROM users u
    INNER JOIN deals d ON d.owner = u.id AND d.deleted_at IS NULL
    LEFT JOIN states s ON s.id = u.state_province
    LEFT JOIN bars b ON b.id = u.bar
    LEFT JOIN industrys ind ON ind.id = u.industry
    WHERE u.role_id = 3 AND u.deleted_at IS NULL";

$params = [];

// ===== Filters =====
if (!empty($title)) {
    $sql_base .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.law_firm LIKE ?)";
    $params[] = "%{$title}%";
    $params[] = "%{$title}%";
}

if (!empty($state_for_search)) {
    $sql_base .= " AND u.state_province = ?";
    $params[] = $state_for_search;
}

if (!empty($industries_for_search)) {
    $placeholders = implode(',', array_fill(0, count($industries_for_search), '?'));
    $sql_base .= " AND u.industry IN ($placeholders)";
    $params = array_merge($params, $industries_for_search);
}

if (!empty($practice_areas_for_search)) {
    foreach ($practice_areas_for_search as $area) {
        $sql_base .= " AND JSON_CONTAINS(u.practice_area, ?)";
        $params[] = json_encode($area);
    }
}

if (!empty($specialities_areas_for_search)) {
    foreach ($specialities_areas_for_search as $spec) {
        $sql_base .= " AND JSON_CONTAINS(u.speciality, ?)";
        $params[] = json_encode($spec);
    }
}

// ===== Count Query =====
try {
    $count_sql = "SELECT COUNT(DISTINCT u.id) as total " . $sql_base;
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    error_log("Leaderboard API count error: " . $e->getMessage());
    echo json_encode([
        "status" => false,
        "message" => $dev_mode ? $e->getMessage() : "An error occurred while fetching data. Please try again later."
    ]);
    exit();
}

// ===== No records found =====
if ($total == 0) {
    $reason = "There are no records matching that search.";
    if (!empty($industries_for_search)) $reason = "No leaderboards found for selected industry.";
    elseif (!empty($practice_areas_for_search)) $reason = "No leaderboards found for selected practice area.";
    elseif (!empty($specialities_areas_for_search)) $reason = "No leaderboards found for selected speciality.";
    elseif (!empty($state_for_search)) $reason = "No leaderboards found for selected state.";

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
$orderBy = "u.id DESC"; // default order
if ($sort === 'deal_volume') {
    $orderBy = "deal_count DESC";
} elseif ($sort === 'deal_total') {
    $orderBy = "deal_total_amount DESC";
} else {
    $sortOptions = [
        'latest'     => "u.id DESC",
        'oldest'     => "u.id ASC",
        'ascending'  => "u.first_name ASC",
        'descending' => "u.first_name DESC"
    ];
    $orderBy = $sortOptions[$sort] ?? $orderBy;
}

// ===== Data Query =====
$sql = "SELECT 
            u.id, u.first_name, u.last_name, u.email, u.law_firm,
            s.name AS state_name, b.title AS bar_title, ind.title AS industry_title,
            COUNT(DISTINCT d.id) AS deal_count,
            IFNULL(SUM(d.amount), 0) AS deal_total_amount
        " . $sql_base . "
        GROUP BY u.id
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$params_with_limit = array_merge($params, [$per_page, $offset]);

try {
    $stmt = $conn->prepare($sql);
    foreach ($params_with_limit as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $leaderboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "Leaderboards fetched successfully",
        "page" => $page,
        "per_page" => $per_page,
        "total" => (int)$total,
        "total_pages" => ceil($total / $per_page),
        "data" => $leaderboards
    ]);
} catch (PDOException $e) {
    error_log("Leaderboard API data fetch error: " . $e->getMessage());
    echo json_encode([
        "status" => false,
        "message" => $dev_mode ? $e->getMessage() : "An error occurred while fetching data. Please try again later."
    ]);
}
?>
