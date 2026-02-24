<?php
// pages/audit_logs.php
// Audit Log Management System

// // Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
//     header('Location: admin_login.php');
//     exit();
// }

// ===== INITIALIZE SESSION VARIABLES (NON-DESTRUCTIVE) =====
$adminRole = $_SESSION['admin_role'] ?? null;
$adminId = $_SESSION['admin_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

if (!$adminRole && $userRole) {
    $_SESSION['admin_role'] = $userRole;
    $adminRole = $userRole;
}
if (($adminId === null || $adminId === '') && $userId !== null) {
    $_SESSION['admin_id'] = $userId;
    $adminId = $userId;
}
if (empty($_SESSION['admin_name']) && $userName) {
    $_SESSION['admin_name'] = $userName;
}

$_SESSION['admin_logged_in'] = in_array(($adminRole ?? ''), ['super_admin', 'admin', 'staff'], true) && !empty($adminId);

$adminRoleNormalized = strtolower((string)($adminRole ?? ''));
$isSuperAdmin = ($adminRoleNormalized === 'super_admin');

function auditLogSafeRedirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    $safeUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
    echo "<script>window.location.href = {$safeUrl};</script>";
    exit;
}

if (!$isSuperAdmin) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Super admin only.'];
    auditLogSafeRedirect('admin_dashboard.php?page=dashboard');
}


require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/audit_helper.php';

$conn = getDBConnection();
// $current_user_id = $_SESSION['user_id'];

// Super admin only: clear old logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old_logs') {
    if (!$isSuperAdmin) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Only super admin can clear audit logs.'];
    } else {
        $daysOld = max(1, (int)($_POST['days_old'] ?? 30));
        $deleteSql = "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $deleteStmt = $conn->prepare($deleteSql);
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $daysOld);
            $deleteStmt->execute();
            $deletedRows = $deleteStmt->affected_rows;
            $deleteStmt->close();
            $_SESSION['message'] = ['type' => 'success', 'text' => "Deleted {$deletedRows} log(s) older than {$daysOld} day(s)."];

            $actorId = (int)($_SESSION['admin_id'] ?? 0);
            $actorIp = $_SERVER['REMOTE_ADDR'] ?? null;
            $meta = json_encode(['days_old' => $daysOld, 'deleted_rows' => $deletedRows], JSON_UNESCAPED_UNICODE);
            auditLogMysqli($conn, 'clear_old_logs', 'audit_log', 0, null, $meta, $actorId, $actorIp);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to clear logs. Please try again.'];
        }
    }

    $redirectQs = http_build_query([
        'page' => 'audit_log',
        'search' => (string)($_GET['search'] ?? ''),
        'action' => (string)($_GET['action'] ?? ''),
        'table' => (string)($_GET['table'] ?? ''),
        'date_from' => (string)($_GET['date_from'] ?? ''),
        'date_to' => (string)($_GET['date_to'] ?? ''),
        'user_id' => (string)($_GET['user_id'] ?? ''),
        'p' => (string)($_GET['p'] ?? 1),
    ]);
    auditLogSafeRedirect('admin_dashboard.php?' . $redirectQs);
}

// Handle filter parameters
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT al.*, 
                 u.full_name as user_name,
                 u.role as user_role
          FROM audit_log al
          LEFT JOIN users u ON al.user_id = u.id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM audit_log al WHERE 1=1";
$params = [];
$count_params = [];

if (!empty($search)) {
    $query .= " AND (al.action LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ? OR al.old_values LIKE ? OR al.new_values LIKE ?)";
    $count_query .= " AND (al.action LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ? OR al.old_values LIKE ? OR al.new_values LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $search_term));
    $count_params = array_merge($count_params, array_fill(0, 5, $search_term));
}

if (!empty($action_filter)) {
    $query .= " AND al.action LIKE ?";
    $count_query .= " AND al.action LIKE ?";
    $params[] = "%$action_filter%";
    $count_params[] = "%$action_filter%";
}

if (!empty($table_filter)) {
    $query .= " AND al.table_name = ?";
    $count_query .= " AND al.table_name = ?";
    $params[] = $table_filter;
    $count_params[] = $table_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= ?";
    $count_query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $count_params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= ?";
    $count_query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $count_params[] = $date_to;
}

if (!empty($user_filter) && $user_filter !== 'all') {
    $query .= " AND al.user_id = ?";
    $count_query .= " AND al.user_id = ?";
    $params[] = $user_filter;
    $count_params[] = $user_filter;
}

$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Get logs
$stmt = $conn->prepare($query);
if ($params) {
    $types = str_repeat('s', count($params) - 2) . 'ii';
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count
$count_stmt = $conn->prepare($count_query);
if ($count_params) {
    $types = str_repeat('s', count($count_params));
    $count_stmt->bind_param($types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_logs = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Get distinct tables for filter
$tables_query = "SELECT DISTINCT table_name FROM audit_log WHERE table_name IS NOT NULL ORDER BY table_name";
$tables_result = $conn->query($tables_query);
$tables = $tables_result->fetch_all(MYSQLI_ASSOC);

// Get distinct actions for filter
$actions_query = "SELECT DISTINCT action FROM audit_log WHERE action IS NOT NULL ORDER BY action";
$actions_result = $conn->query($actions_query);
$actions = $actions_result->fetch_all(MYSQLI_ASSOC);

// Get users for filter
$users_query = "SELECT DISTINCT al.user_id, u.full_name 
                FROM audit_log al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE al.user_id IS NOT NULL 
                ORDER BY u.full_name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT table_name) as unique_tables,
        DATE(created_at) as log_date,
        COUNT(*) as daily_count
    FROM audit_log 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY log_date DESC
";
$stats_result = $conn->query($stats_query);
$daily_stats = $stats_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Function to format action badge
function humanizeAction($action) {
    $label = str_replace(['_', ':'], ' ', (string)$action);
    $label = preg_replace('/\s+/', ' ', $label);
    return ucwords(trim((string)$label));
}

function getActionBadge($action) {
    $action_lower = strtolower($action);
    $display = humanizeAction($action);
    
    if (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) {
        return '<span class="badge bg-success">' . htmlspecialchars($display) . '</span>';
    } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) {
        return '<span class="badge bg-warning">' . htmlspecialchars($display) . '</span>';
    } elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false) {
        return '<span class="badge bg-danger">' . htmlspecialchars($display) . '</span>';
    } elseif (strpos($action_lower, 'access') !== false) {
        return '<span class="badge bg-info">' . htmlspecialchars($display) . '</span>';
    } elseif (strpos($action_lower, 'login') !== false) {
        return '<span class="badge bg-primary">' . htmlspecialchars($display) . '</span>';
    } else {
        return '<span class="badge bg-secondary">' . htmlspecialchars($display) . '</span>';
    }
}

