<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/order_notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$status_type = trim((string)($_POST['status_type'] ?? 'payment'));
$status = trim((string)($_POST['status'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$user_id = (int)($_SESSION['admin_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($order_id <= 0 || $status === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    $pdo->beginTransaction();

    $orderBeforeStmt = $pdo->prepare("SELECT id, order_status, payment_status, notes FROM orders WHERE id = ? LIMIT 1");
    $orderBeforeStmt->execute([$order_id]);
    $beforeOrder = $orderBeforeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$beforeOrder) {
        throw new Exception('Order not found');
    }

    $changes = [];
    if ($status_type === 'payment') {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        if ((string)($beforeOrder['payment_status'] ?? '') !== $status) {
            $changes['payment_status'] = [
                'old' => (string)($beforeOrder['payment_status'] ?? ''),
                'new' => $status
            ];
        }

        $auditMessage = "Updated payment status to '$status' for order #$order_id";
        $auditIp = $_SERVER['REMOTE_ADDR'] ?? null;
        auditLogPdo($pdo, 'payment_status_update', 'orders', $order_id, null, $auditMessage, $user_id > 0 ? $user_id : null, $auditIp);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        if ((string)($beforeOrder['order_status'] ?? '') !== $status) {
            $changes['order_status'] = [
                'old' => (string)($beforeOrder['order_status'] ?? ''),
                'new' => $status
            ];
        }

        if ($status === 'shipped') {
            $pdo->prepare("UPDATE orders SET shipped_at = NOW() WHERE id = ?")->execute([$order_id]);
        } elseif ($status === 'delivered') {
            $pdo->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ?")->execute([$order_id]);
        }

        $auditMessage = "Updated order status to '$status' for order #$order_id";
        $auditIp = $_SERVER['REMOTE_ADDR'] ?? null;
        auditLogPdo($pdo, 'order_status_update', 'orders', $order_id, null, $auditMessage, $user_id > 0 ? $user_id : null, $auditIp);
    }

    if ($notes !== '') {
        $currentNotes = (string)($beforeOrder['notes'] ?? '');
        $newNotes = $currentNotes !== ''
            ? $currentNotes . "\n[$status_type status updated to $status] $notes"
            : "[$status_type status updated to $status] $notes";
        $pdo->prepare("UPDATE orders SET notes = ? WHERE id = ?")->execute([$newNotes, $order_id]);
    }

    $pdo->commit();

    $notification = null;
    if (!empty($changes)) {
        $notification = sendOrderLifecycleNotification($pdo, $order_id, 'status_update', $changes);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'order_id' => $order_id,
        'notification' => $notification
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}
