
<?php
session_start();
require_once '../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id']);
$conn = getDBConnection();

// Get supplier details with product count
$query = "SELECT s.*,
         (SELECT COUNT(*) FROM remedies WHERE supplier_id = s.id) as product_count,
         (SELECT SUM(stock_quantity) FROM remedies WHERE supplier_id = s.id) as total_stock,
         (SELECT COUNT(*) FROM remedies WHERE supplier_id = s.id AND stock_quantity <= reorder_level) as low_stock_count
         FROM suppliers s
         WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

if (!$supplier) {
    echo json_encode(['error' => 'Supplier not found']);
    exit;
}

// Get supplier products
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

$supplier['products'] = $products;

echo json_encode($supplier);

$products_stmt->close();
$stmt->close();
$conn->close();

?>