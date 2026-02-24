<?php
// admin/includes/header.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // Ensure all required session variables are set
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     // Redirect to login if not authenticated
//     header('Location: .../admin_login.php');
//     exit();
// }

// Set default role if not set (for backward compatibility)
if (!isset($_SESSION['role'])) {
    // Try to get role from session variables
    if (isset($_SESSION['admin_role'])) {
        $_SESSION['role'] = $_SESSION['admin_role'];
    } elseif (isset($_SESSION['user_role'])) {
        $_SESSION['role'] = $_SESSION['user_role'];
    } else {
        // Default role for safety
        $_SESSION['role'] = 'customer';
        error_log("Role not set in session, defaulting to 'customer'");
    }
}

// Set full_name if not set
if (!isset($_SESSION['full_name'])) {
    if (isset($_SESSION['admin_name'])) {
        $_SESSION['full_name'] = $_SESSION['admin_name'];
    } elseif (isset($_SESSION['user_full_name'])) {
        $_SESSION['full_name'] = $_SESSION['user_full_name'];
    } else {
        $_SESSION['full_name'] = 'User';
    }
}

// Set username if not set
if (!isset($_SESSION['username'])) {
    if (isset($_SESSION['admin_username'])) {
        $_SESSION['username'] = $_SESSION['admin_username'];
    } else {
        $_SESSION['username'] = 'user';
    }
}

// Set email if not set
if (!isset($_SESSION['email'])) {
    if (isset($_SESSION['admin_email'])) {
        $_SESSION['email'] = $_SESSION['admin_email'];
    } else {
        $_SESSION['email'] = '';
    }
}

require_once 'auth_functions.php';
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';

/**
 * Get pending orders count - UPDATED FOR YOUR DATABASE
 */
