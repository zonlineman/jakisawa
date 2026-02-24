<?php
session_start();
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/order_notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$servitorId = (int)($_SESSION['admin_id'] ?? 0);

$order_id = $_POST['order_id'] ?? 0;
$type = $_POST['type'] ?? '';
$status = $_POST['status'] ?? '';

if ($order_id && $type && $status) {
    $column = $type === 'payment' ? 'payment_status' : 'order_status';

    $beforeStmt = $pdo->prepare("SELECT id, order_status, payment_status FROM orders WHERE id = ? LIMIT 1");
    $beforeStmt->execute([$order_id]);
    $beforeOrder = $beforeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$beforeOrder) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    $oldStatus = (string)($beforeOrder[$column] ?? '');
    
    $stmt = $pdo->prepare("UPDATE orders SET $column = ?, updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$status, $order_id]);
    
    if ($success && $stmt->rowCount() > 0) {
        // Log the status change
        $logStmt = $pdo->prepare("
            INSERT INTO order_logs (order_id, action, details, admin_id) 
            VALUES (?, ?, ?, ?)
        ");
        $logStmt->execute([
            $order_id, 
            'status_update', 
            "Changed $type status to $status", 
            $_SESSION['admin_id']
        ]);

        $notify = sendOrderLifecycleNotification(
            $pdo,
            (int)$order_id,
            'status_update',
            [
                $column => [
                    'old' => $oldStatus,
                    'new' => $status
                ]
            ]
        );
        
        echo json_encode(['success' => true, 'notification' => $notify]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found or no changes made']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?>
