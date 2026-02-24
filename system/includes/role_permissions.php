<?php
require_once 'database.php';
require_once 'audit_helper.php';

// ===== ROLE CONSTANTS =====
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');
define('ROLE_CUSTOMER', 'customer');
define('ROLE_SUPER_ADMIN', 'super_admin');

if (!function_exists('resolveSessionRole')) {
    function resolveSessionRole() {
        $role = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ROLE_CUSTOMER);
        $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $role)));
        if ($role === 'superadmin') {
            $role = ROLE_SUPER_ADMIN;
        }
        return $role;
    }
}


/**
 * Check if user is Staff
 * @return bool
 */
function isStaff() {
    return resolveSessionRole() === ROLE_STAFF;
}

/**
 * Check if user is Customer
 * @return bool
 */
function isCustomer() {
    return resolveSessionRole() === ROLE_CUSTOMER;
}
// includes/role_permissions.php
function getRolePermissions($role) {
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', (string)$role)));
    if ($role === 'superadmin') {
        $role = ROLE_SUPER_ADMIN;
    }
    $permissions = [];
    
    switch ($role) {
        case ROLE_SUPER_ADMIN:
            $permissions = getAllPermissions();
            break;
        case 'admin':
            $permissions = [
                'edit_orders',
                'delete_orders',
                'manage_products',
                'manage_users',
                // ... other permissions
            ];
            break;
            
        case 'staff':
            $permissions = [
                'edit_orders',
                'manage_products',
                // ... other permissions
            ];
            break;
            
        case 'customer':
            $permissions = [
                // Limited permissions
            ];
            break;
    }
    
    return $permissions;
}
/**
 * Get role display name
 * @param string $role Role code
 * @return string Display name
 */
function getRoleDisplayName($role) {
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', (string)$role)));
    if ($role === 'superadmin') {
        $role = ROLE_SUPER_ADMIN;
    }
    $roleNames = [
        ROLE_SUPER_ADMIN => 'Super Admin',
        ROLE_ADMIN => 'admin',
        ROLE_STAFF => 'staff',
        ROLE_CUSTOMER => 'Customer'
    ];
    
    return $roleNames[$role] ?? 'Unknown Role';
}

/**
 * Check if user can access a specific page
 * @param string $page Page name
 * @return bool True if user can access, false otherwise
 */
function canAccessPage($page) {
    // Default accessible pages for each role
    $rolePages = [
        ROLE_ADMIN => ['dashboard', 'orders', 'customers', 'inventory', 'reports', 'products', 'categories', 'suppliers', 'users', 'settings'],
        ROLE_STAFF => ['dashboard', 'orders', 'customers', 'inventory', 'products', 'categories', 'reports'],
        ROLE_CUSTOMER => ['dashboard'], // Customers typically don't have admin access
        ROLE_SUPER_ADMIN => ['dashboard', 'orders', 'customers', 'inventory', 'reports', 'products', 'categories', 'suppliers', 'users', 'audit_log', 'settings']
    ];
    
    $userRole = resolveSessionRole();
    
    if (!isset($rolePages[$userRole])) {
        return false;
    }
    
    return in_array($page, $rolePages[$userRole], true);
}

/**
 * Check action permissions
 */
if (!function_exists('canPerformAction')) {
    function canPerformAction($action) {
        $role = resolveSessionRole();
        
        // Admin can do everything
        if ($role === ROLE_ADMIN || $role === ROLE_SUPER_ADMIN) {
            return true;
        }
        
        // Staff permissions
        $staffPermissions = [
            'view_orders',
            'create_orders',
            'edit_orders',
            'view_customers',
            'manage_customers',
            'view_inventory',
            'restock_inventory',
            'view_products',
            'edit_products',
            'view_reports',
            'view_categories'
        ];
        
        return in_array($action, $staffPermissions, true);
    }
}

/**
 * Show access denied page
 */
