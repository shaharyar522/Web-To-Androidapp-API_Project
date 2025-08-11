<?php
header('Content-Type: application/json');

require_once 'conn.php';
require_once 'config.php'; // $conn from PDO connection

// ===== Pagination =====
$page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['par_page']) ? (int)$_GET['par_page'] : 10; // match Laravel's "par_page"
$offset   = ($page - 1) * $per_page;

$where   = [];
$params  = [];

// ===== Search by name/title =====
if (!empty($_GET['title'])) {
    $where[]  = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.law_firm LIKE ?)";
    $params[] = '%' . $_GET['title'] . '%';
    $params[] = '%' . $_GET['title'] . '%';
}

// ===== State filter =====
if (!empty($_GET['state_for_search'])) {
    $where[]  = "u.state_province = ?";
    $params[] = $_GET['state_for_search'];
}

// ===== Industry filter =====
if (!empty($_GET['industries_for_search'])) {
    $industries    = explode(',', $_GET['industries_for_search']);
    $placeholders  = implode(',', array_fill(0, count($industries), '?'));
    $where[]       = "d.industry IN ($placeholders)";
    $params        = array_merge($params, $industries);
}

// ===== Practice Area filter =====
if (!empty($_GET['practice_areas_for_search'])) {
    $practiceAreas = explode(',', $_GET['practice_areas_for_search']);
    foreach ($practiceAreas as $area) {
        $where[]  = "d.practice_area LIKE ?";
        $params[] = '%' . $area . '%';
    }
}

// ===== Speciality filter =====
if (!empty($_GET['specialities_areas_for_search'])) {
    $specialities = explode(',', $_GET['specialities_areas_for_search']);
    foreach ($specialities as $spec) {
        $where[]  = "d.speciality LIKE ?";
        $params[] = '%' . $spec . '%';
    }
}

$whereSQL = $where ? ' AND ' . implode(' AND ', $where) : '';

// ===== Sorting =====
$orderBy = "ORDER BY u.id DESC"; // default: latest
if (!empty($_GET['sort_data'])) {
    switch ($_GET['sort_data']) {
        case 'deal_volume':
            $orderBy = "ORDER BY deal_count DESC";
            break;
        case 'deal_total':
            $orderBy = "ORDER BY deal_sum DESC";
            break;
        case 'latest':
            $orderBy = "ORDER BY u.id DESC";
            break;
        case 'oldest':
            $orderBy = "ORDER BY u.id ASC";
            break;
        case 'ascending':
            $orderBy = "ORDER BY u.first_name ASC";
            break;
        case 'descending':
            $orderBy = "ORDER BY u.first_name DESC";
            break;
    }
}

// ===== Main Query =====
$sql = "
    SELECT 
        u.id, 
        u.first_name, 
        u.last_name, 
        u.law_firm,
        COUNT(d.id) AS deal_count,
        COALESCE(SUM(d.amount), 0) AS deal_sum
    FROM users u
    JOIN deals d ON d.owner = u.id
    WHERE u.role_id = 3 
      AND u.deleted_at IS NULL
      $whereSQL
    GROUP BY u.id
    $orderBy
    LIMIT $per_page OFFSET $offset
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Total Count =====
$sqlCount = "
    SELECT COUNT(DISTINCT u.id) AS total
    FROM users u
    JOIN deals d ON d.owner = u.id
    WHERE u.role_id = 3 
      AND u.deleted_at IS NULL
      $whereSQL
";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

// ===== Response =====
echo json_encode([
    'status'   => true,
    'page'     => $page,
    'par_page' => $per_page, // match Laravel
    'total'    => $total,
    'data'     => $leaderboard
]);
