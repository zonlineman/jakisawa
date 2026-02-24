<?php
// Keep session alive for longer browsing periods
$sessionLifetime = 60 * 60 * 24 * 7; // 7 days

// Apply session INI/cookie params only before session starts
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    if (ini_get('session.use_cookies')) {
        session_set_cookie_params([
            'lifetime' => $sessionLifetime,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// Refresh current session cookie expiry (sliding expiration)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        session_id(),
        [
            'expires' => time() + $sessionLifetime,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => !empty($params['secure']),
            'httponly' => !empty($params['httponly']),
            'samesite' => 'Lax'
        ]
    );
}


// Shop entry path may be /index.php or /index.php depending on deployment layout.
$shopEntryRelative = file_exists(__DIR__ . '/../index.php') ? '../index.php' : '../index.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . $shopEntryRelative);
    exit();
}

// Allow super admin, admin and staff into the dashboard shell
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'staff'], true)) {
    unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_logged_in'], $_SESSION['admin_is_active']);
    header('Location: ' . $shopEntryRelative);
    exit();
}

// Refresh activity timestamp and mirror admin keys to legacy keys used across pages
$_SESSION['last_activity'] = time();
$_SESSION['user_id'] = $_SESSION['admin_id'];
$_SESSION['user_name'] = $_SESSION['admin_name'] ?? 'Administrator';
$_SESSION['user_role'] = $_SESSION['admin_role'];
$_SESSION['role'] = $_SESSION['admin_role'];

$adminRoleNormalized = strtolower((string) ($_SESSION['admin_role'] ?? ''));
$isAdminOnlyUser = in_array($adminRoleNormalized, ['admin', 'super_admin'], true);
$isSuperAdminUser = ($adminRoleNormalized === 'super_admin');



require_once 'includes/database.php'; 
require_once 'includes/functions.php';
require_once 'includes/role_permissions.php'; 
require_once 'includes/order_notifications.php';
require_once 'includes/super_admin_bootstrap.php';

ensureReservedSuperAdminAccount($pdo);

$page = $_GET['page'] ?? 'admin_dashboard';

switch ($page) {
    case 'remedies':
        $page_file = 'pages/remedies.php';
        break;
    case 'add_remedy':
        $page_file = 'pages/add_remedy.php';
        break;
    case 'edit_remedy':
        $page_file = 'pages/edit_remedy.php';
        break;
    case 'remedy_view':
        $page_file = 'pages/remedy_view.php';
        break;
  case 'user_profile':
        $page_file = 'pages/user_profile.php';
        break;
    case 'orders':
        $page_file = 'pages/orders.php';
        break;
    case 'customers':
        if (!$isAdminOnlyUser) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Admins only.'];
            $page = 'dashboard';
            $page_file = 'pages/dashboard.php';
            break;
        }
        $page_file = 'pages/customers.php';
        break;
    case 'user_management':
        if (!$isAdminOnlyUser) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Admins only.'];
            $page = 'dashboard';
            $page_file = 'pages/dashboard.php';
            break;
        }
        $page_file = 'pages/user_management.php';
        break;
    case 'suppliers':
        if (!$isAdminOnlyUser) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Admins only.'];
            $page = 'dashboard';
            $page_file = 'pages/dashboard.php';
            break;
        }
        $page_file = 'pages/suppliers.php';
        break;
    case 'inventory':
        $page_file = 'pages/inventory.php';
        break;
    case 'reports':
        $page_file = 'pages/reports.php';
        break;
    case 'audit_log':
        if (!$isSuperAdminUser) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Super admin only.'];
            $page = 'dashboard';
            $page_file = 'pages/dashboard.php';
            break;
        }
        $page_file = 'pages/audit_log.php';
        break;
    default:
        $page_file = 'pages/dashboard.php';
}

// For page-specific AJAX/stream responses, bypass dashboard layout output.
// This keeps responses clean (e.g., JSON for user details, CSV export).
if (
    $page === 'user_management' &&
    (isset($_GET['ajax']) || (isset($_GET['action']) && $_GET['action'] === 'export_users'))
) {
    if (file_exists($page_file)) {
        include $page_file;
        exit();
    }
}



// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $handledByDashboard = false;

    switch ($action) {
        case 'update_order_status':
            $handledByDashboard = true;
            $conn = getDBConnection();
            $order_id = intval($_POST['order_id']);
            $new_status = $conn->real_escape_string($_POST['status']);
            
            // Valid statuses
            $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status'];
                break;
            }

            $beforeOrder = null;
            $beforeStmt = $conn->prepare("SELECT order_status, payment_status, order_number FROM orders WHERE id = ? LIMIT 1");
            if ($beforeStmt) {
                $beforeStmt->bind_param("i", $order_id);
                $beforeStmt->execute();
                $beforeOrder = $beforeStmt->get_result()->fetch_assoc();
                $beforeStmt->close();
            }
            if (!$beforeOrder) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Order not found'];
                break;
            }
            
            // Update order status
            $query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_status, $order_id);
            
            if ($stmt->execute()) {
                $notifyResult = sendOrderLifecycleNotification(
                    $pdo,
                    $order_id,
                    'status_update',
                    [
                        'order_status' => [
                            'old' => (string)($beforeOrder['order_status'] ?? ''),
                            'new' => $new_status
                        ]
                    ]
                );

                $oldValues = [
                    'order_status' => (string)($beforeOrder['order_status'] ?? ''),
                    'payment_status' => (string)($beforeOrder['payment_status'] ?? ''),
                    'order_number' => (string)($beforeOrder['order_number'] ?? '')
                ];
                $newValues = [
                    'order_status' => $new_status,
                    'payment_status' => (string)($beforeOrder['payment_status'] ?? ''),
                    'order_number' => (string)($beforeOrder['order_number'] ?? ''),
                    'message' => "Order status changed from '" . (string)($beforeOrder['order_status'] ?? 'unknown') . "' to '" . $new_status . "'"
                ];
                logAudit(
                    'order_status_update',
                    'orders',
                    $order_id,
                    $oldValues,
                    $newValues,
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $notifyText = '';
                if (!empty($notifyResult['email']['success']) || !empty($notifyResult['sms']['success'])) {
                    $notifyText = ' Customer notification sent.';
                }
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Order status updated successfully.' . $notifyText];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update order status'];
            }
            $stmt->close();
            break;
            
        case 'update_product_stock':
            $handledByDashboard = true;
            $conn = getDBConnection();
            $product_id = intval($_POST['product_id']);
            $new_stock = floatval($_POST['stock_quantity']);
            
            if ($new_stock < 0) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Stock cannot be negative'];
                break;
            }

            $beforeRemedy = null;
            $beforeStmt = $conn->prepare("SELECT stock_quantity, name, sku FROM remedies WHERE id = ? LIMIT 1");
            if ($beforeStmt) {
                $beforeStmt->bind_param("i", $product_id);
                $beforeStmt->execute();
                $beforeRemedy = $beforeStmt->get_result()->fetch_assoc();
                $beforeStmt->close();
            }
            if (!$beforeRemedy) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Remedy not found'];
                break;
            }
            
            $query = "UPDATE remedies SET stock_quantity = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("di", $new_stock, $product_id);
            
            if ($stmt->execute()) {
                $oldStock = (float)($beforeRemedy['stock_quantity'] ?? 0);
                logAudit(
                    'stock_update',
                    'remedies',
                    $product_id,
                    [
                        'stock_quantity' => $oldStock,
                        'name' => (string)($beforeRemedy['name'] ?? ''),
                        'sku' => (string)($beforeRemedy['sku'] ?? '')
                    ],
                    [
                        'stock_quantity' => $new_stock,
                        'name' => (string)($beforeRemedy['name'] ?? ''),
                        'sku' => (string)($beforeRemedy['sku'] ?? ''),
                        'message' => "Stock quantity changed from {$oldStock} to {$new_stock}"
                    ],
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Stock updated successfully'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update stock'];
            }
            $stmt->close();
            break;
            
        case 'delete_product':
            $handledByDashboard = true;
            $conn = getDBConnection();
            $product_id = intval($_POST['product_id']);

            $beforeRemedy = null;
            $beforeStmt = $conn->prepare("SELECT is_active, name, sku FROM remedies WHERE id = ? LIMIT 1");
            if ($beforeStmt) {
                $beforeStmt->bind_param("i", $product_id);
                $beforeStmt->execute();
                $beforeRemedy = $beforeStmt->get_result()->fetch_assoc();
                $beforeStmt->close();
            }
            if (!$beforeRemedy) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Remedy not found'];
                break;
            }
            
            // Soft delete (mark as inactive)
            $query = "UPDATE remedies SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                logAudit(
                    'remedy_deactivated',
                    'remedies',
                    $product_id,
                    [
                        'is_active' => (int)($beforeRemedy['is_active'] ?? 0),
                        'name' => (string)($beforeRemedy['name'] ?? ''),
                        'sku' => (string)($beforeRemedy['sku'] ?? '')
                    ],
                    [
                        'is_active' => 0,
                        'name' => (string)($beforeRemedy['name'] ?? ''),
                        'sku' => (string)($beforeRemedy['sku'] ?? ''),
                        'message' => "Remedy deactivated from active state " . (int)($beforeRemedy['is_active'] ?? 0) . " to 0"
                    ],
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Product deactivated successfully'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete product'];
            }
            $stmt->close();
            break;
    }

    // Only redirect if dashboard itself handled the action.
    // Otherwise allow included page handlers (e.g. user_management.php) to process POST.
    if ($handledByDashboard) {
        $redirectPage = $_GET['page'] ?? 'dashboard';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . urlencode($redirectPage));
        exit();
    }
}

