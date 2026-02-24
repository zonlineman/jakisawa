<?php

/*
 *  - Database and business logic functions
 * This file contains all the shared functions for the admin system
 */
require_once 'database.php';
require_once 'audit_helper.php';
require_once 'order_notifications.php';




function getAllCategories($conn) {
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllSuppliers($conn) {
    $stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/// Helper functions for remedies

function validateRemedy(array $remedy): array {
    $errors = [];
    
    if (empty($remedy['sku'])) $errors[] = 'SKU is required';
    if (empty($remedy['name'])) $errors[] = 'Name is required';
    if ($remedy['category_id'] <= 0) $errors[] = 'Category is required';
    if ($remedy['unit_price'] <= 0) $errors[] = 'Valid unit price is required';
    if ($remedy['stock_quantity'] < 0) $errors[] = 'Stock cannot be negative';
    
    return $errors;
}

function isUniqueSKU(mysqli $conn, string $sku, int $exclude_id = 0): bool {
    $query = "SELECT id FROM remedies WHERE sku = ?";
    if ($exclude_id > 0) {
        $query .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($query);
    if ($exclude_id > 0) {
        $stmt->bind_param("si", $sku, $exclude_id);
    } else {
        $stmt->bind_param("s", $sku);
    }
    
    $stmt->execute();
    $stmt->store_result();
    $is_unique = $stmt->num_rows === 0;
    $stmt->close();
    
    return $is_unique;
}

function insertRemedy(mysqli $conn, array $remedy): bool {
    $slug = generateSlug($remedy['name']);
    
    $query = "
        INSERT INTO remedies (
            sku, name, slug, description, category_id, supplier_id,
            ingredients, usage_instructions, unit_price, cost_price,
            discount_price, stock_quantity, reorder_level, is_featured,
            is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssssisssddddii",
        $remedy['sku'], $remedy['name'], $slug, $remedy['description'],
        $remedy['category_id'], $remedy['supplier_id'], $remedy['ingredients'],
        $remedy['usage_instructions'], $remedy['unit_price'], $remedy['cost_price'],
        $remedy['discount_price'], $remedy['stock_quantity'], $remedy['reorder_level'],
        $remedy['is_featured']
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

function updateRemedy(mysqli $conn, int $id, array $remedy): bool {
    $query = "
        UPDATE remedies SET 
            name = ?, description = ?, category_id = ?, supplier_id = ?,
            ingredients = ?, usage_instructions = ?, unit_price = ?,
            cost_price = ?, discount_price = ?, reorder_level = ?,
            is_featured = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssiissddddiiii",
        $remedy['name'], $remedy['description'], $remedy['category_id'],
        $remedy['supplier_id'], $remedy['ingredients'], $remedy['usage_instructions'],
        $remedy['unit_price'], $remedy['cost_price'], $remedy['discount_price'],
        $remedy['reorder_level'], $remedy['is_featured'], $remedy['is_active'],
        $id
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

function getRemedyById(mysqli $conn, int $id): ?array {
    $query = "SELECT * FROM remedies WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $remedy = $result->fetch_assoc();
    $stmt->close();
    
    return $remedy ?: null;
}

function calculateRemediesStatistics(mysqli $conn, array $remedies): array {
    $stats = [
        'total_products' => count($remedies),
        'total_stock_value' => 0,
        'avg_price' => 0,
        'low_stock_count' => 0,
        'featured_count' => 0
    ];
    
    if (empty($remedies)) return $stats;
    
    $totalPrice = 0;
    
    foreach ($remedies as $remedy) {
        $stats['total_stock_value'] += $remedy['stock_quantity'] * $remedy['unit_price'];
        $totalPrice += $remedy['unit_price'];
        
        if ($remedy['stock_quantity'] <= $remedy['reorder_level']) {
            $stats['low_stock_count']++;
        }
        
        if ($remedy['is_featured']) {
            $stats['featured_count']++;
        }
    }
    
    if ($stats['total_products'] > 0) {
        $stats['avg_price'] = $totalPrice / $stats['total_products'];
    }
    
    return $stats;
}

function displayMessages(): void {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($_SESSION['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['errors']) && is_array($_SESSION['errors'])) {
        foreach ($_SESSION['errors'] as $error) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($error) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
        unset($_SESSION['errors']);
    }
}

if (!function_exists('normalizeSystemUserRole')) {
    function normalizeSystemUserRole($role): string {
        $normalized = strtolower(trim((string)$role));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized);
        if ($normalized === 'superadmin') {
            $normalized = 'super_admin';
        }

        return in_array($normalized, ['customer', 'admin', 'staff', 'super_admin'], true)
            ? $normalized
            : '';
    }
}

if (!function_exists('getUsersTableColumnMap')) {
    function getUsersTableColumnMap(PDO $pdo): array {
        static $cache = [];
        $cacheKey = spl_object_id($pdo);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $map = [];
        $rows = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') {
                $map[strtolower($field)] = $field;
            }
        }

        $cache[$cacheKey] = $map;
        return $map;
    }
}

if (!function_exists('ensureCustomerOrderUser')) {
    /**
     * Resolve customer account by email, creating one when missing.
     * Existing valid roles are preserved. Missing/invalid roles are normalized to "customer".
     */
    function ensureCustomerOrderUser(
        PDO $pdo,
        string $email,
        string $fullName = '',
        string $phone = '',
        ?int $createdBy = null,
        string $registrationSource = 'order'
    ): int {
        $email = strtolower(trim($email));
        $fullName = trim($fullName);
        $phone = trim($phone);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer email is required.');
        }

        if ($fullName === '') {
            $local = (string)strstr($email, '@', true);
            $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $local));
            if ($fullName === '') {
                $fullName = 'Customer';
            }
        }

        $lookupStmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
        $lookupStmt->execute([$email]);
        $existing = $lookupStmt->fetch(PDO::FETCH_ASSOC);

        $columns = getUsersTableColumnMap($pdo);

        if ($existing) {
            $userId = (int)($existing['id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user record found for customer email.');
            }

            $role = normalizeSystemUserRole($existing['role'] ?? '');
            if ($role === '') {
                $setParts = [];
                $params = [];

                if (isset($columns['role'])) {
                    $setParts[] = "`{$columns['role']}` = ?";
                    $params[] = 'customer';
                }
                if (isset($columns['status'])) {
                    $setParts[] = "`{$columns['status']}` = ?";
                    $params[] = 'active';
                }
                if (isset($columns['approval_status'])) {
                    $setParts[] = "`{$columns['approval_status']}` = ?";
                    $params[] = 'approved';
                }
                if (isset($columns['is_active'])) {
                    $setParts[] = "`{$columns['is_active']}` = 1";
                }
                if (isset($columns['email_verified'])) {
                    $setParts[] = "`{$columns['email_verified']}` = 1";
                }
                if (isset($columns['updated_at'])) {
                    $setParts[] = "`{$columns['updated_at']}` = NOW()";
                }

                if (!empty($setParts)) {
                    $params[] = $userId;
                    $updateSql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute($params);
                }
            }

            return $userId;
        }

        $insertCols = [];
        $insertValues = [];
        $insertMarks = [];

        $add = static function (
            array $columns,
            array &$insertCols,
            array &$insertValues,
            array &$insertMarks,
            string $key,
            $value
        ): void {
            if (!isset($columns[$key])) {
                return;
            }
            $insertCols[] = "`{$columns[$key]}`";
            $insertValues[] = $value;
            $insertMarks[] = '?';
        };

        $passwordSeed = null;
        try {
            $passwordSeed = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $passwordSeed = sha1(uniqid('cust_', true));
        }

        $username = null;
        if (isset($columns['username'])) {
            $base = strtolower((string)strstr($email, '@', true));
            $base = preg_replace('/[^a-z0-9_]+/', '_', $base);
            $base = trim($base, '_');
            if ($base === '') {
                $base = 'customer';
            }
            $base = substr($base, 0, 24);
            $candidate = $base;
            $suffix = 0;
            $checkUsernameStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            while (true) {
                $checkUsernameStmt->execute([$candidate]);
                if (!$checkUsernameStmt->fetch(PDO::FETCH_ASSOC)) {
                    $username = $candidate;
                    break;
                }
                $suffix++;
                $candidate = substr($base . '_' . $suffix, 0, 32);
            }
        }

        $add($columns, $insertCols, $insertValues, $insertMarks, 'username', $username);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'email', $email);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'password_hash', password_hash($passwordSeed, PASSWORD_DEFAULT));
        $add($columns, $insertCols, $insertValues, $insertMarks, 'full_name', $fullName);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'phone', $phone !== '' ? $phone : null);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'role', 'customer');
        $add($columns, $insertCols, $insertValues, $insertMarks, 'status', 'active');
        $add($columns, $insertCols, $insertValues, $insertMarks, 'approval_status', 'approved');
        $add($columns, $insertCols, $insertValues, $insertMarks, 'is_active', 1);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'email_verified', 1);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'verification_token', null);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'created_by', max(0, (int)$createdBy));
        $add($columns, $insertCols, $insertValues, $insertMarks, 'approved_by', max(0, (int)$createdBy));
        $add($columns, $insertCols, $insertValues, $insertMarks, 'registration_source', $registrationSource);
        $add($columns, $insertCols, $insertValues, $insertMarks, 'registration_ip', $_SERVER['REMOTE_ADDR'] ?? null);

        if (empty($insertCols)) {
            throw new RuntimeException('Could not map users table columns for customer registration.');
        }

        $insertSql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertMarks) . ")";

        try {
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($insertValues);
        } catch (PDOException $e) {
            // Handle race condition: account created between lookup and insert.
            if ((string)$e->getCode() === '23000') {
                $retryStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $retryStmt->execute([$email]);
                $retry = $retryStmt->fetch(PDO::FETCH_ASSOC);
                if ($retry && (int)($retry['id'] ?? 0) > 0) {
                    return (int)$retry['id'];
                }
            }
            throw $e;
        }

        $newId = (int)$pdo->lastInsertId();
        if ($newId <= 0) {
            throw new RuntimeException('Failed to create customer account for order.');
        }

        return $newId;
    }
}

