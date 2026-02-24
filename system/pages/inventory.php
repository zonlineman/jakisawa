<?php
// inventory.php - Updated to match your actual database structure

// Database connection
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
$conn = getDBConnection();

function resolveRemedyImageUrl($imagePath): string
{
    $value = trim((string)$imagePath);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (strpos($value, '/') === 0) {
        return projectPathUrl($value);
    }

    if (strpos($value, 'uploads/') === 0) {
        return systemUrl($value);
    }

    return projectPathUrl($value);
}

// Initialize inventory data
$inventoryData = [
    'items' => [],
    'total' => 0,
    'current_page' => 1,
    'pages' => 1,
    'categories' => []
];

$stats = [
    'total_products' => 0,
    'low_stock' => 0,
    'inventory_turnover' => 4.2
];

$searchQuery = '';
$perPage = 30;
$page = 1;

// Get current page and search query
if (isset($_GET['p']) && is_numeric($_GET['p'])) {
    $page = max(1, intval($_GET['p']));
}

if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
}

// Get items per page
if (isset($_GET['per_page']) && in_array(intval($_GET['per_page']), [15, 30, 50, 100])) {
    $perPage = intval($_GET['per_page']);
}

// Calculate offset
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters - USING remedies TABLE (not products)
$whereConditions = ['r.is_active = 1'];
$params = [];
$paramTypes = '';

if (!empty($searchQuery)) {
    $whereConditions[] = "(r.name LIKE ? OR r.sku LIKE ? OR r.description LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sss';
}

// Category filter
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $whereConditions[] = "r.category_id = ?";
    $params[] = intval($_GET['category']);
    $paramTypes .= 'i';
}

// Stock filter
if (isset($_GET['stock_filter'])) {
    switch ($_GET['stock_filter']) {
        case 'critical':
            $whereConditions[] = "(r.stock_quantity > 0 AND r.stock_quantity <= r.reorder_level)";
            break;
        case 'low':
            $whereConditions[] = "(r.stock_quantity > 0 AND r.stock_quantity <= (r.reorder_level * 1.5))";
            break;
        case 'out_of_stock':
            $whereConditions[] = "r.stock_quantity = 0";
            break;
        case 'adequate':
            $whereConditions[] = "r.stock_quantity > (r.reorder_level * 1.5)";
            break;
    }
}

// Price range filter
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $whereConditions[] = "r.unit_price >= ?";
    $params[] = floatval($_GET['min_price']);
    $paramTypes .= 'd';
}

if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $whereConditions[] = "r.unit_price <= ?";
    $params[] = floatval($_GET['max_price']);
    $paramTypes .= 'd';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Print catalog mode (uses current filters, no pagination)
