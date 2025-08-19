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
    echo json_encode([
        "status" => false,
        "message" => "Connection failed: " . $e->getMessage()
    ]);
    exit();
}

// ===== Detect protocol + host dynamically =====
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host     = $_SERVER['HTTP_HOST'];

// ===== Global image paths =====
// Public URL used in JSON response
$GLOBALS['jobimagepath']   = $protocol . $host . "/profile/";

// Physical folder on server for file_exists()
// Adjust if your images are inside Laravel's public/profile
$GLOBALS['jobimagefolder'] = realpath(__DIR__ . "/../public/profile/") . "/";

// Default image (always full URL)
$GLOBALS['defaultimage']   = $GLOBALS['jobimagepath'] . "default.jpg";

/**
 * Get full image URL or fallback to default
 */
function getImageUrl($filename) {
    if (!empty($filename)) {
        $fullPath = $GLOBALS['jobimagefolder'] . $filename;
        if (file_exists($fullPath)) {
            return $GLOBALS['jobimagepath'] . $filename;
        }
    }
    return $GLOBALS['defaultimage'];
}

/**
 * Normalize photos field (can be JSON string, array, null, etc.)
 * @param mixed $photos
 * @return array
 */
function normalizePhotos($photos) {
    // Already array → return directly
    if (is_array($photos)) {
        return $photos;
    }

    // JSON string → decode safely
    if (is_string($photos)) {
        $decoded = json_decode($photos, true);
        return is_array($decoded) ? $decoded : [];
    }

    // Otherwise empty
    return [];
}
?>
