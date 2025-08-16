<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// ===== Database Connection =====
$host = "localhost";
$db_name = "esqify_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Connection failed: " . $e->getMessage()]);
    exit();
}

// ===== Helper: JSON Response =====
function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ===== Helper: Match Score =====
function calculateMatchScore($row, $fields, $searchValue) {
    $searchValue = strtolower(trim($searchValue));
    $searchWords = preg_split('/\s+/', $searchValue);

    $combinedValue = '';
    foreach ($fields as $field) {
        if (!empty($row[$field])) {
            $combinedValue .= ' ' . strtolower($row[$field]);
        }
    }

    if (str_contains($combinedValue, $searchValue)) {
        return 100;
    }

    $foundWords = 0;
    foreach ($searchWords as $word) {
        if (str_contains($combinedValue, $word)) {
            $foundWords++;
        }
    }

    if ($foundWords === count($searchWords) && $foundWords > 0) {
        return 100;
    }
    if ($foundWords > 0) {
        return intval(50 * ($foundWords / count($searchWords)));
    }

    return 0;
}

// ===== Parse URL (PATH_INFO / GET / fallback) =====
$pathInfo = $_SERVER["PATH_INFO"] ?? "";
$request = explode("/", trim($pathInfo, "/"));
$resource = $request[0] ?? ($_GET['resource'] ?? null);
$id = $request[1] ?? ($_GET['id'] ?? null);

// ✅ Default fallback: if no resource given, assume "referrals"
if (!$resource) {
    $resource = "referrals";
}

$method = $_SERVER['REQUEST_METHOD'];

// ===== Referrals API =====
if ($resource === "referrals") {
    switch ($method) {
        case "GET":
            if ($id) {
                // 🔹 Single referral
                $stmt = $pdo->prepare("SELECT * FROM eq_jobs WHERE id = ? AND job_type = 'referral' AND deleted_at IS NULL");
                $stmt->execute([$id]);
                $referral = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($referral) {
                    response(["status" => true, "data" => $referral]);
                } else {
                    response(["status" => false, "message" => "Referral not found"], 404);
                }
            } else {
                // 🔹 List/search referrals
                $search = strtolower($_GET['search'] ?? '');
                $perPage = intval($_GET['per_page'] ?? 10);
                $page = intval($_GET['page'] ?? 1);
                $offset = ($page - 1) * $perPage;

                $sql = "SELECT * FROM eq_jobs WHERE job_type = 'referral' AND deleted_at IS NULL";
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($search)) {
                    foreach ($rows as &$row) {
                        $row['accuracy_score'] = calculateMatchScore(
                            $row,
                            ['title', 'descriptions', 'notes', 'firm', 'position', 'representative'],
                            $search
                        );
                    }
                    $rows = array_filter($rows, fn($r) => $r['accuracy_score'] > 0);
                    usort($rows, fn($a, $b) => $b['accuracy_score'] <=> $a['accuracy_score']);
                } else {
                    usort($rows, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
                }

                $total = count($rows);
                $rows = array_slice($rows, $offset, $perPage);

                response([
                    "status" => true,
                    "page" => $page,
                    "per_page" => $perPage,
                    "total" => $total,
                    "data" => array_values($rows)
                ]);
            }
            break;

        case "POST":
            $input = json_decode(file_get_contents("php://input"), true);
            if (empty($input['title'])) {
                response(["status" => false, "message" => "Title is required"], 400);
            }

            $stmt = $pdo->prepare("INSERT INTO eq_jobs (title, descriptions, firm, position, representative, job_type, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 'referral', NOW())");
            $stmt->execute([
                $input['title'],
                $input['descriptions'] ?? null,
                $input['firm'] ?? null,
                $input['position'] ?? null,
                $input['representative'] ?? null
            ]);

            response(["status" => true, "message" => "Referral created", "id" => $pdo->lastInsertId()], 201);
            break;

        case "PUT":
            if (!$id) {
                response(["status" => false, "message" => "ID required"], 400);
            }
            $input = json_decode(file_get_contents("php://input"), true);

            $stmt = $pdo->prepare("UPDATE eq_jobs 
                                   SET title=?, descriptions=?, firm=?, position=?, representative=?, updated_at=NOW() 
                                   WHERE id=? AND job_type='referral'");
            $stmt->execute([
                $input['title'] ?? null,
                $input['descriptions'] ?? null,
                $input['firm'] ?? null,
                $input['position'] ?? null,
                $input['representative'] ?? null,
                $id
            ]);

            response(["status" => true, "message" => "Referral updated"]);
            break;

        case "DELETE":
            if (!$id) {
                response(["status" => false, "message" => "ID required"], 400);
            }
            $stmt = $pdo->prepare("UPDATE eq_jobs SET deleted_at=NOW() WHERE id=? AND job_type='referral'");
            $stmt->execute([$id]);

            response(["status" => true, "message" => "Referral deleted"]);
            break;

        default:
            response(["status" => false, "message" => "Method not allowed"], 405);
    }
} else {
    response(["status" => false, "message" => "Resource not found"], 404);
}