// Database helper functions
function fetchAll(mysqli $conn, string $query, array $params = [], string $types = ''): array {
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function getSingleValue(mysqli $conn, string $query, array $params = [], string $types = '') {
    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? reset($row) : 0;
}

class RemediesFilter {
    private $conditions = [];
    private $params = [];
    private $types = '';
    
    public function __construct(
        private string $search = '',
        private string $category = '',
        private string $status = ''
    ) {
        $this->buildConditions();
    }
    
    private function buildConditions(): void {
        $this->conditions[] = '1=1';
        
        if (!empty($this->search)) {
            $this->conditions[] = "(r.name LIKE ? OR r.sku LIKE ? OR r.description LIKE ?)";
            $searchTerm = "%{$this->search}%";
            $this->params = array_merge($this->params, [$searchTerm, $searchTerm, $searchTerm]);
            $this->types .= 'sss';
        }
        
        if (!empty($this->category) && is_numeric($this->category)) {
            $this->conditions[] = "r.category_id = ?";
            $this->params[] = $this->category;
            $this->types .= 'i';
        }
        
        if (!empty($this->status)) {
            switch ($this->status) {
                case 'active':
                    $this->conditions[] = "r.is_active = 1";
                    break;
                case 'inactive':
                    $this->conditions[] = "r.is_active = 0";
                    break;
                case 'featured':
                    $this->conditions[] = "r.is_featured = 1";
                    break;
            }
        }
    }
    
    public function getWhereClause(): string {
        return 'WHERE ' . implode(' AND ', $this->conditions);
    }
    
    public function getParams(): array {
        return $this->params;
    }
    
    public function getParamTypes(): string {
        return $this->types;
    }
}



// /**
//  * Get recent orders
//  */
// Function to log actions
function logAudit($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null, $user_id = null, $ip_address = null) {
    $conn = getDBConnection();
    return auditLogMysqli($conn, (string)$action, $table_name, $record_id, $old_values, $new_values, $user_id, $ip_address);
}

// Example usage in your code:
// logAudit('user_login', 'users', $user_id, null, ['last_login' => date('Y-m-d H:i:s')]);
// logAudit('order_update', 'orders', $order_id, $old_data, $new_data);

function getRecentOrders($limit = 10) {
    $conn = getDBConnection();
    $orders = [];
    
    $query = "SELECT o.*, u.full_name 
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              ORDER BY o.created_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    $stmt->close();
    return $orders;
}

// /**
//  * Get low stock products
//  */
// function getLowStockProducts($limit = 10) {
//     $conn = getDBConnection();
//     $products = [];
    
//     $query = "SELECT r.*, c.name as category_name 
//               FROM remedies r 
//               LEFT JOIN categories c ON r.category_id = c.id 
//               WHERE r.stock_quantity <= r.reorder_level 
//               AND r.is_active = 1 
//               ORDER BY r.stock_quantity ASC 
//               LIMIT ?";
    
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("i", $limit);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     while ($row = $result->fetch_assoc()) {
//         $row['stock_status'] = $row['stock_quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
//         $products[] = $row;
//     }
    
//     $stmt->close();
//     return $products;
// }

// /**
//  * Get recent customers
//  */
// function getRecentCustomers($limit = 10) {
//     $conn = getDBConnection();
//     $customers = [];
    
//     $query = "SELECT id, full_name, email, phone, created_at 
//               FROM users 
//               WHERE role = 'customer' 
//               ORDER BY created_at DESC 
//               LIMIT ?";
    
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("i", $limit);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     while ($row = $result->fetch_assoc()) {
//         $customers[] = $row;
//     }
    
//     $stmt->close();
//     return $customers;
// }
// /**
//  * Get sales chart data (last 7 days)
//  */
// function getSalesChartData() {
//     $conn = getDBConnection();
//     $data = [];
    
//     $query = "SELECT 
//                 DATE(created_at) as date,
//                 COUNT(*) as order_count,
//                 SUM(total_amount) as revenue
//               FROM orders 
//               WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
//                 AND order_status != 'cancelled'
//               GROUP BY DATE(created_at)
//               ORDER BY date";
    
//     $result = $conn->query($query);
    
//     while ($row = $result->fetch_assoc()) {
//         $data[] = $row;
//     }
    
//     return $data;
// }

// /**
//  * Get top selling products
//  */
// function getTopProducts($limit = 5) {
//     $conn = getDBConnection();
//     $products = [];
    
//     $query = "SELECT 
//                 r.id,
//                 r.name,
//                 r.sku,
//                 c.name as category_name,
//                 SUM(oi.quantity) as total_sold,
//                 SUM(oi.total_price) as revenue
//               FROM order_items oi
//               JOIN remedies r ON oi.product_id = r.id
//               JOIN categories c ON r.category_id = c.id
//               JOIN orders o ON oi.order_id = o.id
//               WHERE o.order_status != 'cancelled'
//                 AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
//               GROUP BY r.id, r.name, r.sku, c.name
//               ORDER BY total_sold DESC
//               LIMIT ?";
    
//     $stmt = $conn->prepare($query);
//     $stmt->bind_param("i", $limit);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     while ($row = $result->fetch_assoc()) {
//         $products[] = $row;
//     }
    
//     $stmt->close();
//     return $products;
// }


/**
 * Check action permissions
 */

if (!function_exists('canPerformAction')) {
    function canPerformAction($action) {
        $role = $_SESSION['admin_role'] ?? 'staff';

        // Admin can do everything
        if ($role === 'admin') {
            return true;
        }

        $staffPermissions = [
            'view_orders',
            'create_orders',
            'edit_orders',
            'view_customers',
            'manage_customers',
            'view_inventory',
            'restock_inventory',
            'view_remedies',
            'view_reports'
        ];

        return in_array($action, $staffPermissions, true);
    }
}
// Get low stock products
function getLowStockProducts($limit = 10) {
    $conn = getDBConnection();
    $products = [];
    
    $query = "SELECT r.*, c.name as category_name 
              FROM remedies r
              LEFT JOIN categories c ON r.category_id = c.id 
              WHERE r.stock_quantity <= r.reorder_level 
              AND r.is_active = 1 
              ORDER BY r.stock_quantity ASC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['stock_status'] = $row['stock_quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
        $products[] = $row;
    }
    
    $stmt->close();
    return $products;
}
// Get sales chart data (last 7 days)
function getSalesChartData() {
    $conn = getDBConnection();
    $data = [];
    
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue
              FROM orders 
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND order_status != 'cancelled'
              GROUP BY DATE(created_at)
              ORDER BY date";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get top selling products
function getTopProducts($limit = 5) {
    $conn = getDBConnection();
    $products = [];
    
    $query = "SELECT 
                r.id,
                r.name,
                r.sku,
                c.name as category_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total_price) as revenue
              FROM order_items oi
              JOIN remedies r ON oi.product_id = r.id
              JOIN categories c ON r.category_id = c.id
              JOIN orders o ON oi.order_id = o.id
              WHERE o.order_status != 'cancelled'
                AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              GROUP BY r.id, r.name, r.sku, c.name
              ORDER BY total_sold DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $stmt->close();
    return $products;
}

// Get recent customers
function getRecentCustomers($limit = 10) {
    $conn = getDBConnection();
    $customers = [];
    
    $query = "SELECT id, full_name, email, phone, created_at 
              FROM users 
              WHERE role = 'customer' 
              ORDER BY created_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    $stmt->close();
    return $customers;
}
function logActivities($user_id, $action, $details, $ip_address) {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }

    return auditLogPdo($pdo, (string)$action, 'system', 0, null, $details, $user_id, $ip_address);
}


function hasPermission($permission) {
    // Debug: Check session
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    // Check if role exists in session
    if (!isset($_SESSION['admin_role']) && !isset($_SESSION['role'])) {
        return false;
    }
    
    $role = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '');
    
    // Define permissions
    $permissions = [
        'super_admin' => [
            'view_dashboard',
            'view_orders', 'create_orders', 'edit_orders', 'delete_orders',
            'update_order_status', 'update_payment', 'add_order_notes',
            'bulk_operations', 'export_orders',
            'view_customers', 'manage_customers',
            'view_inventory', 'add_inventory', 'edit_inventory', 'delete_inventory', 'restock_inventory',
            'view_remedies', 'add_remedies', 'edit_remedies', 'delete_remedies',
            'view_reports', 'export_reports',
            'view_suppliers', 'manage_suppliers',
            'view_restocks', 'manage_restocks',
            'view_settings', 'manage_settings',
            'manage_users', 'manage_pending_users',
            'view_user_activity', 'suspend_users',
            'view_audit_logs', 'manage_audit_logs'
        ],
        'admin' => [
            'view_dashboard',
            'view_orders', 'create_orders', 'edit_orders', 'delete_orders',
            'update_order_status', 'update_payment', 'add_order_notes',
            'bulk_operations', 'export_orders',
            'view_customers', 'manage_customers',
            'view_inventory', 'add_inventory', 'edit_inventory', 'delete_inventory', 'restock_inventory',
            'view_remedies', 'add_remedies', 'edit_remedies', 'delete_remedies',
            'view_reports', 'export_reports',
            'view_suppliers', 'manage_suppliers',
            'view_restocks', 'manage_restocks',
            'view_settings', 'manage_settings',
            'manage_users', 'manage_pending_users',
            'view_user_activity', 'suspend_users'
        ],
        'staff' => [
            'view_dashboard',
            'view_orders', 'create_orders', 'edit_orders',
            'update_order_status', 'update_payment', 'add_order_notes',
            'export_orders',
            'view_customers', 'manage_customers',
            'view_inventory', 'add_inventory', 'edit_inventory', 'restock_inventory',
            'view_remedies', 'add_remedies', 'edit_remedies',
            'view_reports',
            'view_suppliers',
            'view_restocks'
        ],
        'customer' => [
            'view_dashboard',
            'view_orders', 'create_orders',
            'view_customers',
            'view_inventory',
            'view_remedies'
        ]
    ];
    
    // Normalize role (lowercase + support spaces/hyphens like "super admin")
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $role)));
    if ($role === 'superadmin') {
        $role = 'super_admin';
    }
    
    // Check if role exists
    if (!array_key_exists($role, $permissions)) {
        error_log("Role '$role' not found in permissions array");
        return false;
    }
    
    // Check permission
    $hasPerm = in_array($permission, $permissions[$role], true);
    
    // Debug log (optional)
    // error_log("Checking permission '$permission' for role '$role': " . ($hasPerm ? 'GRANTED' : 'DENIED'));
    
    return $hasPerm;
}
//  URL-friendly slugs
// Add this function to create URL-friendly slugs
function createSlug($string) {
    // Convert to lowercase
    $string = strtolower($string);
    
    // Replace non-alphanumeric characters with hyphens
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    
    // Replace multiple hyphens with single hyphen
    $string = preg_replace('/-+/', '-', $string);
    
    // Trim hyphens from beginning and end
    return trim($string, '-');
}

