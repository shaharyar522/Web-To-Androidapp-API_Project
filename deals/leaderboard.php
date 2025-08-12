<?php
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'conn.php';
require_once 'config.php';

// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method. Use POST only."
    ]);
    exit();
}

// ✅ Get JSON or form input
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}
if (!is_array($input)) {
    $input = [];
}

// ✅ Helper to get params safely
function getParam($key, $default = null) {
    global $input;
    return isset($input[$key]) && $input[$key] !== '' ? trim($input[$key]) : $default;
}

// 🔹 Parameters
$id        = getParam('id');
$title     = getParam('title');
$state_for_search = getParam('state_for_search');
$search_for       = getParam('search_for');
$sort_data        = getParam('sort_data');

$industries_for_search      = array_filter((array)($input['industries_for_search'] ?? []));
$practice_areas_for_search  = array_filter((array)($input['practice_areas_for_search'] ?? []));
$specialities_areas_for_search = array_filter((array)($input['specialities_areas_for_search'] ?? []));

$per_page = max(1, (int)getParam('per_page', 10));
$page     = max(1, (int)getParam('page', 1));
$offset   = ($page - 1) * $per_page;

// 🔹 Base SQL
$sql_base = "
FROM users u
LEFT JOIN deals d ON d.owner = u.id AND d.deleted_at IS NULL
LEFT JOIN states s ON s.id = u.state_province
LEFT JOIN bars b ON b.id = u.bar
LEFT JOIN industrys ind ON ind.id = u.industry
WHERE u.role_id = 3 AND u.deleted_at IS NULL
";

$params = [];

// 🔍 Filters
if ($id && is_numeric($id)) {
    $sql_base .= " AND u.id = ?";
    $params[] = (int)$id;
}

if (!empty($title)) {
    if ($search_for === 'law_firm') {
        $sql_base .= " AND u.law_firm LIKE ?";
        $params[] = "%{$title}%";
    } elseif ($search_for === 'user') {
        $sql_base .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
        $params[] = "%{$title}%";
    } else {
        $sql_base .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.law_firm LIKE ?)";
        $params[] = "%{$title}%";
        $params[] = "%{$title}%";
    }
}

if (!empty($state_for_search)) {
    if (is_numeric($state_for_search)) {
        $sql_base .= " AND u.state_province = ?";
        $params[] = (int)$state_for_search;
    } else {
        $sql_base .= " AND s.name LIKE ?";
        $params[] = "%{$state_for_search}%";
    }
}

if (!empty($industries_for_search)) {
    $placeholders = implode(',', array_fill(0, count($industries_for_search), '?'));
    $sql_base .= " AND u.industry IN ($placeholders)";
    $params = array_merge($params, $industries_for_search);
}

if (!empty($practice_areas_for_search)) {
    $conds = [];
    foreach ($practice_areas_for_search as $area) {
        $conds[] = "u.practice_area LIKE ?";
        $params[] = '%"'.$area.'"%';
    }
    if ($conds) {
        $sql_base .= " AND (" . implode(" OR ", $conds) . ")";
    }
}

if (!empty($specialities_areas_for_search)) {
    $conds = [];
    foreach ($specialities_areas_for_search as $spec) {
        $conds[] = "u.speciality LIKE ?";
        $params[] = '%"'.$spec.'"%';
    }
    if ($conds) {
        $sql_base .= " AND (" . implode(" OR ", $conds) . ")";
    }
}

// 🔹 Count total
$count_sql = "SELECT COUNT(DISTINCT u.id) AS total " . $sql_base;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

if ($total === 0) {
    echo json_encode([
        "status" => false,
        "message" => "No records found.",
        "page" => $page,
        "per_page" => $per_page,
        "total" => 0,
        "total_pages" => 0,
        "data" => []
    ]);
    exit();
}

// 🔹 Sorting
$orderBy = "u.id DESC";
if ($sort_data === 'deal_volume') {
    $orderBy = "deal_count DESC";
} elseif ($sort_data === 'deal_total') {
    $orderBy = "deal_total_amount DESC";
} elseif ($sort_data === 'latest') {
    $orderBy = "u.id DESC";
} elseif ($sort_data === 'oldest') {
    $orderBy = "u.id ASC";
} elseif ($sort_data === 'ascending') {
    $orderBy = "u.first_name ASC";
} elseif ($sort_data === 'descending') {
    $orderBy = "u.first_name DESC";
}

// 🔹 Main query
$sql = "
SELECT 
    u.id, u.first_name, u.last_name, u.email, u.law_firm,
    s.name AS state_name, b.title AS bar_title, ind.title AS industry_title,
    COUNT(DISTINCT d.id) AS deal_count,
    IFNULL(SUM(d.amount), 0) AS deal_total_amount
" . $sql_base . "
GROUP BY u.id
ORDER BY $orderBy
LIMIT ? OFFSET ?
";

$params_with_limit = array_merge($params, [$per_page, $offset]);
$stmt = $conn->prepare($sql);
foreach ($params_with_limit as $i => $val) {
    $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Response
echo json_encode([
    "status" => true,
    "message" => "Leaderboards fetched successfully.",
    "page" => $page,
    "per_page" => $per_page,
    "total" => $total,
    "total_pages" => ceil($total / $per_page),
    "data" => $data
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

