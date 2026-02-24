<?php
// remove_remedy_image.php - Remove image from a remedy

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

// Get remedy ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid remedy ID']);
    exit();
}

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $project_root = realpath(__DIR__ . '/../../../../');
    if (!$project_root) {
        throw new Exception("Project root not found");
    }

    $resolve_image_file = static function ($stored_path) use ($project_root) {
        $stored_path = trim((string)$stored_path);
        if ($stored_path === '' || preg_match('#^https?://#i', $stored_path)) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $stored_path), '/');
        $candidates = [
            $project_root . '/' . $relative,
            $project_root . '/system/' . $relative
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    };
    
    // 1. Get current image path
    $sql = "SELECT image_url FROM remedies WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Remedy not found']);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $old_image_path = $row['image_url'];
    
    // 2. Delete the image file if it exists
    if ($old_image_path) {
        $main_path = $resolve_image_file($old_image_path);
        if ($main_path && file_exists($main_path)) {
            unlink($main_path);
        }

        $thumb_image_path = str_replace('/products/', '/products/thumb_', str_replace('\\', '/', (string)$old_image_path));
        $thumb_path = $resolve_image_file($thumb_image_path);
        if ($thumb_path && file_exists($thumb_path)) {
            unlink($thumb_path);
        }
    }
    
    // 3. Update database to NULL the image_url
    $update_sql = "UPDATE remedies SET image_url = NULL, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Image removed successfully',
            'remedy_id' => $id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