// Helper function to check if user is admin
function isAdmin() {
    $role = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'staff');
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $role)));
    if ($role === 'superadmin') {
        $role = 'super_admin';
    }
    return in_array($role, ['super_admin', 'admin'], true);
}

// ===== PAGE HANDLER FUNCTIONS =====

/**
 * Handle orders page data - Aligned with your database
 */
function handleOrders() {
    global $ordersData, $stats, $page, $statusFilter, $searchQuery, $perPage;
    
    $conn = getDBConnection();
    $page = intval($_GET['p'] ?? 1);
    $statusFilter = $_GET['status'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($statusFilter && $statusFilter !== 'all') {
        $where .= " AND order_status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    
    if ($searchQuery) {
        $where .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= str_repeat('s', 4);
    }
    
    try {
        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM orders $where";
        $stmt = mysqli_prepare($conn, $countQuery);
        
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalData = mysqli_fetch_assoc($result);
        $total = $totalData['total'] ?? 0;
        
        // Get orders
        $orders = [];
        $query = "SELECT o.*, 
                         COUNT(oi.id) as item_count,
                         SUM(oi.total_price) as items_total
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  $where 
                  GROUP BY o.id
                  ORDER BY o.created_at DESC 
                  LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $perPage;
        $types .= 'ii';
        
        $stmt = mysqli_prepare($conn, $query);
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        
        $ordersData = [
            'orders' => $orders,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    } catch (Exception $e) {
        error_log("Error in handleOrders: " . $e->getMessage());
        $ordersData = [
            'orders' => [],
            'total' => 0,
            'pages' => 0,
            'current_page' => $page
        ];
    }
}

/**
 * Handle inventory page data - Aligned with your database
 */
function handleInventory() {
    global $inventoryData, $page, $searchQuery, $perPage;
    
    $conn = getDBConnection();
    $page = intval($_GET['p'] ?? 1);
    $searchQuery = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $stockFilter = $_GET['stock_filter'] ?? '';
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    
    // Note: Using remedies table (not products)
    $where = "WHERE r.is_active = 1";
    $params = [];
    $types = '';
    
    if ($searchQuery) {
        $where .= " AND (r.name LIKE ? OR r.sku LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    if ($category) {
        $where .= " AND r.category_id = ?";
        $params[] = $category;
        $types .= 'i';
    }
    
    if ($stockFilter) {
        switch ($stockFilter) {
            case 'critical':
                $where .= " AND r.stock_quantity <= 0 AND r.stock_quantity > 0";
                break;
            case 'low':
                $where .= " AND r.stock_quantity <= r.reorder_level AND r.stock_quantity > 0";
                break;
            case 'out_of_stock':
                $where .= " AND r.stock_quantity = 0";
                break;
            case 'adequate':
                $where .= " AND r.stock_quantity > r.reorder_level";
                break;
        }
    }
    
    try {
        // Get inventory items from remedies table
        $items = [];
        $query = "SELECT r.*, c.name as category_name, s.name as supplier_name
                  FROM remedies r 
                  LEFT JOIN categories c ON r.category_id = c.id 
                  LEFT JOIN suppliers s ON r.supplier_id = s.id
                  $where 
                  ORDER BY r.stock_quantity ASC, r.name ASC 
                  LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $perPage;
        $types .= 'ii';
        
        $stmt = mysqli_prepare($conn, $query);
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        
        // Count total from remedies table
        $countQuery = "SELECT COUNT(*) as total FROM remedies r $where";
        $stmt = mysqli_prepare($conn, $countQuery);
        
        // Remove LIMIT params for count
        array_pop($params); // Remove perPage
        array_pop($params); // Remove offset
        $types = substr($types, 0, -2); // Remove 'ii'
        
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalData = mysqli_fetch_assoc($result);
        $total = $totalData['total'] ?? 0;
        
        // Get categories
        $categories = [];
        $catQuery = "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name";
        $catResult = mysqli_query($conn, $catQuery);
        while ($cat = mysqli_fetch_assoc($catResult)) {
            $categories[] = $cat;
        }
        
        $inventoryData = [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page,
            'categories' => $categories
        ];
    } catch (Exception $e) {
        error_log("Error in handleInventory: " . $e->getMessage());
        $inventoryData = [
            'items' => [],
            'total' => 0,
            'pages' => 0,
            'current_page' => $page,
            'categories' => []
        ];
    }
}

/**
 * Handle customers page data - Aligned with your database
 */
function handleCustomersEnhanced() {
    global $customersData, $stats, $page, $statusFilter, $searchQuery, $perPage;
    
    $conn = getDBConnection();
    $page = intval($_GET['p'] ?? 1);
    $searchQuery = $_GET['search'] ?? '';
    $dateFilter = $_GET['date'] ?? '';
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    
    // Build WHERE clause for users
    $where = "WHERE u.role = 'customer'";
    $params = [];
    $types = '';
    
    if ($searchQuery) {
        $where .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= 'sss';
    }
    
    try {
        // Count total customers
        $countQuery = "SELECT COUNT(*) as total FROM users u $where";
        $stmt = mysqli_prepare($conn, $countQuery);
        
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalData = mysqli_fetch_assoc($result);
        $total = $totalData['total'] ?? 0;
        
        // Get customers with order statistics
        $query = "SELECT 
                    u.*,
                    COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.created_at) as last_order,
                    MIN(o.created_at) as first_order,
                    COALESCE(AVG(o.total_amount), 0) as avg_order_value
                  FROM users u
                  LEFT JOIN orders o ON u.id = o.user_id
                  $where 
                  GROUP BY u.id
                  ORDER BY last_order DESC, u.created_at DESC
                  LIMIT ?, ?";
        
        $params[] = $offset;
        $params[] = $perPage;
        $types .= 'ii';
        
        $stmt = mysqli_prepare($conn, $query);
        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate customer value score
            $row['value_score'] = calculateCustomerValueScore($row);
            $row['loyalty_tier'] = determineLoyaltyTier($row);
            $row['activity_status'] = determineActivityStatus($row['last_order']);
            $customers[] = $row;
        }
        
        // Get customer statistics
        $statsQuery = "SELECT 
                        COUNT(DISTINCT u.id) as total_customers,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        COALESCE(AVG(o.total_amount), 0) as avg_revenue_per_customer,
                        COUNT(o.id) / NULLIF(COUNT(DISTINCT u.id), 0) as avg_orders_per_customer,
                        COALESCE(MAX(o.total_amount), 0) as highest_single_purchase,
                        COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.total_amount ELSE 0 END), 0) as revenue_last_30_days,
                        COUNT(DISTINCT CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN u.id ELSE NULL END) as active_customers_30_days
                      FROM users u
                      LEFT JOIN orders o ON u.id = o.user_id
                      WHERE u.role = 'customer'";
        
        $statsResult = mysqli_query($conn, $statsQuery);
        $customerStats = mysqli_fetch_assoc($statsResult);
        
        // Get customer growth data
        $growthQuery = "SELECT 
                        DATE_FORMAT(u.created_at, '%Y-%m') as month,
                        COUNT(DISTINCT u.id) as new_customers,
                        COUNT(DISTINCT CASE WHEN DATE(u.created_at) = CURDATE() THEN u.id ELSE NULL END) as today_new_customers
                      FROM users u
                      WHERE u.role = 'customer'
                      GROUP BY DATE_FORMAT(u.created_at, '%Y-%m')
                      ORDER BY month DESC
                      LIMIT 6";
        
        $growthResult = mysqli_query($conn, $growthQuery);
        $growthData = [];
        while ($row = mysqli_fetch_assoc($growthResult)) {
            $growthData[] = $row;
        }
        
        $customersData = [
            'customers' => $customers,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage,
            'stats' => $customerStats,
            'growth_data' => $growthData
        ];
    } catch (Exception $e) {
        error_log("Error in handleCustomersEnhanced: " . $e->getMessage());
        $customersData = [
            'customers' => [],
            'total' => 0,
            'pages' => 0,
            'current_page' => $page,
            'per_page' => $perPage,
            'stats' => [],
            'growth_data' => []
        ];
    }
}

