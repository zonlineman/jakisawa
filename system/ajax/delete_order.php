<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = strtolower((string)($_SESSION['admin_role'] ?? ''));
if (!in_array($role, ['admin', 'super_admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only admin can delete orders']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get order info before deleting
    $orderStmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
    $orderStmt->execute([$order_id]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete order items
    $stmt1 = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt1->execute([$order_id]);
    
    // Delete order
    $stmt2 = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    $stmt2->execute([$order_id]);
    
    // Log the action
    auditLogPdo(
        $pdo,
        'order_deleted',
        'orders',
        $order_id,
        null,
        "Deleted order #" . $order['order_number'],
        $_SESSION['admin_id'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null
    );
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order deleted successfully',
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
