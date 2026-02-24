<?php
// get_suppliers_details.php - Located at: system/pages/actions/ajax/get_suppliers_details.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in with dashboard privileges
$adminRole = strtolower((string) ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$isAdminLike = in_array($adminRole, ['admin', 'super_admin', 'staff'], true);
$hasDashboardSession = !empty($_SESSION['admin_id']) || (!empty($_SESSION['user_id']) && $isAdminLike);
if (!$hasDashboardSession) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get and validate ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
    exit();
}

try {
    // Include database connection
    require_once '../../../includes/database.php';
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // SIMPLE QUERY FIRST - Get basic supplier info
    $supplier_query = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $conn->prepare($supplier_query);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();
    
    if (!$supplier) {
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        exit();
    }
    
    // Get product count (separate query to avoid issues)
    $count_query = "SELECT COUNT(*) as product_count FROM remedies WHERE supplier_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count = $count_result->fetch_assoc();
    $supplier['product_count'] = $count['product_count'] ?? 0;
    $count_stmt->close();
    
    // Get low stock count
    $low_stock_query = "SELECT COUNT(*) as low_stock_count 
                       FROM remedies 
                       WHERE supplier_id = ? 
                       AND stock_quantity <= reorder_level";
    $low_stmt = $conn->prepare($low_stock_query);
    $low_stmt->bind_param("i", $id);
    $low_stmt->execute();
    $low_result = $low_stmt->get_result();
    $low_stock = $low_result->fetch_assoc();
    $supplier['low_stock_count'] = $low_stock['low_stock_count'] ?? 0;
    $low_stmt->close();
    
    // Get products from this supplier
    $products_query = "SELECT 
                      r.id, r.sku, r.name, r.unit_price, 
                      r.stock_quantity, r.reorder_level, r.is_active,
                      c.name as category_name,
                      COALESCE(s.total_sold, 0) as total_sold,
                      (r.stock_quantity + COALESCE(s.total_sold, 0)) as estimated_previous_stock
                      FROM remedies r
                      LEFT JOIN categories c ON r.category_id = c.id
                      LEFT JOIN (
                        SELECT product_id, SUM(quantity) as total_sold
                        FROM order_items
                        GROUP BY product_id
                      ) s ON s.product_id = r.id
                      WHERE r.supplier_id = ?
                      ORDER BY r.name";
    
    $products_stmt = $conn->prepare($products_query);
    $products_stmt->bind_param("i", $id);
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    $products = $products_result->fetch_all(MYSQLI_ASSOC);
    $products_stmt->close();

    // Attach restocking history for each product from audit logs.
    foreach ($products as &$product) {
        $product_id = (int)($product['id'] ?? 0);
        $restock_query = "SELECT action, created_at
                         FROM audit_log
                         WHERE record_id = ?
                           AND table_name IN ('remedies', 'products')
                           AND action LIKE 'stock_update:%'
                         ORDER BY created_at DESC
                         LIMIT 10";
        $restock_stmt = $conn->prepare($restock_query);
        $restock_stmt->bind_param("i", $product_id);
        $restock_stmt->execute();
        $restock_result = $restock_stmt->get_result();
        $restock_rows = $restock_result ? $restock_result->fetch_all(MYSQLI_ASSOC) : [];
        $restock_stmt->close();

        $history = [];
        foreach ($restock_rows as $entry) {
            $action = (string)($entry['action'] ?? '');
            $updated_to = null;
            if (strpos($action, 'stock_update:') === 0) {
                $updated_to = trim(substr($action, strlen('stock_update:')));
            }
            $history[] = [
                'date' => (string)($entry['created_at'] ?? ''),
                'action' => $action,
                'updated_to' => $updated_to
            ];
        }
        $product['restock_history'] = $history;
        $product['last_restock_date'] = $history[0]['date'] ?? null;
        $product['last_restock_value'] = $history[0]['updated_to'] ?? null;
    }
    unset($product);
    
    // Get sales statistics (FIXED column names)
    $sales_query = "SELECT 
                   COALESCE(SUM(oi.total_price), 0) as total_sales,
                   COALESCE(SUM(oi.quantity), 0) as total_units_sold,
                   COUNT(DISTINCT oi.order_id) as order_count
                   FROM order_items oi
                   INNER JOIN remedies r ON oi.product_id = r.id
                   WHERE r.supplier_id = ?";
    
    $sales_stmt = $conn->prepare($sales_query);
    $sales_stmt->bind_param("i", $id);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    $sales_stats = $sales_result->fetch_assoc() ?: [
        'total_sales' => 0,
        'total_units_sold' => 0,
        'order_count' => 0
    ];
    $sales_stmt->close();
    
    $conn->close();
    
    // Return clean JSON response
    echo json_encode([
        'success' => true,
        'data' => $supplier,
        'products' => $products,
        'sales_stats' => $sales_stats,
        'debug' => [
            'id_received' => $id,
            'products_count' => count($products)
        ]
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log('Supplier details error: ' . $e->getMessage());
    
    // Return safe error message
    echo json_encode([
        'success' => false, 
        'error' => 'Server error occurred',
        'debug' => (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == 1) ? $e->getMessage() : ''
    ]);
}
?>
