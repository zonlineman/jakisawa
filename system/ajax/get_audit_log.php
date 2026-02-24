<?php
// ajax/get_audit_log.php
session_start();
require_once '../includes/database.php';

header('Content-Type: application/json');

$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? ''));
$adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
if ($adminId <= 0 || $role !== 'super_admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    echo json_encode(['error' => 'Invalid log ID']);
    exit;
}

$conn = getDBConnection();

function decodeAuditJson($value)
{
    if (!is_string($value) || trim($value) === '' || strtolower(trim($value)) === 'null') {
        return null;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function firstNonEmptyField(array $data, array $keys)
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = trim((string)$data[$key]);
        if ($value !== '') {
            return $value;
        }
    }
    return null;
}

function resolveRecordNameFromValues(array $log)
{
    $oldValues = decodeAuditJson($log['old_values'] ?? null);
    $newValues = decodeAuditJson($log['new_values'] ?? null);
    $candidateKeys = [
        'name', 'full_name', 'order_number', 'product_name', 'remedy_name',
        'supplier_name', 'category_name', 'receipt_number', 'username', 'email', 'sku'
    ];

    if (is_array($newValues)) {
        $name = firstNonEmptyField($newValues, $candidateKeys);
        if ($name !== null) {
            return $name;
        }
    }
    if (is_array($oldValues)) {
        $name = firstNonEmptyField($oldValues, $candidateKeys);
        if ($name !== null) {
            return $name;
        }
    }

    return null;
}

function resolveRecordName(mysqli $conn, array $log)
{
    $table = strtolower((string)($log['table_name'] ?? ''));
    $recordId = (int)($log['record_id'] ?? 0);

    if ($recordId <= 0) {
        return resolveRecordNameFromValues($log);
    }

    $sql = null;
    $formatter = null;

    switch ($table) {
        case 'orders':
            $sql = "SELECT order_number, customer_name FROM orders WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $orderNumber = trim((string)($row['order_number'] ?? ''));
                $customerName = trim((string)($row['customer_name'] ?? ''));
                if ($orderNumber !== '' && $customerName !== '') {
                    return $orderNumber . ' - ' . $customerName;
                }
                return $orderNumber !== '' ? $orderNumber : ($customerName !== '' ? $customerName : null);
            };
            break;

        case 'remedies':
        case 'products':
            $sql = "SELECT name FROM remedies WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $name = trim((string)($row['name'] ?? ''));
                return $name !== '' ? $name : null;
            };
            break;

        case 'suppliers':
            $sql = "SELECT name FROM suppliers WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $name = trim((string)($row['name'] ?? ''));
                return $name !== '' ? $name : null;
            };
            break;

        case 'categories':
            $sql = "SELECT name FROM categories WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $name = trim((string)($row['name'] ?? ''));
                return $name !== '' ? $name : null;
            };
            break;

        case 'users':
            $sql = "SELECT full_name, username, email FROM users WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $fullName = trim((string)($row['full_name'] ?? ''));
                $username = trim((string)($row['username'] ?? ''));
                $email = trim((string)($row['email'] ?? ''));
                if ($fullName !== '') {
                    return $fullName;
                }
                if ($username !== '') {
                    return $username;
                }
                return $email !== '' ? $email : null;
            };
            break;

        case 'order_items':
            $sql = "SELECT product_name FROM order_items WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $name = trim((string)($row['product_name'] ?? ''));
                return $name !== '' ? $name : null;
            };
            break;

        case 'supplier_stock_receipts':
            $sql = "SELECT receipt_number FROM supplier_stock_receipts WHERE id = ? LIMIT 1";
            $formatter = static function (array $row) {
                $receipt = trim((string)($row['receipt_number'] ?? ''));
                return $receipt !== '' ? $receipt : null;
            };
            break;
    }

    if ($sql !== null) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $recordId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row) && is_callable($formatter)) {
                $resolved = $formatter($row);
                if (is_string($resolved) && trim($resolved) !== '') {
                    return trim($resolved);
                }
            }
        }
    }

    return resolveRecordNameFromValues($log);
}

$query = "SELECT al.*, 
                 u.full_name as user_name,
                 u.role as user_role
          FROM audit_log al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE al.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$log = $result->fetch_assoc();

if (!$log) {
    echo json_encode(['error' => 'Log not found']);
    exit;
}

$log['record_name'] = resolveRecordName($conn, $log);

echo json_encode(['log' => $log]);

$stmt->close();
$conn->close();
?>
