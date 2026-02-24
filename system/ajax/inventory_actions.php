<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/audit_helper.php';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action !== 'restock') {
        respond(['success' => false, 'message' => 'Invalid action'], 400);
    }

    $role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
    $allowed = in_array($role, ['admin', 'super_admin', 'staff'], true);
    $userId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$allowed || $userId <= 0) {
        respond(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $remedyId = (int)($_POST['remedy_id'] ?? 0);
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    if ($remedyId <= 0 || $supplierId <= 0 || $quantity <= 0) {
        respond(['success' => false, 'message' => 'Supplier and valid restock input are required'], 422);
    }
    if ($quantity > 1000000) {
        respond(['success' => false, 'message' => 'Restock quantity is too large'], 422);
    }

    $conn = getDBConnection();
    if (!$conn) {
        respond(['success' => false, 'message' => 'Database connection failed'], 500);
    }

    $conn->begin_transaction();

    $supCheck = $conn->prepare('SELECT id, name, is_active FROM suppliers WHERE id = ? LIMIT 1');
    $supCheck->bind_param('i', $supplierId);
    $supCheck->execute();
    $supRes = $supCheck->get_result();
    $supplier = $supRes ? $supRes->fetch_assoc() : null;
    $supCheck->close();
    if (!$supplier || (int)$supplier['is_active'] !== 1) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Selected supplier is invalid or inactive'], 422);
    }

    $check = $conn->prepare('SELECT id, name, stock_quantity FROM remedies WHERE id = ? LIMIT 1');
    $check->bind_param('i', $remedyId);
    $check->execute();
    $res = $check->get_result();
    $remedy = $res ? $res->fetch_assoc() : null;
    $check->close();
    if (!$remedy) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Remedy not found'], 404);
    }

    $update = $conn->prepare('UPDATE remedies SET stock_quantity = stock_quantity + ?, supplier_id = ?, updated_at = NOW() WHERE id = ?');
    $update->bind_param('dii', $quantity, $supplierId, $remedyId);
    $ok = $update->execute();
    $update->close();
    if (!$ok) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Failed to update stock']);
    }

    $newStock = (float)$remedy['stock_quantity'] + $quantity;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $actionLabel = 'stock_update:' . $newStock;
    $oldValues = json_encode([
        'stock_quantity' => (float)$remedy['stock_quantity']
    ]);
    $newValues = json_encode([
        'stock_quantity' => $newStock,
        'restock_added' => $quantity,
        'supplier_id' => $supplierId,
        'supplier_name' => (string)$supplier['name'],
        'notes' => $notes
    ]);
    auditLogMysqli(
        $conn,
        'stock_update',
        'remedies',
        $remedyId,
        $oldValues,
        $newValues,
        $userId,
        $ip
    );

    $conn->commit();

    respond([
        'success' => true,
        'message' => 'Stock updated successfully',
        'data' => [
            'remedy_id' => $remedyId,
            'remedy_name' => $remedy['name'],
            'previous_stock' => (float)$remedy['stock_quantity'],
            'added' => $quantity,
            'new_stock' => $newStock,
            'supplier_id' => $supplierId,
            'supplier_name' => (string)$supplier['name'],
            'notes' => $notes
        ]
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('inventory_actions error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Server error'], 500);
}
