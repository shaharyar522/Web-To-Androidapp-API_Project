<?php
// config.php - global settings

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// adjust if your folder name is different
$serverpath = $protocol."api.mmmt.app/";
$GLOBALS['jobimagepath'] = $protocol . "api.mmmt.app/public/profile/";
$GLOBALS['dealownerimagepath'] = $protocol . "api.mmmt.app/public/profile/";
$GLOBALS['dealimage'] = $protocol . "api.mmmt.app/public/uploads/deal-images";
$basePath = $serverpath."mobile_api/";

$GLOBALS['serverpath'] = $serverpath;
$GLOBALS['basePath'] = $basePath;

define('BASE_URL', $protocol . $host . $basePath);
define('PROJECT_ROOT', __DIR__);

// $GLOBALS['profile_path'] = $host.'/public/profile'


// ===== Database Connection =====
$host = "$host";
$db_name = "mmmtapp_api";
$username = "mmmtapp_api";
$password = "mmmtapp_api";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "Connection failed: " . $e->getMessage()]);
    exit();
}


require_once 'security.php';
?>


//////////////////////







<?php
header("Content-Type: application/json");
require_once 'connection.php';

// ✅ Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method. Use POST only."
    ]);
    exit();
}

// ===== Request Parameters =====
$title  = isset($_POST['title']) ? trim($_POST['title']) : null;
$state_for_search  = isset($_POST['state_for_search']) ? trim($_POST['state_for_search']) : null;
$bar_for_search    = isset($_POST['bar_for_search']) ? trim($_POST['bar_for_search']) : null;

$industries_for_search       = isset($_POST['industries_for_search']) ? (array) $_POST['industries_for_search'] : [];
$practice_areas_for_search   = isset($_POST['practice_areas_for_search']) ? (array) $_POST['practice_areas_for_search'] : [];
$specialities_areas_for_search = isset($_POST['specialities_areas_for_search']) ? (array) $_POST['specialities_areas_for_search'] : [];

$sort      = isset($_POST['sort_data']) ? $_POST['sort_data'] : null;
$per_page  = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
$page      = isset($_POST['page']) ? intval($_POST['page']) : 1;
$page      = max($page, 1);
$offset    = ($page - 1) * $per_page;

// ===== Base Query =====
$sql_base = "FROM deals d
    LEFT JOIN users u ON u.id = d.owner
    WHERE d.deleted_at IS NULL";

$params = [];

// ===== Filters =====
if (!empty($title)) {
    $sql_base .= " AND (d.title LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params[] = "%{$title}%";
    $params[] = "%{$title}%";
}

if (!empty($state_for_search)) {
    $sql_base .= " AND d.state = ?";
    $params[] = $state_for_search;
}

if (!empty($bar_for_search)) {
    $sql_base .= " AND u.bar = ?";
    $params[] = $bar_for_search;
}

if (!empty($industries_for_search)) {
    $placeholders = implode(',', array_fill(0, count($industries_for_search), '?'));
    $sql_base .= " AND d.industry IN ($placeholders)";
    $params = array_merge($params, $industries_for_search);
}

if (!empty($practice_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($practice_areas_for_search), '?'));
    $sql_base .= " AND d.practice_area IN ($placeholders)";
    $params = array_merge($params, $practice_areas_for_search);
}

if (!empty($specialities_areas_for_search)) {
    $placeholders = implode(',', array_fill(0, count($specialities_areas_for_search), '?'));
    $sql_base .= " AND d.speciality IN ($placeholders)";
    $params = array_merge($params, $specialities_areas_for_search);
}

// ===== Count Query =====
try {
    $count_sql = "SELECT COUNT(DISTINCT d.id) as total " . $sql_base;
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching count: " . $e->getMessage()
    ]);
    exit();
}

// ===== No records found =====
if ($total == 0) {
    $reason = "There are no records matching that search.";
    if (!empty($industries_for_search)) $reason = "No deals found for selected industry.";
    elseif (!empty($practice_areas_for_search)) $reason = "No deals found for selected practice area.";
    elseif (!empty($specialities_areas_for_search)) $reason = "No deals found for selected speciality.";
    elseif (!empty($state_for_search)) $reason = "No deals found for selected state.";
    elseif (!empty($bar_for_search)) $reason = "No deals found for selected bar.";

    echo json_encode([
        "status" => false,
        "message" => $reason,
        "page" => $page,
        "per_page" => $per_page,
        "total" => 0,
        "total_pages" => 0,
        "data" => []
    ]);
    exit();
}

// ===== Sorting =====
$sortOptions = [
    'latest'     => "d.id DESC",
    'oldest'     => "d.id ASC",
    'ascending'  => "d.title ASC",
    'descending' => "d.title DESC"
];
$orderBy = $sortOptions[$sort] ?? "d.id DESC";

// ===== Data Query =====
// ===== Data Query =====
$sql = "SELECT d.*,
               CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
               u.email AS owner_email,
               u.image AS image
        " . $sql_base . "
        GROUP BY d.id
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";


