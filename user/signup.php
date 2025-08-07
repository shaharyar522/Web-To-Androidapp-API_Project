<?php
header("Content-Type: application/json");
require_once("../config/db.php"); // update this path if needed

$data = json_decode(file_get_contents("php://input"));

if (
    isset($data->first_name) &&
    isset($data->last_name) &&
    isset($data->email) &&
    isset($data->password)
) {
    $first_name = htmlspecialchars(strip_tags($data->first_name));
    $last_name = htmlspecialchars(strip_tags($data->last_name));
    $email = htmlspecialchars(strip_tags($data->email));
    $password = password_hash($data->password, PASSWORD_DEFAULT);

    $username = strtolower(str_replace(' ', '.', $first_name . '.' . $last_name)) . rand(100, 999);
    $role_id = 2;
    $created_at = date("Y-m-d H:i:s");
    $updated_at = date("Y-m-d H:i:s");

    try {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "Email already registered"]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO users (username, first_name, last_name, email, password, role_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $username, $first_name, $last_name, $email,
            $password, $role_id, $created_at, $updated_at
        ]);

        if ($result) {
            echo json_encode(["status" => "success", "message" => "User registered successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Insert failed"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "DB error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete data"]);
}
?>
