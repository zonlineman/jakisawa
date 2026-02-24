<?php
// pages/suppliers/ajax/get_supplier_details.php

define('ROOT_PATH', dirname(__DIR__, 3));
require_once ROOT_PATH . '/includes/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$conn = getDBConnection();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid supplier ID']);
    exit();
}

try {
    // Get supplier details
    $supplier_query = "SELECT s.*, 
                      COUNT(DISTINCT r.id) as product_count,
                      SUM(CASE WHEN r.stock_quantity <= r.reorder_level THEN 1 ELSE 0 END) as low_stock_count,
                      SUM(r.stock_quantity) as total_stock
                      FROM suppliers s
                      LEFT JOIN remedies r ON s.id = r.supplier_id
                      WHERE s.id = ?
                      GROUP BY s.id";
    
    $stmt = $conn->prepare($supplier_query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();
    
    if (!$supplier) {
        echo json_encode(['success' => false, 'error' => 'Supplier not found']);
        exit();
    }
    
    // Get products from this supplier
    $products_query = "SELECT r.*, c.name as category_name 
                      FROM remedies r 
                      LEFT JOIN categories c ON r.category_id = c.id
                      WHERE r.supplier_id = ?
                      ORDER BY r.name";
    
    $products_stmt = $conn->prepare($products_query);
    $products_stmt->bind_param("i", $id);
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    $products = $products_result->fetch_all(MYSQLI_ASSOC);
    $products_stmt->close();
    
    // Get sales statistics
    $sales_query = "SELECT 
                   SUM(oi.quantity * oi.price) as total_sales,
                   SUM(oi.quantity) as total_units_sold,
                   COUNT(DISTINCT oi.order_id) as order_count
                   FROM order_items oi
                   INNER JOIN remedies r ON oi.remedy_id = r.id
                   WHERE r.supplier_id = ?";
    
    $sales_stmt = $conn->prepare($sales_query);
    $sales_stmt->bind_param("i", $id);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    $sales_stats = $sales_result->fetch_assoc();
    $sales_stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => $supplier,
        'products' => $products,
        'sales_stats' => $sales_stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>