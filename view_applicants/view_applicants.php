<?php
header("Content-Type: application/json");
include 'connection.php';

// Get search query and pagination
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Helper function to check if table exists
function tableExists($conn, $table) {
    try {
        $result = $conn->query("SELECT 1 FROM $table LIMIT 1");
        return $result !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// Helper function to search table
function searchTable($conn, $table, $columns, $query, $limit, $offset) {
    if (!tableExists($conn, $table)) {
        return [];
    }
    try {
        if ($query) {
            $likeQuery = '%' . $query . '%';
            $where = implode(" OR ", array_map(fn($c) => "$c LIKE :query", $columns));
            $sql = "SELECT * FROM $table WHERE $where LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':query', $likeQuery);
        } else {
            $sql = "SELECT * FROM $table LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sql);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ===== SEARCH ALL TABLES =====
$results = [];

// People
$results['people'] = searchTable($conn, 'users', ['name', 'email'], $search, $limit, $offset);

// Deals
$results['deals'] = searchTable($conn, 'deals', ['title', 'description'], $search, $limit, $offset);

// Referrals
$results['referrals'] = searchTable($conn, 'referrals', ['title', 'details'], $search, $limit, $offset);

// Jobs
$results['jobs'] = searchTable($conn, 'jobs', ['title', 'description'], $search, $limit, $offset);

// Posts
$results['posts'] = searchTable($conn, 'posts', ['title', 'content'], $search, $limit, $offset);

// Files (Feed + Messages)
$feedFiles = searchTable($conn, 'feed_media', ['file_name', 'description'], $search, $limit, $offset);
$messageFiles = searchTable($conn, 'messages', ['file_name', 'message'], $search, $limit, $offset);
$results['files'] = array_merge($feedFiles, $messageFiles);

// Return JSON
echo json_encode([
    "status" => true,
    "data" => $results,
    "pagination" => [
        "page" => $page,
        "limit" => $limit
    ]
]);
?>
