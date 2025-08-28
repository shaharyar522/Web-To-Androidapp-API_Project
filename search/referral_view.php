<?php
header("Content-Type: application/json");
require_once("connection.php"); // DB connection

// Pagination params (only from POST)
$page     = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
$per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 10;
$search   = isset($_POST['search']) ? strtolower(trim($_POST['search'])) : '';
$hasSearch = !empty($search);

// Calculate offset
$offset = ($page - 1) * $per_page;

// -----------------------------------
// Base SQL
// -----------------------------------
$baseSql = " FROM eq_jobs r
    LEFT JOIN citys c ON r.job_city = c.id
    LEFT JOIN industrys i ON r.industry = i.id
    LEFT JOIN users ow ON r.owner = ow.id
    LEFT JOIN users ref ON r.referred_by = ref.id
    LEFT JOIN boosts b ON r.id = b.product_id AND b.model = 'job'
    WHERE r.deleted_at IS NULL
      AND r.job_type = 'referral'";

if ($hasSearch) {
    $baseSql .= " AND (
        LOWER(r.title) LIKE :search
        OR LOWER(r.descriptions) LIKE :search
        OR LOWER(r.notes) LIKE :search
        OR LOWER(r.firm) LIKE :search
        OR LOWER(r.position) LIKE :search
        OR LOWER(r.representative) LIKE :search
        OR LOWER(c.name) LIKE :search
        OR LOWER(i.title) LIKE :search
        OR LOWER(ow.first_name) LIKE :search
        OR LOWER(ow.last_name) LIKE :search
        OR LOWER(ref.first_name) LIKE :search
        OR LOWER(ref.last_name) LIKE :search
        OR LOWER(b.keywords) LIKE :search
    )";
}

// -----------------------------------
// Get total count
// -----------------------------------
$countSql = "SELECT COUNT(*) " . $baseSql;
$countStmt = $conn->prepare($countSql);
if ($hasSearch) {
    $like = "%{$search}%";
    $countStmt->bindParam(":search", $like);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

// -----------------------------------
// Get paginated data
// -----------------------------------
$dataSql = "SELECT r.*, 
               c.name AS city_name, 
               i.title AS industry_title,
               ow.first_name AS owner_first_name, ow.last_name AS owner_last_name,
               ref.first_name AS referred_first_name, ref.last_name AS referred_last_name,
               b.keywords AS boost_keywords
        " . $baseSql . "
        ORDER BY r.created_at DESC
        LIMIT :per_page OFFSET :offset";

$dataStmt = $conn->prepare($dataSql);
if ($hasSearch) {
    $dataStmt->bindParam(":search", $like);
}
$dataStmt->bindParam(":per_page", $per_page, PDO::PARAM_INT);
$dataStmt->bindParam(":offset", $offset, PDO::PARAM_INT);
$dataStmt->execute();
$results = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------
// Accuracy score
// -----------------------------------
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
        $item['referred_first_name'] ?? '',
        $item['referred_last_name'] ?? '',
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

if ($hasSearch) {
    foreach ($results as &$r) {
        $r['accuracy_score'] = calculateMatchScore($r, $search);
    }
} else {
    foreach ($results as &$r) {
        $r['accuracy_score'] = 0;
    }
}

// -----------------------------------
// Response with pagination info
// -----------------------------------
echo json_encode([
    "status"    => true,
    "page"      => $page,
    "per_page"  => $per_page,
    "total"     => $total,
    "last_page" => ceil($total / $per_page),
    "data"      => array_values($results)
]);
