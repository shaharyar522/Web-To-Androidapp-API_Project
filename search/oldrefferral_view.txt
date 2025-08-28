<?php
header("Content-Type: application/json");
require_once("connection.php"); // your DB connection

// Pagination params
$page     = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$search   = isset($_POST['search']) ? strtolower(trim($_POST['search'])) : '';
$hasSearch = !empty($search);

// Calculate offset
$offset = ($page - 1) * $per_page;

// Fetch referrals base query
$sql = "SELECT r.*, 
               c.name AS city_name, 
               i.title AS industry_title,
               ow.first_name AS owner_first_name, ow.last_name AS owner_last_name,
               ref.first_name AS referred_first_name, ref.last_name AS referred_last_name,
               b.keywords AS boost_keywords
        FROM eq_jobs r
        LEFT JOIN citys c ON r.job_city = c.id
        LEFT JOIN industrys i ON r.industry = i.id
        LEFT JOIN users ow ON r.owner = ow.id
        LEFT JOIN users ref ON r.referred_by = ref.id
        LEFT JOIN boosts b ON r.id = b.product_id AND b.model = 'job'
        WHERE r.deleted_at IS NULL
          AND r.job_type = 'referral'";

if ($hasSearch) {
    $sql .= " AND (
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

$stmt = $conn->prepare($sql);

if ($hasSearch) {
    $like = "%{$search}%";
    $stmt->bindParam(":search", $like);
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

// Add accuracy_score
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
