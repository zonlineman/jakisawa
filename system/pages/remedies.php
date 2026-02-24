<?php
/**
 * REMEDIES.PHP - PRODUCTION-READY VERSION
 * Main Remedies Management Display Page
 * Version: 2.0
 * Last Updated: 2026-02-16
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'staff';
$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
$isRemedyAdmin = in_array(strtolower((string)$user_role), ['admin', 'super_admin'], true);

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Initialize variables
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['p'] ?? 1));
$perPage = intval($_GET['per_page'] ?? 15);
$offset = ($page - 1) * $perPage;

// Build filters
$filters = new RemediesFilter($search, $categoryFilter, $statusFilter);
$whereClause = $filters->getWhereClause();
$params = $filters->getParams();
$paramTypes = $filters->getParamTypes();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM remedies r {$whereClause}";
$total = getSingleValue($conn, $countQuery, $params, $paramTypes);

// Calculate pagination
$pages = ceil($total / $perPage);
$current_page = $page;

// Get remedies with pagination
$remediesQuery = "
    SELECT r.*, c.name as category_name, s.name as supplier_name
    FROM remedies r
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN suppliers s ON r.supplier_id = s.id
    {$whereClause}
    ORDER BY r.name ASC
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$queryParams = array_merge($params, [$perPage, $offset]);
$queryParamTypes = $paramTypes . 'ii';

$remedies = fetchAll($conn, $remediesQuery, $queryParams, $queryParamTypes);

// Get categories and suppliers
$categories = fetchAll($conn, "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$suppliers = fetchAll($conn, "SELECT id, name FROM suppliers ORDER BY name");

// Calculate overall statistics (independent of current filters/search)
$statsQuery = "
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(r.stock_quantity * r.unit_price), 0) AS total_stock_value,
        COALESCE(AVG(r.unit_price), 0) AS avg_price,
        SUM(CASE WHEN r.stock_quantity <= r.reorder_level THEN 1 ELSE 0 END) AS low_stock_count,
        SUM(CASE WHEN r.is_featured = 1 THEN 1 ELSE 0 END) AS featured_count
    FROM remedies r
";
$statsRows = fetchAll($conn, $statsQuery);
$stats = $statsRows[0] ?? [
    'total_products' => 0,
    'total_stock_value' => 0,
    'avg_price' => 0,
    'low_stock_count' => 0,
    'featured_count' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remedies Management | system Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/remedies.css', ENT_QUOTES); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container-fluid py-4">
        
        <!-- Messages -->
        <?php displayMessages(); ?>

        <!-- Page Heading -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle-alt"></i>
                Remedies Management
            </h1>
            <div class="btn-toolbar">
                <span class="text-muted">Manage remedy catalog, stock levels, pricing, and availability.</span>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <?php include __DIR__ . '/partials/remedies_statistics.php'; ?>
        </div>
        
        <!-- Filters -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <?php include __DIR__ . '/partials/filters.php'; ?>
            </div>
        </div>
        
        <!-- Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Remedies List</h5>
                <small class="text-muted">Total: <?php echo number_format($total); ?> remedies</small>
            </div>
            <div>
                <a href="pages/categories.php" title="Manage Categories" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Manage Categories
                </a>
                <a href="?page=add_remedy" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Remedy
                </a>
            </div>
        </div>
        
        <!-- Remedies Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php include __DIR__ . '/partials/remedies_table.php'; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="card-footer">
                    <?php include __DIR__ . '/partials/pagination.php'; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- Modals -->
    <?php include __DIR__ . '/modals/view_remedy.php'; ?>
    <?php include __DIR__ . '/modals/edit_remedy.php'; ?>
    <?php include __DIR__ . '/modals/update_stock.php'; ?>

    <!-- jQuery (Optional - only if you need it for other features) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Remedies JS - MUST BE LAST (cache-busted) -->
    <script>
        window.SYSTEM_BASE_URL = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;
        window.IS_REMEDY_ADMIN = <?php echo $isRemedyAdmin ? 'true' : 'false'; ?>;
    </script>
    <script src="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/js/remedies.js', ENT_QUOTES); ?>?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/../assets/js/remedies.js')); ?>"></script>
    <?php $autoEditId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0; ?>
    <?php if ($autoEditId > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.location.href = '?page=edit_remedy&id=<?php echo $autoEditId; ?>';
        });
    </script>
    <?php endif; ?>
</body>
</html>
