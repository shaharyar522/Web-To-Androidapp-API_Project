<?php
// ===== Database Connection =====
$host = "localhost";
$db_name = "esqify_db";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Connection failed: " . $e->getMessage()
    ]);
    exit();
}

// ========= GLOBAL URL PATHS =========
// Base URL where Laravel serves static files
$GLOBALS['jobimagepath']   = "http://127.0.0.1:8000/profile/"; // ✅ match Laravel
$GLOBALS['defaultimage']   = $GLOBALS['jobimagepath'] . "default.jpg";
?>
