<?php
// ajax/export_audit_logs.php
session_start();
require_once '../includes/database.php';

$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? ''));
$adminId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
if ($adminId <= 0 || $role !== 'super_admin') {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$conn = getDBConnection();

function extractAuditSummary(array $row): string {
    $newValues = $row['new_values'] ?? null;
    if (is_string($newValues) && trim($newValues) !== '' && $newValues !== 'null') {
        $decoded = json_decode($newValues, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $message = trim((string)($decoded['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }
        return $newValues;
    }

    $action = str_replace('_', ' ', (string)($row['action'] ?? ''));
    $action = ucwords($action);
    $recordId = (int)($row['record_id'] ?? 0);
    if ($recordId > 0) {
        return trim($action . ' #' . $recordId);
    }
    return $action;
}

function extractAuditPayloadField(array $row, string $field, string $default = ''): string {
    $newValues = $row['new_values'] ?? null;
    if (!is_string($newValues) || trim($newValues) === '' || $newValues === 'null') {
        return $default;
    }

    $decoded = json_decode($newValues, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return $default;
    }

    $value = $decoded[$field] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user_id'] ?? '';

// Build query
$query = "SELECT al.*, 
                 u.full_name as user_name,
                 u.role as user_role
          FROM audit_log al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (al.action LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($action_filter)) {
    $query .= " AND al.action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= 's';
}

if (!empty($table_filter)) {
    $query .= " AND al.table_name = ?";
    $params[] = $table_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($user_filter) && $user_filter !== 'all') {
    $query .= " AND al.user_id = ?";
    $params[] = $user_filter;
    $types .= 's';
}

$query .= " ORDER BY al.created_at DESC";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_logs_' . date('Y-m-d') . '.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'ID', 'Timestamp', 'Action', 'Table', 'Record ID', 
    'User ID', 'User Name', 'User Role', 'IP Address', 'Severity', 'Category', 'Summary',
    'Old Values', 'New Values'
]);

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['created_at'],
        $row['action'],
        $row['table_name'] ?? '',
        $row['record_id'] ?? '',
        $row['user_id'] ?? '',
        $row['user_name'] ?? '',
        $row['user_role'] ?? '',
        $row['ip_address'] ?? '',
        extractAuditPayloadField($row, 'severity'),
        extractAuditPayloadField($row, 'category'),
        extractAuditSummary($row),
        $row['old_values'] ?? '',
        $row['new_values'] ?? ''
    ]);
}

fclose($output);

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
