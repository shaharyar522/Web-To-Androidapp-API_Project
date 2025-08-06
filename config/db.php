<?php
$server = 'localhost';
$dbname = 'esqifydb'; 
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$server;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}
?>
