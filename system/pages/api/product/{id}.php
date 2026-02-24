<?php
require_once '../../config.php';
header('Content-Type: application/json');

try {
    $product_id = intval($_GET['id'] ?? 0);
    
    $sql = "
        SELECT r.*, c.name as category_name, c.color as category_color
        FROM remedies r 
        LEFT JOIN categories c ON r.category_id = c.id 
        WHERE r.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        echo json_encode([
            'success' => true,
            'data' => $product
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading product: ' . $e->getMessage()
    ]);
}
?>