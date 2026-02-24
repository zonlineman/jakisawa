<?php
// Force JSON output
header('Content-Type: application/json');

// Start session and include database
session_start();
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/audit_helper.php';
require_once __DIR__ . '/../includes/order_notifications.php';

// Check authentication
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
$servitorId = (int)($_SESSION['admin_id'] ?? 0);

// Get and validate input
$order_id = intval($_POST['order_id'] ?? 0);
$type = trim($_POST['type'] ?? '');
$status = trim($_POST['status'] ?? '');

if ($order_id <= 0 || empty($type) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate status types
$valid_types = ['payment', 'order'];
if (!in_array($type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit;
}

// Valid status values
$valid_statuses = [
    'payment' => ['pending', 'paid', 'failed', 'refunded', 'cancelled'],
    'order' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled']
];

if (!in_array($status, $valid_statuses[$type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status for this type']);
    exit;
}

try {
    // Determine column to update
    $column = ($type === 'payment') ? 'payment_status' : 'order_status';
    $beforeStmt = $pdo->prepare("SELECT id, order_number, order_status, payment_status FROM orders WHERE id = ? LIMIT 1");
    $beforeStmt->execute([$order_id]);
    $beforeOrder = $beforeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$beforeOrder) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $oldStatus = (string)($beforeOrder[$column] ?? '');
    
    // Update order
    $stmt = $pdo->prepare("UPDATE orders SET $column = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $order_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        $changes = [
            $column => [
                'old' => $oldStatus,
                'new' => $status
            ]
        ];
        $notify = sendOrderLifecycleNotification($pdo, (int)$order_id, 'status_update', $changes);

        // Log the change
        $auditAction = $type === 'payment' ? 'payment_status_update' : 'order_status_update';
        auditLogPdo(
            $pdo,
            $auditAction,
            'orders',
            $order_id,
            null,
            "Updated $type status to '$status' for order #$order_id",
            $servitorId > 0 ? $servitorId : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'order_id' => $order_id,
            'type' => $type,
            'status' => $status,
            'notification' => $notify
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found or no changes made']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
