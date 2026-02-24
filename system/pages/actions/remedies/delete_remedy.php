<?php
// DELETE_REMEDY.PHP - ACTUALLY DELETES FROM DATABASE

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
if (!in_array($role, ['admin', 'super_admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only admin can delete remedies']);
    exit();
}

// Get the remedy ID to delete
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
    
    // First, check if remedy exists and get its name for logging
    $check_sql = "SELECT name FROM remedies WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Remedy not found']);
        exit();
    }
    
    $remedy = $result->fetch_assoc();
    $remedy_name = $remedy['name'];
    
    // DELETE the remedy from database
    $delete_sql = "DELETE FROM remedies WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $id);
    
    if ($delete_stmt->execute()) {
        $affected_rows = $delete_stmt->affected_rows;
        
        if ($affected_rows > 0) {
            // Log the deletion
            error_log("Remedy DELETED: ID=$id, Name='$remedy_name'");
            
            echo json_encode([
                'success' => true,
                'message' => 'Remedy deleted successfully',
                'deleted_id' => $id,
                'deleted_name' => $remedy_name,
                'affected_rows' => $affected_rows
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No remedy was deleted']);
        }
    } else {
        throw new Exception("Delete failed: " . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
