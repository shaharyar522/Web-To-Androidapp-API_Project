<?php
header("Content-Type: application/json");
require_once 'connection.php';

$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
$searchValue = isset($_POST['search']) ? trim($_POST['search']) : '';
$offset = ($page - 1) * $per_page;

function calculateMatchScore(array $item, string $searchValue, array $fields): int {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue);

    $combinedValue = '';
    foreach ($fields as $field) {
        if (isset($item[$field]) && $item[$field] !== null) {
            $combinedValue .= ' ' . strtolower($item[$field]);
        }
    }
    $combinedValue = trim($combinedValue);

    if (strpos($combinedValue, $searchValue) !== false) {
        return 100;
    }

    $foundWords = 0;
    foreach ($searchWords as $word) {
        if (strpos($combinedValue, $word) !== false) {
            $foundWords++;
        }
    }

    $totalWords = count($searchWords);
    if ($foundWords === $totalWords && $totalWords > 1) {
        return 100;
    }
    if ($foundWords > 0) {
        return intval(50 * ($foundWords / $totalWords));
    }
    return 0;
}

try {
    $baseSQL = "FROM users u
                LEFT JOIN citys c ON u.city = c.id
                LEFT JOIN industrys i ON u.industry = i.id
                WHERE u.deleted_at IS NULL
                  AND u.role_id = 3";

    $params = [];
    $fieldsToSearch = ['first_name', 'last_name', 'email', 'phone', 'law_firm', 'practice_area', 'city_name', 'industry_title'];

    if (!empty($searchValue)) {
        $baseSQL .= " AND (
            LOWER(u.first_name) LIKE :search
            OR LOWER(u.last_name) LIKE :search
            OR LOWER(u.email) LIKE :search
            OR LOWER(u.phone) LIKE :search
            OR LOWER(u.law_firm) LIKE :search
            OR LOWER(u.practice_area) LIKE :search
            OR LOWER(c.name) LIKE :search
            OR LOWER(i.title) LIKE :search
        )";
        $params[':search'] = '%' . strtolower($searchValue) . '%';
    }

    // Total count
    $countSQL = "SELECT COUNT(*) as total " . $baseSQL;
    $stmtCount = $conn->prepare($countSQL);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmtCount->execute();
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch paginated data
    $dataSQL = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.law_firm, u.practice_area,
                       c.name AS city_name, i.title AS industry_title, u.created_at
                " . $baseSQL . "
                ORDER BY u.created_at DESC
                LIMIT :offset, :per_page";

    $stmt = $conn->prepare($dataSQL);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $peoples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate accuracy score
    if (!empty($searchValue)) {
        foreach ($peoples as &$person) {
            $person['accuracy_score'] = calculateMatchScore($person, $searchValue, $fieldsToSearch);
        }
        // Filter out zero-score results and sort by accuracy
        $peoples = array_filter($peoples, fn($p) => $p['accuracy_score'] > 0);
        usort($peoples, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
        $peoples = array_values($peoples);
    } else {
        foreach ($peoples as &$person) {
            $person['accuracy_score'] = 0;
        }
    }

    // Add type for merged result consistency
    foreach ($peoples as &$person) {
        $person['type'] = 'person';
    }

    echo json_encode([
        "status" => true,
        "page" => $page,
        "per_page" => $per_page,
        "total" => $total,
        "data" => $peoples
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
