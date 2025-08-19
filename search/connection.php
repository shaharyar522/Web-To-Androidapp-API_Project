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
    echo json_encode(["status" => false, "message" => "Connection failed: " . $e->getMessage()]);
    exit();
}


// ===== Detect protocol =====
$protocol = "http://"; // force HTTP for local
$GLOBALS['jobimagepath'] = $protocol . "127.0.0.1:8000/profile/"; // folder with images

?>



	