$isCatalogPrint = isset($_GET['print_catalog']) && $_GET['print_catalog'] === '1';
if ($isCatalogPrint && $conn) {
    $printQuery = "SELECT 
                    r.id, r.sku, r.name, r.description,
                    r.stock_quantity, r.reorder_level,
                    r.unit_price, r.discount_price,
                    r.image_url,
                    c.name as category_name
                FROM remedies r
                LEFT JOIN categories c ON r.category_id = c.id
                $whereClause
                ORDER BY c.name ASC, r.name ASC";

    $printRows = [];
    if (!empty($params)) {
        $printStmt = mysqli_prepare($conn, $printQuery);
        if ($printStmt) {
            mysqli_stmt_bind_param($printStmt, $paramTypes, ...$params);
            mysqli_stmt_execute($printStmt);
            $printRes = mysqli_stmt_get_result($printStmt);
            while ($r = mysqli_fetch_assoc($printRes)) {
                $printRows[] = $r;
            }
            mysqli_stmt_close($printStmt);
        }
    } else {
        $printRes = mysqli_query($conn, $printQuery);
        if ($printRes) {
            while ($r = mysqli_fetch_assoc($printRes)) {
                $printRows[] = $r;
            }
        }
    }

    $generatedAt = date('Y-m-d H:i:s');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Catalog</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; background: #f4f7fb; color: #111827; }
        .page { max-width: 1120px; margin: 16px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { padding: 18px 20px; color: #fff; background: linear-gradient(135deg, #1d4ed8, #2563eb); }
        .header h1 { margin: 0; font-size: 24px; }
        .header .meta { margin-top: 6px; font-size: 12px; opacity: .95; }
        .content { padding: 16px 20px; }
        .summary { margin-bottom: 10px; color: #4b5563; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; font-size: 12px; }
        th { background: #f8fafc; text-align: left; color: #111827; }
        .imgbox { width: 54px; height: 54px; border-radius: 8px; border: 1px solid #e5e7eb; object-fit: cover; }
        .pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-weight: 600; font-size: 11px; }
        .in { background: #dcfce7; color: #166534; }
        .out { background: #fee2e2; color: #991b1b; }
        .foot { padding: 10px 20px; background: #f9fafb; color: #6b7280; font-size: 11px; display: flex; justify-content: space-between; gap: 12px; }
        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff; }
            .page { border: none; border-radius: 0; margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <h1>JAKISAWA SHOP - Inventory Catalog</h1>
        <div class="meta">Generated: <?php echo htmlspecialchars($generatedAt); ?></div>
    </div>
    <div class="content">
        <div class="summary">
            Total Items: <strong><?php echo number_format(count($printRows)); ?></strong>
            <?php if (!empty($searchQuery)): ?>
                | Search: <strong><?php echo htmlspecialchars($searchQuery); ?></strong>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:68px;">Image</th>
                    <th>Remedy</th>
                    <th style="width:170px;">Category</th>
                    <th style="width:150px;">Stock</th>
                    <th style="width:150px;">Price</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($printRows)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;">No remedies found for current filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($printRows as $row): ?>
                    <?php $inStock = (float)$row['stock_quantity'] > 0; ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['image_url'])): ?>
                                <img class="imgbox" src="<?php echo htmlspecialchars(resolveRemedyImageUrl((string)$row['image_url'])); ?>" alt="">
                            <?php else: ?>
                                <div class="imgbox"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700;"><?php echo htmlspecialchars((string)$row['name']); ?></div>
                            <div style="color:#6b7280;">SKU: <?php echo htmlspecialchars((string)$row['sku']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars((string)($row['category_name'] ?? 'Uncategorized')); ?></td>
                        <td>
                            <span class="pill <?php echo $inStock ? 'in' : 'out'; ?>">
                                <?php echo $inStock ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                        </td>
                        <td>
                            <div><strong>KES <?php echo number_format((float)$row['unit_price'], 2); ?></strong></div>
                            <?php if (!empty($row['discount_price']) && (float)$row['discount_price'] > 0): ?>
                                <div style="color:#6b7280;">Discount: KES <?php echo number_format((float)$row['discount_price'], 2); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)($row['description'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="foot">
        <span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span>
        <span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span>
    </div>
</div>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 120);
});
</script>
</body>
</html>
<?php
    exit;
}

// Fetch inventory data from remedies table
if ($conn) {
    // Get total count from remedies table
    $countQuery = "SELECT COUNT(*) as total FROM remedies r $whereClause";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $countQuery);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $inventoryData['total'] = $row['total'];
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $result = mysqli_query($conn, $countQuery);
        if ($row = mysqli_fetch_assoc($result)) {
            $inventoryData['total'] = $row['total'];
        }
    }
    
    // Calculate pages
    $inventoryData['pages'] = ceil($inventoryData['total'] / $perPage);
    $inventoryData['current_page'] = $page;
    
    // Fetch inventory items from remedies table
    $query = "SELECT 
                r.id, r.sku, r.name, r.description, 
                r.ingredients, r.usage_instructions,
                r.stock_quantity, r.reorder_level, 
                r.unit_price, r.cost_price, r.discount_price,
                r.image_url,
                r.supplier_id,
                r.is_featured, r.is_active,
                r.created_at, r.updated_at,
                c.name as category_name,
                s.name as supplier_name
            FROM remedies r
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN suppliers s ON r.supplier_id = s.id
            $whereClause
            ORDER BY r.stock_quantity ASC, r.name ASC
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $params[] = $perPage;
        $params[] = $offset;
        $paramTypes .= 'ii';
        
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $inventoryData['items'][] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fetch categories from categories table
    $result = mysqli_query($conn, "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    while ($row = mysqli_fetch_assoc($result)) {
        $inventoryData['categories'][] = $row;
    }

    // Fetch only active suppliers for restock modal
    $inventoryData['suppliers'] = [];
    $supResult = mysqli_query($conn, "SELECT id, name, is_active FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
    while ($srow = mysqli_fetch_assoc($supResult)) {
        $inventoryData['suppliers'][] = $srow;
    }
    
    // Get statistics from remedies table
    $result = mysqli_query($conn, "SELECT COUNT(*) as total_products FROM remedies WHERE is_active = 1");
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['total_products'] = $row['total_products'];
    }
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as low_stock FROM remedies WHERE stock_quantity <= reorder_level AND stock_quantity > 0 AND is_active = 1");
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['low_stock'] = $row['low_stock'];
    }
}

// Helper functions - add these if not in your functions.php
if (!function_exists('getStockStatus')) {
    function getStockStatus($stock_quantity, $reorder_level) {
        if ($stock_quantity == 0) {
            return 'Out of Stock';
        } elseif ($stock_quantity <= $reorder_level) {
            return 'Critical';
        } elseif ($stock_quantity <= ($reorder_level * 1.5)) {
            return 'Low';
        } else {
            return 'Adequate';
        }
    }
}

if (!function_exists('getStockColor')) {
    function getStockColor($stock_quantity, $reorder_level) {
        if ($stock_quantity == 0) {
            return '#6c757d'; // Gray for out of stock
        } elseif ($stock_quantity <= $reorder_level) {
            return '#dc3545'; // Red for critical
        } elseif ($stock_quantity <= ($reorder_level * 1.5)) {
            return '#ffc107'; // Yellow for low
        } else {
            return '#28a745'; // Green for adequate
        }
    }
}

if (!function_exists('getStockStatusBadge')) {
    function getStockStatusBadge($stock_quantity, $reorder_level) {
        $status = getStockStatus($stock_quantity, $reorder_level);
        $colors = [
            'Out of Stock' => 'secondary',
            'Critical' => 'danger',
            'Low' => 'warning',
            'Adequate' => 'success'
        ];
        
        $color = $colors[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . $status . '</span>';
    }
}

if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $steps) {
        // Steps should be between -255 and 255. Negative = darker, positive = lighter
        $steps = max(-255, min(255, $steps));
        
        // Normalize into a six character long hex string
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
        }
        
        // Split into three parts: R, G and B
        $color_parts = str_split($hex, 2);
        $return = '#';
        
        foreach ($color_parts as $color) {
            $color   = hexdec($color); // Convert to decimal
            $color   = max(0,min(255,$color + $steps)); // Adjust color
            $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
        }
        
        return $return;
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        if ($amount === null) {
            return 'KSh 0.00';
        }
        return 'KSh ' . number_format($amount, 2);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('canPerformAction')) {
    function canPerformAction($action) {
        // Simple check - admin can do everything
        if (isAdmin()) {
            return true;
        }
        
        // Staff permissions
        $staffPermissions = [
            'edit_inventory',
            'restock_inventory',
            'view_inventory'
        ];
        
        return in_array($action, $staffPermissions, true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            min-height: 100vh;
            display: flex;
            position: relative;
        }
        
        .sidebar {
            width: 250px;
            background: #1f2830;
            color: #f3efef;
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid #dee2e6;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            min-height: 100vh;
            background-color: #f5f7fb;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-menu {
            padding: 20px 0;
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
        
        .dashboard-avatar {
            margin-right: 15px;
        }
        
        .avatar-circle {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .quick-stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
            transition: transform 0.3s;
        }
        
        .quick-stats-content {
            display: flex;
            align-items: center;
        }
        
        .quick-stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            position: relative;
            transition: transform 0.3s;
            min-height: 150px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.products { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.value { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.low { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
        .stat-icon.out { background: linear-gradient(135deg, #fe8c00 0%, #f83600 100%); }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
            min-height: 128px;
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .summary-icon.bg-primary { background: var(--primary); }
        .summary-icon.bg-success { background: var(--success); }
        .summary-icon.bg-warning { background: var(--warning); }
        .summary-icon.bg-danger { background: var(--danger); }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .stat-info,
        .summary-content {
            min-width: 0;
            flex: 1;
            width: 100%;
        }

        .stat-info h3,
        .summary-value,
        .quick-stats-value {
            margin-bottom: 4px;
            line-height: 1.2;
            font-weight: 700;
            font-size: clamp(0.95rem, 2.8vw, 1.45rem);
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
        }

        .stat-info h3.currency-value {
            font-size: clamp(0.82rem, 2.4vw, 1.08rem);
            line-height: 1.25;
        }

        .quick-stats-info {
            min-width: 0;
        }

        .stat-info p,
        .summary-label,
        .quick-stats-label {
            margin-bottom: 2px;
            font-size: .95rem;
        }

        #inventoryFilterForm .form-control,
        #inventoryFilterForm .form-select,
        #inventoryFilterForm .input-group-text,
        #inventoryFilterForm .btn {
            min-height: 42px;
        }

        #inventoryFilterForm .form-control,
        #inventoryFilterForm .form-select {
            border-radius: .5rem;
        }

        #inventoryFilterForm #categoryFilter,
        #inventoryFilterForm #stockFilter {
            min-height: 38px;
            height: 38px;
            font-size: 0.88rem;
            line-height: 1.45;
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
            padding-right: 1.8rem;
            max-width: 100%;
            text-overflow: ellipsis;
        }

        .table-responsive {
            overflow-x: auto;
        }

        #inventoryTable th,
        #inventoryTable td {
            vertical-align: top;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, #f8f9fa, #ffffff);
        }
        
        .product-info {
            max-width: 300px;
        }
        
        .product-avatar {
            flex-shrink: 0;
        }
        
        .avatar-circle-sm {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .stock-progress .progress {
            border-radius: 4px;
            overflow: hidden;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .pagination-enhanced {
            padding: 20px;
            border-top: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
            border: none;
        }
        
        .critical-item {
            background-color: rgba(220, 53, 69, 0.05) !important;
        }
        
        .out-of-stock-item {
            background-color: rgba(108, 117, 125, 0.05) !important;
        }
        
        @media (max-width: 768px) {
            .stat-info h3,
            .summary-value,
            .quick-stats-value {
                font-size: clamp(0.84rem, 4.1vw, 1.08rem);
                line-height: 1.15;
            }

            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2 span,
            .menu-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr !important;
            }

            .stat-card {
                padding: 16px;
                min-height: 0;
                align-items: flex-start;
            }

            .stat-icon {
                width: 46px;
                height: 46px;
                margin-right: 10px;
                font-size: 18px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .pagination-enhanced {
                flex-direction: column;
                align-items: flex-start;
            }

            #inventoryFilterForm #categoryFilter,
            #inventoryFilterForm #stockFilter {
                font-size: 0.86rem;
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

            <!-- Notifications -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show mb-4">
                    <i class="fas <?php echo $_SESSION['message']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $_SESSION['message']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="top-bar">
                <h1 class="page-title">
                    <i class="fas fa-boxes"></i>
                    Inventory Management
                </h1>
                <div class="btn-toolbar">
                    <span class="text-muted">
                        Total Items: <?php echo number_format($inventoryData['total']); ?>
                        <?php if ($searchQuery): ?>
                            | Search: "<?php echo htmlspecialchars($searchQuery); ?>"
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Inventory Statistics Cards -->
            <div class="stats-grid mb-4">
                <!-- Total Products -->
                <div class="stat-card">
                    <div class="stat-icon products">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_products']); ?></h3>
                        <p>Total Remedies</p>
                        <div class="stat-trend">
                            <i class="fas fa-chart-line"></i>
                            <span>All active remedies</span>
                        </div>
                    </div>
                </div>
                
                <!-- Total Stock Value -->
                <div class="stat-card">
                    <div class="stat-icon value">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="currency-value" title="<?php
                            $totalStockValue = 0;
                            foreach ($inventoryData['items'] as $item) {
                                $totalStockValue += ($item['stock_quantity'] * $item['unit_price']);
                            }
                            echo htmlspecialchars(formatCurrency($totalStockValue));
                        ?>">
                            <?php
                            $totalStockValue = 0;
                            foreach ($inventoryData['items'] as $item) {
                                $totalStockValue += ($item['stock_quantity'] * $item['unit_price']);
                            }
                            $absStockValue = abs($totalStockValue);
                            if ($absStockValue >= 1000000000) {
                                echo 'KSh ' . number_format($totalStockValue / 1000000000, 2) . 'B';
                            } elseif ($absStockValue >= 1000000) {
                                echo 'KSh ' . number_format($totalStockValue / 1000000, 2) . 'M';
                            } elseif ($absStockValue >= 1000) {
                                echo 'KSh ' . number_format($totalStockValue / 1000, 1) . 'K';
                            } else {
                                echo formatCurrency($totalStockValue);
                            }
                            ?>
                        </h3>
                        <p>Stock Value</p>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up text-success"></i>
                            <span class="text-success">Current value</span>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Items -->
                <div class="stat-card">
                    <div class="stat-icon low">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['low_stock']); ?></h3>
                        <p>Low Stock</p>
                        <div class="stat-trend">
                            <i class="fas fa-exclamation-circle text-warning"></i>
                            <span class="text-warning">Needs attention</span>
                        </div>
                    </div>
                    <?php if ($stats['low_stock'] > 0): ?>
                    <div class="stat-notification">
                        <span class="notification-dot" style="display: block; width: 10px; height: 10px; border-radius: 50%; background: var(--danger);"></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Out of Stock -->
                <div class="stat-card">
                    <div class="stat-icon out">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php
                            $outOfStock = 0;
                            foreach ($inventoryData['items'] as $item) {
                                if ($item['stock_quantity'] == 0) {
                                    $outOfStock++;
                                }
                            }
                            echo number_format($outOfStock);
                            ?>
                        </h3>
                        <p>Out of Stock</p>
                        <div class="stat-trend">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                            <span class="text-danger">Requires restocking</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Filters and Actions -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" id="inventoryFilterForm">
                        <input type="hidden" name="page" value="inventory">
                        <div class="row g-3">
                            <!-- Search -->
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" 
                                           name="search" 
                                           class="form-control" 
                                           placeholder="Search remedies..." 
                                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                                           id="inventorySearchInput">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearInventorySearch()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Category Filter -->
                            <div class="col-md-2">
                                <select name="category" class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($inventoryData['categories'] as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($_GET['category'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Stock Status Filter -->
                            <div class="col-md-2">
                                <select name="stock_filter" class="form-select" id="stockFilter">
                                    <option value="">All Stock</option>
                                    <option value="critical" <?php echo ($_GET['stock_filter'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="low" <?php echo ($_GET['stock_filter'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="out_of_stock" <?php echo ($_GET['stock_filter'] ?? '') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="adequate" <?php echo ($_GET['stock_filter'] ?? '') === 'adequate' ? 'selected' : ''; ?>>Adequate</option>
                                </select>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text">KSh</span>
                                    <input type="number" 
                                           name="min_price" 
                                           class="form-control" 
                                           placeholder="Min Price"
                                           value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>"
                                           id="minPrice">
                                    <span class="input-group-text">to</span>
                                    <input type="number" 
                                           name="max_price" 
                                           class="form-control" 
                                           placeholder="Max Price"
                                           value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>"
                                           id="maxPrice">
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-filter me-1"></i> Filter
                                    </button>
                                    <a href="?page=inventory" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inventory Summary Stats -->
            <div class="row mb-4 g-3">
                <div class="col-xl-3 col-md-6">
                    <div class="summary-card">
                        <div class="summary-icon bg-primary">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value"><?php echo number_format($inventoryData['total']); ?></div>
                            <div class="summary-label">Total Items Found</div>
                            <div class="summary-subtext">
                                <small>Page <?php echo $inventoryData['current_page']; ?> of <?php echo $inventoryData['pages']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="summary-card">
                        <div class="summary-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value">
                                <?php
                                $adequateStock = 0;
                                foreach ($inventoryData['items'] as $item) {
                                    if ($item['stock_quantity'] > ($item['reorder_level'] * 1.5)) {
                                        $adequateStock++;
                                    }
                                }
                                echo number_format($adequateStock);
                                ?>
                            </div>
                            <div class="summary-label">Adequate Stock</div>
                            <div class="summary-subtext">
                                <small>Above reorder level</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="summary-card">
                        <div class="summary-icon bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value">
                                <?php
                                $lowStockCount = 0;
                                foreach ($inventoryData['items'] as $item) {
                                    if ($item['stock_quantity'] <= ($item['reorder_level'] * 1.5) && $item['stock_quantity'] > 0) {
                                        $lowStockCount++;
                                    }
                                }
                                echo number_format($lowStockCount);
                                ?>
                            </div>
                            <div class="summary-label">Low Stock Items</div>
                            <div class="summary-subtext">
                                <small>Below reorder level</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="summary-card">
                        <div class="summary-icon bg-danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="summary-content">
                            <div class="summary-value">
                                <?php
                                $outOfStockCount = 0;
                                foreach ($inventoryData['items'] as $item) {
                                    if ($item['stock_quantity'] == 0) {
                                        $outOfStockCount++;
                                    }
                                }
                                echo number_format($outOfStockCount);
                                ?>
                            </div>
                            <div class="summary-label">Out of Stock</div>
                            <div class="summary-subtext">
                                <small>Requires immediate attention</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="table-container">
                <div class="table-header">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-boxes me-2"></i>
                            Remedy Inventory
                            <span class="badge bg-primary ms-2"><?php echo $inventoryData['total']; ?></span>
                        </h5>
                        <small class="text-muted">
                            Showing <?php echo count($inventoryData['items']); ?> of <?php echo $inventoryData['total']; ?> items
                        </small>
                    </div>
                    
                    <div class="d-flex gap-2 align-items-center">
                        <!-- Add New Remedy -->
                        <?php if (isAdmin()): ?>
                        <a href="?page=add_remedy" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Remedy
                        </a>
                        <?php endif; ?>

                        <!-- Print Catalog -->
                        <button class="btn btn-sm btn-outline-primary" onclick="printInventoryCatalog()" title="Print Inventory Catalog">
                            <i class="fas fa-print me-1"></i> Print Catalog
                        </button>
                        
                        <!-- Refresh Button -->
                        <button class="btn btn-sm btn-info" onclick="refreshInventory()" id="refreshInventoryBtn">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <?php if (!empty($inventoryData['items'])): ?>
                    <table class="table table-hover" id="inventoryTable">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Image</th>
                                <th>Remedy Details</th>
                                <th>Stock Information</th>
                                <th>Pricing</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryData['items'] as $item): ?>
                            <?php 
                            $stockStatus = getStockStatus($item['stock_quantity'], $item['reorder_level']);
                            $stockColor = getStockColor($item['stock_quantity'], $item['reorder_level']);
                            $isCritical = $item['stock_quantity'] <= $item['reorder_level'] && $item['stock_quantity'] > 0;
                            $isOutOfStock = $item['stock_quantity'] == 0;
                            $stockValue = $item['stock_quantity'] * $item['unit_price'];
                            ?>
                            <tr class="<?php echo $isCritical ? 'critical-item' : ($isOutOfStock ? 'out-of-stock-item' : ''); ?>" 
                                data-item-id="<?php echo $item['id']; ?>">
                                <td>
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars(resolveRemedyImageUrl($item['image_url'])); ?>"
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             style="width:56px;height:56px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;">
                                    <?php else: ?>
                                        <div style="width:56px;height:56px;border-radius:10px;border:1px solid #e5e7eb;background:#f8f9fa;display:flex;align-items:center;justify-content:center;color:#6c757d;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="product-info">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="product-avatar">
                                                <div class="avatar-circle-sm" style="background: linear-gradient(135deg, <?php echo $stockColor; ?>, <?php echo adjustBrightness($stockColor, -20); ?>);">
                                                    <i class="fas fa-box text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="fw-bold product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="product-code">
                                                    <small class="text-muted">
                                                        <i class="fas fa-barcode me-1"></i> <?php echo $item['sku']; ?>
                                                    </small>
                                                </div>
                                                <?php if ($item['description']): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <div class="stock-level" style="font-size: 1.2rem; font-weight: bold; color: <?php echo $stockColor; ?>;">
                                                    <?php echo number_format($item['stock_quantity'], 3); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="stock-progress">
                                                    <div class="progress" style="height: 8px; width: 150px;">
                                                        <?php
                                                        $maxStock = max($item['stock_quantity'], $item['reorder_level'] * 2, 100);
                                                        $stockPercentage = ($item['stock_quantity'] / $maxStock) * 100;
                                                        ?>
                                                        <div class="progress-bar" 
                                                             style="width: <?php echo min($stockPercentage, 100); ?>%; 
                                                                    background-color: <?php echo $stockColor; ?>;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="stock-limits mt-1">
                                                    <small class="text-muted">
                                                        <i class="fas fa-arrow-down me-1"></i> Reorder: <?php echo number_format($item['reorder_level'], 3); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-calculator me-1"></i> Value: <?php echo formatCurrency($stockValue); ?>
                                                <?php if ($item['updated_at']): ?>
                                                | <i class="fas fa-calendar me-1"></i> Updated: <?php echo date('M d', strtotime($item['updated_at'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="pricing-info">
                                        <div class="mb-2">
                                            <div class="text-primary fw-bold" style="font-size: 1.1rem;">
                                                <?php echo formatCurrency($item['unit_price']); ?>
                                            </div>
                                            <?php if ($item['cost_price']): ?>
                                            <div>
                                                <small class="text-muted">
                                                    Cost: <?php echo formatCurrency($item['cost_price']); ?>
                                                    <?php 
                                                    if ($item['cost_price'] > 0) {
                                                        $margin = (($item['unit_price'] - $item['cost_price']) / $item['cost_price']) * 100;
                                                        echo '<span class="' . ($margin > 0 ? 'text-success' : 'text-danger') . '">';
                                                        echo ' Profit(' . number_format($margin, 1) . '%)';
                                                        echo '</span>';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item['discount_price']): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-danger">
                                                <i class="fas fa-tag me-1">Discount</i> <?php echo formatCurrency($item['discount_price']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="category-info">
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                        <?php if ($item['supplier_name'] ?? ''): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-truck me-1"></i> <?php echo substr(htmlspecialchars($item['supplier_name']), 0, 20); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-info">
                                        <div class="mb-2">
                                            <?php echo getStockStatusBadge($item['stock_quantity'], $item['reorder_level']); ?>
                                        </div>
                                        <?php if ($isCritical): ?>
                                        <div class="mt-1">
                                            <small class="text-danger">
                                                <i class="fas fa-exclamation-triangle"></i> Critical stock level
                                            </small>
                                        </div>
                                        <?php elseif ($isOutOfStock): ?>
                                        <div class="mt-1">
                                            <small class="text-dark">
                                                <i class="fas fa-ban"></i> Out of stock
                                            </small>
                                        </div>
                                        <?php elseif ($item['stock_quantity'] <= $item['reorder_level']): ?>
                                        <div class="mt-1">
                                            <small class="text-warning">
                                                <i class="fas fa-clock"></i> Below reorder level
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details -->
                                        <a href="?page=remedy_view&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <!-- Print Remedy -->
                                        <button class="btn btn-sm btn-primary" onclick="printSingleRemedy(this)" title="Print Remedy">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        
                                        <!-- Edit Remedy -->
                                        <?php if (canPerformAction('edit_inventory')): ?>
                                        <a href="?page=edit_remedy&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Edit Remedy">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Restock Remedy -->
                                        <?php if (canPerformAction('restock_inventory')): ?>
                                        <button class="btn btn-sm btn-success" onclick="restockRemedy(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo (float)$item['stock_quantity']; ?>, <?php echo (float)$item['reorder_level']; ?>, <?php echo isset($item['supplier_id']) && $item['supplier_id'] !== null ? (int)$item['supplier_id'] : 0; ?>)" 
                                                title="Restock Remedy">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-boxes fa-4x text-muted"></i>
                        </div>
                        <h4>No Inventory Items Found</h4>
                        <p class="text-muted">No items match your current filters.</p>
                        <div class="mt-3">
                            <?php if ($searchQuery || ($_GET['stock_filter'] ?? '') || ($_GET['category'] ?? '')): ?>
                            <a href="?page=inventory" class="btn btn-primary">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                            <?php if (isAdmin()): ?>
                            <a href="?page=add_remedy" class="btn btn-success ms-2">
                                <i class="fas fa-plus me-1"></i> Add New Remedy
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($inventoryData['pages'] > 1): ?>
                <div class="pagination-enhanced">
                    <div class="pagination-info">
                        <small class="text-muted">
                            Page <?php echo $inventoryData['current_page']; ?> of <?php echo $inventoryData['pages']; ?> 
                            | Showing <?php echo count($inventoryData['items']); ?> of <?php echo $inventoryData['total']; ?> items
                        </small>
                    </div>
                    <div class="pagination-controls">
                        <!-- First Page -->
                        <?php if ($inventoryData['current_page'] > 1): ?>
                        <a href="?page=inventory&p=1&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($_GET['category'] ?? ''); ?>&stock_filter=<?php echo urlencode($_GET['stock_filter'] ?? ''); ?>&per_page=<?php echo $perPage; ?>" 
                           class="btn btn-sm btn-outline-primary" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Previous Page -->
                        <?php if ($inventoryData['current_page'] > 1): ?>
                        <a href="?page=inventory&p=<?php echo $inventoryData['current_page'] - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($_GET['category'] ?? ''); ?>&stock_filter=<?php echo urlencode($_GET['stock_filter'] ?? ''); ?>&per_page=<?php echo $perPage; ?>" 
                           class="btn btn-sm btn-outline-primary" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php 
                        $startPage = max(1, $inventoryData['current_page'] - 2);
                        $endPage = min($inventoryData['pages'], $inventoryData['current_page'] + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=inventory&p=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($_GET['category'] ?? ''); ?>&stock_filter=<?php echo urlencode($_GET['stock_filter'] ?? ''); ?>&per_page=<?php echo $perPage; ?>" 
                           class="btn btn-sm <?php echo $i == $inventoryData['current_page'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <?php if ($inventoryData['current_page'] < $inventoryData['pages']): ?>
                        <a href="?page=inventory&p=<?php echo $inventoryData['current_page'] + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($_GET['category'] ?? ''); ?>&stock_filter=<?php echo urlencode($_GET['stock_filter'] ?? ''); ?>&per_page=<?php echo $perPage; ?>" 
                           class="btn btn-sm btn-outline-primary" title="Next Page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Last Page -->
                        <?php if ($inventoryData['current_page'] < $inventoryData['pages']): ?>
                        <a href="?page=inventory&p=<?php echo $inventoryData['pages']; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($_GET['category'] ?? ''); ?>&stock_filter=<?php echo urlencode($_GET['stock_filter'] ?? ''); ?>&per_page=<?php echo $perPage; ?>" 
                           class="btn btn-sm btn-outline-primary" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Items Per Page Selector -->
                    <div class="pagination-per-page">
                        <small class="text-muted me-2">Show:</small>
                        <select class="form-select form-select-sm" style="width: auto;" onchange="changeInventoryItemsPerPage(this.value)">
                            <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                            <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

<!-- Enhanced Restock Modal -->
<div class="modal fade" id="restockRemedyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title mb-0"><i class="fas fa-box-open me-2"></i>Restock Remedy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="restockRemedyId">
                <input type="hidden" id="restockCurrentSupplierId" value="0">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Remedy</label>
                    <div class="form-control bg-light" id="restockRemedyName"></div>
                </div>
                <div class="mb-3">
                    <label for="restockSupplierInput" class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                    <select id="restockSupplierInput" class="form-select" required>
                        <option value="">Select supplier</option>
                        <?php foreach (($inventoryData['suppliers'] ?? []) as $sup): ?>
                            <option value="<?php echo (int)$sup['id']; ?>"><?php echo htmlspecialchars((string)$sup['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($inventoryData['suppliers'])): ?>
                        <small class="text-danger">No suppliers found. Please add a supplier first.</small>
                    <?php else: ?>
                    <small class="text-muted">Supplier is required to process restock.</small>
                    <?php endif; ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Current Stock</label>
                        <div class="form-control bg-light" id="restockCurrentStock">0.000</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reorder Level</label>
                        <div class="form-control bg-light" id="restockReorderLevel">0.000</div>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="restockQuantityInput" class="form-label fw-semibold">Quantity to Add</label>
                    <input type="number" min="0.001" step="0.001" class="form-control" id="restockQuantityInput" placeholder="Enter quantity">
                    <small class="text-muted">Use positive values only.</small>
                </div>
                <div class="mt-3">
                    <label for="restockNotesInput" class="form-label">Notes (optional)</label>
                    <textarea class="form-control" id="restockNotesInput" rows="2" placeholder="e.g. Received from supplier batch #A102"></textarea>
                </div>
                <div class="mt-3 p-2 rounded border bg-light">
                    <small class="text-muted d-block">Projected New Stock</small>
                    <strong id="restockProjectedStock">0.000</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="restockSubmitBtn" class="btn btn-success" onclick="submitRestock()">
                    <i class="fas fa-check me-1"></i>Update Stock
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    if (window.bootstrap && window.bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            var toggle = (tooltipTriggerEl.getAttribute('data-bs-toggle') || '').toLowerCase();
            if (toggle && toggle !== 'tooltip' && toggle !== 'popover') {
                return;
            }
            window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
        });
    }
});

function clearInventorySearch() {
    document.getElementById('inventorySearchInput').value = '';
    document.getElementById('inventoryFilterForm').submit();
}

function refreshInventory() {
    const btn = document.getElementById('refreshInventoryBtn');
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        setTimeout(() => {
            location.reload();
        }, 500);
    } else {
        location.reload();
    }
}

function changeInventoryItemsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    window.location.href = url.toString();
}

function printInventoryCatalog() {
    const current = new URL(window.location.href);
    const params = new URLSearchParams(current.search);
    params.delete('page');
    params.delete('p');
    params.delete('per_page');
    params.delete('print_catalog');

    const url = new URL('<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/pages/inventory_catalog_print.php', ENT_QUOTES); ?>', window.location.origin);
    url.search = params.toString();

    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    frame.src = url.toString();
    document.body.appendChild(frame);

    setTimeout(() => {
        try {
            if (frame && frame.parentNode) {
                frame.parentNode.removeChild(frame);
            }
        } catch (e) {}
    }, 30000);
}

function restockRemedy(id, name, currentStock, reorderLevel, supplierId) {
    document.getElementById('restockRemedyId').value = id;
    document.getElementById('restockCurrentSupplierId').value = Number(supplierId || 0);
    document.getElementById('restockRemedyName').textContent = name || '-';
    document.getElementById('restockCurrentStock').textContent = Number(currentStock || 0).toFixed(3);
    document.getElementById('restockReorderLevel').textContent = Number(reorderLevel || 0).toFixed(3);
    const supplierSelect = document.getElementById('restockSupplierInput');
    if (supplierSelect) {
        supplierSelect.value = (supplierId && Number(supplierId) > 0) ? String(Number(supplierId)) : '';
    }
    document.getElementById('restockQuantityInput').value = '';
    document.getElementById('restockNotesInput').value = '';
    document.getElementById('restockProjectedStock').textContent = Number(currentStock || 0).toFixed(3);

    const qtyInput = document.getElementById('restockQuantityInput');
    qtyInput.oninput = function () {
        const qty = parseFloat(this.value || 0);
        const nextStock = Number(currentStock || 0) + (isNaN(qty) ? 0 : qty);
        document.getElementById('restockProjectedStock').textContent = nextStock.toFixed(3);
    };

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        new bootstrap.Modal(document.getElementById('restockRemedyModal')).show();
    } else {
        const quantity = prompt('Enter quantity to restock for: ' + name + '\n\nCurrent stock will be increased by this amount.', '10');
        if (quantity && !isNaN(quantity) && Number(quantity) > 0) {
            if (!supplierId || Number(supplierId) <= 0) {
                showNotification('danger', 'Supplier is required for restocking.');
                return;
            }
            submitRestockFromValues(id, Number(supplierId), quantity, '');
        }
    }
}

function submitRestock() {
    const id = parseInt(document.getElementById('restockRemedyId').value, 10);
    const supplierId = parseInt(document.getElementById('restockSupplierInput').value || '0', 10);
    const qtyRaw = document.getElementById('restockQuantityInput').value;
    const notes = document.getElementById('restockNotesInput').value.trim();
    if (!supplierId || supplierId <= 0) {
        showNotification('danger', 'Please select a supplier before restocking.');
        return;
    }
    if (!qtyRaw || isNaN(qtyRaw) || Number(qtyRaw) <= 0) {
        showNotification('danger', 'Please enter a valid restock quantity greater than 0.');
        return;
    }
    submitRestockFromValues(id, supplierId, qtyRaw, notes);
}

function submitRestockFromValues(id, supplierId, qtyRaw, notes) {
    const submitBtn = document.getElementById('restockSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
    }

    const formData = new FormData();
    formData.append('action', 'restock');
    formData.append('remedy_id', id);
    formData.append('supplier_id', supplierId);
    formData.append('quantity', qtyRaw);
    formData.append('notes', notes || '');

    fetch('ajax/inventory_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        try {
            const result = JSON.parse(data);
            if (result.success) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modalEl = document.getElementById('restockRemedyModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                showNotification('success', result.message || 'Stock updated successfully.');
                setTimeout(() => location.reload(), 700);
            } else {
                showNotification('danger', result.message || 'Failed to update stock');
            }
        } catch (e) {
            showNotification('danger', 'Invalid response from server');
        }
    })
    .catch(() => {
        showNotification('danger', 'Network error. Please try again.');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-1"></i>Update Stock';
        }
    });
}

function printSingleRemedy(button) {
    const row = button?.closest('tr');
    if (!row) {
        showNotification('danger', 'Could not read remedy row for printing.');
        return;
    }

    const cols = row.querySelectorAll('td');
    if (!cols || cols.length < 7) {
        showNotification('danger', 'Remedy data is incomplete for printing.');
        return;
    }

    const generated = new Date().toLocaleString();
    const html = `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Remedy Record</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#f5f7fb;color:#111}
.sheet{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.hdr{padding:16px 18px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
.hdr h2{margin:0;font-size:22px}.sub{margin-top:4px;font-size:12px;opacity:.95}
.cnt{padding:14px 18px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.item{border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#fafafa}
.label{font-size:12px;color:#6b7280;margin-bottom:4px}.value{font-size:14px;font-weight:600;white-space:pre-wrap}
.ftr{padding:10px 18px;background:#f9fafb;color:#6b7280;font-size:11px;display:flex;justify-content:space-between}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff;padding:0}.sheet{border:none;border-radius:0}}
</style>
</head>
<body>
<div class="sheet">
  <div class="hdr">
    <h2>JAKISAWA SHOP - Remedy Record</h2>
    <div class="sub">Generated: ${generated}</div>
  </div>
  <div class="cnt">
    <div class="grid">
      <div class="item"><div class="label">Remedy Details</div><div class="value">${cols[1].innerText.trim()}</div></div>
      <div class="item"><div class="label">Stock Information</div><div class="value">${cols[2].innerText.trim()}</div></div>
      <div class="item"><div class="label">Pricing</div><div class="value">${cols[3].innerText.trim()}</div></div>
      <div class="item"><div class="label">Category</div><div class="value">${cols[4].innerText.trim()}</div></div>
      <div class="item"><div class="label">Status</div><div class="value">${cols[5].innerText.trim()}</div></div>
    </div>
  </div>
  <div class="ftr">
    <span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span>
    <span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span>
  </div>
</div>
</body>
</html>`;

    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    const doc = frame.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    const doPrint = () => {
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (e) {
            showNotification('danger', 'Unable to open print dialog.');
        }
        setTimeout(() => frame.remove(), 300);
    };

    frame.onload = () => setTimeout(doPrint, 120);
    setTimeout(doPrint, 450);
}

function showNotification(type, message) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(note => note.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `custom-notification alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    notification.innerHTML = `
        <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F for search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('inventorySearchInput').focus();
    }
    
    // F5 to refresh
    if (e.key === 'F5') {
        e.preventDefault();
        refreshInventory();
    }
});
</script>
</body>
</html>
