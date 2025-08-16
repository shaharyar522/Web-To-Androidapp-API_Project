<?php
header("Content-Type: application/json");
require_once("connection.php"); // include your DB connection

// Pagination params
$page     = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$search   = isset($_POST['search']) ? strtolower(trim($_POST['search'])) : '';
$hasSearch = !empty($search);

// Calculate offset
$offset = ($page - 1) * $per_page;

// Fetch jobs base query
$sql = "SELECT j.*, 
               c.name AS city_name, 
               i.title AS industry_title,
               ow.first_name AS owner_first_name, ow.last_name AS owner_last_name,
               b.keywords AS boost_keywords
        FROM eq_jobs j
        LEFT JOIN citys c ON j.job_city = c.id
        LEFT JOIN industrys i ON j.industry = i.id
        LEFT JOIN users ow ON j.owner = ow.id
        LEFT JOIN boosts b ON b.product_id = j.id AND b.model = 'job'
        WHERE j.deleted_at IS NULL
          AND j.is_active = 1"; // only active jobs

if ($hasSearch) {
    $sql .= " AND (
                LOWER(j.title) LIKE :search
                OR LOWER(j.descriptions) LIKE :search
                OR LOWER(j.notes) LIKE :search
                OR LOWER(j.firm) LIKE :search
                OR LOWER(j.position) LIKE :search
                OR LOWER(j.representative) LIKE :search
                OR LOWER(c.name) LIKE :search
                OR LOWER(i.title) LIKE :search
                OR LOWER(ow.first_name) LIKE :search
                OR LOWER(ow.last_name) LIKE :search
                OR LOWER(b.keywords) = :searchExact
                OR FIND_IN_SET(:searchExact, LOWER(b.keywords))
              )";
}

$stmt = $conn->prepare($sql);

if ($hasSearch) {
    $like = "%{$search}%";
    $stmt->bindParam(":search", $like);
    $stmt->bindParam(":searchExact", $search);
}

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate accuracy_score
function calculateMatchScore($item, $searchValue) {
    $fields = [
        $item['title'] ?? '',
        $item['descriptions'] ?? '',
        $item['notes'] ?? '',
        $item['firm'] ?? '',
        $item['position'] ?? '',
        $item['representative'] ?? '',
        $item['city_name'] ?? '',
        $item['industry_title'] ?? '',
        $item['owner_first_name'] ?? '',
        $item['owner_last_name'] ?? '',
        $item['boost_keywords'] ?? ''
    ];

    $combined = strtolower(implode(' ', $fields));
    $searchValue = strtolower(trim($searchValue));
    $words = preg_split('/\s+/', $searchValue);

    if (str_contains($combined, $searchValue)) {
        return 100;
    }

    $found = 0;
    foreach ($words as $w) {
        if (str_contains($combined, $w)) {
            $found++;
        }
    }
    $total = count($words);

    if ($found === $total && $total > 1) {
        return 100;
    }
    if ($found > 0) {
        return intval(50 * ($found / $total));
    }
    return 0;
}

foreach ($results as &$r) {
    $r['accuracy_score'] = $hasSearch ? calculateMatchScore($r, $search) : 0;
}

// Filter + sort
if ($hasSearch) {
    $results = array_filter($results, fn($r) => $r['accuracy_score'] > 0);
    usort($results, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($results, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

// Pagination slice
$total = count($results);
$results = array_slice($results, $offset, $per_page);

// Response
echo json_encode([
    "status" => true,
    "page" => $page,
    "per_page" => $per_page,
    "total" => $total,
    "data" => array_values($results)
]);
