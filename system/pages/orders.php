<?php
// C:\xampp\htdocs\JAKISAWA\system\pages\orders.php

// Define absolute path
define('BASE_PATH', dirname(__DIR__));
require_once dirname(__DIR__, 2) . '/config/paths.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', SYSTEM_BASE_URL);
}


// Include required files
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/role_permissions.php';


// Get user info
$user_id = $_SESSION['admin_id'] ?? 0;
$user_name = $_SESSION['admin_name'] ?? 'Admin';
$user_role = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'staff');
$normalizedUserRole = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $user_role)));
if ($normalizedUserRole === 'superadmin') {
    $normalizedUserRole = 'super_admin';
}
$isOrderAdmin = in_array($normalizedUserRole, ['admin', 'super_admin'], true);



// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$order_status = $_GET['order_status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        o.order_number LIKE :search_order
        OR CAST(o.id AS CHAR) LIKE :search_id
        OR o.customer_name LIKE :search_name
        OR o.customer_email LIKE :search_email
        OR o.customer_phone LIKE :search_phone
        OR o.payment_status LIKE :search_payment
        OR o.order_status LIKE :search_order_status
        OR EXISTS (
            SELECT 1
            FROM order_items oi2
            WHERE oi2.order_id = o.id
              AND (oi2.product_name LIKE :search_product_name OR oi2.product_sku LIKE :search_product_sku)
        )
    )";
    $searchTerm = '%' . $search . '%';
    $params[':search_order'] = $searchTerm;
    $params[':search_id'] = $searchTerm;
    $params[':search_name'] = $searchTerm;
    $params[':search_email'] = $searchTerm;
    $params[':search_phone'] = $searchTerm;
    $params[':search_payment'] = $searchTerm;
    $params[':search_order_status'] = $searchTerm;
    $params[':search_product_name'] = $searchTerm;
    $params[':search_product_sku'] = $searchTerm;
}

if ($status !== '') {
    $where[] = "o.payment_status = :status";
    $params[':status'] = $status;
}

if ($order_status !== '') {
    $where[] = "o.order_status = :order_status";
    $params[':order_status'] = $order_status;
}

if ($start_date !== '') {
    $where[] = "DATE(o.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}

if ($end_date !== '') {
    $where[] = "DATE(o.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
try {
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.id) as total FROM orders o $whereClause");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    $pages = max(1, (int)ceil($total / $limit));
    
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               (SELECT SUM(total_price) FROM order_items WHERE order_id = o.id) as items_total
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $whereClause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate filtered revenue
    $filteredRevenue = 0;
    foreach ($orders as $order) {
        $filteredRevenue += $order['total_amount'] ?? 0;
    }
    
} catch (PDOException $e) {
    $orders = [];
    $total = 0;
    $pages = 1;
    $filteredRevenue = 0;
    $error = "Database error: " . $e->getMessage();
}

// Get OVERALL statistics (not just filtered)
try {
    $statsQuery = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_sales,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
            COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN order_status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
            COUNT(*) as total_sales
        FROM orders
    ");
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_orders' => 0,
        'total_sales' => 0,
        'today_sales' => 0,
        'total_revenue' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'paid_orders' => 0,
        'pending_payments' => 0
    ];
}

// Get status counts for current filtered results
$statusCounts = [];
$paymentStatusCounts = [];
foreach ($orders as $order) {
    $orderStatus = $order['order_status'] ?? 'pending';
    $paymentStatus = $order['payment_status'] ?? 'pending';
    
    $statusCounts[$orderStatus] = ($statusCounts[$orderStatus] ?? 0) + 1;
    $paymentStatusCounts[$paymentStatus] = ($paymentStatusCounts[$paymentStatus] ?? 0) + 1;
}

