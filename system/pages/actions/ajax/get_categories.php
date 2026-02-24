<?php
/**
 * GET CATEGORIES API ENDPOINT
 * Returns categories list as JSON
 */

// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Clear any previous output
ob_clean();

require_once __DIR__ . '/../../../includes/database.php';

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $query = "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
?>