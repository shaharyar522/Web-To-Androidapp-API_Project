<?php
header("Content-Type: application/json");
require_once("connection.php");

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit;
}

// Read POST parameters
$page      = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
$per_page  = isset($_POST['per_page']) ? max(1, (int)$_POST['per_page']) : 10;
$search    = isset($_POST['search']) ? strtolower(trim($_POST['search'])) : '';
$hasSearch = !empty($search);
$offset    = ($page - 1) * $per_page;

// Base query
$sql = "SELECT j.*, 
               c.name AS city_name,
               i.title AS industry_title,
               ow.first_name AS owner_first_name, ow.last_name AS owner_last_name,
               ref.first_name AS referred_first_name, ref.last_name AS referred_last_name,
               b.keywords AS boost_keywords
        FROM eq_jobs j
        LEFT JOIN citys c ON j.job_city = c.id
        LEFT JOIN industrys i ON j.industry = i.id
        LEFT JOIN users ow ON j.owner = ow.id
        LEFT JOIN users ref ON j.referred_by = ref.id
        LEFT JOIN boosts b ON b.product_id = j.id AND b.model = 'job'
        WHERE j.deleted_at IS NULL AND j.job_type = 'regular'";

// Add search filter if provided
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
                OR FIND_IN_SET(:searchExact, LOWER(b.keywords))
                OR LOWER(b.keywords) = :searchExact
              )";
}

// Execute query
$stmt = $conn->prepare($sql);
if ($hasSearch) {
    $like = "%$search%";
    $stmt->bindParam(":search", $like);
    $stmt->bindParam(":searchExact", $search);
}
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Accuracy score calculation
function calculateMatchScore($item, $fields, $searchValue) {
    $combined = '';
    foreach ($fields as $f) $combined .= ' ' . ($item[$f] ?? '');
    $combined = strtolower($combined);
    $words = preg_split('/\s+/', strtolower(trim($searchValue)));

    if (str_contains($combined, strtolower($searchValue))) return 100;

    $found = 0;
    foreach ($words as $w) if (str_contains($combined, $w)) $found++;
    $total = count($words);
    if ($found === $total && $total > 1) return 100;
    if ($found > 0) return intval(50 * ($found / $total));
    return 0;
}

// Fields to check
$fields = [
    'title','descriptions','notes','firm','position','representative',
    'city_name','industry_title','owner_first_name','owner_last_name','boost_keywords'
];

// Add accuracy score
foreach ($jobs as &$job) {
    $job['accuracy_score'] = $hasSearch ? calculateMatchScore($job, $fields, $search) : 0;
}

// Filter and sort
if ($hasSearch) {
    $jobs = array_filter($jobs, fn($j) => $j['accuracy_score'] > 0);
    usort($jobs, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($jobs, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

// Pagination
$total = count($jobs);
$jobs_paginated = array_slice($jobs, $offset, $per_page);

// Response
echo json_encode([
    "status" => true,
    "page" => $page,
    "per_page" => $per_page,
    "total" => $total,
    "total_pages" => ceil($total / $per_page),
    "data" => array_values($jobs_paginated)
]);
?>
