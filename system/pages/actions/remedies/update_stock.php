<?php
/**
 * UPDATE STOCK API ENDPOINT
 * Handles stock quantity updates
 */

// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Clear any previous output
ob_clean();

session_start();
require_once __DIR__ . '/../../../includes/database.php';

try {
    // Verify POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $remedy_id = intval($_POST['remedy_id'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if ($remedy_id <= 0) {
        throw new Exception('Invalid remedy ID');
    }
    
    if ($stock_quantity < 0) {
        throw new Exception('Stock quantity cannot be negative');
    }

    if ($supplier_id <= 0) {
        throw new Exception('Supplier is required');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Validate supplier (must be active)
    $supCheck = $conn->prepare("SELECT id FROM suppliers WHERE id = ? AND is_active = 1 LIMIT 1");
    if (!$supCheck) {
        throw new Exception('Failed to prepare supplier check');
    }
    $supCheck->bind_param('i', $supplier_id);
    $supCheck->execute();
    $supRes = $supCheck->get_result();
    if (!$supRes || $supRes->num_rows === 0) {
        $supCheck->close();
        throw new Exception('Selected supplier is invalid or inactive');
    }
    $supCheck->close();

    // Update stock and supplier link
    $query = "UPDATE remedies SET stock_quantity = ?, supplier_id = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $stock_quantity, $supplier_id, $remedy_id);
    $stmt->execute();
    
    if ($stmt->affected_rows >= 0) {
        $stmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update stock');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
?>