// Set page title
$page_title = "Orders Management - JAKISAWA SHOP Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ff0054;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            min-height: 100vh;
            display: flex;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--accent);
        }
        
        .menu-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: white;
        }
        
        .menu-item i {
            width: 24px;
            margin-right: 12px;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
            position: relative;
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
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.sales { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.today { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.pending { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
        .stat-icon.revenue { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        
        .stat-info h6 {
            margin: 0;
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .stat-info h3 {
            margin: 5px 0 0;
            color: var(--dark);
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .bg-paid { background: #d4edda; color: #155724; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-cancelled { background: #f8d7da; color: #721c24; }
        .bg-refunded { background: #d1ecf1; color: #0c5460; }
        .bg-processing { background: #cfe2ff; color: #084298; }
        .bg-completed { background: #d1e7dd; color: #0f5132; }
        
        .status-selector {
            width: 100%;
            min-width: 92px;
            max-width: 124px;
            font-size: 0.82rem;
            line-height: 1.2;
            padding-right: 1.5rem;
        }

        #ordersFilterForm .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        #ordersFilterForm .select2-container .select2-selection--single {
            min-height: 38px;
            display: flex;
            align-items: center;
            padding: 0.2rem 0.35rem;
            font-size: 0.88rem;
        }

        #ordersFilterForm .select2-container .select2-selection__rendered {
            width: 100%;
            padding-right: 1.35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.45rem;
        }

        #ordersFilterForm .select2-container .select2-selection__arrow {
            right: 0.35rem;
        }

        #ordersFilterForm .select2-dropdown {
            max-width: min(260px, calc(100vw - 16px));
        }

        #ordersFilterForm .select2-results__option {
            font-size: 0.86rem;
            padding: 0.35rem 0.55rem;
        }
        
        .bulk-actions {
            background: var(--primary);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        
        .bulk-actions.active {
            display: flex;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
        }
        
        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .btn-filter {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .btn-filter:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .btn-view { background: #0dcaf0; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; }
        .btn-print { background: #6c757d; }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #eef2f7;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .summary-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
        }
        
        .summary-content {
            flex: 1;
        }
        
        .summary-label {
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .summary-subtext {
            border-top: 1px solid #eef2f7;
            padding-top: 12px;
            margin-top: 12px;
        }
        
        .badge-dot {
            flex-shrink: 0;
        }
        
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: #dee2e6 #f8f9fa;
        }
        
        .table-responsive::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        
        .table-responsive::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 2px;
        }
        
        .table-responsive::-webkit-scrollbar-thumb {
            background: #dee2e6;
            border-radius: 2px;
        }
        
        .progress {
            border-radius: 3px;
            background-color: #eef2f7;
        }
        
        .summary-row {
            display: flex;
            flex-wrap: wrap;
        }
        
        .summary-row > .col-md-4 {
            display: flex;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .content-card {
                box-shadow: none;
            }
        }

        /* Professional cards override */
        .stats-grid {
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat-card {
            border: 1px solid #e9edf3;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            min-height: 128px;
            padding: 18px 20px;
            align-items: flex-start;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.09);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            margin-right: 14px;
            border-radius: 12px;
            font-size: 20px;
            flex: 0 0 auto;
        }

        .stat-info h6 {
            margin: 0;
            color: #64748b;
            font-size: 0.82rem;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .stat-info h3 {
            margin: 8px 0 0;
            color: #0f172a;
            line-height: 1.1;
            font-size: clamp(1.35rem, 2vw, 1.95rem);
            font-weight: 800;
        }

        .stat-trend {
            margin-top: 8px;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 500;
        }

        .summary-card {
            border: 1px solid #e9edf3;
            border-radius: 14px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            padding: 18px;
            align-items: flex-start;
            min-height: 250px;
            width: 100%;
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.09);
        }

        .summary-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            margin-right: 14px;
            font-size: 21px;
            flex: 0 0 auto;
        }

        .summary-value {
            font-size: clamp(1.35rem, 2vw, 2rem);
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .summary-row .summary-content {
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
        }

        .summary-subtext {
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px solid #edf1f6;
        }

        .summary-row .summary-subtext {
            margin-top: auto;
            min-height: 92px;
            max-height: 92px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .summary-row .summary-subtext .table-responsive {
            max-height: none !important;
        }

        .summary-subtext .table {
            margin-bottom: 0;
        }

        @media (max-width: 991px) {
            .summary-row > .col-md-4 {
                margin-bottom: 12px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                min-height: auto;
            }

            .summary-card {
                min-height: auto;
            }

            .summary-row .summary-subtext {
                min-height: 0;
                max-height: none;
            }

            .status-selector {
                min-width: 84px;
                max-width: 108px;
                font-size: 0.78rem;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>

        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                Orders Management
            </h1>
            <div class="btn-toolbar">
                <?php if (hasPermission('export_orders')): ?>
                <button class="btn btn-sm btn-outline-secondary me-2" onclick="showExportModal()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
                <?php endif; ?>
                <?php if (hasPermission('create_orders')): ?>
                <button class="btn btn-sm btn-primary" onclick="createNewOrder()">
                    <i class="fas fa-plus me-1"></i> New Order
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon sales">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h6>Total Orders</h6>
                    <h3><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span>All time</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h6>Today's Orders</h6>
                    <h3><?php echo number_format($stats['today_sales'] ?? 0); ?></h3>
                    <div class="stat-trend">
                        <i class="fas fa-clock"></i>
                        <span>Last 24 hours</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h6>Pending Payment</h6>
                    <h3><?php echo number_format($stats['pending_payments'] ?? 0); ?></h3>
                    <div class="stat-trend">
                        <i class="fas fa-exclamation-circle text-warning"></i>
                        <span class="text-warning">Requires attention</span>
                    </div>
                </div>
                <?php if (($stats['pending_payments'] ?? 0) > 0): ?>
                <div class="stat-notification">
                    <span class="notification-dot" style="width: 10px; height: 10px; background: #f72585; border-radius: 50%; display: block; animation: pulse 2s infinite;"></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h6>Total Revenue</h6>
                    <h3><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span class="text-success">All time</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row mb-4 summary-row">
            <!-- Card 1: Total Orders -->
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon bg-primary">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label text-uppercase text-muted small fw-bold mb-1">Total Orders Found</div>
                        <div class="summary-value display-6 fw-bold mb-2"><?php echo number_format($total ?? 0); ?></div>
                        <div class="summary-subtext">
                            <div class="table-responsive" style="max-height: 70px;">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="p-0 pe-2" style="width: 50%;">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge-dot bg-primary me-2" 
                                                          style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                    <small class="text-truncate">
                                                        <span class="text-muted">Page:</span>
                                                        <span class="fw-bold ms-1"><?php echo $page; ?>/<?php echo $pages; ?></span>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="p-0" style="width: 50%;">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge-dot bg-secondary me-2" 
                                                          style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                    <small class="text-truncate">
                                                        <span class="text-muted">Per Page:</span>
                                                        <span class="fw-bold ms-1"><?php echo $limit; ?></span>
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($pages > 1): ?>
                                        <tr>
                                            <td colspan="2" class="p-0 pt-2">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Showing <?php echo number_format(min($limit, $total - ($page-1)*$limit)); ?> of <?php echo number_format($total); ?> orders
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Filtered Revenue -->
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon bg-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label text-uppercase text-muted small fw-bold mb-1">Filtered Revenue</div>
                        <div class="summary-value display-6 fw-bold mb-2 text-success"><?php echo formatCurrency($filteredRevenue); ?></div>
                        <div class="summary-subtext">
                            <div class="table-responsive" style="max-height: 70px;">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="p-0 pe-2" style="width: 50%;">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge-dot bg-success me-2" 
                                                          style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                    <small class="text-truncate">
                                                        <span class="text-muted">Avg. Order:</span>
                                                        <span class="fw-bold ms-1 text-success">
                                                            <?php 
                                                            $avgOrder = count($orders) > 0 ? $filteredRevenue / count($orders) : 0;
                                                            echo formatCurrency($avgOrder);
                                                            ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="p-0" style="width: 50%;">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge-dot bg-info me-2" 
                                                          style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                    <small class="text-truncate">
                                                        <span class="text-muted">Orders:</span>
                                                        <span class="fw-bold ms-1"><?php echo count($orders); ?></span>
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php if ($total > 0): ?>
                                        <tr>
                                            <td colspan="2" class="p-0 pt-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 me-2">
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-success" 
                                                                 style="width: <?php echo min(100, (count($orders) / $total) * 100); ?>%">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted nowrap">
                                                        <?php echo round((count($orders) / $total) * 100, 1); ?>%
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 3: Status Categories -->
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon bg-info">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-label text-uppercase text-muted small fw-bold mb-1">Status Categories</div>
                        <div class="summary-value display-6 fw-bold mb-2 text-info"><?php echo count($statusCounts); ?></div>
                        <div class="summary-subtext">
                            <div class="table-responsive" style="max-height: 110px;">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <?php 
                                        $rows = ceil(count($statusCounts) / 2);
                                        $statusItems = array_values($statusCounts);
                                        $statusKeys = array_keys($statusCounts);
                                        
                                        for ($row = 0; $row < $rows; $row++): 
                                        ?>
                                        <tr>
                                            <?php for ($col = 0; $col < 2; $col++): 
                                                $index = ($row * 2) + $col;
                                                if (isset($statusKeys[$index])):
                                                    $statusKey = $statusKeys[$index];
                                                    $count = $statusItems[$index];
                                            ?>
                                            <td class="p-0 pe-2" style="width: 50%;">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge-dot bg-<?php echo getStatusColor($statusKey); ?> me-2" 
                                                          style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                    <small class="text-truncate">
                                                        <span class="text-muted"><?php echo ucfirst($statusKey); ?>:</span>
                                                        <span class="fw-bold ms-1"><?php echo $count; ?></span>
                                                    </small>
                                                </div>
                                            </td>
                                            <?php else: ?>
                                            <td class="p-0" style="width: 50%;"></td>
                                            <?php endif; endfor; ?>
                                        </tr>
                                        <?php endfor; ?>
                                        <?php if (count($statusCounts) > 0): ?>
                                        <tr>
                                            <td colspan="2" class="p-0 pt-2">
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-chart-bar me-1"></i>
                                                    Total: <?php echo array_sum($statusCounts); ?> orders across <?php echo count($statusCounts); ?> statuses
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="filter-card mb-4 no-print">
            <form method="GET" action="" id="ordersFilterForm">
                <input type="hidden" name="page" value="orders">
                <input type="hidden" name="p" value="1">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" id="ordersSearchInput" name="search" class="form-control" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearchInput()" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select select2-status">
                            <option value="">Payment Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="order_status" class="form-select select2-status">
                            <option value="">Order Status</option>
                            <option value="pending" <?php echo $order_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $order_status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="completed" <?php echo $order_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $order_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="fas fa-undo-alt me-1"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <span id="selectedCount">0 orders selected</span>
            <div>
                <button class="btn btn-sm btn-outline-light me-2" onclick="updateBulkStatus('processing')">
                    <i class="fas fa-cog"></i> Mark Processing
                </button>
                <button class="btn btn-sm btn-outline-light me-2" onclick="updateBulkStatus('completed')">
                    <i class="fas fa-check"></i> Mark Completed
                </button>
                <button class="btn btn-sm btn-danger me-2" onclick="deleteBulkOrders()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn btn-sm btn-outline-light" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
     <!-- Orders Table -->
            <div class="content-card">
                <div class="card-header-custom">
                    <h4><i class="fas fa-list"></i> Orders List (Showing <?php echo count($orders); ?> of <?php echo $total; ?>)</h4>
                    <div>
                        <button class="btn btn-primary btn-sm me-2" onclick="createNewOrder()">
                            <i class="fas fa-plus"></i> New Order
                        </button>
                        <button class="btn btn-success btn-sm" onclick="showExportModal()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                
        <!-- Orders Table -->
        <div class="table-container">
            <div class="table-header">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Orders (<?php echo count($orders); ?> / <?php echo $total; ?>)</h5>
            </div>
            <div class="table-responsive p-3">
                <?php if (!empty($orders)): ?>
                <table class="table table-hover" id="ordersTable">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr data-order-id="<?php echo $order['id']; ?>">
                            <td>
                                <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>" onchange="updateBulkActions()">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <?php if ($isOrderAdmin): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $order['item_count']; ?> items</span>
                            </td>
                            <td>
                                <strong><?php echo formatCurrency($order['total_amount']); ?></strong>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                            </td>
                            <td>
                                <select class="form-select form-select-sm status-selector payment-status" 
                                        data-order-id="<?php echo $order['id']; ?>" 
                                        data-type="payment">
                                    <option value="pending" <?php echo ($order['payment_status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo ($order['payment_status'] ?? '') == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="failed" <?php echo ($order['payment_status'] ?? '') == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="refunded" <?php echo ($order['payment_status'] ?? '') == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm status-selector order-status" 
                                        data-order-id="<?php echo $order['id']; ?>" 
                                        data-type="order">
                                    <option value="pending" <?php echo ($order['order_status'] ?? '') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo ($order['order_status'] ?? '') == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo ($order['order_status'] ?? '') == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo ($order['order_status'] ?? '') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="completed" <?php echo ($order['order_status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($order['order_status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewOrderDetails(<?php echo $order['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($isOrderAdmin): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editOrder(<?php echo $order['id']; ?>)" title="Edit Order (Admin/Super Admin only)">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <?php endif; ?>
                                   
                                    <?php if ($isOrderAdmin): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')" title="Delete Order">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick="printInvoice(<?php echo $order['id']; ?>)" title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h4>No Orders Found</h4>
                    <p class="text-muted">No orders match your current filters.</p>
                    <button class="btn btn-primary" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav aria-label="Orders pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page + 1])); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm" action="<?php echo BASE_URL; ?>/ajax/export_orders.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select name="format" class="form-control">
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-control">
                                <option value="all">All Orders</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Include Columns</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="order_number" checked>
                                <label class="form-check-label">Order Number</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="customer_info" checked>
                                <label class="form-check-label">Customer Info</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="amount" checked>
                                <label class="form-check-label">Amount</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="columns[]" value="status">
                                <label class="form-check-label">Status</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitExport()">Export</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // Global variables
    let selectedOrders = [];
    
    $(document).ready(function() {
        // Keep filtering/search server-side to avoid conflicts with DataTables.
        
        // Initialize Select2
        $('.select2-status').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select status',
            minimumResultsForSearch: Infinity,
            dropdownAutoWidth: false,
            dropdownParent: $('#ordersFilterForm')
        });

        // When applying a new filter/search, always return to page 1.
        $('#ordersFilterForm').on('submit', function () {
            $(this).find('input[name=\"p\"]').val('1');
        });
        
        // Initialize status change listeners
        $('.status-selector').change(function() {
            const orderId = $(this).data('order-id');
            const type = $(this).data('type');
            const status = $(this).val();
            
            updateOrderStatus(orderId, type, status);
        });
    });
    
    // View order details
    function viewOrderDetails(orderId) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/ajax/get_order_details.php',
            type: 'POST',
            data: { order_id: orderId },
            success: function(response) {
                Swal.fire({
                    title: 'Order Details',
                    html: response,
                    width: '800px',
                    showCloseButton: true,
                    showConfirmButton: false
                });
            },
            error: function() {
                Swal.fire('Error', 'Could not load order details', 'error');
            }
        });
    }
    
    // Update order status
    function updateOrderStatus(orderId, type, status) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/ajax/update_order_status.php',
            type: 'POST',
            data: { 
                order_id: orderId, 
                type: type,
                status: status 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message || 'Order status updated', 'success');
                } else {
                    Swal.fire('Error', response.message || 'Update failed', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Could not update status', 'error');
            }
        });
    }
    
    // Delete order
    function deleteOrder(orderId, orderNumber) {
        Swal.fire({
            title: 'Delete Order?',
            text: `Are you sure you want to delete order ${orderNumber}? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/ajax/delete_order.php',
                    type: 'POST',
                    data: { order_id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', 'Order has been deleted.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Could not delete order', 'error');
                    }
                });
            }
        });
    }
    
    // Edit order
    function editOrder(orderId) {
        window.location.href = '<?php echo BASE_URL; ?>/pages/edit_order.php?id=' + orderId;
    }
    
    // Print invoice
    function printInvoice(orderId) {
        const printUrl = '<?php echo BASE_URL; ?>/ajax/print_invoice.php?order_id=' + orderId;
        const printWindow = window.open(printUrl, '_blank', 'width=800,height=600');
        
        if (!printWindow) {
            Swal.fire('Error', 'Please allow popups for this site to print invoices', 'warning');
            return;
        }
        
        printWindow.onload = function() {
            setTimeout(function() {
                printWindow.print();
            }, 1000);
        };
    }
    
    // Show export modal
    function showExportModal() {
        const modal = new bootstrap.Modal(document.getElementById('exportModal'));
        modal.show();
    }
    
    // Submit export
    function submitExport() {
        document.getElementById('exportForm').submit();
    }
    
    // Create new order
    function createNewOrder() {
        window.location.href = '<?php echo BASE_URL; ?>/pages/create_order.php';
    }
    
    // Clear filters
    function clearFilters() {
        window.location.href = window.location.pathname + '?page=orders';
    }

    function clearSearchInput() {
        const input = document.getElementById('ordersSearchInput');
        if (!input) return;
        input.value = '';
        input.focus();
    }
    
    // Bulk actions
    function toggleSelectAll() {
        const isChecked = $('#selectAll').prop('checked');
        $('.order-checkbox').prop('checked', isChecked);
        updateBulkActions();
    }
    
    function updateBulkActions() {
        selectedOrders = [];
        $('.order-checkbox:checked').each(function() {
            selectedOrders.push($(this).val());
        });
        
        const count = selectedOrders.length;
        $('#selectedCount').text(`${count} orders selected`);
        
        if (count > 0) {
            $('#bulkActions').addClass('active');
        } else {
            $('#bulkActions').removeClass('active');
        }
    }
    
    function clearSelection() {
        $('.order-checkbox').prop('checked', false);
        $('#selectAll').prop('checked', false);
        updateBulkActions();
    }
    
    function updateBulkStatus(status) {
        if (selectedOrders.length === 0) {
            Swal.fire('Warning', 'Please select orders first', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Update Status',
            text: `Update ${selectedOrders.length} orders to "${status}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Update'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/ajax/bulk_update_status.php',
                    type: 'POST',
                    data: { 
                        order_ids: selectedOrders,
                        status: status 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success', 'Orders updated successfully', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    }
    
    function deleteBulkOrders() {
        if (selectedOrders.length === 0) {
            Swal.fire('Warning', 'Please select orders first', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Delete Orders',
            text: `Delete ${selectedOrders.length} orders? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/ajax/bulk_delete_orders.php',
                    type: 'POST',
                    data: { order_ids: selectedOrders },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', 'Orders deleted successfully', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    }
    </script>
</body>
</html>
