<?php
require_once 'connection.php'; // Make sure $pdo is your PDO instance

// Get job ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $sql = "DELETE FROM eq_jobs WHERE id = :id AND job_type = 'referral'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => true, 'message' => 'Referral job deleted successfully.']);
    } else {
        echo json_encode(['status' => false, 'message' => 'No referral job found with that ID.']);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid job ID.']);
}
