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