/**
 * Handle remedies page data (your products are in remedies table)
 */
function handleProducts() {
    global $productsData, $page, $search;
    
    $conn = getDBConnection();
    $page = intval($_GET['p'] ?? 1);
    $search = $_GET['search'] ?? '';
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    
    $where = "WHERE r.is_active = 1";
    if ($search) {
        $where .= " AND (r.name LIKE '%$search%' OR r.sku LIKE '%$search%')";
    }
    
    // Get remedies (products) from remedies table
    $products = [];
    $query = "SELECT r.*, c.name as category_name 
              FROM remedies r 
              LEFT JOIN categories c ON r.category_id = c.id 
              $where 
              ORDER BY r.name 
              LIMIT $offset, $perPage";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    
    // Get categories
    $categories = [];
    $catQuery = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
    $catResult = mysqli_query($conn, $catQuery);
    while ($cat = mysqli_fetch_assoc($catResult)) {
        $categories[] = $cat;
    }
    
    $productsData = [
        'products' => $products,
        'categories' => $categories,
        'current_page' => $page
    ];
}

/**
 * Handle reports page data - Aligned with your database
 */
function handleReports() {
    global $reportsData, $reportType, $startDate, $endDate;
    
    $reportType = $_GET['report_type'] ?? 'daily';
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    $conn = getDBConnection();
    
    $reportsData = [
        'sales' => [],
        'top_products' => [],
        'payment_methods' => [],
        'order_status' => []
    ];
    
    // Sales data
    if ($reportType == 'daily') {
        $query = "SELECT DATE(created_at) as date, 
                         COUNT(*) as orders_count, 
                         SUM(total_amount) as total_revenue 
                  FROM orders 
                  WHERE created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' 
                  AND order_status NOT IN ('cancelled')
                  GROUP BY DATE(created_at) 
                  ORDER BY date DESC";
    } else {
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                         COUNT(*) as orders_count, 
                         SUM(total_amount) as total_revenue 
                  FROM orders 
                  WHERE created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' 
                  AND order_status NOT IN ('cancelled')
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                  ORDER BY month DESC";
    }
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $reportsData['sales'][] = $row;
    }
    
    // Top products from remedies table
    $query = "SELECT r.name, r.sku, 
                     SUM(oi.quantity) as total_sold, 
                     SUM(oi.total_price) as total_revenue 
              FROM order_items oi 
              JOIN remedies r ON oi.product_id = r.id 
              JOIN orders o ON oi.order_id = o.id
              WHERE o.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' 
              AND o.order_status NOT IN ('cancelled')
              GROUP BY r.id 
              ORDER BY total_revenue DESC 
              LIMIT 10";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $reportsData['top_products'][] = $row;
    }
    
    // Payment methods
    $query = "SELECT payment_method, 
                     COUNT(*) as count, 
                     SUM(total_amount) as total 
              FROM orders 
              WHERE created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' 
              AND order_status NOT IN ('cancelled')
              GROUP BY payment_method 
              ORDER BY total DESC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $reportsData['payment_methods'][] = $row;
    }
    
    // Order status distribution
    $query = "SELECT order_status, 
                     COUNT(*) as count, 
                     SUM(total_amount) as total 
              FROM orders 
              WHERE created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
              GROUP BY order_status 
              ORDER BY count DESC";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $reportsData['order_status'][] = $row;
    }
}

