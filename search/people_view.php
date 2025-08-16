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

// ===== Fetch People =====
try {
    $sql = "SELECT id, first_name, last_name, email, phone, law_firm, practice_area, city, industry, created_at 
            FROM users 
            WHERE deleted_at IS NULL";
    $params = [];

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

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $peoples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== Join City =====
    foreach ($peoples as &$person) {
        if (!empty($person['city'])) {
            $cityStmt = $conn->prepare("SELECT id, name FROM citys WHERE id = ?");
            $cityStmt->execute([$person['city']]);
            $person['cityInfo'] = $cityStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $person['cityInfo'] = null;
        }

        // Industry
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

    foreach ($peoples as &$person) {
        $fields = ['first_name','last_name','email','phone','law_firm','practice_area','cityInfo.name','industryInfo.title'];
        $person['accuracy_score'] = $hasSearch ? calculateMatchScore($person, $fields, $searchValue) : 0;
    }

    // ===== Sort & Paginate =====
    if ($hasSearch) {
        $peoples = array_filter($peoples, fn($p) => $p['accuracy_score'] > 0);
        usort($peoples, fn($a,$b) => $b['accuracy_score'] <=> $a['accuracy_score']);
    } else {
        usort($peoples, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    }

    $total_people = count($peoples);
    $peoples = array_slice($peoples, ($people_page - 1) * $people_per_page, $people_per_page);

    // ===== Response =====
    echo json_encode([
        "status" => true,
        "total" => $total_people,
        "page" => $people_page,
        "per_page" => $people_per_page,
        "data" => $peoples
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
