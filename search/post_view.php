<?php
header("Content-Type: application/json");

// ===== Database Connection =====
$host = "localhost";
$db_name = "esqify_db";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Connection failed: " . $e->getMessage()]);
    exit();
}

// ===== Only POST Method =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit();
}

// ===== Input =====
$searchValue   = isset($_POST['search']) ? trim($_POST['search']) : '';
$posts_page    = isset($_POST['posts_page']) ? (int)$_POST['posts_page'] : 1;
$post_per_page = isset($_POST['post_per_page']) ? (int)$_POST['post_per_page'] : 10;
$hasSearch     = !empty($searchValue);

try {
    // ===== Fetch Only Needed Feed Columns =====
    $sql = "SELECT id, title, descriptions, tags, photos, owner, created_at 
            FROM feeds 
            WHERE deleted_at IS NULL";
    $params = [];

    // ===== Search Condition (exact same as controller) =====
    if ($hasSearch) {
        $sql .= " AND (
            LOWER(title) LIKE :search OR 
            LOWER(descriptions) LIKE :search OR 
            LOWER(tags) LIKE :search OR 
            LOWER(photos) LIKE :search
        )";
        $params[':search'] = "%" . strtolower($searchValue) . "%";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== Attach Relations (Controller Style) =====
    foreach ($posts as &$post) {
        // --- Owner Info ---
        if (!empty($post['owner'])) {
            $ownerStmt = $conn->prepare("SELECT id, first_name, last_name, email, city 
                                         FROM users 
                                         WHERE id = ?");
            $ownerStmt->execute([$post['owner']]);
            $post['ownerInfo'] = $ownerStmt->fetch(PDO::FETCH_ASSOC);

            // --- Owner City ---
            if (!empty($post['ownerInfo']['city'])) {
                $cityStmt = $conn->prepare("SELECT id, name 
                                            FROM citys 
                                            WHERE id = ?");
                $cityStmt->execute([$post['ownerInfo']['city']]);
                $post['ownerInfo']['usercity'] = $cityStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $post['ownerInfo']['usercity'] = null;
            }

            // --- Boosts for this user ---
            $boostStmt = $conn->prepare("SELECT id, user_id, keywords 
                                         FROM boosts 
                                         WHERE user_id = ?");
            $boostStmt->execute([$post['ownerInfo']['id']]);
            $post['boost'] = $boostStmt->fetch(PDO::FETCH_ASSOC);

        } else {
            $post['ownerInfo'] = null;
            $post['boost'] = null;
        }
    }

    // ===== Accuracy Score (Controller Rule) =====
    function calculateMatchScore($post, $fields, $searchValue) {
        $score = 0;
        $searchValue = strtolower($searchValue);
        $searchWords = preg_split('/\s+/', $searchValue);

        foreach ($fields as $field) {
            $value = $post;
            foreach (explode('.', $field) as $k) {
                $value = isset($value[$k]) ? $value[$k] : null;
            }
            if ($value) {
                $value = strtolower($value);
                if (strpos($value, $searchValue) !== false) {
                    $score += 100; // full match
                    continue;
                }
                $foundWords = 0;
                foreach ($searchWords as $word) {
                    if (strpos($value, $word) !== false) {
                        $foundWords++;
                    }
                }
                if ($foundWords > 0) {
                    $score += intval(50 * ($foundWords / count($searchWords)));
                }
            }
        }
        return $score;
    }

    foreach ($posts as &$post) {
        $fields = [
            'title',
            'descriptions',
            'tags',
            'photos',
            'ownerInfo.first_name',
            'ownerInfo.last_name',
            'ownerInfo.usercity.name'
        ];
        if (!empty($post['boost']['keywords'])) {
            $fields[] = 'boost.keywords';
            $post['boost_keywords'] = $post['boost']['keywords'];
        }
        $post['accuracy_score'] = $hasSearch ? calculateMatchScore($post, $fields, $searchValue) : 0;
    }

    // ===== Sort & Paginate =====
    if ($hasSearch) {
        $posts = array_filter($posts, fn($p) => $p['accuracy_score'] > 0);
        usort($posts, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
    } else {
        usort($posts, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

    $total_posts = count($posts);
    $posts = array_slice($posts, ($posts_page - 1) * $post_per_page, $post_per_page);

    // ===== Response =====
    echo json_encode([
        "status"    => true,
        "total"     => $total_posts,
        "page"      => $posts_page,
        "per_page"  => $post_per_page,
        "data"      => $posts
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