function showAccessDenied() {
    $role = $_SESSION['role'] ?? 'Not logged in';
    $roleName = getRoleDisplayName($role);
    
    // Log the access denial
    logActivity('access_denied', "Attempted to access restricted area. Role: $role");
    
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - JAKISAWA SHOP</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
            }
            .access-denied {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
                width: 90%;
            }
            .icon {
                font-size: 80px;
                color: #ff4757;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 2rem;
            }
            p {
                color: #666;
                margin-bottom: 10px;
                line-height: 1.6;
            }
            .role-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                text-align: left;
            }
            .role-info p {
                margin: 5px 0;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: all 0.3s;
                margin: 5px;
                border: none;
                cursor: pointer;
                font-size: 1rem;
            }
            .btn:hover {
                background: #5a67d8;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #6c757d;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
            .text-muted {
                color: #6c757d;
                font-size: 0.9rem;
            }
        </style>
        <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
    <body>
        <div class="access-denied">
            <div class="icon">🚫</div>
            <h1>Access Denied</h1>
            <p>You don't have permission to access this page.</p>
            <div class="role-info">
                <p><strong>Your Role:</strong> <?php echo $roleName; ?></p>
                <p><strong>Username:</strong> <?php echo $_SESSION['full_name'] ?? 'Unknown'; ?></p>
                <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'N/A'; ?></p>
            </div>
            <p class="text-muted">Please contact your administrator for access.</p>
            <div>
                <button onclick="window.location.href='?page=dashboard'" class="btn">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </button>
                <button onclick="window.location.href='logout.php'" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Log activity to audit_log table
 */
function logActivity($action, $description) {
    try {
        $conn = getDBConnection();
        auditLogMysqli(
            $conn,
            (string)$action,
            'audit_log',
            0,
            null,
            (string)$description,
            ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
            ($_SERVER['REMOTE_ADDR'] ?? null)
        );
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Get all permissions for current user
 * @return array List of permissions
 */
function getUserPermissions() {
    if (!isset($_SESSION['role']) && !isset($_SESSION['admin_role'])) {
        return [];
    }
    
    $userRole = resolveSessionRole();
    
    // Admin gets all permissions
    if ($userRole === ROLE_ADMIN || $userRole === ROLE_SUPER_ADMIN) {
        return getAllPermissions();
    }
    
    // Staff permissions
    if ($userRole === ROLE_STAFF) {
        return [
            'view_orders',
            'create_orders',
            'edit_orders',
            'view_customers',
            'manage_customers',
            'view_inventory',
            'restock_inventory',
            'view_products',
            'edit_products',
            'view_reports',
            'view_categories'
        ];
    }
    
    // Customer permissions (if they have admin access somehow)
    if ($userRole === ROLE_CUSTOMER) {
        return ['view_dashboard'];
    }
    
    return [];
}

/**
 * Get role hierarchy
 * @return array Role hierarchy with levels
 */
function getRoleHierarchy() {
    return [
        ROLE_SUPER_ADMIN => 4, // Highest level
        ROLE_ADMIN => 3,      // Highest level
        ROLE_STAFF => 2,
        ROLE_CUSTOMER => 1    // Lowest level
    ];
}

/**
 * Get permission groups for display
 * @return array Permission groups
 */
function getPermissionGroups() {
    return [
        'dashboard' => [
            'name' => 'Dashboard',
            'permissions' => ['view_dashboard']
        ],
        'orders' => [
            'name' => 'Orders',
            'permissions' => ['view_orders', 'create_orders', 'edit_orders']
        ],
        'customers' => [
            'name' => 'Customers',
            'permissions' => ['view_customers', 'manage_customers']
        ],
        'inventory' => [
            'name' => 'Inventory',
            'permissions' => ['view_inventory', 'restock_inventory']
        ],
        'products' => [
            'name' => 'Products',
            'permissions' => ['view_products', 'edit_products']
        ],
        'reports' => [
            'name' => 'Reports',
            'permissions' => ['view_reports']
        ],
        'categories' => [
            'name' => 'Categories',
            'permissions' => ['view_categories']
        ],
        'users' => [
            'name' => 'User Management',
            'permissions' => ['manage_users']
        ]
    ];
}

/**
 * Get all available permissions (not from database, but defined here)
 * @return array All permissions
 */
function getAllPermissions() {
    return [
        'view_dashboard',
        'view_orders',
        'create_orders',
        'edit_orders',
        'delete_orders',
        'view_customers',
        'manage_customers',
        'view_inventory',
        'edit_inventory',
        'restock_inventory',
        'delete_inventory',
        'view_products',
        'edit_products',
        'delete_products',
        'view_reports',
        'export_reports',
        'view_categories',
        'manage_categories',
        'manage_suppliers',
        'manage_users',
        'manage_settings',
        'view_audit_log'
    ];
}

/**
 * Check if user has any of specified roles
 * @param array $roles Roles to check
 * @return bool True if user has any of the roles
 */
function hasAnyRole($roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Get available roles for user management
 * @param string $currentUserRole Current user's role
 * @return array Available roles
 */
function getAvailableRoles($currentUserRole) {
    $currentUserRole = strtolower(trim((string)preg_replace('/[\s-]+/', '_', (string)$currentUserRole)));
    if ($currentUserRole === 'superadmin') {
        $currentUserRole = ROLE_SUPER_ADMIN;
    }

    $allRoles = [
        ROLE_SUPER_ADMIN => 'Super Admin',
        ROLE_ADMIN => 'Administrator',
        ROLE_STAFF => 'Staff Member',
        ROLE_CUSTOMER => 'Customer'
    ];
    
    // Filter based on hierarchy
    $hierarchy = getRoleHierarchy();
    $currentLevel = $hierarchy[$currentUserRole] ?? 0;
    
    $availableRoles = [];
    foreach ($allRoles as $role => $name) {
        $roleLevel = $hierarchy[$role] ?? 0;
        if ($roleLevel <= $currentLevel) { // Can assign same or lower level
            $availableRoles[$role] = $name;
        }
    }
    
    return $availableRoles;
}

/**
 * Validate user role
 * @param string $role Role to validate
 * @return bool True if valid role
 */
function isValidRole($role) {
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', (string)$role)));
    if ($role === 'superadmin') {
        $role = ROLE_SUPER_ADMIN;
    }
    $validRoles = [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF, ROLE_CUSTOMER];
    return in_array($role, $validRoles);
}

/**
 * Get role color
 * @param string $role Role code
 * @return string Color class
 */
function getRoleColor($role) {
    $role = strtolower(trim((string)preg_replace('/[\s-]+/', '_', (string)$role)));
    if ($role === 'superadmin') {
        $role = ROLE_SUPER_ADMIN;
    }
    $colors = [
        ROLE_SUPER_ADMIN => 'dark',
        ROLE_ADMIN => 'danger',
        ROLE_STAFF => 'warning',
        ROLE_CUSTOMER => 'info',
        'inactive' => 'secondary'
    ];
    
    return $colors[$role] ?? 'secondary';
}

/**
 * Check if user can modify system settings
 * @return bool
 */
function canModifySettings() {
    return isAdmin();
}

/**
 * Check if user can export data
 * @param string $type Type of data (orders, reports, etc.)
 * @return bool
 */
function canExportData($type) {
    if (!isAdmin()) {
        return false;
    }
    
    // Admin can export everything
    return true;
}

/**
 * Get user's allowed actions for a specific module
 * @param string $module Module name
 * @return array Allowed actions
 */
function getModulePermissions($module) {
    $modulePermissions = [
        'orders' => ['view_orders', 'create_orders', 'edit_orders'],
        'inventory' => ['view_inventory', 'restock_inventory'],
        'products' => ['view_products', 'edit_products'],
        'customers' => ['view_customers', 'manage_customers'],
        'users' => ['manage_users']
    ];
    
    if (!isset($modulePermissions[$module])) {
        return [];
    }
    
    $allowedActions = [];
    foreach ($modulePermissions[$module] as $action) {
        if (canPerformAction($action)) {
            $allowedActions[] = $action;
        }
    }
    
    return $allowedActions;
}

/**
 * Check if user can perform bulk operations
 * @param string $module Module name
 * @return bool
 */
function canPerformBulkOperations($module) {
    if (!isAdmin()) {
        return false;
    }
    
    // Admin can perform bulk operations on all modules
    return in_array($module, ['orders', 'inventory', 'products', 'customers', 'users']);
}

/**
 * Get user's permission level
 * @return int Permission level (1-3)
 */
function getUserPermissionLevel() {
    $hierarchy = getRoleHierarchy();
    $userRole = $_SESSION['role'] ?? '';
    return $hierarchy[$userRole] ?? 0;
}

/**
 * Check if user can view sensitive information
 * @return bool
 */
function canViewSensitiveInfo() {
    return isAdmin();
}

/**
 * Check if user can manage users
 * @return bool
 */
function canManageUsers() {
    return isAdmin();
}

/**
 * Check if user can manage products
 * @return bool
 */
function canManageProducts() {
    return isAdmin() || isStaff();
}

/**
 * Check if user can manage inventory
 * @return bool
 */
function canManageInventory() {
    return isAdmin() || isStaff();
}

/**
 * Check if user can manage orders
 * @return bool
 */
function canManageOrders() {
    return isAdmin() || isStaff();
}

/**
 * Check if user can view reports
 * @return bool
 */
function canViewReports() {
    return isAdmin() || isStaff();
}

/**
 * Check if user can view audit log
 * @return bool
 */
function canViewAuditLog() {
    $role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
    return $role === 'super_admin';
}

/**
 * Get user's role-based dashboard widgets
 * @return array Dashboard widgets
 */
function getDashboardWidgets() {
    $role = resolveSessionRole();
    
    $widgets = [
        'stats' => true,
        'recent_orders' => true,
        'low_stock' => true,
        'sales_chart' => true
    ];
    
    if ($role === ROLE_ADMIN || $role === 'super_admin') {
        $widgets['pending_users'] = true;
        $widgets['revenue_chart'] = true;
    }
    if ($role === 'super_admin') {
        $widgets['audit_log'] = true;
    }
    
    if ($role === ROLE_STAFF) {
        $widgets['my_tasks'] = true;
    }
    
    return $widgets;
}

/**
 * Check if user requires authentication for action
 * @param string $action Action to check
 * @return bool True if authentication required
 */
function requiresAuthentication($action) {
    $protectedActions = [
        'view_orders',
        'create_orders',
        'edit_orders',
        'manage_customers',
        'restock_inventory',
        'edit_products',
        'manage_users',
        'view_reports'
    ];
    
    return in_array($action, $protectedActions);
}

/**
 * Get user's role-based navigation menu
 * @return array Navigation menu items
 */
function getRoleBasedMenu() {
    $role = resolveSessionRole();
    
    $menu = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
    ];
    
    if ($role === ROLE_ADMIN || $role === ROLE_SUPER_ADMIN || $role === ROLE_STAFF) {
        $menu['orders'] = ['icon' => 'fas fa-shopping-cart', 'text' => 'Orders'];
        $menu['customers'] = ['icon' => 'fas fa-users', 'text' => 'Customers'];
        $menu['inventory'] = ['icon' => 'fas fa-boxes', 'text' => 'Inventory'];
        $menu['products'] = ['icon' => 'fas fa-heartbeat', 'text' => 'Products'];
    }
    
    if ($role === ROLE_ADMIN || $role === ROLE_SUPER_ADMIN) {
        $menu['categories'] = ['icon' => 'fas fa-tags', 'text' => 'Categories'];
        $menu['suppliers'] = ['icon' => 'fas fa-truck', 'text' => 'Suppliers'];
        $menu['reports'] = ['icon' => 'fas fa-chart-bar', 'text' => 'Reports'];
        $menu['users'] = ['icon' => 'fas fa-user-cog', 'text' => 'Users'];
        $menu['settings'] = ['icon' => 'fas fa-cog', 'text' => 'Settings'];
        if ($role === ROLE_SUPER_ADMIN) {
            $menu['audit_log'] = ['icon' => 'fas fa-history', 'text' => 'Audit Log'];
        }
    } elseif ($role === ROLE_STAFF) {
        $menu['reports'] = ['icon' => 'fas fa-chart-bar', 'text' => 'Reports'];
    }
    
    return $menu;
}

/**
 * Check if user can delete records
 * @param string $table Table name
 * @return bool True if can delete
 */
function canDeleteRecords($table) {
    if (!isAdmin()) {
        return false;
    }
    
    $deletableTables = [
        'orders',
        'products',
        'customers',
        'categories',
        'suppliers'
    ];
    
    return in_array($table, $deletableTables);
}

/**
 * Check if user can edit records
 * @param string $table Table name
 * @return bool True if can edit
 */
function canEditRecords($table) {
    if (isAdmin()) {
        return true;
    }
    
    if (isStaff()) {
        $editableTables = [
            'orders',
            'products',
            'customers'
        ];
        
        return in_array($table, $editableTables);
    }
    
    return false;
}

/**
 * Check if user can view record details
 * @param string $table Table name
 * @return bool True if can view
 */
function canViewRecords($table) {
    if (isAdmin() || isStaff()) {
        return true;
    }
    
    // Customers can only view their own orders
    if (isCustomer()) {
        return $table === 'orders';
    }
    
    return false;
}
?>
