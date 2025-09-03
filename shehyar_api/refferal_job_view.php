<?php
include 'connection.php';
header('Content-Type: application/json');

// Get POST inputs
$title = $_POST['title'] ?? null;
$state = $_POST['state_for_search'] ?? null;
$industries = isset($_POST['industries_for_search']) ? (array) $_POST['industries_for_search'] : [];
$practiceAreas = isset($_POST['practice_areas_for_search']) ? (array) $_POST['practice_areas_for_search'] : [];
$specialities = isset($_POST['specialities_areas_for_search']) ? (array) $_POST['specialities_areas_for_search'] : [];

$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;

$sort = $_POST['sort_data'] ?? 'latest';
$perPage = isset($_POST['par_page']) && is_numeric($_POST['par_page']) ? (int)$_POST['par_page'] : 10;
$page = isset($_POST['page']) && is_numeric($_POST['page']) ? (int)$_POST['page'] : 1;
$offset = ($page - 1) * $perPage;

// Start SQL
$sql = "SELECT * FROM eq_jobs WHERE deleted_at IS NULL AND job_type = 'referral'";
$conditions = [];
$params = [];

// Filters
if (!empty($title)) {
    $conditions[] = "(title LIKE :title)";
    $params[':title'] = "%$title%";
}

if (!empty($state)) {
    $conditions[] = "job_state = :state";
    $params[':state'] = $state;
}

if (!empty($industries)) {
    $placeholders = [];
    foreach ($industries as $i => $val) {
        $key = ":industry_$i";
        $placeholders[] = $key;
        $params[$key] = $val;
    }
    $conditions[] = "industry IN (" . implode(',', $placeholders) . ")";
}

if (!empty($practiceAreas)) {
    $placeholders = [];
    foreach ($practiceAreas as $i => $val) {
        $key = ":practice_$i";
        $placeholders[] = $key;
        $params[$key] = $val;
    }
    $conditions[] = "FIND_IN_SET($key, practice_area)";
}

if (!empty($specialities)) {
    $placeholders = [];
    foreach ($specialities as $i => $val) {
        $key = ":speciality_$i";
        $placeholders[] = $key;
        $params[$key] = $val;
    }
    $conditions[] = "FIND_IN_SET($key, speciality)";
}

// Date filtering
if (!empty($startDate)) {
    $conditions[] = "DATE(created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}
if (!empty($endDate)) {
    $conditions[] = "DATE(created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}

// Combine conditions
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Sorting
$sortOptions = [
    'latest'     => 'id DESC',
    'oldest'     => 'id ASC',
    'ascending'  => 'title ASC',
    'descending' => 'title DESC'
];
$sql .= " ORDER BY " . ($sortOptions[$sort] ?? $sortOptions['latest']);

// Pagination
$sql .= " LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $perPage;

// Prepare and execute
try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'error' => 'Query failed: ' . $e->getMessage()
    ]);
    exit;
}

// Count total
$countSQL = "SELECT COUNT(*) FROM eq_jobs WHERE deleted_at IS NULL AND job_type = 'referral'";
if (!empty($conditions)) {
    $countSQL .= " AND " . implode(" AND ", $conditions);
}
try {
    $countStmt = $pdo->prepare($countSQL);
    foreach ($params as $key => $value) {
        if ($key === ':offset' || $key === ':limit') continue;
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'error' => 'Count query failed: ' . $e->getMessage()
    ]);
    exit;
}

// Output
echo json_encode([
    'status' => true,
    'jobs' => $jobs,
    'total_jobs' => (int)$total,
    'page' => $page,
    'per_page' => $perPage
]);
