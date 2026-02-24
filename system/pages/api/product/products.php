<?php
require_once __DIR__ . '/../../../includes/database.php';
header('Content-Type: application/json');

$projectRoot = dirname(__DIR__, 4);

function resolveProductImageUrl(string $imagePath): string {
    $value = trim(str_replace('\\', '/', $imagePath));
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

function productImageExistsOnDisk(string $imagePath, string $projectRoot): bool {
    $value = trim(str_replace('\\', '/', $imagePath));
    if ($value === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $value)) {
        return true;
    }

    $relative = ltrim($value, '/');
    $candidates = [
        $projectRoot . '/' . $relative,
        $projectRoot . '/system/' . $relative
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return true;
        }
    }

    return false;
}

try {
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $featured = isset($_GET['featured']) ? boolval($_GET['featured']) : false;
    
    $sql = "
        SELECT r.*, c.name as category_name, c.color as category_color
        FROM remedies r 
        LEFT JOIN categories c ON r.category_id = c.id 
        WHERE r.is_active = 1 
        AND r.stock_quantity > 0
    ";
    
    $params = [];
    
    if ($category_id > 0) {
        $sql .= " AND r.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($featured) {
        $sql .= " AND r.is_featured = 1";
    }
    
    $sql .= " ORDER BY r.is_featured DESC, r.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Add image URLs
    foreach ($products as &$product) {
        $storedImage = trim((string)($product['image_url'] ?? ''));
        if ($storedImage === '' || !productImageExistsOnDisk($storedImage, $projectRoot)) {
            $default_images = [
                1 => 'images/products/default-tea.jpg',
                2 => 'images/products/default-oil.jpg',
                3 => 'images/products/default-capsules.jpg',
                4 => 'images/products/default-liquid.jpg'
            ];
            $product['image_url'] = $default_images[$product['category_id']] ?? 'images/products/default-product.jpg';
        } else {
            $product['image_url'] = resolveProductImageUrl($storedImage);
        }
        $product['available_stock'] = $product['stock_quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading products: ' . $e->getMessage()
    ]);
}
?>
