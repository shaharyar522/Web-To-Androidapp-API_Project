<?php
require_once 'connection.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get job ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Delete job
if ($id > 0) {
    $conn->query("DELETE FROM eq_jobs WHERE id = $id");
}

?>