// Function to format table badge
function getTableBadge($table) {
    $tables_colors = [
        'users' => 'primary',
        'orders' => 'success',
        'remedies' => 'info',
        'products' => 'info',
        'suppliers' => 'warning',
        'categories' => 'secondary',
        'audit_log' => 'dark'
    ];
    
    $color = $tables_colors[$table] ?? 'light';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($table) . '</span>';
}

// Function to format user info
function getUserInfo($user_id, $user_name, $user_role) {
    if (!$user_id) {
        return '<span class="text-muted">System</span>';
    }
    
    $role_badge = '';
    if ($user_role === 'super_admin') {
        $role_badge = '<span class="badge bg-danger ms-1">Super Admin</span>';
    } elseif ($user_role === 'admin') {
        $role_badge = '<span class="badge bg-danger ms-1">Admin</span>';
    } elseif ($user_role === 'staff') {
        $role_badge = '<span class="badge bg-primary ms-1">Staff</span>';
    }
    
    return htmlspecialchars($user_name) . $role_badge;
}

// Function to format JSON data
function formatJsonData($json) {
    if (empty($json) || $json === 'null') {
        return '<span class="text-muted">No data</span>';
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<code>' . htmlspecialchars(substr($json, 0, 100)) . '...</code>';
    }
    
    return '<pre class="mb-0" style="font-size: 0.8rem; max-height: 200px; overflow-y: auto;">' 
           . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) 
           . '</pre>';
}