$params_with_limit = array_merge($params, [$per_page, $offset]);
try {
    $stmt = $conn->prepare($sql);
    foreach ($params_with_limit as $i => $val) {
        $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Image base paths
    $GLOBALS['dealimage'] = $protocol . "api.mmmt.app/public/uploads/deal-images/";
    $GLOBALS['dealownerimagepath'] = $protocol . "api.mmmt.app/public/profile/";

   foreach ($deals as &$deal) {
    // ----- Replace `photos` with full URLs -----
    if (!empty($deal['photos'])) {
        $photosArray = json_decode($deal['photos'], true);
        if (is_array($photosArray)) {
            foreach ($photosArray as &$photo) {
                $photo = $GLOBALS['dealimage'] . $photo;
            }
            $deal['photos'] = $photosArray;
        } else {
            $deal['photos'] = [];
        }
    } else {
        $deal['photos'] = [];
    }

    // ----- Replace `image` with full URL -----
    if (!empty($deal['image'])) {
        $deal['image'] = $GLOBALS['dealownerimagepath'] . $deal['image'];
    } else {
        $deal['image'] = null;
    }
}


    echo json_encode([
        "status" => true,
        "message" => "Deals fetched successfully",
        "page" => $page,
        "per_page" => $per_page,
        "total" => (int)$total,
        "total_pages" => ceil($total / $per_page),
        "data" => $deals
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching deals: " . $e->getMessage()
    ]);
}


?>




<!-- ////////////////////////////////////// -->










<?php
header("Content-Type: application/json");

// ===== Only Allow POST =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method is allowed"]);
    exit();
}

// ===== Database Connection =====
require_once 'connection.php';

// ===== Helper: Get POST param safely =====
function getPostParam($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// ===== Determine Owner =====
// From POST if exists, else default to 1
$owner_id = intval(getPostParam('owner', 1));

// ===== Validate Required Fields =====
$required_fields = ["title", "state", "industry", "practice_area", "speciality"];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}
if (!empty($missing_fields)) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields: " . implode(", ", $missing_fields)
    ]);
    exit();
}

// ===== Handle file uploads (photos) =====
$image_names = [];
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
    $upload_dir = __DIR__ . '/../public/uploads/deal-images/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
        if (is_uploaded_file($tmp_name)) {
            $filename = preg_replace("/[^a-zA-Z0-9\.\-\s]/", "", basename($_FILES['photos']['name'][$key]));
            $target = $upload_dir . $filename;
            if (file_exists($target)) {
                $filename = time() . "_" . $filename;
                $target = $upload_dir . $filename;
            }
            if (move_uploaded_file($tmp_name, $target)) {
                $image_names[] = $filename;
            } else {
                echo json_encode([
                    "status" => false,
                    "message" => "Failed to upload file: " . $_FILES['photos']['name'][$key]
                ]);
                exit();
            }
        }
    }
}

// ===== Handle tags JSON string =====
$tagValues = [];
$tags_raw = getPostParam('tags', '[]');
$tags_decoded = json_decode($tags_raw, true);
if (is_array($tags_decoded)) {
    foreach ($tags_decoded as $tag) {
        if (isset($tag['value'])) $tagValues[] = $tag['value'];
    }
}

// ===== Handle other_attorneys array =====
$other_attorneys = getPostParam('other_attorneys', []);
if (is_string($other_attorneys)) $other_attorneys = json_decode($other_attorneys, true);
if (!is_array($other_attorneys)) $other_attorneys = [];

// ===== Prepare Insert Query =====
$sql = "INSERT INTO deals 
(title, owner, state, industry, practice_area, speciality, descriptions, notes, press_release_link, tags, photos, amount, firm, client, company_name, city, other_attorneys, created_at) 
VALUES 
(:title, :owner, :state, :industry, :practice_area, :speciality, :descriptions, :notes, :press_release_link, :tags, :photos, :amount, :firm, :client, :company_name, :city, :other_attorneys, NOW())";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(":title", trim(getPostParam("title")), PDO::PARAM_STR);
    $stmt->bindValue(":owner", $owner_id, PDO::PARAM_INT);
    $stmt->bindValue(":state", intval(getPostParam("state")), PDO::PARAM_INT);
    $stmt->bindValue(":industry", intval(getPostParam("industry")), PDO::PARAM_INT);
    $stmt->bindValue(":practice_area", intval(getPostParam("practice_area")), PDO::PARAM_INT);
    $stmt->bindValue(":speciality", intval(getPostParam("speciality")), PDO::PARAM_INT);
    $stmt->bindValue(":descriptions", trim(getPostParam("descriptions", '')), PDO::PARAM_STR);
    $stmt->bindValue(":notes", trim(getPostParam("notes", '')), PDO::PARAM_STR);
    $stmt->bindValue(":press_release_link", trim(getPostParam("press_release_link", '')), PDO::PARAM_STR);
    $stmt->bindValue(":tags", json_encode($tagValues), PDO::PARAM_STR);
    $stmt->bindValue(":photos", json_encode($image_names), PDO::PARAM_STR);
    $stmt->bindValue(":amount", getPostParam("amount", null), PDO::PARAM_STR);
    $stmt->bindValue(":firm", trim(getPostParam("firm", '')), PDO::PARAM_STR);
    $stmt->bindValue(":client", trim(getPostParam("client", '')), PDO::PARAM_STR);
    $stmt->bindValue(":company_name", trim(getPostParam("company_name", '')), PDO::PARAM_STR);
    $stmt->bindValue(":city", intval(getPostParam("city", 0)), PDO::PARAM_INT);
    $stmt->bindValue(":other_attorneys", json_encode($other_attorneys), PDO::PARAM_STR);

    $stmt->execute();

    $full_image_urls = [];
    foreach ($image_names as $img) {
        $full_image_urls[] = rtrim($GLOBALS['dealimage'], '/') . '/' . ltrim($img, '/');
    }

    echo json_encode([
        "status" => true,
        "message" => "Deal posted successfully",
        "deal_id" => $conn->lastInsertId(),
        "photos" => $full_image_urls
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error posting deal: " . $e->getMessage()
    ]);
}