// ===== HELPER FUNCTIONS =====

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'KSh ' . number_format($amount, 2);
}


function getPendingOrdersCount() {
    global $pdo; // Assuming you have a PDO connection
    
    try {
        $sql = "SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting pending orders count: " . $e->getMessage());
        return 0;
    }
}
/**
 * Get priority class for orders
 */
function getPriorityClass($order) {
    $status = $order['order_status'];
    $date = strtotime($order['created_at']);
    $now = time();
    $hoursDiff = ($now - $date) / 3600;
    
    if ($status === 'pending' && $hoursDiff > 24) {
        return 'priority-high';
    } else if ($status === 'pending' && $hoursDiff > 12) {
        return 'priority-medium';
    } else if ($status === 'pending') {
        return 'priority-low';
    }
    return '';
}

// ===== CUSTOMER MANAGEMENT HELPER FUNCTIONS =====

/**
 * Calculate customer value score
 */
function calculateCustomerValueScore($customer) {
    $score = 0;
    
    // Points for total spent
    $score += min(50, ($customer['total_spent'] / 1000) * 10);
    
    // Points for order count
    $score += min(30, $customer['order_count'] * 5);
    
    // Points for average order value
    $score += min(20, ($customer['avg_order_value'] / 100) * 5);
    
    return round($score);
}