// Function to extract a concise summary for table view
function getLogSummary(array $log): string {
    $newValuesRaw = $log['new_values'] ?? '';
    if (!empty($newValuesRaw) && $newValuesRaw !== 'null') {
        $decoded = json_decode((string)$newValuesRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $message = trim((string)($decoded['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }

            if (isset($decoded['status'])) {
                return 'Status: ' . (string)$decoded['status'];
            }
        } else {
            $plain = trim((string)$newValuesRaw);
            if ($plain !== '') {
                return $plain;
            }
        }
    }

    $action = str_replace('_', ' ', (string)($log['action'] ?? ''));
    $action = ucwords($action);
    $record = (int)($log['record_id'] ?? 0);
    if ($record > 0) {
        return trim($action . ' #' . $record);
    }

    return $action !== '' ? $action : 'No summary';
}

function truncateText($text, $limit = 90) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit - 3) . '...';
}

function extractLogPayload($json) {
    if (empty($json) || $json === 'null') {
        return [];
    }
    $decoded = json_decode((string)$json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }
    return [];
}

function getLogSeverity(array $log): string {
    $payload = extractLogPayload($log['new_values'] ?? null);
    $severity = strtolower((string)($payload['severity'] ?? ''));
    if (in_array($severity, ['high', 'medium', 'low'], true)) {
        return $severity;
    }

    $action = strtolower((string)($log['action'] ?? ''));
    if (
        strpos($action, 'access_denied') !== false ||
        strpos($action, 'failed') !== false ||
        strpos($action, 'delete') !== false ||
        strpos($action, 'deactivate') !== false ||
        strpos($action, 'reject') !== false ||
        strpos($action, 'clear_old_logs') !== false
    ) {
        return 'high';
    }
    if (
        strpos($action, 'update') !== false ||
        strpos($action, 'edit') !== false ||
        strpos($action, 'approve') !== false ||
        strpos($action, 'create') !== false ||
        strpos($action, 'status') !== false
    ) {
        return 'medium';
    }

    return 'low';
}

function getSeverityBadge(string $severity): string {
    $severity = strtolower(trim($severity));
    if ($severity === 'high') {
        return '<span class="badge bg-danger">High</span>';
    }
    if ($severity === 'medium') {
        return '<span class="badge bg-warning text-dark">Medium</span>';
    }
    return '<span class="badge bg-success">Low</span>';
}
?>

<div class="container-fluid">
    <?php if (!empty($_SESSION['message'])): $flash = $_SESSION['message']; unset($_SESSION['message']); ?>
        <div class="alert alert-<?php echo ($flash['type'] ?? 'info') === 'error' ? 'danger' : htmlspecialchars((string)($flash['type'] ?? 'info')); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars((string)($flash['text'] ?? '')); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="top-bar">
        <h1 class="page-title">
            <i class="bi bi-journal-text"></i>
            Audit Logs
            <?php if ($isSuperAdmin): ?>
                <span class="badge bg-danger ms-2">Super Admin</span>
            <?php endif; ?>
        </h1>
        <div class="btn-toolbar d-flex gap-2">
            <button class="btn btn-success" onclick="exportAuditLogs()">
                <i class="bi bi-download"></i> Export Logs
            </button>
            <button type="button" class="btn btn-outline-primary" id="bulkPrintBtn" onclick="printSelectedLogs()" disabled>
                <i class="bi bi-printer"></i> Print Selected (<span id="bulkPrintCount">0</span>)
            </button>
            <?php if ($isSuperAdmin): ?>
                <button class="btn btn-danger" onclick="clearOldLogs()">
                    <i class="bi bi-trash"></i> Clear Old Logs
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted">Total Logs</h5>
                            <h3 class="mb-0"><?php echo number_format($total_logs); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary-light">
                            <i class="bi bi-journal text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted">Users Tracked</h5>
                            <h3 class="mb-0"><?php echo count($users); ?></h3>
                        </div>
                        <div class="stat-icon bg-success-light">
                            <i class="bi bi-people text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted">Tables Tracked</h5>
                            <h3 class="mb-0"><?php echo count($tables); ?></h3>
                        </div>
                        <div class="stat-icon bg-warning-light">
                            <i class="bi bi-table text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-muted">Last 7 Days</h5>
                            <h3 class="mb-0"><?php echo count($daily_stats); ?></h3>
                        </div>
                        <div class="stat-icon bg-info-light">
                            <i class="bi bi-calendar-week text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="page" value="audit_log">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Search in logs..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Action</label>
                        <select class="form-select" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action_row): ?>
                                <option value="<?php echo htmlspecialchars($action_row['action']); ?>"
                                    <?php echo $action_filter === $action_row['action'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(humanizeAction($action_row['action'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Table</label>
                        <select class="form-select" name="table">
                            <option value="">All Tables</option>
                            <?php foreach ($tables as $table_row): ?>
                                <option value="<?php echo htmlspecialchars($table_row['table_name']); ?>"
                                    <?php echo $table_filter === $table_row['table_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($table_row['table_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">User</label>
                        <select class="form-select" name="user_id">
                            <option value="all">All Users</option>
                            <?php foreach ($users as $user_row): ?>
                                <option value="<?php echo htmlspecialchars($user_row['user_id']); ?>"
                                    <?php echo $user_filter === $user_row['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user_row['full_name'] ?? 'Unknown'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Apply Filters
                            </button>
                            <a href="?page=audit_log" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear All
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Audit Logs Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 42px;">
                                <input type="checkbox" class="form-check-input" id="selectAllLogs" onclick="toggleSelectAllLogs(this)" title="Select all visible logs">
                            </th>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Severity</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Summary / Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="bi bi-journal-x display-4 text-muted"></i>
                                    <p class="mt-3">No audit logs found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input bulk-log-checkbox"
                                            value="<?php echo (int)$log['id']; ?>"
                                            onchange="updateBulkPrintSelectionUI()"
                                            aria-label="Select audit log <?php echo (int)$log['id']; ?>"
                                        >
                                    </td>
                                    <td><?php echo $log['id']; ?></td>
                                    <td>
                                        <div class="small text-muted">
                                            <?php echo date('Y-m-d', strtotime($log['created_at'])); ?>
                                        </div>
                                        <div class="small">
                                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo getActionBadge($log['action']); ?></td>
                                    <td><?php echo getSeverityBadge(getLogSeverity($log)); ?></td>
                                    <td><?php echo getTableBadge($log['table_name']); ?></td>
                                    <td>
                                        <?php if ($log['record_id']): ?>
                                            <span class="badge bg-light text-dark">#<?php echo $log['record_id']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo getUserInfo($log['user_id'], $log['user_name'], $log['user_role']); ?>
                                    </td>
                                    <td>
                                        <code class="small"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                    <td style="min-width: 300px;">
                                        <?php $summary = getLogSummary($log); ?>
                                        <div class="small mb-2"><?php echo htmlspecialchars(truncateText($summary, 120)); ?></div>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="showLogDetails(<?php echo $log['id']; ?>)"
                                                data-bs-toggle="tooltip" title="View Full Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_logs > $per_page): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                        $total_pages = ceil($total_logs / $per_page);
                        $visible_pages = 5;
                        $start_page = max(1, $page - floor($visible_pages / 2));
                        $end_page = min($total_pages, $start_page + $visible_pages - 1);
                        
                        if ($end_page - $start_page + 1 < $visible_pages) {
                            $start_page = max(1, $end_page - $visible_pages + 1);
                        }
                        ?>
                        
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=audit_log&p=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&table=<?php echo urlencode($table_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&user_id=<?php echo urlencode($user_filter); ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=audit_log&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&table=<?php echo urlencode($table_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&user_id=<?php echo urlencode($user_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=audit_log&p=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action_filter); ?>&table=<?php echo urlencode($table_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&user_id=<?php echo urlencode($user_filter); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printLogDetails()">Print</button>
            </div>
        </div>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Clear Old Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Old Audit Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="clear_old_logs">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Delete logs older than:</label>
                        <select class="form-select" name="days_old">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. Old logs will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Old Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Initialize tooltips
if (window.bootstrap && window.bootstrap.Tooltip) {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
    });
}

// Show log details
function showLogDetails(logId) {
    // Show loading
    document.getElementById('logDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading log details...</p>
        </div>
    `;
    
    // Fetch log details via AJAX
    fetch('ajax/get_audit_log.php?id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('logDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.error}
                    </div>
                `;
                return;
            }
            
            const log = data.log;
            const parsedNewValues = parseJsonSafe(log.new_values);
            const meta = parsedNewValues && typeof parsedNewValues === 'object' && parsedNewValues._meta && typeof parsedNewValues._meta === 'object'
                ? parsedNewValues._meta
                : {};
            const summaryMessage = parsedNewValues && typeof parsedNewValues.message === 'string'
                ? parsedNewValues.message.trim()
                : '';
            const requestMethod = log.request_method || meta.request_method || 'N/A';
            const requestUrl = log.request_url || meta.request_uri || 'N/A';
            const userAgent = log.user_agent || meta.user_agent || 'N/A';
            const actorRole = log.user_role || meta.actor_role || 'N/A';
            const severity = (parsedNewValues && parsedNewValues.severity) ? String(parsedNewValues.severity) : 'N/A';
            const category = (parsedNewValues && parsedNewValues.category) ? String(parsedNewValues.category) : 'N/A';
            const methodBadgeClass = requestMethod === 'POST'
                ? 'bg-warning'
                : (requestMethod === 'GET' ? 'bg-info' : 'bg-secondary');
            const severityBadgeClass = severity.toLowerCase() === 'high'
                ? 'bg-danger'
                : (severity.toLowerCase() === 'medium' ? 'bg-warning text-dark' : (severity.toLowerCase() === 'low' ? 'bg-success' : 'bg-secondary'));
            const recordName = (typeof log.record_name === 'string' && log.record_name.trim() !== '')
                ? log.record_name.trim()
                : null;
            
            // Format the details
            const html = `
                ${summaryMessage ? `
                <div class="alert alert-primary py-2">
                    <strong>Summary:</strong> ${escapeHtml(summaryMessage)}
                </div>
                ` : ''}
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 150px;">Log ID:</th>
                                <td><strong>${log.id}</strong></td>
                            </tr>
                            <tr>
                                <th>Action:</th>
                                <td>${getActionBadgeHtml(log.action)}</td>
                            </tr>
                            <tr>
                                <th>Table:</th>
                                <td>${getTableBadgeHtml(log.table_name)}</td>
                            </tr>
                            <tr>
                                <th>Record ID:</th>
                                <td>${log.record_id ? '#' + log.record_id : '<span class="text-muted">N/A</span>'}</td>
                            </tr>
                            <tr>
                                <th>Record Name:</th>
                                <td>${recordName ? escapeHtml(recordName) : '<span class="text-muted">N/A</span>'}</td>
                            </tr>
                            <tr>
                                <th>User:</th>
                                <td>${getUserInfoHtml(log.user_id, log.user_name, log.user_role)}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th style="width: 150px;">IP Address:</th>
                                <td><code>${log.ip_address || 'N/A'}</code></td>
                            </tr>
                            <tr>
                                <th>Timestamp:</th>
                                <td>
                                    ${new Date(log.created_at).toLocaleDateString()} 
                                    ${new Date(log.created_at).toLocaleTimeString()}
                                </td>
                            </tr>
                            <tr>
                                <th>User Agent:</th>
                                <td><small class="text-muted">${escapeHtml(userAgent)}</small></td>
                            </tr>
                            <tr>
                                <th>Severity:</th>
                                <td><span class="badge ${severityBadgeClass}">${escapeHtml(humanizeActionText(severity))}</span></td>
                            </tr>
                            <tr>
                                <th>Category:</th>
                                <td>${escapeHtml(humanizeActionText(category))}</td>
                            </tr>
                            <tr>
                                <th>Actor Role:</th>
                                <td>${escapeHtml(actorRole)}</td>
                            </tr>
                            <tr>
                                <th>Request Method:</th>
                                <td><span class="badge ${methodBadgeClass}">
                                    ${escapeHtml(requestMethod)}
                                </span></td>
                            </tr>
                            <tr>
                                <th>Request URL:</th>
                                <td><small>${escapeHtml(requestUrl)}</small></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Change Breakdown (Before -> After):</h6>
                    <div class="card">
                        <div class="card-body p-0">
                            ${buildChangeBreakdownHtml(log.old_values, log.new_values)}
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>Old Values:</h6>
                    <div class="card">
                        <div class="card-body p-2">
                            ${formatJsonDataHtml(log.old_values)}
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>New Values:</h6>
                    <div class="card">
                        <div class="card-body p-2">
                            ${formatJsonDataHtml(log.new_values)}
                        </div>
                    </div>
                </div>
                
                ${log.details ? `
                <div class="mt-3">
                    <h6>Additional Details:</h6>
                    <div class="card">
                        <div class="card-body">
                            <pre class="mb-0" style="font-size: 0.8rem;">${escapeHtml(log.details)}</pre>
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('logDetailsContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('logDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error loading log details: ${error.message}
                </div>
            `;
        });
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    modal.show();
}

// Helper functions
function getActionBadgeHtml(action) {
    const actionLower = action.toLowerCase();
    const displayAction = humanizeActionText(action);
    
    if (actionLower.includes('create') || actionLower.includes('add')) {
        return '<span class="badge bg-success">' + escapeHtml(displayAction) + '</span>';
    } else if (actionLower.includes('update') || actionLower.includes('edit')) {
        return '<span class="badge bg-warning">' + escapeHtml(displayAction) + '</span>';
    } else if (actionLower.includes('delete') || actionLower.includes('remove')) {
        return '<span class="badge bg-danger">' + escapeHtml(displayAction) + '</span>';
    } else if (actionLower.includes('access')) {
        return '<span class="badge bg-info">' + escapeHtml(displayAction) + '</span>';
    } else if (actionLower.includes('login')) {
        return '<span class="badge bg-primary">' + escapeHtml(displayAction) + '</span>';
    } else {
        return '<span class="badge bg-secondary">' + escapeHtml(displayAction) + '</span>';
    }
}

function getTableBadgeHtml(table) {
    const tablesColors = {
        'users': 'primary',
        'orders': 'success',
        'remedies': 'info',
        'products': 'info',
        'suppliers': 'warning',
        'categories': 'secondary',
        'audit_log': 'dark'
    };
    
    const color = tablesColors[table] || 'light';
    return '<span class="badge bg-' + color + '">' + escapeHtml(table) + '</span>';
}

function getUserInfoHtml(userId, userName, userRole) {
    if (!userId) {
        return '<span class="text-muted">System</span>';
    }
    
    let roleBadge = '';
    if (userRole === 'super_admin') {
        roleBadge = '<span class="badge bg-danger ms-1">Super Admin</span>';
    } else if (userRole === 'admin') {
        roleBadge = '<span class="badge bg-danger ms-1">Admin</span>';
    } else if (userRole === 'staff') {
        roleBadge = '<span class="badge bg-primary ms-1">Staff</span>';
    }
    
    return escapeHtml(userName) + roleBadge;
}

function normalizeAuditValueForDiff(raw) {
    if (raw === null || raw === undefined) {
        return null;
    }

    if (typeof raw === 'object') {
        return raw;
    }

    const text = String(raw).trim();
    if (text === '' || text.toLowerCase() === 'null') {
        return null;
    }

    const parsed = parseJsonSafe(text);
    if (parsed !== null) {
        return parsed;
    }

    return text;
}

function flattenAuditValueForDiff(value, prefix = '', output = {}) {
    const key = prefix || 'value';

    if (value === null || value === undefined) {
        output[key] = null;
        return output;
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            output[key] = [];
            return output;
        }
        value.forEach((item, index) => {
            const nextKey = prefix ? `${prefix}[${index}]` : `[${index}]`;
            flattenAuditValueForDiff(item, nextKey, output);
        });
        return output;
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value).filter(([entryKey]) => entryKey !== '_meta');
        if (entries.length === 0) {
            output[key] = {};
            return output;
        }
        entries.forEach(([entryKey, entryValue]) => {
            const nextKey = prefix ? `${prefix}.${entryKey}` : entryKey;
            flattenAuditValueForDiff(entryValue, nextKey, output);
        });
        return output;
    }

    output[key] = value;
    return output;
}

function filterDiffNoise(diffMap) {
    const reservedKeys = new Set(['message', 'action', 'table', 'record_id', 'severity', 'category']);
    const keys = Object.keys(diffMap);
    const hasBusinessKeys = keys.some(key => !reservedKeys.has(key));

    if (!hasBusinessKeys) {
        return diffMap;
    }

    const filtered = {};
    keys.forEach(key => {
        if (!reservedKeys.has(key)) {
            filtered[key] = diffMap[key];
        }
    });
    return filtered;
}

function stringifyComparableDiffValue(value) {
    if (value === null || value === undefined) {
        return 'null';
    }
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch (error) {
            return String(value);
        }
    }
    return String(value);
}

function formatDiffValueHtml(value, exists) {
    if (!exists) {
        return '<span class="text-muted small">Not captured</span>';
    }
    if (value === null) {
        return '<span class="text-muted small">NULL</span>';
    }
    if (typeof value === 'string') {
        const normalized = value.trim() === '' ? '(empty)' : value;
        return `<span class="small">${escapeHtml(normalized)}</span>`;
    }
    if (typeof value === 'boolean') {
        return `<span class="small">${value ? 'true' : 'false'}</span>`;
    }
    if (typeof value === 'number') {
        return `<span class="small">${value}</span>`;
    }
    return `<code class="small">${escapeHtml(JSON.stringify(value))}</code>`;
}

function buildChangeBreakdownHtml(oldRaw, newRaw) {
    const oldNormalized = normalizeAuditValueForDiff(oldRaw);
    const newNormalized = normalizeAuditValueForDiff(newRaw);

    const oldMap = oldNormalized === null
        ? {}
        : filterDiffNoise(flattenAuditValueForDiff(oldNormalized, '', {}));
    const newMap = newNormalized === null
        ? {}
        : filterDiffNoise(flattenAuditValueForDiff(newNormalized, '', {}));

    const keys = Array.from(new Set([...Object.keys(oldMap), ...Object.keys(newMap)])).sort();
    if (keys.length === 0) {
        return '<div class="p-3 text-muted small">No before/after values were captured for this event.</div>';
    }

    const rows = keys.map((key) => {
        const oldExists = Object.prototype.hasOwnProperty.call(oldMap, key);
        const newExists = Object.prototype.hasOwnProperty.call(newMap, key);
        const oldValue = oldExists ? oldMap[key] : undefined;
        const newValue = newExists ? newMap[key] : undefined;
        const changed = !(oldExists && newExists && stringifyComparableDiffValue(oldValue) === stringifyComparableDiffValue(newValue));
        const changeBadge = changed
            ? '<span class="badge bg-warning text-dark">Changed</span>'
            : '<span class="badge bg-secondary">Same</span>';

        return `
            <tr class="${changed ? 'table-warning' : ''}">
                <td><code class="small">${escapeHtml(key)}</code></td>
                <td>${formatDiffValueHtml(oldValue, oldExists)}</td>
                <td>${formatDiffValueHtml(newValue, newExists)}</td>
                <td>${changeBadge}</td>
            </tr>
        `;
    }).join('');

    return `
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 22%;">Field</th>
                        <th style="width: 33%;">Before</th>
                        <th style="width: 33%;">After</th>
                        <th style="width: 12%;">State</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        </div>
    `;
}

function formatJsonDataHtml(json) {
    if (!json || json === 'null') {
        return '<span class="text-muted">No data</span>';
    }
    
    try {
        const data = JSON.parse(json);
        return '<pre class="mb-0" style="font-size: 0.8rem; max-height: 200px; overflow-y: auto;">' 
               + escapeHtml(JSON.stringify(data, null, 2)) 
               + '</pre>';
    } catch (e) {
        return '<code>' + escapeHtml(json.substring(0, 100)) + '...</code>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export audit logs
function exportAuditLogs() {
    const search = '<?php echo urlencode($search); ?>';
    const action = '<?php echo urlencode($action_filter); ?>';
    const table = '<?php echo urlencode($table_filter); ?>';
    const dateFrom = '<?php echo urlencode($date_from); ?>';
    const dateTo = '<?php echo urlencode($date_to); ?>';
    const userId = '<?php echo urlencode($user_filter); ?>';
    
    const url = `ajax/export_audit_logs.php?search=${search}&action=${action}&table=${table}&date_from=${dateFrom}&date_to=${dateTo}&user_id=${userId}`;
    
    // Create download link
    const downloadLink = document.createElement('a');
    downloadLink.href = url;
    downloadLink.download = `audit_logs_${new Date().toISOString().slice(0, 10)}.csv`;
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Clear old logs
function clearOldLogs() {
    const modalEl = document.getElementById('clearLogsModal');
    if (!modalEl) {
        alert('Only super admin can clear logs.');
        return;
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function getSelectedAuditLogIds() {
    return Array.from(document.querySelectorAll('.bulk-log-checkbox:checked'))
        .map((checkbox) => parseInt(checkbox.value, 10))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function updateBulkPrintSelectionUI() {
    const selectedCount = getSelectedAuditLogIds().length;
    const countEl = document.getElementById('bulkPrintCount');
    const bulkBtn = document.getElementById('bulkPrintBtn');
    const selectAllEl = document.getElementById('selectAllLogs');
    const checkboxes = Array.from(document.querySelectorAll('.bulk-log-checkbox'));

    if (countEl) {
        countEl.textContent = String(selectedCount);
    }
    if (bulkBtn) {
        bulkBtn.disabled = selectedCount === 0;
    }
    if (selectAllEl) {
        const total = checkboxes.length;
        const allSelected = total > 0 && selectedCount === total;
        selectAllEl.checked = allSelected;
        selectAllEl.indeterminate = selectedCount > 0 && selectedCount < total;
    }
}

function toggleSelectAllLogs(masterCheckbox) {
    const shouldCheck = !!(masterCheckbox && masterCheckbox.checked);
    const checkboxes = document.querySelectorAll('.bulk-log-checkbox');
    checkboxes.forEach((checkbox) => {
        checkbox.checked = shouldCheck;
    });
    updateBulkPrintSelectionUI();
}

function formatJsonDataForPrint(json) {
    if (!json || json === 'null') {
        return '<span class="text-muted">No data captured</span>';
    }
    try {
        const data = JSON.parse(json);
        return `<pre>${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
    } catch (e) {
        return `<pre>${escapeHtml(String(json))}</pre>`;
    }
}

function buildBulkLogSectionHtml(log, index, total) {
    const hasError = !!log._bulk_error;
    const sectionTitle = hasError ? `Entry ${index} (Unavailable)` : `Entry ${index} - Log #${log.id}`;
    const pageBreakStyle = index < total ? 'break-after: page; page-break-after: always;' : '';

    if (hasError) {
        return `
            <section class="bulk-log-section" style="${pageBreakStyle}">
                <h3 class="bulk-log-title">${escapeHtml(sectionTitle)}</h3>
                <div class="alert">
                    Could not load this entry: ${escapeHtml(log._bulk_error)}
                </div>
            </section>
        `;
    }

    const createdAt = log.created_at
        ? `${new Date(log.created_at).toLocaleDateString()} ${new Date(log.created_at).toLocaleTimeString()}`
        : 'N/A';
    const userRole = log.user_role ? ` (${humanizeActionText(log.user_role)})` : '';
    const userLabel = log.user_name ? `${log.user_name}${userRole}` : 'System';
    const recordId = log.record_id ? `#${log.record_id}` : 'N/A';
    const recordName = (typeof log.record_name === 'string' && log.record_name.trim() !== '')
        ? log.record_name.trim()
        : 'N/A';
    const summaryPayload = parseJsonSafe(log.new_values);
    const summaryMessage = summaryPayload && typeof summaryPayload.message === 'string'
        ? summaryPayload.message.trim()
        : '';

    return `
        <section class="bulk-log-section" style="${pageBreakStyle}">
            <h3 class="bulk-log-title">${escapeHtml(sectionTitle)}</h3>
            ${summaryMessage ? `<p class="bulk-log-summary"><strong>Summary:</strong> ${escapeHtml(summaryMessage)}</p>` : ''}
            <table class="table table-sm">
                <tr>
                    <th style="width: 22%;">Action</th>
                    <td>${getActionBadgeHtml(log.action || '')}</td>
                    <th style="width: 22%;">Timestamp</th>
                    <td>${escapeHtml(createdAt)}</td>
                </tr>
                <tr>
                    <th>Table</th>
                    <td>${getTableBadgeHtml(log.table_name || '')}</td>
                    <th>Record ID</th>
                    <td>${escapeHtml(recordId)}</td>
                </tr>
                <tr>
                    <th>Record Name</th>
                    <td>${escapeHtml(recordName)}</td>
                    <th>User</th>
                    <td>${getUserInfoHtml(log.user_id, log.user_name, log.user_role)}</td>
                </tr>
                <tr>
                    <th>IP Address</th>
                    <td><code>${escapeHtml(log.ip_address || 'N/A')}</code></td>
                    <th>Log ID</th>
                    <td>${escapeHtml(String(log.id ?? 'N/A'))}</td>
                </tr>
            </table>

            <h4 class="bulk-subheading">Change Breakdown (Before -> After)</h4>
            <div class="card">
                <div class="card-body p-0">
                    ${buildChangeBreakdownHtml(log.old_values, log.new_values)}
                </div>
            </div>

            <div class="bulk-values-grid">
                <div>
                    <h4 class="bulk-subheading">Old Values</h4>
                    <div class="card">
                        <div class="card-body p-2">
                            ${formatJsonDataForPrint(log.old_values)}
                        </div>
                    </div>
                </div>
                <div>
                    <h4 class="bulk-subheading">New Values</h4>
                    <div class="card">
                        <div class="card-body p-2">
                            ${formatJsonDataForPrint(log.new_values)}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    `;
}

async function printSelectedLogs() {
    const selectedIds = getSelectedAuditLogIds();
    if (selectedIds.length === 0) {
        alert('Select at least one audit log to print.');
        return;
    }

    const bulkBtn = document.getElementById('bulkPrintBtn');
    const originalBtnHtml = bulkBtn ? bulkBtn.innerHTML : '';
    if (bulkBtn) {
        bulkBtn.disabled = true;
        bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Preparing...';
    }

    try {
        const results = await Promise.all(
            selectedIds.map(async (logId) => {
                try {
                    const response = await fetch('ajax/get_audit_log.php?id=' + encodeURIComponent(logId));
                    const data = await response.json();
                    if (!response.ok || data.error || !data.log) {
                        return { _bulk_error: data.error || `Failed to load log #${logId}` };
                    }
                    return data.log;
                } catch (error) {
                    return { _bulk_error: error.message || `Failed to load log #${logId}` };
                }
            })
        );

        const sections = results
            .map((log, idx) => buildBulkLogSectionHtml(log, idx + 1, results.length))
            .join('');
        const failedCount = results.filter((item) => item._bulk_error).length;
        const okCount = results.length - failedCount;
        const description = failedCount > 0
            ? `Selected ${results.length} entries. Loaded ${okCount}; ${failedCount} could not be fully retrieved.`
            : `Selected ${results.length} audit entries from the current page for consolidated printing.`;

        printLogDetails(sections, {
            documentTitle: 'Bulk Audit Log Report',
            reportHeading: 'Bulk Audit Log Report',
            reportDescription: description
        });
    } finally {
        if (bulkBtn) {
            bulkBtn.innerHTML = originalBtnHtml;
        }
        updateBulkPrintSelectionUI();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateBulkPrintSelectionUI);
} else {
    updateBulkPrintSelectionUI();
}

function parseJsonSafe(value) {
    if (!value || value === 'null') {
        return null;
    }
    try {
        return JSON.parse(value);
    } catch (e) {
        return null;
    }
}

function humanizeActionText(action) {
    if (!action) return '';
    return action
        .toString()
        .replace(/[_:]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, c => c.toUpperCase());
}

// Print log details (single or bulk)
function printLogDetails(contentOverride = null, options = {}) {
    const defaultContent = document.getElementById('logDetailsContent');
    const content = contentOverride !== null
        ? contentOverride
        : (defaultContent ? defaultContent.innerHTML : '<div class="alert">No log content available.</div>');
    const printWindow = window.open('', '_blank', 'width=1100,height=800');
    if (!printWindow) {
        alert('Please allow pop-ups to print audit log details.');
        return;
    }

    const documentTitle = options.documentTitle || 'Audit Log Details';
    const reportHeading = options.reportHeading || 'Audit Log Details';
    const reportDescription = options.reportDescription || 'Generated from admin audit logs for review, traceability, and compliance reference.';
    const printedAt = new Date().toLocaleString();
    const shopName = 'JAKISAWA SHOP';
    const shopAddress = 'Nairobi Information HSE, Room 405, Fourth Floor';
    const shopPhone = '0792546080 / +254 720 793609';
    const shopEmail = 'support@jakisawashop.co.ke';
    const shopWebsite = 'https://www.jakisawashop.co.ke/';
    const adminBase = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;
    const adminLink = window.location.origin + adminBase + '/admin_dashboard.php?page=audit_log';

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>${documentTitle} - ${shopName}</title>
            <style>
                :root {
                    --brand: #0f5132;
                    --brand-soft: #e8f4ee;
                    --ink: #1f2937;
                    --muted: #5b6573;
                    --line: #cfd8e3;
                    --panel: #f7fafc;
                }

                @page {
                    margin: 150px 26px 108px 26px;
                }

                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                html,
                body {
                    margin: 0;
                    padding: 0;
                    font-family: "Trebuchet MS", "Segoe UI", Arial, sans-serif;
                    color: var(--ink);
                    font-size: 12.5px;
                    line-height: 1.45;
                    background: #ffffff;
                }

                .watermark {
                    position: fixed;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 72px;
                    font-weight: 700;
                    letter-spacing: 6px;
                    color: rgba(15, 81, 50, 0.04);
                    transform: rotate(-24deg);
                    pointer-events: none;
                    z-index: 0;
                }

                .print-header,
                .print-footer {
                    position: fixed;
                    left: 0;
                    right: 0;
                    background: #ffffff;
                    z-index: 20;
                }

                .print-header {
                    top: 0;
                    padding: 12px 26px 10px;
                    border-bottom: 1.6px solid var(--brand);
                }

                .header-topbar {
                    height: 6px;
                    border-radius: 3px;
                    margin-bottom: 10px;
                    background: linear-gradient(90deg, #0f5132, #198754, #7dbf9a);
                }

                .header-grid {
                    display: grid;
                    grid-template-columns: 1.25fr 1fr;
                    gap: 14px;
                    align-items: start;
                }

                .brand-name {
                    margin: 0;
                    color: #0b3d27;
                    font-size: 20px;
                    font-weight: 800;
                    letter-spacing: 0.35px;
                }

                .brand-subtitle {
                    margin-top: 2px;
                    font-size: 11.5px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #4b5563;
                    font-weight: 600;
                }

                .header-contact,
                .header-report-meta {
                    margin-top: 7px;
                    font-size: 11.5px;
                    color: #374151;
                }

                .header-report-meta {
                    background: var(--panel);
                    border: 1px solid var(--line);
                    border-radius: 8px;
                    padding: 8px 10px;
                }

                .header-report-meta strong {
                    color: #111827;
                }

                .print-footer {
                    bottom: 0;
                    border-top: 1px solid var(--line);
                    padding: 8px 26px 10px;
                    font-size: 10.5px;
                    color: var(--muted);
                }

                .footer-grid {
                    display: grid;
                    grid-template-columns: 1fr auto auto;
                    gap: 14px;
                    align-items: center;
                }

                .footer-links a {
                    color: #0b5ed7;
                    text-decoration: none;
                    margin-right: 8px;
                }

                .footer-links a:last-child {
                    margin-right: 0;
                }

                .footer-page::after {
                    content: "Page " counter(page);
                    color: #334155;
                    font-weight: 600;
                }

                .print-main {
                    width: 100%;
                    position: relative;
                    z-index: 5;
                }

                .report-chip {
                    display: inline-block;
                    margin-bottom: 8px;
                    padding: 2px 8px;
                    border-radius: 999px;
                    border: 1px solid #b5ddc9;
                    background: var(--brand-soft);
                    color: var(--brand);
                    font-size: 10.5px;
                    font-weight: 700;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                }

                .print-heading {
                    margin: 0 0 6px;
                    font-size: 16px;
                    font-weight: 700;
                    color: #0f172a;
                }

                .print-meta {
                    margin: 0 0 14px;
                    font-size: 11px;
                    color: #6b7280;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }

                th,
                td {
                    border: 1px solid #d8e0e8;
                    padding: 7px 8px;
                    text-align: left;
                    vertical-align: top;
                }

                th {
                    background-color: #eef4f8;
                    font-size: 11px;
                    letter-spacing: 0.35px;
                    text-transform: uppercase;
                    color: #374151;
                    font-weight: 700;
                }

                tr:nth-child(even) td {
                    background: #fcfdff;
                }

                .badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 999px;
                    font-size: 10px;
                    font-weight: 700;
                    line-height: 1.4;
                    border: 1px solid transparent;
                    background: #f3f4f6;
                    color: #111827;
                }

                .badge.bg-success { background: #e9f9ee; border-color: #9ed9b2; color: #196c3d; }
                .badge.bg-danger { background: #fdecec; border-color: #f0b7b7; color: #8a1c1c; }
                .badge.bg-warning { background: #fff7e8; border-color: #f4d7a2; color: #8a5b14; }
                .badge.bg-info { background: #e8f5ff; border-color: #b3d8f7; color: #1f5d8c; }
                .badge.bg-primary { background: #ebf0ff; border-color: #b7c6f8; color: #1e3a8a; }
                .badge.bg-secondary { background: #eef1f5; border-color: #c9d1db; color: #475569; }
                .badge.bg-light { background: #f8fafc; border-color: #d9e1ea; color: #334155; }

                .card {
                    border: 1px solid #dbe3ea;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    background: #ffffff;
                    overflow: hidden;
                }

                .card-body {
                    padding: 8px 10px;
                }

                pre {
                    margin: 0;
                    white-space: pre-wrap;
                    word-break: break-word;
                    font-size: 10.5px;
                    line-height: 1.4;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 8px;
                }

                code {
                    font-family: Consolas, "Courier New", monospace;
                    font-size: 10.5px;
                }

                .alert {
                    border: 1px solid #c3d6f4;
                    background: #eef5ff;
                    color: #1e3a8a;
                    border-radius: 8px;
                    padding: 8px 10px;
                    margin: 0 0 10px;
                }

                .text-muted {
                    color: #6b7280 !important;
                }

                .bulk-log-section {
                    margin-bottom: 16px;
                }

                .bulk-log-title {
                    margin: 0 0 8px;
                    font-size: 14px;
                    color: #0b3d27;
                    border-left: 4px solid #0f5132;
                    padding-left: 8px;
                }

                .bulk-subheading {
                    margin: 10px 0 6px;
                    font-size: 11.5px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.35px;
                    color: #334155;
                }

                .bulk-log-summary {
                    margin: 0 0 8px;
                    font-size: 11.5px;
                    color: #475569;
                }

                .bulk-values-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                }

                .table-warning td {
                    background: #fff9e8 !important;
                }
            </style>
            <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
        <body>
            <div class="watermark">${shopName}</div>
            <header class="print-header">
                <div class="header-topbar"></div>
                <div class="header-grid">
                    <div>
                        <h1 class="brand-name">${shopName}</h1>
                        <div class="brand-subtitle">Audit and Compliance Report</div>
                        <div class="header-contact">
                            <strong>Phone:</strong> ${shopPhone}<br>
                            <strong>Email:</strong> ${shopEmail}<br>
                            <strong>Address:</strong> ${shopAddress}
                        </div>
                    </div>
                    <div class="header-report-meta">
                        <div><strong>Document:</strong> ${documentTitle}</div>
                        <div><strong>Printed:</strong> ${printedAt}</div>
                        <div><strong>Website:</strong> ${shopWebsite}</div>
                    </div>
                </div>
            </header>

            <main class="print-main">
                <span class="report-chip">Internal Use</span>
                <h2 class="print-heading">${reportHeading}</h2>
                <p class="print-meta">${reportDescription}</p>
                ${content}
            </main>

            <footer class="print-footer">
                <div class="footer-grid">
                    <div>
                        <strong>${shopName}</strong> | Confidential audit information for authorized personnel.
                    </div>
                    <div class="footer-links">
                        <a href="${shopWebsite}">${shopWebsite}</a>
                        <a href="mailto:${shopEmail}">${shopEmail}</a>
                        <a href="${adminLink}">Admin Audit Logs</a>
                    </div>
                    <div class="footer-page"></div>
                </div>
            </footer>
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();
    printWindow.onload = function () {
        printWindow.print();
        printWindow.onafterprint = function () {
            printWindow.close();
        };
    };
}
</script>

<style>
.stat-card {
    border-radius: 10px;
    border: none;
    transition: transform 0.3s;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.bg-primary-light {
    background-color: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.bg-success-light {
    background-color: rgba(39, 174, 96, 0.1);
    color: #27ae60;
}

.bg-warning-light {
    background-color: rgba(243, 156, 18, 0.1);
    color: #f39c12;
}

.bg-info-light {
    background-color: rgba(41, 128, 185, 0.1);
    color: #2980b9;
}

.bg-danger-light {
    background-color: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}
</style>
