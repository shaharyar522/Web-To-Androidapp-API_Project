<?php
require_once 'connection.php'; // Defines $pdo, $GLOBALS['UPLOADS_DIR'], $GLOBALS['UPLOADS_URL']
session_start();
header('Content-Type: application/json');

// Simulate logged-in user
$_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['status' => false, 'message' => 'User not authenticated']);
    exit;
}

$status = $_POST['descriptions'] ?? null;
$images = $_FILES['photos'] ?? null;

$validMime = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024;
$imageNames = [];

try {
    if ($images && is_array($images['name'])) {
        $uploadDir = $GLOBALS['UPLOADS_DIR'];

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        for ($i = 0; $i < count($images['name']); $i++) {
            if ($images['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

            if ($images['error'][$i] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error for image: ' . $images['name'][$i]);
            }

            if ($images['size'][$i] > $maxSize) {
                throw new Exception('File too large: ' . $images['name'][$i]);
            }

            if (!in_array($images['type'][$i], $validMime)) {
                throw new Exception('Invalid file type: ' . $images['name'][$i]);
            }

            $ext = pathinfo($images['name'][$i], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $filename;

            if (!move_uploaded_file($images['tmp_name'][$i], $dest)) {
                throw new Exception('Failed to move uploaded file: ' . $images['name'][$i]);
            }

            // Resize using GD if available
            if (function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
                list($w, $h) = getimagesize($dest);
                $newW = min(1980, $w);
                $newH = min(500, $h);

                switch ($images['type'][$i]) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $src_image = imagecreatefromjpeg($dest);
                        break;
                    case 'image/png':
                        $src_image = imagecreatefrompng($dest);
                        break;
                    case 'image/gif':
                        $src_image = imagecreatefromgif($dest);
                        break;
                    case 'image/webp':
                        $src_image = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($dest) : null;
                        break;
                    default:
                        $src_image = null;
                }

                if ($src_image) {
                    $tmp = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($tmp, $src_image, 0, 0, 0, 0, $newW, $newH, $w, $h);
                    imagejpeg($tmp, $dest, 85);
                    imagedestroy($src_image);
                    imagedestroy($tmp);
                }
            }

            $imageNames[] = $filename;
        }
    }

    // Store filenames in DB (as JSON)
    $photosJson = json_encode($imageNames);
    $stmt = $pdo->prepare("
        INSERT INTO feeds (descriptions, photos, posted_date, owner, created_at)
        VALUES (:descriptions, :photos, NOW(), :owner, NOW())
    ");
    $stmt->execute([
        ':descriptions' => $status,
        ':photos' => $photosJson,
        ':owner' => $userId
    ]);

    // Generate URLs from filenames using global UPLOADS_URL
    $imageUrls = array_map(fn($img) => $GLOBALS['UPLOADS_URL'] . $img, $imageNames);

    echo json_encode([
        'status' => true,
        'message' => 'Post created successfully.',
        'post_id' => $pdo->lastInsertId(),
        'photos' => $imageUrls
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
