<?php
session_start();
require_once '../../includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = $_POST['order_id'] ?? 0;

if ($order_id) {
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete order items first
        $stmt1 = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt1->execute([$order_id]);
        
        // Delete order
        $stmt2 = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt2->execute([$order_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
}
?>