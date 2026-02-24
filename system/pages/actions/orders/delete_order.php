<?php
// delete_order.php - Soft delete an order
require_once '../../../includes/database.php';
require_once '../../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $user_id = $_SESSION['admin_id'] ?? 0;
    
    if (!$order_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing order ID']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if order exists
        $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND deleted_at IS NULL");
        $checkStmt->execute([$order_id]);
        
        if (!$checkStmt->fetch()) {
            throw new Exception('Order not found or already deleted');
        }
        
        // Soft delete the order
        $stmt = $pdo->prepare("UPDATE orders SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Log the deletion
        $auditMessage = "Deleted order #$order_id";
        $auditIp = $_SERVER['REMOTE_ADDR'] ?? null;
        auditLogPdo($pdo, 'order_deleted', 'orders', $order_id, null, $auditMessage, $user_id > 0 ? $user_id : null, $auditIp);
        
        $pdo->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting order: ' . $e->getMessage()
        ]);
    }
}
?>