// Get data for dashboard
$stats = getDashboardStats();
$recent_orders = getRecentOrders(10);
$low_stock_products = getLowStockProducts(10);
$recent_customers = getRecentCustomers(10);
$sales_chart_data = getSalesChartData();
$top_products = getTopProducts(5);

$sidebarUserName = trim((string)($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin'));
$sidebarUserRole = trim((string)($_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? 'User'));
$sidebarUserAvatarUrl = '';

try {
    $sidebarConn = getDBConnection();
    if ($sidebarConn instanceof mysqli) {
        $sidebarUserId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($sidebarUserId > 0) {
            $avatarColumn = '';
            $avatarCandidates = ['avatar_url', 'profile_image', 'image_url'];
            foreach ($avatarCandidates as $candidate) {
                $safeCandidate = $sidebarConn->real_escape_string($candidate);
                $colResult = $sidebarConn->query("SHOW COLUMNS FROM `users` LIKE '{$safeCandidate}'");
                if ($colResult instanceof mysqli_result) {
                    if ($colResult->num_rows > 0) {
                        $avatarColumn = $candidate;
                        $colResult->free();
                        break;
                    }
                    $colResult->free();
                }
            }

            $avatarSql = $avatarColumn !== '' ? ", `{$avatarColumn}` AS avatar_path" : '';
            $sidebarUserQuery = "SELECT full_name, role{$avatarSql} FROM users WHERE id = {$sidebarUserId} LIMIT 1";
            $sidebarUserResult = $sidebarConn->query($sidebarUserQuery);
            if ($sidebarUserResult instanceof mysqli_result) {
                $sidebarRow = $sidebarUserResult->fetch_assoc();
                if (is_array($sidebarRow)) {
                    if (!empty($sidebarRow['full_name'])) {
                        $sidebarUserName = trim((string)$sidebarRow['full_name']);
                    }
                    if (!empty($sidebarRow['role'])) {
                        $sidebarUserRole = trim((string)$sidebarRow['role']);
                    }
                    $rawAvatar = trim((string)($sidebarRow['avatar_path'] ?? ''));
                    if ($rawAvatar !== '') {
                        if (strpos($rawAvatar, '/') === 0) {
                            $sidebarUserAvatarUrl = projectPathUrl($rawAvatar);
                        } elseif (strpos($rawAvatar, 'systemuploads/users/') === 0) {
                            $sidebarUserAvatarUrl = projectPathUrl('/' . $rawAvatar);
                        } elseif (strpos($rawAvatar, '/uploads/') === 0) {
                            $sidebarUserAvatarUrl = systemUrl(ltrim($rawAvatar, '/'));
                        } elseif (strpos($rawAvatar, 'uploads/') === 0) {
                            $sidebarUserAvatarUrl = systemUrl($rawAvatar);
                        } else {
                            $sidebarUserAvatarUrl = projectPathUrl($rawAvatar);
                        }
                    }
                }
                $sidebarUserResult->free();
            }
        }
    }
} catch (Throwable $e) {
    // Keep sidebar rendering using session values when DB lookup fails.
}

$sidebarUserRoleLabel = ucwords(str_replace('_', ' ', strtolower($sidebarUserRole !== '' ? $sidebarUserRole : 'User')));
$sidebarUserInitial = strtoupper(substr($sidebarUserName !== '' ? $sidebarUserName : 'U', 0, 1));


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JAKISAWA SHOP</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap 5 JS (loaded early so page-level inline scripts can use bootstrap.Modal/Tooltip) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        .sidebar {
            background-color: var(--primary-color);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.25s ease;
        }

        .sidebar-backdrop {
            display: none;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: #212529;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.2;
        }

        .mobile-topbar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 62px;
            padding: 10px 14px;
            background: var(--primary-color);
            color: #fff;
            z-index: 1050;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .mobile-topbar-title {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .mobile-menu-toggle {
            border: 0;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
        }

        .mobile-menu-toggle i {
            pointer-events: none;
        }

        body.sidebar-open {
            overflow: hidden;
        }
        
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
            color: var(--secondary-color);
        }
        
        .bg-success-light {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .bg-warning-light {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .bg-danger-light {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .badge-shipped {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-delivered {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .action-btn {
            padding: 3px 8px;
            font-size: 12px;
            margin: 0 2px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 5px;
            margin: 2px 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .user-info {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .profile-avatar-btn {
            border: 0;
            padding: 0;
            background: transparent;
            cursor: zoom-in;
        }

        .profile-image-lightbox {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.75);
            z-index: 4000;
            padding: 24px;
        }

        .profile-image-lightbox.is-open {
            display: flex;
        }

        .profile-image-lightbox img {
            max-width: min(90vw, 900px);
            max-height: 85vh;
            width: auto;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.4);
            object-fit: contain;
        }

        .profile-image-lightbox-close {
            position: absolute;
            top: 14px;
            right: 16px;
            border: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fff;
            color: #111;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }
        
        @media (max-width: 991px) {
            .mobile-topbar {
                display: flex;
            }

            .sidebar {
                width: 270px;
                transform: translateX(-100%);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.28);
            }

            .sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                opacity: 0;
                visibility: hidden;
                z-index: 1035;
                transition: opacity 0.2s ease, visibility 0.2s ease;
                display: block;
            }

            .sidebar-backdrop.show {
                opacity: 1;
                visibility: visible;
            }
            
            .main-content {
                margin-left: 0;
                padding: 86px 14px 16px;
            }
            
            .logo span, .nav-link span {
                display: inline;
            }
            
            .logo {
                font-size: 20px;
                padding: 16px 20px;
                text-align: left;
            }
            
            .nav-link {
                text-align: left;
                padding: 12px 14px;
                margin: 2px 12px;
            }
            
            .nav-link i {
                margin-right: 10px;
                font-size: 18px;
            }

            .user-info {
                padding: 16px;
            }

            .user-info .ms-2 {
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .mobile-topbar {
                padding: 10px 12px;
            }

            .mobile-topbar-title {
                font-size: 0.95rem;
            }

            .main-content {
                padding: 82px 10px 14px;
            }

            .page-title {
                font-size: 1.2rem;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <header class="mobile-topbar">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open sidebar menu" aria-controls="adminSidebar" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div class="mobile-topbar-title">JAKISAWA SHOP</div>
    </header>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column" id="adminSidebar">
        <div class="logo">
            <i class="bi bi-shop"></i>
            <span>JAKISAWA SHOP</span>
        </div>
     
     <?php

?>
<nav class="nav flex-column flex-grow-1">
    <a href="?page=dashboard" class="nav-link <?= $page==='dashboard'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>
    <a href="?page=remedies" class="nav-link <?= $page === 'remedies' ? 'active' : '' ?>">

        <i class="bi bi-box-seam"></i>
        <span>Remedies</span>
    </a>
  <a href="?page=orders" class="nav-link <?= $page==='orders'?'active':'' ?>">
    <i class="bi bi-cart-check"></i>
    <span>Orders</span>
</a>

   <?php if ($isAdminOnlyUser): ?>
   <a href="?page=customers" class="nav-link <?= $page === 'customers' ? 'active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Customers</span>
    </a>
   <?php endif; ?>
   
   <?php if ($isAdminOnlyUser): ?>
   <a href="?page=suppliers" class="nav-link <?= $page === 'suppliers' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i>
        <span>Suppliers</span>
    </a>
   <?php endif; ?>
   <a href="?page=inventory" class="nav-link <?= $page === 'inventory' ? 'active' : '' ?>">
        <i class="bi bi-clipboard-data"></i>
        <span>Inventory</span>
    </a>
   <a href="?page=reports" class="nav-link <?= $page === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i>
        <span>Reports</span>
    </a>
   <?php if ($isAdminOnlyUser): ?>
   <a href="?page=user_management" class="nav-link <?= $page === 'user_management' ? 'active' : '' ?>">
    <i class="bi bi-person-gear"></i>  <!-- Person with settings -->
    <span>Manage Users</span>
   </a>
   <?php endif; ?>
    <a href="?page=user_profile" class="nav-link <?= $page==='user_profile'?'active':'' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Manage Profile</span>
    </a>
   <?php if ($isSuperAdminUser): ?>
   <a href="?page=audit_log" class="nav-link <?= $page === 'audit_log' ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i>
        <span>Audit Log</span>
    </a>
   <?php endif; ?>
</nav>  
        <div class="user-info">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px; font-weight: bold; overflow: hidden;">
                    <?php if ($sidebarUserAvatarUrl !== ''): ?>
                        <button type="button" class="profile-avatar-btn" onclick="openProfileImageLightbox('<?php echo htmlspecialchars($sidebarUserAvatarUrl, ENT_QUOTES); ?>')" aria-label="Open profile image">
                            <img src="<?php echo htmlspecialchars($sidebarUserAvatarUrl); ?>" alt="Profile Photo" style="width:100%;height:100%;object-fit:cover;">
                        </button>
                    <?php else: ?>
                        <?php echo htmlspecialchars($sidebarUserInitial); ?>
                    <?php endif; ?>
                </div>
                <div class="ms-2">
                    <div class="small"><?php echo htmlspecialchars($sidebarUserName !== '' ? $sidebarUserName : 'Admin'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($sidebarUserRoleLabel); ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-sm btn-outline-light w-100 mt-3">
                <i class="bi bi-box-arrow-right"></i>
                <span>Exit</span>
            </a>
        </div>
    </div>
    
    
    <!-- Main Content -->
    
        <div class="main-content">
    <?php
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        echo "<h3>Page not found</h3>";
    }
    ?>
</div>

    <?php
    $iconTooltipsUrl = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/js/icon-tooltips.js';
    ?>
    <script src="<?php echo htmlspecialchars($iconTooltipsUrl, ENT_QUOTES); ?>?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/assets/js/icon-tooltips.js')); ?>"></script>
    <div id="profileImageLightbox" class="profile-image-lightbox" onclick="closeProfileImageLightbox(event)">
        <button type="button" class="profile-image-lightbox-close" aria-label="Close" onclick="closeProfileImageLightbox(event)">&times;</button>
        <img id="profileImageLightboxImg" src="" alt="Profile image preview">
    </div>
    <script>
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const mobileSidebarBreakpoint = window.matchMedia('(max-width: 991px)');

        function setMobileSidebarState(isOpen) {
            if (!mobileMenuToggle || !adminSidebar || !sidebarBackdrop) return;

            adminSidebar.classList.toggle('is-open', isOpen);
            sidebarBackdrop.classList.toggle('show', isOpen);
            document.body.classList.toggle('sidebar-open', isOpen);
            mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            mobileMenuToggle.setAttribute('aria-label', isOpen ? 'Close sidebar menu' : 'Open sidebar menu');

            const icon = mobileMenuToggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-list', !isOpen);
                icon.classList.toggle('bi-x-lg', isOpen);
            }
        }

        function closeMobileSidebar() {
            setMobileSidebarState(false);
        }

        if (mobileMenuToggle && adminSidebar && sidebarBackdrop) {
            mobileMenuToggle.addEventListener('click', function () {
                const shouldOpen = !adminSidebar.classList.contains('is-open');
                setMobileSidebarState(shouldOpen);
            });

            sidebarBackdrop.addEventListener('click', closeMobileSidebar);

            adminSidebar.querySelectorAll('.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (mobileSidebarBreakpoint.matches) {
                        closeMobileSidebar();
                    }
                });
            });

            window.addEventListener('resize', function () {
                if (!mobileSidebarBreakpoint.matches) {
                    closeMobileSidebar();
                }
            });
        }

        closeMobileSidebar();

        function openProfileImageLightbox(imageUrl) {
            if (!imageUrl) return;
            const lightbox = document.getElementById('profileImageLightbox');
            const image = document.getElementById('profileImageLightboxImg');
            if (!lightbox || !image) return;
            image.src = imageUrl;
            lightbox.classList.add('is-open');
        }

        function closeProfileImageLightbox(event) {
            const lightbox = document.getElementById('profileImageLightbox');
            const image = document.getElementById('profileImageLightboxImg');
            if (!lightbox || !image) return;

            if (event && event.target && event.target !== lightbox && !event.target.classList.contains('profile-image-lightbox-close')) {
                return;
            }

            lightbox.classList.remove('is-open');
            image.src = '';
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeProfileImageLightbox();
                closeMobileSidebar();
            }
        });
    </script>
    
</body>
</html>
