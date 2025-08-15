<?php
header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => false,
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

include('connection.php');

// Get search parameters from POST form-data
$searchValue = isset($_POST['search']) ? strtolower(trim($_POST['search'])) : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Base query
$sql = "SELECT * FROM posts WHERE deleted_at IS NULL";

// Apply search filter if exists
$params = [];
if (!empty($searchValue)) {
    $sql .= " AND (LOWER(title) LIKE :search OR LOWER(description) LIKE :search)";
    $params[':search'] = "%$searchValue%";
}

// Count total results
$countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Add sorting and pagination
$sql .= " ORDER BY created_at DESC LIMIT :offset, :perPage";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);

$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
echo json_encode([
    'status' => true,
    'data' => $posts,
    'total' => (int)$total,
    'per_page' => $perPage,
    'current_page' => $page
]);
