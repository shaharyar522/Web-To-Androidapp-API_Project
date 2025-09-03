<?php
require_once 'connection.php'; // Must return $pdo (PDO instance)
session_start();
header('Content-Type: application/json');

// Simulate logged-in user (for testing only)
$_SESSION['user_id'] = 1;

// 1. Auth check
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Fetch current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND deleted_at IS NULL");
$stmt->bindParam(':id', $userId, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo json_encode(['status' => false, 'message' => 'User not found']);
    exit;
}

// 3. Leaderboards (top 6 users with most deals)
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(d.id) AS deals_count
    FROM users u
    JOIN deals d ON d.owner = u.id AND d.deleted_at IS NULL
    WHERE u.role_id = 3 AND u.id <> :uid AND u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY deals_count DESC
    LIMIT 6
");
$stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
$stmt->execute();
$leaderboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Get 3 latest unique deals by owner
$stmt = $pdo->prepare("
    SELECT * FROM deals
    WHERE deleted_at IS NULL
    ORDER BY created_at DESC
");
$stmt->execute();
$allDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique deals by owner
$uniqueOwners = [];
$deals = [];
foreach ($allDeals as $deal) {
    if (!in_array($deal['owner'], $uniqueOwners)) {
        $uniqueOwners[] = $deal['owner'];
        $deals[] = $deal;
    }
    if (count($deals) >= 3) break;
}

// 5. Build feed query
$sql = "
    SELECT f.*,
        (SELECT COUNT(*) FROM feed_likes l WHERE l.feed_id = f.id) AS likes_count,
        (SELECT COUNT(*) FROM feed_comments c WHERE c.feed_id = f.id) AS comments_count
    FROM feeds f
    WHERE f.deleted_at IS NULL
      AND NOT EXISTS (
        SELECT 1 FROM feed_not_interested ni WHERE ni.feed_id = f.id AND ni.user_id = :uid
      )
";
$params = [':uid' => $userId];

// 6. Handle AJAX (form-data with `X-Requested-With`)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    // Sort options
    $sortOptions = [
        'latest'     => 'f.id DESC',
        'oldest'     => 'f.id ASC',
        'ascending'  => 'f.title ASC',
        'descending' => 'f.title DESC',
    ];
    $sort = $_POST['sort_data'] ?? 'latest';
    $orderBy = $sortOptions[$sort] ?? 'f.id DESC';

    // Pagination
    $perPage = isset($_POST['par_page']) ? (int)$_POST['par_page'] : 7;
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $offset = ($page - 1) * $perPage;

    $sql .= " ORDER BY $orderBy LIMIT :offset, :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fix missing or empty titles
    foreach ($feeds as &$feed) {
        if (!isset($feed['title']) || $feed['title'] === null || trim($feed['title']) === '') {
            $feed['title'] = '(No Title)';
        }
    }

    echo json_encode([
        'status' => true,
        'feeds' => $feeds,
        'leaderboards' => $leaderboards,
        'deals' => $deals,
        'page' => $page,
        'per_page' => $perPage,
        'input' => $_POST
    ]);
    exit;
}

// Non-AJAX: regular request fallback
$perPage = isset($_GET['per_feed_record']) ? (int)$_GET['per_feed_record'] : 7;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$sql .= " ORDER BY f.id DESC LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fix missing or empty titles
foreach ($feeds as &$feed) {
    if (!isset($feed['title']) || $feed['title'] === null || trim($feed['title']) === '') {
        $feed['title'] = '(No Title)';
    }
}

echo json_encode([
    'status' => true,
    'feeds' => $feeds,
    'leaderboards' => $leaderboards,
    'deals' => $deals,
    'page' => $page,
    'per_page' => $perPage,
]);
