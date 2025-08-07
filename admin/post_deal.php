<?php
// ✅ Display all PHP and MySQL errors (important for debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ✅ Database connection settings
$server = 'localhost';
$username = 'root';
$password = '';
$dbname = 'esqifydb';

// ✅ Connect to database
$conn = mysqli_connect($server, $username, $password, $dbname);
if (!$conn) {
    die(json_encode(['status' => false, 'message' => 'Connection failed: ' . mysqli_connect_error()]));
}

header('Content-Type: application/json');

// ✅ Get JSON POST data
$data = json_decode(file_get_contents("php://input"), true);

// ✅ Optional: Log the received data for debugging
file_put_contents('debug_post_data.txt', print_r($data, true));

// ✅ Check required fields
if (empty($data['title']) || empty($data['amount']) || empty($data['owner'])) {
    echo json_encode(['status' => false, 'message' => 'Missing required fields']);
    exit;
}

// ✅ Convert amount to float in case it's sent as string
$data['amount'] = floatval($data['amount']);

// ✅ Prepare insert query — 21 columns (excluding id, including NOW() for timestamps)
$query = "INSERT INTO deals (
    title, descriptions, notes, press_release_link, tags,
    photos, amount, owner, firm, posted_date,
    other_attorneys, client, industry, company_name, state,
    city, practice_area, speciality, status, created_at, updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
)";

// ✅ Prepare statement
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// ✅ Bind 19 parameters (s = string, d = double)
$stmt->bind_param(
    'sssssssdsssssssssss',
    $data['title'],
    $data['descriptions'],
    $data['notes'],
    $data['press_release_link'],
    $data['tags'],
    $data['photos'],
    $data['amount'],
    $data['owner'],
    $data['firm'],
    $data['posted_date'],
    $data['other_attorneys'],
    $data['client'],
    $data['industry'],
    $data['company_name'],
    $data['state'],
    $data['city'],
    $data['practice_area'],
    $data['speciality'],
    $data['status']
);

// ✅ Execute statement
$success = $stmt->execute();
$affected = $stmt->affected_rows;

// ✅ Optional: Log insert result
file_put_contents('insert_debug.txt', print_r([
    'data' => $data,
    'affected_rows' => $affected,
    'stmt_error' => $stmt->error
], true));

// ✅ Send JSON response
if ($success && $affected > 0) {
    echo json_encode([
        'status' => true,
        'message' => 'Deal posted successfully',
        'inserted_id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Insert failed or no rows affected: ' . $stmt->error
    ]);
}

// ✅ Close connections
$stmt->close();
$conn->close();
?>
