<?php
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No order ID provided']);
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               SUM(oi.total_price) as items_total
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $items_stmt = $pdo->prepare("
        SELECT oi.*, r.image_url, r.description as product_description
        FROM order_items oi
        LEFT JOIN remedies r ON oi.product_id = r.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get audit log for this order
    $audit_stmt = $pdo->prepare("
        SELECT al.*, u.full_name as user_name
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.table_name = 'orders' AND al.record_id = ?
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $audit_stmt->execute([$order_id]);
    $audit_logs = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'audit_logs' => $audit_logs
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>