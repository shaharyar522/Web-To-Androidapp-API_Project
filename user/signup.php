<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$data = json_decode(file_get_contents("php://input"));

if (
    isset($data->first_name) &&
    isset($data->last_name) &&
    isset($data->email) &&
    isset($data->password)
){
    $first_name = htmlspecialchars(strip_tags($data->first_name));
    $last_name = htmlspecialchars(strip_tags($data->last_name));
    $email = htmlspecialchars(strip_tags($data->email));
    $password = password_hash($data->password, PASSWORD_DEFAULT); // secure

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        echo json_encode(["status" => "error", "message" => "Email already registered"]);
        exit;
    }

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$first_name, $last_name, $email, $password]);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "User registered successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Something went wrong"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete data"]);
}

?>