function getPendingOrdersCount() {
    if (!canAccessPage('orders')) return 0;
    
    try {
        $conn = getDBConnection();
        // Using your 'orders' table with 'payment_status' field
        $query = "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            error_log("Query failed: " . mysqli_error($conn));
            return 0;
        }
        
        $data = mysqli_fetch_assoc($result);
        return $data['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting pending orders count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get low stock count - UPDATED FOR YOUR DATABASE
 */
function getLowStockCount() {
    if (!canAccessPage('inventory')) return 0;
    
    try {
        $conn = getDBConnection();
        // Using your 'products' table with correct field names
        $query = "SELECT COUNT(*) as count FROM products 
                  WHERE stock_quantity <= reorder_level 
                  AND stock_quantity > 0 
                  AND is_active = 1";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            error_log("Query failed: " . mysqli_error($conn));
            return 0;
        }
        
        $data = mysqli_fetch_assoc($result);
        return $data['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting low stock count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get pending users count - UPDATED FOR YOUR DATABASE
 */
function getPendingUsersCount() {
    // Use the session role variable that's now properly set
    if (!isAdmin()) return 0;
    
    try {
        $conn = getDBConnection();
        // Using your 'users' table with 'status' field (active/inactive)
        $query = "SELECT COUNT(*) as count FROM users WHERE status = 'inactive'";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            error_log("Query failed: " . mysqli_error($conn));
            return 0;
        }
        
        $data = mysqli_fetch_assoc($result);
        return $data['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting pending users count: " . $e->getMessage());
        return 0;
    }
}


// Get the requested page
$page = $_GET['page'] ?? 'dashboard';

// Define valid pages and their corresponding files
$validPages = [
    'dashboard' => 'pages/dashboard.php',
    'orders' => 'pages/orders.php',
    'customers' => 'pages/customers.php',
    'inventory' => 'pages/inventory.php',
    'products' => 'pages/products.php',
    'categories' => 'pages/categories.php',
    'suppliers' => 'pages/suppliers.php',
    'reports' => 'pages/reports.php',
    'users' => 'pages/users.php',
    'audit_log' => 'pages/audit_log.php',
    'settings' => 'pages/settings.php',
    'profile' => 'pages/profile.php'
];

// Check if page exists and user has access
if (isset($validPages[$page])) {
    $pageFile = $validPages[$page];
    
    // Check if file exists
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo '<div class="alert alert-danger">Page file not found: ' . $pageFile . '</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid page requested: ' . $page . '</div>';
}


/**
 * Get user's current page title
 */
function getPageTitle() {
    $currentPage = $_GET['page'] ?? 'dashboard';
    $pageTitles = [
        'dashboard' => 'Dashboard Overview',
        'orders' => 'Orders Management',
        'customers' => 'Customer Management',
        'inventory' => 'Inventory Management',
        'reports' => 'Reports & Analytics',
        'products' => 'Products Management',
        'categories' => 'Categories Management',
        'suppliers' => 'Suppliers Management',
        'audit_log' => 'Audit Logs',
        'settings' => 'System Settings',
        'users' => 'User Management',
        'profile' => 'My Profile'
    ];
    
    return $pageTitles[$currentPage] ?? 'Dashboard';
}

/**
 * Get user's current page icon
 */
function getPageIcon() {
    $currentPage = $_GET['page'] ?? 'dashboard';
    $pageIcons = [
        'dashboard' => 'fas fa-tachometer-alt',
        'orders' => 'fas fa-shopping-cart',
        'customers' => 'fas fa-users',
        'inventory' => 'fas fa-boxes',
        'reports' => 'fas fa-chart-bar',
        'products' => 'fas fa-heartbeat',
        'categories' => 'fas fa-tags',
        'suppliers' => 'fas fa-truck',
        'audit_log' => 'fas fa-history',
        'settings' => 'fas fa-cog',
        'users' => 'fas fa-user-cog',
        'profile' => 'fas fa-user'
    ];
    
    return $pageIcons[$currentPage] ?? 'fas fa-tachometer-alt';
}

// Check authentication
requireAuth();

// Get current page
$currentPage = $_GET['page'] ?? 'dashboard';

// Check page access - use the properly set session role
if (!canAccessPage($currentPage)) {
    logActivity('access_denied', "Attempted to access restricted page: $currentPage");
    showAccessDenied();
}

// Get counts for badges
$pendingOrders = canAccessPage('orders') ? getPendingOrdersCount() : 0;
$lowStock = canAccessPage('inventory') ? getLowStockCount() : 0;
$pendingUsers = isAdmin() ? getPendingUsersCount() : 0;

// Get sidebar menu
$sidebarMenu = getSidebarMenu();

// Get user role and name from session (now properly set)
$userRole = $_SESSION['role'] ?? 'customer';
$userFullName = $_SESSION['full_name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$userUsername = $_SESSION['username'] ?? 'user';

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getPageTitle(); ?> - <?php echo SITE_NAME; ?> Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Admin Styles -->
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #4caf50;
            --primary-dark: #1b5e20;
            --accent: #ff9800;
            --light: #f1f8e9;
            --dark: #333;
            --danger: #d32f2f;
            --success: #388e3c;
            --warning: #f57c00;
            --info: #1976d2;
            --radius: 8px;
        }

        body {
            background: #f8f9fa;
            color: var(--dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, #1a472a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            top: 0;
            left: 0;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            opacity: 0;
            visibility: hidden;
            z-index: 999;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            position: relative;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent);
        }

        .menu-item .badge {
            position: absolute;
            right: 15px;
            font-size: 0.7rem;
            padding: 3px 6px;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 1.6rem;
            color: var(--primary-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive Design */
        @media (max-width: 991px) {
            .sidebar {
                width: 270px;
                transform: translateX(-100%);
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                display: block;
            }

            .sidebar-backdrop.show {
                opacity: 1;
                visibility: visible;
            }
            
            .main-content {
                margin-left: 0;
                padding: 82px 14px 16px;
            }
            
            .top-bar {
                padding: 14px;
                gap: 12px;
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .mobile-menu-toggle {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 78px 10px 12px;
            }
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: var(--light);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            border-radius: var(--radius) var(--radius) 0 0 !important;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: var(--radius);
            color: white;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 1001;
            animation: slideInRight 0.3s ease;
            max-width: 400px;
        }

        .notification.success { background: var(--success); }
        .notification.error { background: var(--danger); }
        .notification.warning { background: var(--warning); }
        .notification.info { background: var(--info); }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Badge Variations */
        .badge-pending { background: var(--warning); }
        .badge-processing { background: var(--info); }
        .badge-completed { background: var(--success); }
        .badge-cancelled { background: var(--danger); }

        /* Loading Spinner */
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* User Profile in Sidebar */
        .user-profile {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            font-size: 1.1rem;
        }

        .mobile-menu-toggle i {
            pointer-events: none;
        }

        body.sidebar-open {
            overflow: hidden;
        }
        
        /* Role-based styling */
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-admin { background: var(--danger); color: white; }
        .role-staff { background: var(--warning); color: white; }
        .role-customer { background: var(--info); color: white; }
        .role-customer { background: var(--success); color: white; }
    </style>
    
    <!-- Page-specific CSS -->
    <?php if (file_exists("css/{$currentPage}.css")): ?>
        <link rel="stylesheet" href="css/<?php echo $currentPage; ?>.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open sidebar menu" aria-controls="sidebar" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="fas fa-leaf"></i>
                <span><?php echo SITE_NAME; ?></span>
            </h2>
        </div>
        
        <nav class="sidebar-menu">
            <a href="?page=dashboard" class="menu-item <?php echo $currentPage == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <?php if (canAccessPage('orders')): ?>
            <a href="?page=orders" class="menu-item <?php echo $currentPage == 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <?php if ($pendingOrders > 0): ?>
                    <span class="badge bg-danger"><?php echo $pendingOrders; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('customers')): ?>
            <a href="?page=customers" class="menu-item <?php echo $currentPage == 'customers' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('inventory')): ?>
            <a href="?page=inventory" class="menu-item <?php echo $currentPage == 'inventory' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
                <?php if ($lowStock > 0): ?>
                    <span class="badge bg-warning"><?php echo $lowStock; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('products')): ?>
            <a href="?page=products" class="menu-item <?php echo $currentPage == 'products' ? 'active' : ''; ?>">
                <i class="fas fa-heartbeat"></i>
                <span>Products</span>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('categories')): ?>
            <a href="?page=categories" class="menu-item <?php echo $currentPage == 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('suppliers')): ?>
            <a href="?page=suppliers" class="menu-item <?php echo $currentPage == 'suppliers' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i>
                <span>Suppliers</span>
            </a>
            <?php endif; ?>
            
            <?php if (canAccessPage('reports')): ?>
            <a href="?page=reports" class="menu-item <?php echo $currentPage == 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
                <a href="?page=users" class="menu-item <?php echo $currentPage == 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Users</span>
                    <?php if ($pendingUsers > 0): ?>
                        <span class="badge bg-danger"><?php echo $pendingUsers; ?></span>
                    <?php endif; ?>
                </a>
                
                <?php if (strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '')) === 'super_admin'): ?>
                <a href="?page=audit_log" class="menu-item <?php echo $currentPage == 'audit_log' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Audit Log</span>
                </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (canAccessPage('settings')): ?>
            <a href="?page=settings" class="menu-item <?php echo $currentPage == 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <?php endif; ?>
            
            <!-- User Profile Section -->
            <div class="user-profile">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userFullName, 0, 1)); ?>
                    </div>
                    <div style="flex: 1; ">
                        <div style="font-weight: 600; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($userFullName); ?>
                        </div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">
                            <span class="role-badge role-<?php echo $userRole; ?>">
                                <?php echo ucfirst($userRole); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="?page=profile" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-user me-1"></i> Profile
                    </a>
                    <a href="../logout.php" class="btn btn-sm btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                    
                </div>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="<?php echo getPageIcon(); ?>"></i>
                <?php echo getPageTitle(); ?>
            </h1>
            
            <div class="top-bar-actions">
                <div class="d-flex gap-2">
                    <button class="btn btn-info btn-sm" onclick="location.reload()" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <?php if ($userRole === 'admin'): ?>
                        <button class="btn btn-warning btn-sm" onclick="exportData('<?php echo $currentPage; ?>')" title="Export Data">
                            <i class="fas fa-download"></i>
                        </button>
                    <?php endif; ?>
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?page=profile"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <?php if ($userRole === 'admin'): ?>
                                <li><a class="dropdown-item" href="?page=settings"><i class="fas fa-cogs me-2"></i>Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="notification <?php echo $_SESSION['message']['type']; ?>">
                <i class="fas <?php 
                    switch($_SESSION['message']['type']) {
                        case 'success': echo 'fa-check-circle'; break;
                        case 'error': echo 'fa-exclamation-circle'; break;
                        case 'warning': echo 'fa-exclamation-triangle'; break;
                        default: echo 'fa-info-circle';
                    }
                ?> me-2"></i>
                <?php echo $_SESSION['message']['text']; ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Page Content -->
        <div id="page-content">
        <?php
        // Content will be included here
        ?>
        </div>
        
        <!-- Footer -->
        <footer class="mt-5 pt-4 border-top">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted">
                        <small>
                            <i class="fas fa-shield-alt me-1"></i>
                            Logged in as: <?php echo htmlspecialchars($userFullName); ?> 
                            (<span class="role-badge role-<?php echo $userRole; ?>">
                                <?php echo ucfirst($userRole); ?>
                            </span>)
                        </small>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">
                        <small>
                            <i class="fas fa-clock me-1"></i>
                            Server Time: <?php echo date('Y-m-d H:i:s'); ?>
                            | <?php echo SITE_NAME; ?> Admin v1.0
                        </small>
                    </p>
                </div>
            </div>
        </footer>
        <?php
