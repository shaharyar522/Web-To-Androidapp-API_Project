<?php
// config.php - global settings

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// adjust if your folder name is different
$basePath = "/my-api-project/deals/";

define('BASE_URL', $protocol . $host . $basePath);
define('PROJECT_ROOT', __DIR__);
