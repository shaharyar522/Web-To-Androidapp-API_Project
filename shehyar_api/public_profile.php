<?php
header('Content-Type: application/json');
require_once 'connection.php';  // Your PDO $pdo setup with ERRMODE_EXCEPTION

$response = ['status' => false, 'message' => '', 'data' => null];

// Helper function to check if a column exists in a table
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

// Helper function to ensure 'title' field is not null or empty
function ensureTitle(array &$items) {
    foreach ($items as &$item) {
        if (!isset($item['title']) || $item['title'] === null || $item['title'] === '') {
            $item['title'] = '(No title)';
        }
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response);
    exit;
}

$username = $_POST['username'] ?? '';
if (empty($username)) {
    $response['message'] = 'Username is required.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Fetch the user by username (single user)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit;
    }
    $userId = $user['id'];

    // Check which columns exist for feeds
    $feedsHasTitle = columnExists($pdo, 'feeds', 'title');

    // 2. Recent Feeds by this user
    $feedSelectFields = ['id', 'created_at', 'owner'];
    if ($feedsHasTitle) {
        $feedSelectFields[] = 'title';
    }
    $feedSelectString = implode(', ', $feedSelectFields);

    $stmt = $pdo->prepare("
        SELECT $feedSelectString
        FROM feeds
        WHERE owner = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($feedsHasTitle) {
        $feeds = ensureTitle($feeds);
    }

    // 3. Site Stats (general site stats)
    $stats = [
        'practice_area_count' => (int)$pdo->query("SELECT COUNT(*) FROM practice_areas")->fetchColumn(),
        'speciality_count'    => (int)$pdo->query("SELECT COUNT(*) FROM specialties")->fetchColumn(),
        'deal_count'          => (int)$pdo->query("SELECT COUNT(*) FROM deals")->fetchColumn(),
        'max_deal_amount'     => (float)$pdo->query("SELECT MAX(amount) FROM deals")->fetchColumn()
    ];

    // Check columns for user_documents
    $userDocsHasTitle = columnExists($pdo, 'user_documents', 'title');
    $userDocsHasFileName = columnExists($pdo, 'user_documents', 'file_name');

    $docSelectFields = ['id', 'file_type', 'created_at']; // always present fields
    if ($userDocsHasTitle) {
        $docSelectFields[] = 'title';
    }
    if ($userDocsHasFileName) {
        $docSelectFields[] = 'file_name';
    }
    $docSelectFieldsString = implode(', ', $docSelectFields);

    // 4. Documents by this user
    $pdfs = $pdo->prepare("
        SELECT $docSelectFieldsString
        FROM user_documents
        WHERE user_id = ? AND file_type IN ('pdf','PDF')
        LIMIT 5
    ");
    $pdfs->execute([$userId]);
    $pdfsData = $pdfs->fetchAll(PDO::FETCH_ASSOC);
    if ($userDocsHasTitle) {
        $pdfsData = ensureTitle($pdfsData);
    }

    $images = $pdo->prepare("
        SELECT $docSelectFieldsString
        FROM user_documents
        WHERE user_id = ? AND file_type IN ('png','jpg','jpeg','gif','webp','heic','jfif')
        LIMIT 10
    ");
    $images->execute([$userId]);
    $imagesData = $images->fetchAll(PDO::FETCH_ASSOC);
    if ($userDocsHasTitle) {
        $imagesData = ensureTitle($imagesData);
    }

    $videos = $pdo->prepare("
        SELECT $docSelectFieldsString
        FROM user_documents
        WHERE user_id = ? AND file_type IN ('mp4','mov')
        LIMIT 10
    ");
    $videos->execute([$userId]);
    $videosData = $videos->fetchAll(PDO::FETCH_ASSOC);
    if ($userDocsHasTitle) {
        $videosData = ensureTitle($videosData);
    }

    // 5. Practice Areas & Specialities for this user
    $fetchList = function(array $ids, $table) use ($pdo) {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $areas = json_decode($user['practice_area'] ?? '[]', true);
    $areas = is_array($areas) ? $areas : [];
    $practiceDetails = $fetchList($areas, 'practice_areas');

    $specIds = json_decode($user['speciality'] ?? '[]', true);
    $specIds = is_array($specIds) ? $specIds : [];
    $specialityDetails = $fetchList($specIds, 'specialties');

    // Check columns for deals
    $dealsHasTitle = columnExists($pdo, 'deals', 'title');

    // 6. Deals by this user
    $dealSelectFields = ['id', 'amount', 'status', 'created_at', 'owner'];
    if ($dealsHasTitle) {
        $dealSelectFields[] = 'title';
    }
    $dealSelectFieldsString = implode(', ', $dealSelectFields);

    $stmt = $pdo->prepare("
        SELECT $dealSelectFieldsString
        FROM deals
        WHERE owner = ? AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($dealsHasTitle) {
        $deals = ensureTitle($deals);
    }

    // 7. Followers count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
    $stmt->execute([$userId]);
    $followers = (int)$stmt->fetchColumn();

    // Final response only for this single user and related info
    $response['status'] = true;
    $response['data'] = [
        'user' => $user,
        'feeds' => $feeds,
        'stats' => $stats,
        'pdfs' => $pdfsData,
        'images' => $imagesData,
        'videos' => $videosData,
        'practiceDetails' => $practiceDetails,
        'specialityDetails' => $specialityDetails,
        'deals' => $deals,
        'followersCount' => $followers,
    ];

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
