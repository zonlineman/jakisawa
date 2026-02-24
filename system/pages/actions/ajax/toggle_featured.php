<?php
// File: C:\xampp\htdocs\JAKISAWA\system\pages\actions\ajax\toggle_featured.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

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
    
    // 1. First, get the current featured status
    $sql = "SELECT is_featured FROM remedies WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Remedy not found']);
        exit();
    }
    
    $row = $result->fetch_assoc();
    $current_featured = $row['is_featured'];
    
    // 2. Toggle the value (1 becomes 0, 0 becomes 1)
    $new_featured = $current_featured ? 0 : 1;
    
    // 3. Update the database
    $update_sql = "UPDATE remedies SET is_featured = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $new_featured, $id);
    
    if ($update_stmt->execute()) {
        // SUCCESS - database was updated
        echo json_encode([
            'success' => true,
            'message' => 'Featured status updated',
            'is_featured' => $new_featured,
            'affected_rows' => $update_stmt->affected_rows
        ]);
        
        // Log the change
        error_log("Remedy $id featured status changed to: $new_featured");
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
