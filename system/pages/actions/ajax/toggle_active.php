<?php
// File: C:\xampp\htdocs\JAKISAWA\system\pages\actions\ajax\toggle_active.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
if (!in_array($role, ['admin', 'super_admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only admin can activate/deactivate remedies']);
    exit();
}

// Get the remedy ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid remedy ID']);
    exit();
}

try {
    // Connect to database
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Could not connect to database");
    }
    
    // 1. First, get the current active status
    $sql = "SELECT is_active FROM remedies WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Remedy not found']);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $current_active = $row['is_active'];
    
    // 2. Toggle the value (1 becomes 0, 0 becomes 1)
    $new_active = $current_active ? 0 : 1;
    
    // 3. Update the database
    $update_sql = "UPDATE remedies SET is_active = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $new_active, $id);
    
    if ($update_stmt->execute()) {
        // SUCCESS - database was updated
        echo json_encode([
            'success' => true,
            'message' => 'Active status updated',
            'is_active' => $new_active,
            'affected_rows' => $update_stmt->affected_rows
        ]);
        
        // Log the change
        error_log("Remedy $id active status changed to: $new_active");
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database update failed: ' . $update_stmt->error
        ]);
    }
    
    $update_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
