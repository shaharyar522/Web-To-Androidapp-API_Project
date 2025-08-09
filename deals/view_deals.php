<?php
header("Content-Type: application/json");


// ===== Database Connection =====
require_once 'conn.php';


// ===== Request Parameters =====
$title = isset($_GET['title']) ? trim($_GET['title']) : null;
$state_for_search = isset($_GET['state_for_search']) ? trim($_GET['state_for_search']) : null;
$bar_for_search = isset($_GET['bar_for_search']) ? trim($_GET['bar_for_search']) : null;

$industries_for_search = isset($_GET['industries_for_search']) ? $_GET['industries_for_search'] : [];
$practice_areas_for_search = isset($_GET['practice_areas_for_search']) ? $_GET['practice_areas_for_search'] : [];
$specialities_areas_for_search = isset($_GET['specialities_areas_for_search']) ? $_GET['specialities_areas_for_search'] : [];

$sort = isset($_GET['sort_data']) ? $_GET['sort_data'] : null;
$per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max($page, 1);  // Ensure page >= 1
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

if (!empty($industries_for_search) && is_array($industries_for_search)) {
    $placeholders = implode(',', array_fill(0, count($industries_for_search), '?'));
    $sql_base .= " AND d.industry IN ($placeholders)";
    foreach ($industries_for_search as $ind) {
        $params[] = $ind;
    }
}

if (!empty($practice_areas_for_search) && is_array($practice_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($practice_areas_for_search), '?'));
    $sql_base .= " AND d.practice_area IN ($placeholders)";
    foreach ($practice_areas_for_search as $pa) {
        $params[] = $pa;
    }
}

if (!empty($specialities_areas_for_search) && is_array($specialities_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($specialities_areas_for_search), '?'));
    $sql_base .= " AND d.speciality IN ($placeholders)";
    foreach ($specialities_areas_for_search as $sp) {
        $params[] = $sp;
    }
}

// ===== Get total count for pagination =====
try {
    $count_sql = "SELECT COUNT(*) as total " . $sql_base;
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

// ===== Sorting =====
$sortOptions = [
    'latest'     => "d.id DESC",
    'oldest'     => "d.id ASC",
    'ascending'  => "d.title ASC",
    'descending' => "d.title DESC"
];

$orderBy = $sortOptions[$sort] ?? "d.id DESC";

// ===== Final Data Query =====
$sql = "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS owner_name, u.email AS owner_email " . $sql_base . " ORDER BY $orderBy LIMIT ? OFFSET ?";

$params_with_limit = array_merge($params, [$per_page, $offset]);

try {
    $stmt = $conn->prepare($sql);

    // Bind all params
    foreach ($params_with_limit as $i => $val) {
        $paramType = PDO::PARAM_STR;
        if (is_int($val)) {
            $paramType = PDO::PARAM_INT;
        }
        $stmt->bindValue($i + 1, $val, $paramType);
    }

    $stmt->execute();
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => true,
        "message" => "Deals fetched successfully",
        "data" => $deals,
        "page" => $page,
        "per_page" => $per_page,
        "total" => (int)$total,
        "total_pages" => ceil($total / $per_page)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching deals: " . $e->getMessage()
    ]);
}
?>