/**
 * Determine customer loyalty tier
 */
function determineLoyaltyTier($customer) {
    $totalSpent = $customer['total_spent'] ?? 0;
    $orderCount = $customer['order_count'] ?? 0;
    
    if ($totalSpent >= 5000 || $orderCount >= 20) {
        return 'Platinum';
    } elseif ($totalSpent >= 2000 || $orderCount >= 10) {
        return 'Gold';
    } elseif ($totalSpent >= 500 || $orderCount >= 5) {
        return 'Silver';
    } else {
        return 'Bronze';
    }
}

/**
 * Determine customer activity status
 */
function determineActivityStatus($lastOrderDate) {
    if (!$lastOrderDate) return 'New';
    
    $lastOrder = new DateTime($lastOrderDate);
    $now = new DateTime();
    $interval = $now->diff($lastOrder);
    $days = $interval->days;
    
    if ($days <= 7) return 'Active';
    if ($days <= 30) return 'Regular';
    if ($days <= 90) return 'Occasional';
    return 'Inactive';
}

/**
 * Get loyalty badge HTML
 */
function getLoyaltyBadge($tier) {
    $badges = [
        'Platinum' => '<span class="badge bg-dark"><i class="fas fa-crown me-1"></i>Platinum</span>',
        'Gold' => '<span class="badge bg-warning"><i class="fas fa-star me-1"></i>Gold</span>',
        'Silver' => '<span class="badge bg-secondary"><i class="fas fa-star me-1"></i>Silver</span>',
        'Bronze' => '<span class="badge bg-brown"><i class="fas fa-award me-1"></i>Bronze</span>'
    ];
    return $badges[$tier] ?? '<span class="badge bg-light text-dark">New</span>';
}

/**
 * Get activity badge HTML
 */
