<?php
header('Content-Type: application/json');
require_once 'connection.php'; // Your PDO connection

$response = [
    'status'     => false,
    'message'    => '',
    'data'       => [],
    'pagination' => [
        'current_page' => 1,
        'per_page'     => 10,
        'total_pages'  => 1,
        'total_items'  => 0,
    ],
    'request'    => $_POST,
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response);
    exit;
}

$user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 1;
$perPage = isset($_POST['par_page']) && is_numeric($_POST['par_page']) ? (int)$_POST['par_page'] : 10;
$page = isset($_POST['page']) && is_numeric($_POST['page']) && $_POST['page'] > 0 ? (int)$_POST['page'] : 1;
$offset = ($page - 1) * $perPage;

try {
    // Basic WHERE condition
    $where = 'user_id = :user_id';
    $params = [':user_id' => $user_id];

    // Count total matching rows
    $countSql = "SELECT COUNT(*) FROM transactions WHERE {$where}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalItems / $perPage));

    // Fetch paginated rows
    $sql = "SELECT * FROM transactions WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($transactions) {
        $response['status'] = true;
        $response['message'] = 'Transactions fetched successfully.';
        $response['data'] = $transactions;
    } else {
        $response['message'] = 'No transactions found.';
    }

    $response['pagination'] = [
        'current_page' => $page,
        'per_page'     => $perPage,
        'total_pages'  => $totalPages,
        'total_items'  => $totalItems,
    ];
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
