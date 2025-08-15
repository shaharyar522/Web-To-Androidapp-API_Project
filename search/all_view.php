<?php
header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Database connection (update credentials)

include_once 'connection.php'; 

// Helper function for scoring
function calculateMatchScore($item, $fields, $searchValue) {
    $score = 0;
    $searchValue = strtolower($searchValue);
    $searchWords = preg_split('/\s+/', $searchValue);

    foreach ($fields as $field) {
        if (!isset($item[$field])) continue;
        $value = strtolower((string)$item[$field]);

        if ($value) {
            if (strpos($value, $searchValue) !== false) {
                $score++;
                continue;
            }
            foreach ($searchWords as $word) {
                if (strpos($value, $word) !== false) {
                    $score++;
                    break;
                }
            }
        }
    }
    return $score;
}

// Get POST parameters
$searchValue = isset($_POST['search_value']) ? trim($_POST['search_value']) : '';
$post_per_page = isset($_POST['post_per_page']) ? (int)$_POST['post_per_page'] : 9;
$people_per_page = isset($_POST['people_per_page']) ? (int)$_POST['people_per_page'] : 9;
$job_per_page = isset($_POST['job_per_page']) ? (int)$_POST['job_per_page'] : 9;
$deal_per_page = isset($_POST['deal_per_page']) ? (int)$_POST['deal_per_page'] : 9;

// ======== Search Queries ========

// 1) Feeds
$feeds = [];
$sql = "SELECT * FROM feeds WHERE deleted_at IS NULL";
if ($searchValue !== '') {
    $sql .= " AND (
        LOWER(title) LIKE :search OR
        LOWER(descriptions) LIKE :search OR
        LOWER(tags) LIKE :search OR
        LOWER(photos) LIKE :search
    )";
}
// $stmt = $pdo->prepare($sql);

$stmt = $conn->prepare($sql);
if ($searchValue !== '') $stmt->bindValue(':search', "%".strtolower($searchValue)."%");
$stmt->execute();
$feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add scoring
foreach ($feeds as &$f) {
    $f['accuracy_score'] = calculateMatchScore($f, ['title','descriptions','tags','photos'], $searchValue);
}

// Filter & sort by score
if ($searchValue !== '') {
    $feeds = array_filter($feeds, fn($p) => $p['accuracy_score'] > 0);
    usort($feeds, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($feeds, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

// Paginate
$feeds = array_slice($feeds, 0, $post_per_page);

// 2) Users (role_id = 3)
$users = [];
$sql = "SELECT * FROM users WHERE role_id = 3 AND deleted_at IS NULL";
if ($searchValue !== '') {
    $sql .= " AND (
        LOWER(first_name) LIKE :search OR
        LOWER(last_name) LIKE :search OR
        LOWER(law_firm) LIKE :search
    )";
}
$stmt = $conn->prepare($sql);
if ($searchValue !== '') $stmt->bindValue(':search', "%".strtolower($searchValue)."%");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as &$u) {
    $u['accuracy_score'] = calculateMatchScore($u, [
        'first_name','last_name','law_firm','image','address','intro','bio'
    ], $searchValue);
}

if ($searchValue !== '') {
    $users = array_filter($users, fn($p) => $p['accuracy_score'] > 0);
    usort($users, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($users, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

$users = array_slice($users, 0, $people_per_page);

// 3) EQ Jobs
$jobs = [];
$sql = "SELECT * FROM eq_jobs WHERE deleted_at IS NULL";
if ($searchValue !== '') {
    $sql .= " AND (
        LOWER(title) LIKE :search OR
        LOWER(descriptions) LIKE :search OR
        LOWER(tags) LIKE :search OR
        LOWER(job_type) LIKE :search OR
        LOWER(position) LIKE :search OR
        LOWER(job_city) LIKE :search OR
        LOWER(job_state) LIKE :search
    )";
}
$stmt = $conn->prepare($sql);
if ($searchValue !== '') $stmt->bindValue(':search', "%".strtolower($searchValue)."%");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($jobs as &$j) {
    $j['accuracy_score'] = calculateMatchScore($j, [
        'title','job_type','descriptions','notes','tags','position','job_city','job_state'
    ], $searchValue);
}

if ($searchValue !== '') {
    $jobs = array_filter($jobs, fn($p) => $p['accuracy_score'] > 0);
    usort($jobs, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($jobs, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

$jobs = array_slice($jobs, 0, $job_per_page);

// 4) Deals
$deals = [];
$sql = "SELECT * FROM deals WHERE deleted_at IS NULL";
if ($searchValue !== '') {
    $sql .= " AND (
        LOWER(title) LIKE :search OR
        LOWER(descriptions) LIKE :search OR
        LOWER(tags) LIKE :search OR
        LOWER(photos) LIKE :search OR
        LOWER(company_name) LIKE :search OR
        LOWER(client) LIKE :search OR
        LOWER(city) LIKE :search OR
        LOWER(state) LIKE :search
    )";
}
$stmt = $conn->prepare($sql);
if ($searchValue !== '') $stmt->bindValue(':search', "%".strtolower($searchValue)."%");
$stmt->execute();
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($deals as &$d) {
    $d['accuracy_score'] = calculateMatchScore($d, [
        'title','descriptions','notes','press_release_link','tags','photos',
        'amount','company_name','client','city','state'
    ], $searchValue);
}

if ($searchValue !== '') {
    $deals = array_filter($deals, fn($p) => $p['accuracy_score'] > 0);
    usort($deals, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
} else {
    usort($deals, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
}

$deals = array_slice($deals, 0, $deal_per_page);

// ======== Response ========
echo json_encode([
    'status' => true,
    'posts' => array_values($feeds),
    'peoples' => array_values($users),
    'jobs' => array_values($jobs),
    'deals' => array_values($deals)
]);