function getActivityBadge($status) {
    $badges = [
        'Active' => '<span class="badge bg-success"><i class="fas fa-bolt me-1"></i>Active</span>',
        'Regular' => '<span class="badge bg-primary"><i class="fas fa-calendar-check me-1"></i>Regular</span>',
        'Occasional' => '<span class="badge bg-info"><i class="fas fa-clock me-1"></i>Occasional</span>',
        'Inactive' => '<span class="badge bg-secondary"><i class="fas fa-moon me-1"></i>Inactive</span>',
        'New' => '<span class="badge bg-warning"><i class="fas fa-user-plus me-1"></i>New</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-light text-dark">Unknown</span>';
}

/**
 * Get customer value score badge
 */
function getCustomerValueScoreBadge($score) {
    if ($score >= 80) {
        return '<span class="badge bg-success">Excellent</span>';
    } elseif ($score >= 60) {
        return '<span class="badge bg-primary">Good</span>';
    } elseif ($score >= 40) {
        return '<span class="badge bg-info">Average</span>';
    } else {
        return '<span class="badge bg-secondary">Low</span>';
    }
}

// ===== INVENTORY HELPER FUNCTIONS =====

function getStockStatus($quantity, $reorderLevel) {
    if ($quantity <= 0) {
        return 'Out of Stock';
    }
    
    if ($quantity <= $reorderLevel) {
        return 'Low Stock';
    }
    
    return 'Adequate';
}

function getStockColor($quantity, $reorderLevel, $minimumStock = 5) {
    $stockStatus = getStockStatus($quantity, $reorderLevel, $minimumStock);
    
    switch ($stockStatus) {
        case 'Out of Stock':
            return '#dc3545'; // Bootstrap danger red
        case 'Critical':
            return '#ff6b6b'; // Light red
        case 'Low Stock':
            return '#ffc107'; // Bootstrap warning yellow
        case 'In Stock':
            return '#28a745'; // Bootstrap success green
        case 'Good Stock':
            return '#20c997'; // Bootstrap teal
        case 'Overstock':
            return '#6f42c1'; // Bootstrap purple
        default:
            return '#6c757d'; // Bootstrap secondary gray
    }
}

function getStockStatusBadge($quantity, $reorderLevel) {
    $status = getStockStatus($quantity, $reorderLevel);
    
    $badges = [
        'Out of Stock' => '<span class="badge bg-dark">Out of Stock</span>',
        'Critical' => '<span class="badge bg-danger">Critical</span>',
        'Low Stock' => '<span class="badge bg-warning">Low Stock</span>',
        'Adequate' => '<span class="badge bg-success">Adequate</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function getStockFilterColor($filter) {
    $colors = [
        'critical' => 'danger',
        'low' => 'warning',
        'out_of_stock' => 'dark',
        'adequate' => 'success'
    ];
    
    return $colors[$filter] ?? 'secondary';
}

/**
 * Get status color for orders
 */
function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'paid' => 'success',
        'failed' => 'danger',
        'refunded' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

// ===== SIDEBAR MENU FUNCTIONS =====

/**
 * Get sidebar menu items
 */
function getSidebarMenu() {
    $currentPage = $_GET['page'] ?? 'dashboard';
    $role = $_SESSION['admin_role'] ?? 'staff';
    
    $menu = [
        [
            'url' => '?page=dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'text' => 'Dashboard',
            'active' => $currentPage === 'dashboard',
            'badge' => 0
        ],
        [
            'url' => '?page=orders',
            'icon' => 'fas fa-shopping-cart',
            'text' => 'Orders',
            'active' => $currentPage === 'orders',
            'badge' => 0
        ],
        [
            'url' => '?page=customers',
            'icon' => 'fas fa-users',
            'text' => 'Customers',
            'active' => $currentPage === 'customers',
            'badge' => 0
        ],
        [
            'url' => '?page=inventory',
            'icon' => 'fas fa-boxes',
            'text' => 'Inventory',
            'active' => $currentPage === 'inventory',
            'badge' => 0
        ],
        [
            'url' => '?page=remedies',
            'icon' => 'fas fa-heartbeat',
            'text' => 'Remedies',
            'active' => $currentPage === 'remedies' || $currentPage === 'products',
            'badge' => 0
        ],
        [
            'url' => '?page=reports',
            'icon' => 'fas fa-chart-bar',
            'text' => 'Reports',
            'active' => $currentPage === 'reports',
            'badge' => 0
        ]
    ];
    
    // Add admin-only menu items
    if (isAdmin()) {
        $menu = array_merge($menu, [
            [
                'url' => '?page=suppliers',
                'icon' => 'fas fa-truck',
                'text' => 'Suppliers',
                'active' => $currentPage === 'suppliers',
                'badge' => 0
            ],
            [
                'url' => '?page=users',
                'icon' => 'fas fa-user-cog',
                'text' => 'Users',
                'active' => $currentPage === 'users',
                'badge' => 0
            ]
        ]);
    }

    $currentRole = strtolower(trim((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '')));
    if ($currentRole === 'super_admin') {
        $menu[] = [
            'url' => '?page=audit_log',
            'icon' => 'fas fa-history',
            'text' => 'Audit Log',
            'active' => $currentPage === 'audit_log',
            'badge' => 0
        ];
    }
    
    return $menu;
}

// ===== OTHER PAGE HANDLERS =====

/**
 * Handle suppliers page data
 */
function handleSuppliers() {
    global $suppliersData;
    
    $conn = getDBConnection();
    $suppliers = [];
    
    $query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers[] = $row;
    }
    
    $suppliersData = ['suppliers' => $suppliers];
}

/**
 * Handle users page data
 */
function handleUsers() {
    global $usersData;
    
    $conn = getDBConnection();
    $users = [];
    
    $query = "SELECT * FROM users ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    $usersData = ['users' => $users];
}

/**
 * Handle audit log page data
 */
function handleAuditLog() {
    global $auditData;
    
    $conn = getDBConnection();
    $logs = [];
    
    $query = "SELECT al.*, u.full_name as user_name 
              FROM audit_log al
              LEFT JOIN users u ON al.user_id = u.id
              ORDER BY al.created_at DESC 
              LIMIT 100";
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    
    $auditData = ['logs' => $logs];
}

/**
 * Handle dashboard data
 */
function handleDashboard() {
    global $dashboardData;
    
    $conn = getDBConnection();
    
    // Get today's sales
    $todayQuery = "SELECT 
                    COUNT(*) as today_orders,
                    SUM(total_amount) as today_revenue,
                    AVG(total_amount) as today_avg_order
                   FROM orders 
                   WHERE DATE(created_at) = CURDATE() 
                   AND order_status NOT IN ('cancelled')";
    
    $result = mysqli_query($conn, $todayQuery);
    $today = mysqli_fetch_assoc($result);
    
    // Get this month's sales
    $monthQuery = "SELECT 
                    COUNT(*) as month_orders,
                    SUM(total_amount) as month_revenue
                   FROM orders 
                   WHERE MONTH(created_at) = MONTH(CURDATE()) 
                   AND YEAR(created_at) = YEAR(CURDATE())
                   AND order_status NOT IN ('cancelled')";
    
    $result = mysqli_query($conn, $monthQuery);
    $month = mysqli_fetch_assoc($result);
    
    // Get low stock remedies
    $lowStockQuery = "SELECT COUNT(*) as low_stock_count 
                      FROM remedies 
                      WHERE stock_quantity <= reorder_level 
                      AND is_active = 1";
    
    $result = mysqli_query($conn, $lowStockQuery);
    $lowStock = mysqli_fetch_assoc($result);
    
    // Get pending orders
    $pendingQuery = "SELECT COUNT(*) as pending_orders 
                     FROM orders 
                     WHERE order_status = 'pending'";
    
    $result = mysqli_query($conn, $pendingQuery);
    $pending = mysqli_fetch_assoc($result);
    
    // Get recent orders
    $recentOrdersQuery = "SELECT o.*, u.full_name as customer_name 
                          FROM orders o
                          LEFT JOIN users u ON o.user_id = u.id
                          ORDER BY o.created_at DESC 
                          LIMIT 10";
    
    $recentOrders = [];
    $result = mysqli_query($conn, $recentOrdersQuery);
    while ($row = mysqli_fetch_assoc($result)) {
        $recentOrders[] = $row;
    }
    
    // Get top remedies (products)
    $topRemediesQuery = "SELECT r.name, r.sku, 
                                SUM(oi.quantity) as total_sold,
                                SUM(oi.total_price) as revenue
                         FROM order_items oi
                         JOIN remedies r ON oi.product_id = r.id
                         JOIN orders o ON oi.order_id = o.id
                         WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         AND o.order_status NOT IN ('cancelled')
                         GROUP BY r.id
                         ORDER BY revenue DESC
                         LIMIT 5";
    
    $topRemedies = [];
    $result = mysqli_query($conn, $topRemediesQuery);
    while ($row = mysqli_fetch_assoc($result)) {
        $topRemedies[] = $row;
    }
    
    $dashboardData = [
        'today' => $today,
        'month' => $month,
        'low_stock' => $lowStock,
        'pending_orders' => $pending,
        'recent_orders' => $recentOrders,
        'top_remedies' => $topRemedies
    ];
}

// ===== FORM PROCESSING FUNCTIONS =====

/**
 * Process form submissions
 */
function processFormSubmission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $conn = getDBConnection();
        
        switch ($action) {
            case 'update_order_status':
                if (isset($_POST['order_id']) && isset($_POST['status'])) {
                    $orderId = intval($_POST['order_id']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    $userId = $_SESSION['admin_id'] ?? 0;

                    global $pdo;
                    $oldStatus = '';
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $beforeStmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ? LIMIT 1");
                        $beforeStmt->execute([$orderId]);
                        $oldStatus = (string)($beforeStmt->fetchColumn() ?? '');
                    }
                    
                    $query = "UPDATE orders SET order_status = '$status', updated_at = NOW() WHERE id = $orderId";
                    if (mysqli_query($conn, $query)) {
                        $notifyResult = null;
                        if (isset($pdo) && $pdo instanceof PDO && $oldStatus !== '' && $oldStatus !== $status) {
                            $notifyResult = sendOrderLifecycleNotification(
                                $pdo,
                                $orderId,
                                'status_update',
                                ['order_status' => ['old' => $oldStatus, 'new' => $status]]
                            );
                        }

                        logAudit(
                            'order_status_update',
                            'orders',
                            $orderId,
                            null,
                            "Order status updated to '$status'",
                            $userId
                        );
                        
                        $notifyText = (!empty($notifyResult['email']['success']) || !empty($notifyResult['sms']['success']))
                            ? ' Customer notification sent.'
                            : '';
                        $_SESSION['flash_message'] = 'Order status updated successfully.' . $notifyText;
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Failed to update order status';
                        $_SESSION['flash_type'] = 'danger';
                    }
                }
                break;
                
            case 'update_payment_status':
                if (isset($_POST['order_id']) && isset($_POST['payment_status'])) {
                    $orderId = intval($_POST['order_id']);
                    $paymentStatus = mysqli_real_escape_string($conn, $_POST['payment_status']);
                    $userId = $_SESSION['admin_id'] ?? 0;

                    global $pdo;
                    $oldPaymentStatus = '';
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $beforeStmt = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ? LIMIT 1");
                        $beforeStmt->execute([$orderId]);
                        $oldPaymentStatus = (string)($beforeStmt->fetchColumn() ?? '');
                    }
                    
                    $query = "UPDATE orders SET payment_status = '$paymentStatus', updated_at = NOW() WHERE id = $orderId";
                    if (mysqli_query($conn, $query)) {
                        $notifyResult = null;
                        if (isset($pdo) && $pdo instanceof PDO && $oldPaymentStatus !== '' && $oldPaymentStatus !== $paymentStatus) {
                            $notifyResult = sendOrderLifecycleNotification(
                                $pdo,
                                $orderId,
                                'status_update',
                                ['payment_status' => ['old' => $oldPaymentStatus, 'new' => $paymentStatus]]
                            );
                        }

                        logAudit(
                            'payment_status_update',
                            'orders',
                            $orderId,
                            null,
                            "Payment status updated to '$paymentStatus'",
                            $userId
                        );
                        
                        $notifyText = (!empty($notifyResult['email']['success']) || !empty($notifyResult['sms']['success']))
                            ? ' Customer notification sent.'
                            : '';
                        $_SESSION['flash_message'] = 'Payment status updated successfully.' . $notifyText;
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Failed to update payment status';
                        $_SESSION['flash_type'] = 'danger';
                    }
                }
                break;
                
            case 'update_inventory':
                if (isset($_POST['product_id']) && isset($_POST['quantity'])) {
                    $productId = intval($_POST['product_id']);
                    $quantity = floatval($_POST['quantity']);
                    $userId = $_SESSION['admin_id'] ?? 0;
                    
                    // Update remedies table (your products table)
                    $query = "UPDATE remedies SET stock_quantity = $quantity, updated_at = NOW() WHERE id = $productId";
                    if (mysqli_query($conn, $query)) {
                        logAudit(
                            'stock_update',
                            'remedies',
                            $productId,
                            null,
                            ['stock_quantity' => $quantity],
                            $userId
                        );
                        
                        $_SESSION['flash_message'] = 'Inventory updated successfully';
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Failed to update inventory';
                        $_SESSION['flash_type'] = 'danger';
                    }
                }
                break;
                
            case 'add_user':
                if (isset($_POST['email']) && isset($_POST['full_name']) && isset($_POST['role'])) {
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $fullName = mysqli_real_escape_string($conn, $_POST['full_name']);
                    $role = mysqli_real_escape_string($conn, $_POST['role']);
                    $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
                    $userId = $_SESSION['admin_id'] ?? 0;
                    
                    $query = "INSERT INTO users (email, password_hash, full_name, role) 
                              VALUES ('$email', '$password', '$fullName', '$role')";
                    if (mysqli_query($conn, $query)) {
                        $newUserId = mysqli_insert_id($conn);

                        logAudit(
                            'user_created',
                            'users',
                            $newUserId,
                            null,
                            ['email' => $email, 'role' => $role],
                            $userId
                        );
                        
                        $_SESSION['flash_message'] = 'User added successfully';
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Failed to add user';
                        $_SESSION['flash_type'] = 'danger';
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . ($_GET['page'] ?? 'dashboard'));
        exit;
    }
}
// Get statistics for dashboard
function getDashboardStats() {
    $conn = getDBConnection();
    $stats = [];
    
    // Total products
    $result = $conn->query("SELECT COUNT(*) as count FROM remedies WHERE is_active = 1");
    $stats['total_products'] = $result->fetch_assoc()['count'];
    
    // Total customers
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $stats['total_customers'] = $result->fetch_assoc()['count'];
    
    // Total orders (today)
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()");
    $stats['today_orders'] = $result->fetch_assoc()['count'];
    
    // Total revenue (today)
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status != 'cancelled'");
    $stats['today_revenue'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total revenue (month)
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND order_status != 'cancelled'");
    $stats['month_revenue'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Low stock products
    $result = $conn->query("SELECT COUNT(*) as count FROM remedies WHERE stock_quantity <= reorder_level AND is_active = 1");
    $stats['low_stock'] = $result->fetch_assoc()['count'];
    
    // Pending orders
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
    $stats['pending_orders'] = $result->fetch_assoc()['count'];
    
    return $stats;
}



?>
