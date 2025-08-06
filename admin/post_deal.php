<?php
$server = 'localhost';
$username = 'root';
$password = '';
$dbname = 'esqifydb';

$conn = mysqli_connect($server, $username, $password, $dbname);

if (!$conn) {
    die(json_encode(['status' => false, 'message' => 'Connection failed: ' . mysqli_connect_error()]));
}

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields (customize as needed)
if (empty($data['title']) || empty($data['amount']) || empty($data['owner'])) {
    echo json_encode(['status' => false, 'message' => 'Missing required fields']);
    exit;
}

// Insert query with 20 fields (excluding ID which is auto-increment)
$query = "INSERT INTO deals (
    title, descriptions, notes, press_release_link, tags,
    photos, amount, owner, firm, posted_date,
    other_attorneys, client, industry, company_name, state,
    city, practice_area, speciality, status, created_at, updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
)";

$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// Bind 19 variables (ignore created_at and updated_at as they are set using NOW())
$stmt->bind_param(
    'sssssssdsssssssssss',  // 19 placeholders: s=string, d=double
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

// Execute
$success = $stmt->execute();

if ($success) {
    echo json_encode([
        'status' => true,
        'message' => 'Deal posted successfully',
        'inserted_id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Insert failed: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
