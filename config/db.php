<?php
$server = 'localhost';
$username = 'root';
$password = '';
$dbname = 'esqifydb';

$conn = mysqli_connect($server, $username, $password, $dbname);

if (!$conn) {
    die(json_encode(['error' => 'Connection failed: ' . mysqli_connect_error()]));
}


?>
