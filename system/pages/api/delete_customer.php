<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['admin_id']) || !hasPermission('manage_customers')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$customerId = $_POST['customer_id'] ?? 0;

if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID required']);
    exit;
}

$conn = getDBConnection();

// Check if customer exists
$checkQuery = "SELECT id, full_name, email, status FROM users WHERE id = ? AND role = 'customer' LIMIT 1";
$checkStmt = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 'i', $customerId);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) === 0) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit;
}
$customer = mysqli_fetch_assoc($checkResult);

// Soft delete: mark as inactive instead of actual deletion
$updateQuery = "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?";
$updateStmt = mysqli_prepare($conn, $updateQuery);
mysqli_stmt_bind_param($updateStmt, 'i', $customerId);

if (mysqli_stmt_execute($updateStmt)) {
    $oldValues = [
        'status' => (string)($customer['status'] ?? ''),
        'full_name' => (string)($customer['full_name'] ?? ''),
        'email' => (string)($customer['email'] ?? '')
    ];
    $newValues = [
        'status' => 'inactive',
        'full_name' => (string)($customer['full_name'] ?? ''),
        'email' => (string)($customer['email'] ?? ''),
        'message' => "Customer status changed from '" . (string)($customer['status'] ?? 'unknown') . "' to 'inactive'"
    ];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    auditLogMysqli($conn, 'customer_deactivated', 'users', $customerId, $oldValues, $newValues, $adminId, $ipAddress);
    
    echo json_encode(['success' => true, 'message' => 'Customer deactivated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to deactivate customer']);
}

mysqli_close($conn);
?>