echo "Current dir: " . __DIR__ . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
?>
    </main>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const smallScreenQuery = window.matchMedia('(max-width: 991px)');

        function setSidebarOpen(isOpen) {
            if (!mobileMenuToggle || !sidebar || !sidebarBackdrop) return;

            sidebar.classList.toggle('show', isOpen);
            sidebarBackdrop.classList.toggle('show', isOpen);
            document.body.classList.toggle('sidebar-open', isOpen);
            mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            mobileMenuToggle.setAttribute('aria-label', isOpen ? 'Close sidebar menu' : 'Open sidebar menu');

            const icon = mobileMenuToggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars', !isOpen);
                icon.classList.toggle('fa-times', isOpen);
            }
        }

        function closeSidebarMenu() {
            setSidebarOpen(false);
        }

        if (mobileMenuToggle && sidebar && sidebarBackdrop) {
            mobileMenuToggle.addEventListener('click', function() {
                const shouldOpen = !sidebar.classList.contains('show');
                setSidebarOpen(shouldOpen);
            });

            sidebarBackdrop.addEventListener('click', closeSidebarMenu);

            document.querySelectorAll('.sidebar .menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (smallScreenQuery.matches) {
                        closeSidebarMenu();
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (!smallScreenQuery.matches) {
                    closeSidebarMenu();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeSidebarMenu();
                }
            });
        }

        closeSidebarMenu();

        // Auto-hide notifications
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);

        // Initialize tooltips
        $(document).ready(function() {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                document.querySelectorAll('[title]').forEach(function (el) {
                    const toggle = (el.getAttribute('data-bs-toggle') || '').toLowerCase();
                    if (toggle && toggle !== 'tooltip' && toggle !== 'popover') {
                        return;
                    }
                    window.bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }
            
            // Initialize DataTables if table exists
            if ($.fn.DataTable.isDataTable('.data-table')) {
                $('.data-table').DataTable({
                    pageLength: 25,
                    responsive: true,
                    order: [[0, 'desc']]
                });
            }
        });

        // Export data function
        function exportData(type) {
            const params = new URLSearchParams(window.location.search);
            const url = `export.php?type=${type}&${params.toString()}`;
            window.open(url, '_blank');
        }

        // Refresh page data
        function refreshData() {
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Check for session timeout
        let idleTime = 0;
        setInterval(function() {
            idleTime++;
            if (idleTime > 30) { // 30 minutes
                alert('Session timeout due to inactivity. You will be logged out.');
                window.location.href = '../logout.php';
            }
        }, 60000); // Check every minute

        // Reset idle time on user activity
        document.addEventListener('mousemove', resetIdleTime);
        document.addEventListener('keypress', resetIdleTime);

        function resetIdleTime() {
            idleTime = 0;
        }

        // Role-based UI adjustments
        document.addEventListener('DOMContentLoaded', function() {
            const userRole = '<?php echo $userRole; ?>';
            
            // Hide admin-only elements for non-admin users
            if (userRole !== 'admin') {
                document.querySelectorAll('.admin-only').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });
    </script>
    
    <!-- Page-specific JavaScript -->
    <?php if (file_exists("js/{$currentPage}.js")): ?>
        <script src="js/<?php echo $currentPage; ?>.js"></script>
    <?php endif; ?>

    <script>
// Debug: Track all link clicks
document.addEventListener('click', function(e) {
    if (e.target.closest('.menu-item')) {
        const link = e.target.closest('.menu-item');
        console.log('Menu clicked:', {
            href: link.href,
            text: link.textContent.trim(),
            url: window.location.href
        });
    }
});

// Debug: Log current page info
console.log('Current page info:', {
    currentPage: '<?php echo $currentPage; ?>',
    userRole: '<?php echo $userRole; ?>',
    canAccessPage: '<?php echo canAccessPage($currentPage) ? "YES" : "NO"; ?>',
    url: window.location.href,
    pathname: window.location.pathname
});
</script>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();

?>
