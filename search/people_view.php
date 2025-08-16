
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
$searchValue = isset($_POST['search']) ? trim($_POST['search']) : '';
$people_page = isset($_POST['people_page']) ? (int)$_POST['people_page'] : 1;
$people_per_page = isset($_POST['people_per_page']) ? (int)$_POST['people_per_page'] : 10;
$hasSearch = !empty($searchValue);

// ===== Pagination values =====
$offset = ($people_page - 1) * $people_per_page;


// ===== Fetch People =====
try {
    // Base query
    $sql = "SELECT SQL_CALC_FOUND_ROWS id, first_name, last_name, email, phone, law_firm, practice_area, city, industry, created_at 
            FROM users 
            WHERE deleted_at IS NULL";
    $params = [];

    // Search conditions
    if ($hasSearch) {
        $sql .= " AND (
            LOWER(first_name) LIKE :search 
            OR LOWER(last_name) LIKE :search 
            OR LOWER(email) LIKE :search 
            OR LOWER(phone) LIKE :search 
            OR LOWER(law_firm) LIKE :search 
            OR LOWER(practice_area) LIKE :search
        )";
        $params[':search'] = "%" . strtolower($searchValue) . "%";
    }

    // Sorting
    if ($hasSearch) {
        // When searching, initial sort by created_at (accuracy handled later)
        $sql .= " ORDER BY created_at DESC";
    } else {
        // Default sort
        $sql .= " ORDER BY created_at DESC";
    }

    // Pagination in SQL
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $people_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $peoples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total rows
    $total = $conn->query("SELECT FOUND_ROWS()")->fetchColumn();

    // ===== Join City & Industry =====
    foreach ($peoples as &$person) {
        if (!empty($person['city'])) {
            $cityStmt = $conn->prepare("SELECT id, name FROM citys WHERE id = ?");
            $cityStmt->execute([$person['city']]);
            $person['cityInfo'] = $cityStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $person['cityInfo'] = null;
        }

        if (!empty($person['industry'])) {
            $indStmt = $conn->prepare("SELECT id, title FROM industrys WHERE id = ?");
            $indStmt->execute([$person['industry']]);
            $person['industryInfo'] = $indStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $person['industryInfo'] = null;
        }
    }

    // ===== Accuracy Score =====
    function calculateMatchScore($person, $fields, $searchValue) {
        $score = 0;
        $searchValue = strtolower(trim($searchValue));
        $searchWords = preg_split('/\s+/', $searchValue);

        $combinedValue = '';
        foreach ($fields as $field) {
            $value = $person;
            foreach (explode('.', $field) as $k) {
                $value = isset($value[$k]) ? $value[$k] : null;
            }
            if ($value) {
                $combinedValue .= ' ' . strtolower($value);
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

        if ($foundWords === count($searchWords) && count($searchWords) > 1) {
            return 100;
        }

        if ($foundWords > 0) {
            return intval(50 * ($foundWords / count($searchWords)));
        }

        return 0;
    }

    if ($hasSearch) {
        foreach ($peoples as &$person) {
            $fields = ['first_name','last_name','email','phone','law_firm','practice_area','cityInfo.name','industryInfo.title'];
            $person['accuracy_score'] = calculateMatchScore($person, $fields, $searchValue);
        }
        // Keep only matches
        $peoples = array_filter($peoples, fn($p) => $p['accuracy_score'] > 0);
        // Sort by score
        usort($peoples, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
    } else {
        foreach ($peoples as &$person) {
            $person['accuracy_score'] = 0;
        }
    }

    // ===== Response =====
    echo json_encode([
        "status" => true,
        "total" => (int)$total,
        "total_pages" => ceil($total / $people_per_page),
        "page" => $people_page,
        "per_page" => $people_per_page,
        "data" => array_values($peoples)
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
