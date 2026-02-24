<?php
// Start session FIRST
session_start();

// Include config
require_once 'config.php';

function getProductImageCandidates($image_url) {
    $value = trim((string)$image_url);
    if ($value === '') {
        return [];
    }

    if (preg_match('#^https?://#i', $value)) {
        return [['url' => $value, 'file' => null]];
    }

    $relative = ltrim(str_replace('\\', '/', $value), '/');
    $candidates = [];
    $seen = [];

    $addCandidate = static function (string $url, ?string $file) use (&$candidates, &$seen): void {
        $key = $url . '|' . ($file ?? '');
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $candidates[] = ['url' => $url, 'file' => $file];
    };

    if (strpos($relative, 'uploads/') === 0) {
        $addCandidate(systemUrl($relative), __DIR__ . '/system/' . $relative);
    }

    $addCandidate(projectPathUrl($relative), __DIR__ . '/' . $relative);
    $addCandidate(systemUrl($relative), __DIR__ . '/system/' . $relative);

    return $candidates;
}

function getProductImageUrl($image_url) {
    $candidates = getProductImageCandidates($image_url);
    if (empty($candidates)) {
        return null;
    }

    foreach ($candidates as $candidate) {
        if ($candidate['file'] !== null && file_exists($candidate['file'])) {
            return $candidate['url'];
        }
    }

    return $candidates[0]['url'];
}

function productImageExists($image_url) {
    $candidates = getProductImageCandidates($image_url);
    if (empty($candidates)) {
        return false;
    }

    if ($candidates[0]['file'] === null) {
        return true;
    }

    foreach ($candidates as $candidate) {
        if ($candidate['file'] !== null && file_exists($candidate['file'])) {
            return true;
        }
    }

    return false;
}

function parseRemedyVariationLine($line, $fallbackPrice) {
    $line = trim((string)$line);
    if ($line === '') {
        return null;
    }

    $label = '';
    $price = null;

    if (strpos($line, '|') !== false) {
        $parts = explode('|', $line, 2);
        $label = trim((string)$parts[0]);
        $priceRaw = trim((string)$parts[1]);
        $priceRaw = str_ireplace(['ksh', 'kes'], '', $priceRaw);
        $priceRaw = str_replace(',', '', $priceRaw);
        if ($priceRaw !== '' && is_numeric($priceRaw)) {
            $price = (float)$priceRaw;
        }
    } elseif (preg_match('/^(.*?)\s*[:=-]\s*(?:ksh|kes)?\s*([0-9]+(?:\.[0-9]{1,2})?)\s*(?:ksh|kes)?$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $price = (float)$matches[2];
    } elseif (preg_match('/^(.*\S)\s+(?:ksh|kes)\s*([0-9]+(?:\.[0-9]{1,2})?)$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $price = (float)$matches[2];
    } elseif (preg_match('/^(.*\S)\s+([0-9]+(?:\.[0-9]{1,2})?)\s*(?:ksh|kes)?$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $price = (float)$matches[2];
    } else {
        // Backward compatibility for data saved before "Label|Price" format.
        $label = $line;
        $price = (float)$fallbackPrice;
    }

    if ($label === '' || $price === null || $price <= 0) {
        return null;
    }

    return [
        'label' => $label,
        'price' => round($price, 2)
    ];
}

function getRemedyVariationOptions(array $product) {
    $basePrice = !empty($product['discount_price']) && (float)$product['discount_price'] > 0
        ? (float)$product['discount_price']
        : (float)($product['unit_price'] ?? 0);

    $options = [];
    $seen = [];
    $sources = [
        (string)($product['custom_sizes'] ?? ''),
        (string)($product['custom_sachets'] ?? '')
    ];

    foreach ($sources as $source) {
        $lines = preg_split('/\r\n|\r|\n/', $source);
        foreach ($lines as $line) {
            $parsed = parseRemedyVariationLine($line, $basePrice);
            if (!$parsed) {
                continue;
            }

            $normalizedLabel = strtolower(trim(preg_replace('/\s+/', ' ', $parsed['label'])));
            if ($normalizedLabel === '' || isset($seen[$normalizedLabel])) {
                continue;
            }

            $seen[$normalizedLabel] = true;
            $options[] = $parsed;
        }
    }

    return $options;
}

function getRemedyVariationSupport(PDO $pdo) {
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $cached = [
        'remedies_custom_sizes' => false,
        'remedies_custom_sachets' => false,
        'seo_table' => false,
        'seo_custom_sizes' => false,
        'seo_custom_sachets' => false
    ];

    try {
        $remediesColumnsStmt = $pdo->query("SHOW COLUMNS FROM remedies");
        $remediesColumns = $remediesColumnsStmt ? $remediesColumnsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($remediesColumns as $columnRow) {
            $fieldName = strtolower(trim((string)($columnRow['Field'] ?? '')));
            if ($fieldName === 'custom_sizes') {
                $cached['remedies_custom_sizes'] = true;
            } elseif ($fieldName === 'custom_sachets') {
                $cached['remedies_custom_sachets'] = true;
            }
        }
    } catch (Exception $e) {
        // Remedies table introspection failed; keep defaults.
    }

    try {
        $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'remedy_seo_marketing'");
        if (!(bool)$tableCheckStmt->fetchColumn()) {
            return $cached;
        }

        $cached['seo_table'] = true;
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM remedy_seo_marketing");
        $columnRows = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($columnRows as $columnRow) {
            $fieldName = strtolower(trim((string)($columnRow['Field'] ?? '')));
            if ($fieldName === 'custom_sizes') {
                $cached['seo_custom_sizes'] = true;
            } elseif ($fieldName === 'custom_sachets') {
                $cached['seo_custom_sachets'] = true;
            }
        }
    } catch (Exception $e) {
        // Auxiliary table introspection failed; keep currently detected support.
    }

    return $cached;
}

function getRemedyVariationQueryParts(PDO $pdo) {
    $support = getRemedyVariationSupport($pdo);

    $sizeSources = [];
    if ($support['remedies_custom_sizes']) {
        $sizeSources[] = "NULLIF(r.custom_sizes, '')";
    }
    if ($support['seo_custom_sizes']) {
        $sizeSources[] = "NULLIF(m.custom_sizes, '')";
    }

    $sachetSources = [];
    if ($support['remedies_custom_sachets']) {
        $sachetSources[] = "NULLIF(r.custom_sachets, '')";
    }
    if ($support['seo_custom_sachets']) {
        $sachetSources[] = "NULLIF(m.custom_sachets, '')";
    }

    $sizesExpr = !empty($sizeSources) ? ('COALESCE(' . implode(', ', $sizeSources) . ')') : 'NULL';
    $sachetsExpr = !empty($sachetSources) ? ('COALESCE(' . implode(', ', $sachetSources) . ')') : 'NULL';

    $needsSeoJoin = $support['seo_table'] && ($support['seo_custom_sizes'] || $support['seo_custom_sachets']);

    return [
        'select' => ", {$sizesExpr} AS custom_sizes, {$sachetsExpr} AS custom_sachets",
        'join' => $needsSeoJoin
            ? " LEFT JOIN remedy_seo_marketing m ON m.remedy_id = r.id "
            : " "
    ];
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get initial cart count from session
$cart_count = 0;
$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_count += $item['quantity'];
    $cart_total += $item['price'] * $item['quantity'];
}

$isAuthenticated = isset($_SESSION['user_id']);
$authenticatedUserId = $_SESSION['user_id'] ?? null;
$authenticatedUserName = $_SESSION['full_name'] ?? '';
$authenticatedUserEmail = $_SESSION['email'] ?? '';
$authenticatedUserPhone = '';
$authenticatedUserAddress = '';
$authenticatedUserCity = '';
$authenticatedUserPostalCode = '';
$loginUrl = 'login.php';
$signupUrl = 'signup.php';
$logoutUrl = 'logout.php';
$customerContactDisplay = '0792546080 / +254 720 793609';
$customerCallLink = '254792546080';
$customerWhatsAppLink = '254720793609';

if ($isAuthenticated && $authenticatedUserId) {
    $userStmt = $pdo->prepare("SELECT full_name, email, phone, address, city, postal_code FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$authenticatedUserId]);
    $sessionUser = $userStmt->fetch();

    if ($sessionUser) {
        $authenticatedUserName = $sessionUser['full_name'] ?? $authenticatedUserName;
        $authenticatedUserEmail = $sessionUser['email'] ?? $authenticatedUserEmail;
        $authenticatedUserPhone = $sessionUser['phone'] ?? '';
        $authenticatedUserAddress = $sessionUser['address'] ?? '';
        $authenticatedUserCity = $sessionUser['city'] ?? '';
        $authenticatedUserPostalCode = $sessionUser['postal_code'] ?? '';
    }
}

// Get categories for dropdown
$categories_stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $categories_stmt->fetchAll();

$variationQueryParts = getRemedyVariationQueryParts($pdo);
$variationSelectColumns = $variationQueryParts['select'];
$variationJoinClause = $variationQueryParts['join'];

// Get featured products
$products_stmt = $pdo->query("
    SELECT r.*, c.name as category_name {$variationSelectColumns}
    FROM remedies r 
    LEFT JOIN categories c ON r.category_id = c.id 
    {$variationJoinClause}
    WHERE r.is_active = 1 AND r.stock_quantity > 0 
    ORDER BY r.is_featured DESC, r.name 
    LIMIT 12
");
$products = $products_stmt->fetchAll();

$promoProduct = null;
if ($isAuthenticated) {
    $mostBoughtStmt = $pdo->query("
        SELECT 
            r.id,
            r.name,
            r.image_url,
            r.unit_price,
            r.discount_price,
            c.name AS category_name,
            SUM(oi.quantity) AS total_sold
        FROM order_items oi
        INNER JOIN remedies r ON r.id = oi.product_id
        LEFT JOIN categories c ON c.id = r.category_id
        WHERE r.is_active = 1
        GROUP BY r.id, r.name, r.image_url, r.unit_price, r.discount_price, c.name
        ORDER BY total_sold DESC, r.is_featured DESC, r.id DESC
        LIMIT 1
    ");
    $promoProduct = $mostBoughtStmt->fetch();

    if (!$promoProduct) {
        $featuredStmt = $pdo->query("
            SELECT 
                r.id,
                r.name,
                r.image_url,
                r.unit_price,
                r.discount_price,
                c.name AS category_name,
                0 AS total_sold
            FROM remedies r
            LEFT JOIN categories c ON c.id = r.category_id
            WHERE r.is_active = 1
            ORDER BY r.is_featured DESC, r.stock_quantity DESC, r.id DESC
            LIMIT 1
        ");
        $promoProduct = $featuredStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New JAKISAWA SHOP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <style>
        :root {
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary: #ff9800;
            --light: #f5f5f5;
            --dark: #333;
            --gray: #666;
            --light-gray: #eee;
            --border: #ddd;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --info: #2196f3;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header & Navigation */
        header {
            background-color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            position: relative;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .logo i {
            color: var(--primary);
            font-size: 2rem;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .logo span {
            color: var(--secondary);
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: stretch;
        }

        nav {
            position: relative;
        }
        
        nav li {
            display: flex;
        }
        
        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        nav a:hover, nav a.active {
            background-color: var(--primary-light);
            color: white;
        }

        .menu-toggle {
            display: none;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            min-width: 42px;
            line-height: 1;
        }

        .menu-toggle i {
            font-size: 1rem;
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.4rem;
            cursor: pointer;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        /* Cart Dropdown */
        .cart-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: min(350px, calc(100vw - 24px));
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-top: 15px;
            z-index: 1000;
            display: none;
        }
        
        .cart-dropdown.show {
            display: block;
        }
        
        .cart-dropdown-header {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            font-weight: bold;
            color: var(--primary);
        }
        
        .cart-dropdown-items {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .cart-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .cart-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .cart-dropdown-item-img {
            width: 50px;
            height: 50px;
            background: var(--light-gray);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .cart-dropdown-item-info {
            flex: 1;
        }
        
        .cart-dropdown-item-name {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .cart-dropdown-item-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .cart-dropdown-item-quantity {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        .cart-dropdown-footer {
            padding: 15px;
            border-top: 1px solid var(--light-gray);
            background: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }
        
        .cart-dropdown-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .cart-dropdown-buttons {
            display: flex;
            gap: 10px;
        }
        
        .cart-dropdown-buttons .btn {
            flex: 1;
            padding: 8px;
            font-size: 0.9rem;
        }
        
        .cart-empty-message {
            padding: 20px;
            text-align: center;
            color: var(--gray);
        }
        
        /* Main Content */
        main {
            min-height: 70vh;
            padding: 40px 0;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
        }
        
        .section-title {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary);
            color: var(--primary-dark);
        }

        .welcome-promo {
            background: linear-gradient(120deg, #1b5e20, #2e7d32);
            color: #fff;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: grid;
            gap: 8px;
        }

        .welcome-promo h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .welcome-promo .promo-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.95rem;
        }

        .welcome-promo a {
            display: inline-block;
            margin-top: 6px;
            color: #fff;
            text-decoration: underline;
            font-weight: 600;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .product-toolbar {
            background: #fff;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 14px;
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .product-toolbar .toolbar-input,
        .product-toolbar .toolbar-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--dark);
        }

        .product-toolbar .toolbar-btn {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--dark);
            cursor: pointer;
            font-weight: 600;
        }

        .products-empty {
            display: none;
            text-align: center;
            padding: 18px;
            border: 1px dashed var(--border);
            border-radius: 10px;
            color: var(--gray);
            background: #fff;
            margin-bottom: 24px;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-img {
            height: 180px;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--primary);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-name {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: var(--primary-dark);
        }
        
        .product-category {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .product-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .original-price {
            text-decoration: line-through;
            color: var(--gray);
            font-size: 0.9rem;
            margin-right: 5px;
        }
        
        .discount-price {
            color: var(--danger);
            font-weight: bold;
        }
        
        .product-savings {
            color: var(--success);
            font-size: 0.8rem;
            margin-bottom: 10px;
        }

        .product-variation {
            margin-bottom: 12px;
        }

        .product-variation label {
            display: block;
            font-size: 0.82rem;
            color: var(--gray);
            margin-bottom: 4px;
        }

        .variation-select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 0.9rem;
            background: #fff;
        }
        
        .product-stock {
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .in-stock {
            color: var(--success);
        }
        
        .low-stock {
            color: var(--warning);
        }
        
        .out-of-stock {
            color: var(--danger);
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            min-width: 0;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #e68900;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-light);
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Cart Section */
        .cart-item {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .cart-item-info h4 {
            margin-bottom: 5px;
            color: var(--primary-dark);
        }
        
        .cart-item-price {
            font-weight: bold;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .quantity-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .quantity-input {
            width: 70px;
            text-align: center;
            padding: 8px;
            border: 2px solid var(--border);
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
        }
        
        .cart-totals {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .total-row.grand-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
            border-top: 2px solid var(--primary);
            border-bottom: none;
            margin-top: 10px;
        }
        
        /* Forms */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .tracking-message {
            max-width: 700px;
            margin: 0 auto 20px;
            padding: 12px 15px;
            border-radius: 6px;
            display: none;
            font-weight: 500;
        }

        .tracking-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .tracking-message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .recent-orders-panel {
            max-width: 860px;
            margin: 0 auto 25px;
            background: #fff;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 16px;
        }

        .recent-orders-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }

        .recent-orders-list {
            display: grid;
            gap: 10px;
        }

        .recent-order-item {
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 10px 12px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            background: #fff;
        }

        .recent-order-meta {
            font-size: 0.9rem;
            color: var(--gray);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .recent-order-number {
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid var(--primary);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: white;
            margin-top: 20px;
            font-size: 1.2rem;
        }
        
        /* Order Tracking */
        .order-status-tracker {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }
        
        .status-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--light-gray);
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
            border: 3px solid var(--light-gray);
        }
        
        .status-step.active .status-icon {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .status-step.completed .status-icon {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }
        
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .order-items-table th,
        .order-items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .order-items-table th {
            background: var(--primary);
            color: white;
        }
        
        /* Footer */
        footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .footer-section p, .footer-section a {
            color: #ccc;
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
        }
        
        .footer-section a:hover {
            color: white;
        }

        .footer-minute {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.9rem;
            color: #dfe7d6;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
        }

        .footer-minute a {
            color: #dfe7d6;
            text-decoration: none;
        }

        .footer-minute a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        
        /* Hidden receipt for PDF generation */
        #receipt-hidden {
            position: absolute;
            left: -9999px;
            width: 800px;
            background: white;
            padding: 40px;
            z-index: -1;
        }

        .contact-widget {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 1400;
            width: min(290px, calc(100vw - 32px));
        }

        .contact-widget-card {
            background: #ffffff;
            border: 1px solid #dce9dd;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
            padding: 10px;
        }

        .contact-widget-title {
            font-size: 0.85rem;
            color: #4a4a4a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .contact-widget-close {
            border: none;
            background: transparent;
            color: #6b6b6b;
            font-size: 1.05rem;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .contact-widget-close:hover {
            background: #efefef;
            color: #222;
        }

        .contact-widget-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .contact-widget-btn {
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s ease;
        }

        .contact-widget-btn:hover {
            transform: translateY(-1px);
        }

        .contact-widget-btn.call {
            background: #2e7d32;
            color: #fff;
        }

        .contact-widget-btn.whatsapp {
            background: #25d366;
            color: #fff;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 14px;
            }
            
            .header-container {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 15px;
                align-items: center;
            }
            
            .logo {
                width: 100%;
                justify-content: center;
                order: 1;
            }

            .logo h1 {
                font-size: 1.35rem;
            }
            
            nav {
                width: auto;
                position: relative;
                order: 2;
            }

            .menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            
            nav ul {
                display: none;
                gap: 8px;
                position: absolute;
                left: 0;
                right: auto;
                top: calc(100% + 8px);
                width: min(250px, calc(100vw - 28px));
                background: white;
                border: 1px solid var(--light-gray);
                border-radius: 8px;
                box-shadow: var(--shadow);
                padding: 10px;
                z-index: 1200;
                transform-origin: top left;
            }

            nav ul.show {
                display: flex;
                flex-direction: column;
            }
            
            nav a {
                width: 100%;
                text-align: center;
                padding: 8px 10px;
                font-size: 0.95rem;
            }
            
            .cart-icon {
                align-self: center;
                margin-left: auto;
                order: 3;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-toolbar {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 15px;
            }
            
            .cart-dropdown {
                right: 0;
                width: min(320px, calc(100vw - 24px));
            }
            
            .form-container {
                padding: 18px;
            }
            
            .order-status-tracker {
                flex-wrap: wrap;
                gap: 15px;
                justify-content: center;
            }
            
            .status-step {
                min-width: 120px;
                flex: 0 1 120px;
            }
            
            #order-info > div {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
            
            #tracking-results .table-wrapper table {
                min-width: 560px;
            }

            .contact-widget {
                right: 10px;
                bottom: 10px;
                width: min(96vw, 320px);
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text" id="loadingText">Processing...</div>
    </div>

    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="#" class="logo" data-section="products">
                <i class="fas fa-leaf"></i>
                <h1>JAKISAWA <span>SHOP</span></h1>
            </a>
            
            <nav>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fas fa-bars" id="menuToggleIcon"></i>
                </button>
                <ul id="mainNavList">
                    <li><a href="#" class="active" data-section="products">Products</a></li>
                    <li><a href="#" data-section="cart">My Cart (<span id="nav-cart-count"><?php echo $cart_count; ?></span>)</a></li>
                    <li><a href="#" data-section="order">Place Order</a></li>
                    <li><a href="#" data-section="track">Track Order</a></li>
                    <li><a href="#" data-section="profile">My Profile</a></li>
                    <?php if ($isAuthenticated): ?>
                    <li><a href="<?php echo htmlspecialchars($logoutUrl); ?>">Exit</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="cart-icon" id="cartToggle">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cart-count-badge"><?php echo $cart_count; ?></span>
                
                <!-- Cart Dropdown -->
                <div class="cart-dropdown" id="cartDropdown">
                    <div class="cart-dropdown-header">
                        <i class="fas fa-shopping-cart"></i> Shopping Cart
                    </div>
                    <div class="cart-dropdown-items" id="cartDropdownItems">
                        <div class="cart-empty-message">
                            <i class="fas fa-shopping-basket" style="font-size: 2rem; color: #ccc; margin-bottom: 10px;"></i>
                            <p>Your cart is empty</p>
                        </div>
                    </div>
                    <div class="cart-dropdown-footer" id="cartDropdownFooter" style="display: none;">
                        <div class="cart-dropdown-total">
                            <span>Total:</span>
                            <span id="cartDropdownTotal">KES 0.00</span>
                        </div>
                        <div class="cart-dropdown-buttons">
                            <button class="btn btn-outline btn-small" onclick="showSection('cart')">
                                <i class="fas fa-eye"></i> View Cart
                            </button>
                            <button class="btn btn-primary btn-small" onclick="showSection('order')">
                                <i class="fas fa-check"></i> Checkout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="container">
       <!-- Products Section -->
<section id="products-section" class="section active">
    <?php if ($isAuthenticated): ?>
        <?php
            $displayName = $authenticatedUserName !== '' ? $authenticatedUserName : 'Customer';
            $promoLabel = ($promoProduct && (int)($promoProduct['total_sold'] ?? 0) > 0) ? 'Most Bought Product' : 'Featured Product';
            $promoPrice = null;
            if ($promoProduct) {
                $promoPrice = (!empty($promoProduct['discount_price']) && (float)$promoProduct['discount_price'] > 0)
                    ? (float)$promoProduct['discount_price']
                    : (float)$promoProduct['unit_price'];
            }
        ?>
        <div class="welcome-promo">
            <h3>Welcome back, <?php echo htmlspecialchars($displayName); ?>.</h3>
            <?php if ($promoProduct): ?>
                <div><?php echo $promoLabel; ?>: <strong><?php echo htmlspecialchars($promoProduct['name']); ?></strong></div>
                <div class="promo-meta">
                    <span>Category: <?php echo htmlspecialchars($promoProduct['category_name'] ?? 'General'); ?></span>
                    <span>Price: KES <?php echo number_format($promoPrice, 2); ?></span>
                    <?php if ((int)($promoProduct['total_sold'] ?? 0) > 0): ?>
                        <span>Units sold: <?php echo (int)$promoProduct['total_sold']; ?></span>
                    <?php endif; ?>
                </div>
                <a href="product-details.php?id=<?php echo (int)$promoProduct['id']; ?>">View product</a>
            <?php else: ?>
                <div>Welcome. Explore our remedies curated for you.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Our Herbal Remedies</h2>
    
    <div id="alert-container"></div>

    <div class="product-toolbar">
        <input type="text" id="product-search" class="toolbar-input" placeholder="Search products by name...">
        <select id="product-category-filter" class="toolbar-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int)$category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="product-sort" class="toolbar-select">
            <option value="">Sort: Default</option>
            <option value="price_low">Price: Low to High</option>
            <option value="price_high">Price: High to Low</option>
            <option value="name_asc">Name: A to Z</option>
            <option value="name_desc">Name: Z to A</option>
        </select>
        <button type="button" id="product-filter-reset" class="toolbar-btn">Reset</button>
    </div>
    
    <!-- Debug info - remove after fixing -->
    <?php if (empty($products)): ?>
        <div class="alert alert-warning">
            <strong>Debug:</strong> No products found in database.
        </div>
    <?php else: ?>
        <div class="alert alert-info" style="font-size: 12px;">
            <strong>Debug:</strong> Found <?php echo count($products); ?> products.
            <?php foreach ($products as $p): ?>
                <div>Product: <?php echo $p['name']; ?> - Image URL: "<?php echo $p['image_url'] ?? 'NULL'; ?>"</div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
        <div class="product-grid" id="products-container">
            <?php foreach ($products as $product): 
                $stock_class = 'in-stock';
                $stock_text = 'In Stock';
            
            if ($product['stock_quantity'] <= 0) {
                $stock_class = 'out-of-stock';
                $stock_text = 'Out of Stock';
            }
            
                $icon = getProductIcon($product['category_id']);
                
                $has_discount = !empty($product['discount_price']) && $product['discount_price'] > 0;
                $savings = $has_discount ? $product['unit_price'] - $product['discount_price'] : 0;
                $savings_percentage = $has_discount ? round(($savings / $product['unit_price']) * 100) : 0;
                $variationOptions = getRemedyVariationOptions($product);
                $hasVariations = !empty($variationOptions);
                $defaultVariation = $hasVariations ? $variationOptions[0] : null;
                $baseEffectivePrice = $has_discount ? (float)$product['discount_price'] : (float)$product['unit_price'];
                $displayPrice = $hasVariations ? (float)$defaultVariation['price'] : $baseEffectivePrice;
                
                // Check if image exists
                $raw_image_url = $product['image_url'] ?? '';
                $image_url = getProductImageUrl($raw_image_url);
                $has_image = $image_url && productImageExists($raw_image_url);
        ?>
        <div class="product-card"
             data-id="<?php echo $product['id']; ?>"
             data-name="<?php echo htmlspecialchars(strtolower($product['name']), ENT_QUOTES); ?>"
             data-category="<?php echo (int)($product['category_id'] ?? 0); ?>"
             data-price="<?php echo (float)$displayPrice; ?>">
            <div class="product-img">
                <?php if ($has_image): ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                
                <div class="product-price">
                    <?php if ($hasVariations): ?>
                        <span class="js-product-price">KES <?php echo number_format($displayPrice, 2); ?></span>
                        <div class="product-savings">Price depends on selected size/pack.</div>
                    <?php elseif ($has_discount): ?>
                        <span class="original-price">KES <?php echo number_format($product['unit_price'], 2); ?></span>
                        <span class="discount-price">KES <?php echo number_format($product['discount_price'], 2); ?></span>
                        <div class="product-savings">Save KES <?php echo number_format($savings, 2); ?> (<?php echo $savings_percentage; ?>%)</div>
                    <?php else: ?>
                        <span class="js-product-price">KES <?php echo number_format($displayPrice, 2); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($hasVariations): ?>
                    <div class="product-variation">
                        <label for="variation-<?php echo (int)$product['id']; ?>">Size / Pack</label>
                        <select class="variation-select" id="variation-<?php echo (int)$product['id']; ?>" data-product-id="<?php echo (int)$product['id']; ?>">
                            <?php foreach ($variationOptions as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['label'], ENT_QUOTES); ?>" data-price="<?php echo number_format((float)$option['price'], 2, '.', ''); ?>">
                                    <?php echo htmlspecialchars($option['label']); ?> - KES <?php echo number_format((float)$option['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="product-stock <?php echo $stock_class; ?>"><?php echo $stock_text; ?></div>
                <div class="product-actions">
                    <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline btn-small">
                        <i class="fas fa-info-circle"></i> Details
                    </a>
                    <button class="btn btn-primary btn-small add-to-cart" 
                            data-id="<?php echo $product['id']; ?>"
                            <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div id="products-empty-message" class="products-empty">No products match your search/filter.</div>
</section>


        <!-- Cart Section -->
        <section id="cart-section" class="section">
            <h2 class="section-title">My Shopping Cart</h2>
            
            <div id="cart-empty" style="text-align: center; padding: 40px; <?php echo $cart_count > 0 ? 'display: none;' : ''; ?>">
                <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
                <h3>Your cart is empty</h3>
                <p>Add some herbal remedies to get started!</p>
                <button class="btn btn-primary" style="margin-top: 20px;" data-section="products">Browse Products</button>
            </div>
            
            <div id="cart-content" style="<?php echo $cart_count > 0 ? '' : 'display: none;'; ?>">
                <div id="cart-items-container"></div>
                <div class="cart-totals" id="cart-totals"></div>
                <button id="checkout-btn" class="btn btn-primary btn-block" style="margin-top: 20px;">Proceed to Checkout</button>
            </div>
        </section>
        
        <!-- Order Section -->
        <section id="order-section" class="section">
            <h2 class="section-title">Checkout</h2>
            
            <div class="form-container">
                <?php if (!$isAuthenticated): ?>
                <div style="background:#fff3cd;border:1px solid #ffe69c;color:#664d03;padding:15px;border-radius:6px;margin-bottom:20px;">
                    <p style="margin:0 0 10px 0;"><strong>Sign in required:</strong> You must sign in before placing an order.</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn btn-primary">Sign In</a>
                        <a href="<?php echo htmlspecialchars($signupUrl); ?>" class="btn btn-outline">Create Account</a>
                    </div>
                </div>
                <?php else: ?>
                <form id="order-form">
                    <div class="form-group">
                        <label for="customer-name">Full Name *</label>
                        <input type="text" id="customer-name" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserName); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer-email">Email *</label>
                        <input type="email" id="customer-email" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserEmail); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer-phone">Phone *</label>
                        <input type="text" id="customer-phone" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserPhone); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer-alt-phone">Alternative Phone</label>
                        <input type="text" id="customer-alt-phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping-address">Shipping Address *</label>
                        <textarea id="shipping-address" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping-city">City</label>
                        <input type="text" id="shipping-city" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping-postal-code">Postal Code</label>
                        <input type="text" id="shipping-postal-code" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-method">Payment Method *</label>
                        <select id="payment-method" class="form-control" required>
                            <option value="">Select Payment Method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="cash" selected>Cash on Delivery</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction-id">Transaction ID (if paid)</label>
                        <input type="text" id="transaction-id" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="order-notes">Order Notes (Optional)</label>
                        <textarea id="order-notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div id="order-summary" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <h4>Order Summary</h4>
                        <div id="summary-items"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        Place Order & Download Receipt
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <!-- Profile Section -->
        <section id="profile-section" class="section">
            <h2 class="section-title">My Profile</h2>
            <div class="form-container">
                <?php if (!$isAuthenticated): ?>
                <div style="background:#fff3cd;border:1px solid #ffe69c;color:#664d03;padding:15px;border-radius:6px;margin-bottom:20px;">
                    <p style="margin:0 0 10px 0;"><strong>Sign in required:</strong> You must sign in to manage profile.</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn btn-primary">Sign In</a>
                        <a href="<?php echo htmlspecialchars($signupUrl); ?>" class="btn btn-outline">Create Account</a>
                    </div>
                </div>
                <?php else: ?>
                <form id="profile-form">
                    <div class="form-group">
                        <label for="profile-full-name">Full Name *</label>
                        <input type="text" id="profile-full-name" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserName); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email *</label>
                        <input type="email" id="profile-email" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserEmail); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-phone">Phone</label>
                        <input type="text" id="profile-phone" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserPhone); ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile-address">Address</label>
                        <textarea id="profile-address" class="form-control" rows="3"><?php echo htmlspecialchars($authenticatedUserAddress); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="profile-city">City</label>
                        <input type="text" id="profile-city" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserCity); ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile-postal-code">Postal Code</label>
                        <input type="text" id="profile-postal-code" class="form-control" value="<?php echo htmlspecialchars($authenticatedUserPostalCode); ?>">
                    </div>

                    <h4 style="margin:20px 0 10px;">Change Password (Optional)</h4>
                    <div class="form-group">
                        <label for="profile-current-password">Current Password</label>
                        <input type="password" id="profile-current-password" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="profile-new-password">New Password</label>
                        <input type="password" id="profile-new-password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="profile-confirm-password">Confirm New Password</label>
                        <input type="password" id="profile-confirm-password" class="form-control" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Save Profile</button>
                </form>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Track Order Section -->
        <section id="track-section" class="section">
            <h2 class="section-title">Track Your Order</h2>

            <?php if ($isAuthenticated): ?>
            <div class="recent-orders-panel">
                <div class="recent-orders-head">
                    <h3 style="margin:0;font-size:1.1rem;">Recent Orders</h3>
                    <small style="color:#666;">Click any order number to track quickly</small>
                </div>
                <div id="recent-orders-list" class="recent-orders-list">
                    <div style="color:#666;">Loading recent orders...</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="max-width: 500px; margin: 0 auto 40px;">
                <div class="form-group">
                    <label for="order-number">Enter Your Order Number</label>
                    <input type="text" id="order-number" class="form-control" placeholder="e.g., ORD-20260213-ABC123">
                </div>
                <button id="track-btn" class="btn btn-primary btn-block">Track Order</button>
            </div>

            <div id="tracking-message" class="tracking-message"></div>
            
            <div id="tracking-results" style="display: none;">
                <h3>Order Details</h3>
                <div id="order-info" style="background: white; padding: 30px; border-radius: 8px; margin: 20px 0;"></div>
                
                <div class="order-status-tracker" id="order-status-tracker"></div>
                
                <h4>Order Items</h4>
                <div class="table-wrapper">
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="order-items-list"></tbody>
                    </table>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button onclick="downloadReceipt()" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Receipt Again
                    </button>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Hidden Receipt Template for PDF -->
    <div id="receipt-hidden"></div>
    
    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <div class="footer-section">
                <h3>JAKISAWA SHOP</h3>
                <p>Traditional herbal remedies for modern wellness.</p>
                <p>Nairobi, Kenya</p>
            </div>
            
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fas fa-phone"></i> 0792546080 / +254 720 793609</p>
                <p><i class="fas fa-envelope"></i> jakisawa@jakisawashop.co.ke</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="#" data-section="products">Products</a>
                <a href="#" data-section="track">Track Order</a>
                <a href="#" data-section="order">Place Order</a>
                <a href="#" data-section="profile">My Profile</a>
                <a href="<?php echo htmlspecialchars($loginUrl); ?>">Sign In</a>
                <a href="<?php echo htmlspecialchars($signupUrl); ?>">Sign Up</a>
                <?php if ($isAuthenticated): ?>
                <a href="<?php echo htmlspecialchars($logoutUrl); ?>">Exit</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="container footer-minute">
            <span>Developer: Arumansi John</span>
            <a href="tel:0741351755">0741351755</a>
            <a href="mailto:johnarumansi@gmail.com">johnarumansi@gmail.com</a>
        </div>
    </footer>

    <div class="contact-widget" aria-label="Customer contact widget">
        <div class="contact-widget-card">
            <div class="contact-widget-title">
                <span>Need help? Contact us: <?php echo htmlspecialchars($customerContactDisplay); ?></span>
                <button type="button" id="close-contact-widget" class="contact-widget-close" aria-label="Close contact widget">&times;</button>
            </div>
            <div class="contact-widget-actions">
                <a href="tel:+<?php echo htmlspecialchars($customerCallLink); ?>" class="contact-widget-btn call" title="Call us now">
                    <i class="fas fa-phone"></i> Call
                </a>
                <a href="https://wa.me/<?php echo htmlspecialchars($customerWhatsAppLink); ?>?text=Hello%20JAKISAWA%20SHOP%2C%20I%20need%20help%20with%20a%20product."
                   target="_blank"
                   rel="noopener noreferrer"
                   class="contact-widget-btn whatsapp"
                   title="Chat on WhatsApp">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Load scripts in correct order -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <?php
    $customerIconTooltipsUrl = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/js/icon-tooltips.js';
    ?>
    <script src="<?php echo htmlspecialchars($customerIconTooltipsUrl, ENT_QUOTES); ?>?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/system/assets/js/icon-tooltips.js')); ?>"></script>
    
    <script>
        // Global variables
        let currentOrderNumber = null;
        let currentOrderData = null;
        const IS_AUTHENTICATED = <?php echo $isAuthenticated ? 'true' : 'false'; ?>;
        const LOGIN_URL = <?php echo json_encode($loginUrl); ?>;

        // Helper functions
        function showLoadingOverlay(text = 'Processing...') {
            $('#loadingText').text(text);
            $('#loadingOverlay').addClass('active');
        }
        
        function hideLoadingOverlay() {
            $('#loadingOverlay').removeClass('active');
        }
        
        function showAlert(message, type = 'info') {
            const alertContainer = $('#alert-container');
            const alert = $(`<div class="alert alert-${type}">${message}</div>`);
            alertContainer.html(alert);
            alert.show();
            
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function getProductIcon(categoryId) {
            switch(String(categoryId)) {
                case '1': return 'fa-mug-hot';
                case '2': return 'fa-tint';
                case '3': return 'fa-capsules';
                case '4': return 'fa-flask';
                default: return 'fa-leaf';
            }
        }
        
        // Check if jsPDF is loaded
        function isJsPDFLoaded() {
            return typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined';
        }

        function showTrackingMessage(message, type = 'error', showProductsAction = false) {
            const safeMessage = escapeHtml(message);
            const actionHtml = showProductsAction ? `
                <div style="margin-top: 10px;">
                    <button type="button" class="btn btn-outline btn-small" id="tracking-products-btn">
                        <i class="fas fa-shopping-bag"></i> Go to Products
                    </button>
                </div>
            ` : '';

            $('#tracking-message')
                .removeClass('error info')
                .addClass(type)
                .html(`<div>${safeMessage}</div>${actionHtml}`)
                .show();
        }

        function clearTrackingMessage() {
            $('#tracking-message').hide().text('').removeClass('error info');
        }

        function renderRecentOrders(orders) {
            if (!IS_AUTHENTICATED) {
                return;
            }
            const $list = $('#recent-orders-list');
            if (!$list.length) {
                return;
            }

            if (!Array.isArray(orders) || orders.length === 0) {
                $list.html('<div style="color:#666;">No previous orders yet.</div>');
                return;
            }

            let html = '';
            orders.forEach(order => {
                const orderNumber = String(order.order_number || '');
                const orderDate = order.created_at ? new Date(order.created_at).toLocaleString() : '';
                const orderStatus = String(order.order_status || 'pending').toUpperCase();
                const paymentStatus = String(order.payment_status || 'pending').toUpperCase();
                const total = Number(order.total_amount || 0).toFixed(2);

                html += `
                    <div class="recent-order-item">
                        <div>
                            <div class="recent-order-number">${escapeHtml(orderNumber)}</div>
                            <div class="recent-order-meta">
                                <span>${escapeHtml(orderDate)}</span>
                                <span>Order: ${escapeHtml(orderStatus)}</span>
                                <span>Payment: ${escapeHtml(paymentStatus)}</span>
                                <span>Total: KES ${escapeHtml(total)}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline btn-small recent-order-track" data-order-number="${escapeHtml(orderNumber)}">
                            Track
                        </button>
                    </div>
                `;
            });

            $list.html(html);
        }

        function loadRecentOrders() {
            if (!IS_AUTHENTICATED) {
                return;
            }
            const $list = $('#recent-orders-list');
            if (!$list.length) {
                return;
            }

            $.ajax({
                url: 'api.php?action=my-orders&limit=12',
                method: 'GET',
                success: function(response) {
                    if (response && response.success) {
                        renderRecentOrders(response.data || []);
                    } else {
                        $list.html('<div style="color:#a94442;">Could not load recent orders.</div>');
                    }
                },
                error: function() {
                    $list.html('<div style="color:#a94442;">Could not load recent orders.</div>');
                }
            });
        }
        
        // Show section
        function showSection(sectionName) {
            if ((sectionName === 'order' || sectionName === 'profile') && !IS_AUTHENTICATED) {
                showAlert('Please sign in to access this section.', 'warning');
            }

            const targetSection = $(`#${sectionName}-section`);
            if (!targetSection.length) {
                showAlert(`Section "${sectionName}" is not available.`, 'error');
                return;
            }

            $('.section').removeClass('active');
            targetSection.addClass('active');
            
            $('nav a').removeClass('active');
            $(`nav a[data-section="${sectionName}"]`).addClass('active');
            $('#mainNavList').removeClass('show');
            $('#menuToggleIcon').removeClass('fa-times').addClass('fa-bars');
            
            if (sectionName === 'cart' || sectionName === 'order') {
                updateCartUI();
            }
            if (sectionName === 'track') {
                loadRecentOrders();
            }
            
            // Close cart dropdown when navigating
            $('#cartDropdown').removeClass('show');

            const main = document.querySelector('main.container');
            if (main) {
                main.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        // Update cart dropdown
        function updateCartDropdown() {
            $.ajax({
                url: 'api.php?action=cart',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const cart = response.data;
                        const summary = response.summary;
                        
                        const dropdownItems = $('#cartDropdownItems');
                        const dropdownFooter = $('#cartDropdownFooter');
                        
                        if (cart.length === 0) {
                            dropdownItems.html(`
                                <div class="cart-empty-message">
                                    <i class="fas fa-shopping-basket" style="font-size: 2rem; color: #ccc; margin-bottom: 10px;"></i>
                                    <p>Your cart is empty</p>
                                    <button class="btn btn-primary btn-small" style="margin-top: 10px;" onclick="showSection('products')">
                                        Browse Products
                                    </button>
                                </div>
                            `);
                            dropdownFooter.hide();
                        } else {
                            let itemsHtml = '';
                            
                            cart.forEach(item => {
                                const variationLine = item.variation_label
                                    ? `<div class="cart-dropdown-item-quantity">${escapeHtml(item.variation_label)}</div>`
                                    : '';
                                itemsHtml += `
                                    <div class="cart-dropdown-item">
                                        <div class="cart-dropdown-item-img">
                                            <i class="fas ${getProductIcon(item.category_id || '1')}"></i>
                                        </div>
                                        <div class="cart-dropdown-item-info">
                                            <div class="cart-dropdown-item-name">${escapeHtml(item.name)}</div>
                                            ${variationLine}
                                            <div class="cart-dropdown-item-price">KES ${parseFloat(item.price).toFixed(2)}</div>
                                            <div class="cart-dropdown-item-quantity">Qty: ${item.quantity}</div>
                                        </div>
                                        <div>
                                            <button class="btn btn-outline btn-small remove-from-dropdown" data-key="${escapeHtml(item.cart_key || '')}" data-id="${item.id}" title="Remove">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            dropdownItems.html(itemsHtml);
                            $('#cartDropdownTotal').text(`KES ${summary.total_amount.toFixed(2)}`);
                            dropdownFooter.show();
                        }
                        
                        // Update cart count badge
                        $('#nav-cart-count').text(summary.item_count);
                        $('#cart-count-badge').text(summary.item_count);
                    }
                }
            });
        }
        
        // Update cart UI
        function updateCartUI() {
            $.ajax({
                url: 'api.php?action=cart',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const cart = response.data;
                        const summary = response.summary;
                        
                        // Update counts
                        $('#nav-cart-count').text(summary.item_count);
                        $('#cart-count-badge').text(summary.item_count);
                        
                        // Update dropdown if it's open
                        if ($('#cartDropdown').hasClass('show')) {
                            updateCartDropdown();
                        }
                        
                        if (cart.length === 0) {
                            $('#cart-empty').show();
                            $('#cart-content').hide();
                        } else {
                            $('#cart-empty').hide();
                            $('#cart-content').show();
                            
                            // Display cart items
                            let itemsHtml = '';
                            cart.forEach(item => {
                                const itemTotal = parseFloat(item.price) * parseFloat(item.quantity);
                                const hasDiscount = item.regular_price && item.regular_price > item.price;
                                const variationLine = item.variation_label
                                    ? `<div>Variation: ${escapeHtml(item.variation_label)}</div>`
                                    : '';
                                
                                itemsHtml += `
                                    <div class="cart-item">
                                        <div class="cart-item-info">
                                            <h4>${escapeHtml(item.name)}</h4>
                                            ${variationLine}
                                            <div>SKU: ${escapeHtml(item.sku || 'N/A')}</div>
                                            ${hasDiscount ? `
                                                <div style="color: #999; text-decoration: line-through;">KES ${parseFloat(item.regular_price).toFixed(2)}</div>
                                                <div class="cart-item-price" style="color: var(--danger);">KES ${parseFloat(item.price).toFixed(2)}</div>
                                                <div style="color: var(--success); font-size: 0.9rem;">Save KES ${(item.regular_price - item.price).toFixed(2)}</div>
                                            ` : `
                                                <div class="cart-item-price">KES ${parseFloat(item.price).toFixed(2)}</div>
                                            `}
                                        </div>
                                        <div class="cart-item-quantity">
                                            <button class="quantity-btn decrease-quantity" data-key="${escapeHtml(item.cart_key || '')}" data-id="${item.id}">-</button>
                                            <input type="number" class="quantity-input" data-key="${escapeHtml(item.cart_key || '')}" data-id="${item.id}" value="${item.quantity}" min="1">
                                            <button class="quantity-btn increase-quantity" data-key="${escapeHtml(item.cart_key || '')}" data-id="${item.id}">+</button>
                                        </div>
                                        <div>
                                            <strong>KES ${itemTotal.toFixed(2)}</strong>
                                        </div>
                                        <div>
                                            <button class="btn btn-outline btn-small remove-item" data-key="${escapeHtml(item.cart_key || '')}" data-id="${item.id}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            $('#cart-items-container').html(itemsHtml);
                            
                            // Display cart totals
                            let totalsHtml = '<h3>Cart Totals</h3>';
                            totalsHtml += `<div class="total-row"><span>Subtotal:</span><span>KES ${summary.subtotal.toFixed(2)}</span></div>`;
                            
                            if (summary.discount_amount > 0) {
                                totalsHtml += `<div class="total-row" style="color: var(--success);"><span>Discount:</span><span>-KES ${summary.discount_amount.toFixed(2)}</span></div>`;
                            }
                            
                            totalsHtml += `<div class="total-row"><span>Shipping:</span><span>KES ${summary.shipping_cost.toFixed(2)}</span></div>`;
                            totalsHtml += `<div class="total-row"><span>Tax (16%):</span><span>KES ${summary.tax_amount.toFixed(2)}</span></div>`;
                            totalsHtml += `<div class="total-row grand-total"><span>Total:</span><span>KES ${summary.total_amount.toFixed(2)}</span></div>`;
                            
                            if (summary.savings > 0) {
                                totalsHtml += `<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px; text-align: center;">
                                    <strong style="color: var(--success);">ðŸŽ‰ You're saving KES ${summary.savings.toFixed(2)} on this order!</strong>
                                </div>`;
                            }
                            
                            $('#cart-totals').html(totalsHtml);
                            
                            // Update order summary
                            updateOrderSummary(cart, summary);
                        }
                    }
                }
            });
        }
        
        // Update order summary in checkout
        function updateOrderSummary(cart, summary) {
            let summaryHtml = '';
            
            cart.forEach(item => {
                const itemTotal = parseFloat(item.price) * parseFloat(item.quantity);
                const itemLabel = item.variation_label
                    ? `${item.name} (${item.variation_label})`
                    : item.name;
                summaryHtml += `
                    <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                        <span>${escapeHtml(itemLabel)} Ã— ${item.quantity}</span>
                        <span>KES ${itemTotal.toFixed(2)}</span>
                    </div>
                `;
            });
            
            summaryHtml += '<hr style="margin: 10px 0;">';
            summaryHtml += `<div style="display: flex; justify-content: space-between; margin: 5px 0;"><span>Subtotal</span><span>KES ${summary.subtotal.toFixed(2)}</span></div>`;
            
            if (summary.discount_amount > 0) {
                summaryHtml += `<div style="display: flex; justify-content: space-between; margin: 5px 0; color: green;"><span>Discount</span><span>-KES ${summary.discount_amount.toFixed(2)}</span></div>`;
            }
            
            summaryHtml += `<div style="display: flex; justify-content: space-between; margin: 5px 0;"><span>Shipping</span><span>KES ${summary.shipping_cost.toFixed(2)}</span></div>`;
            summaryHtml += `<div style="display: flex; justify-content: space-between; margin: 5px 0;"><span>Tax (16%)</span><span>KES ${summary.tax_amount.toFixed(2)}</span></div>`;
            summaryHtml += `<div style="display: flex; justify-content: space-between; margin: 10px 0; font-weight: bold; font-size: 1.2rem; border-top: 2px solid #ddd; padding-top: 10px;"><span>Total</span><span>KES ${summary.total_amount.toFixed(2)}</span></div>`;
            
            $('#summary-items').html(summaryHtml);
        }
        
        // Add to cart
        function addToCart(productId, quantity = 1, variationLabel = '') {
            const payload = {
                product_id: productId,
                quantity: quantity
            };
            if (variationLabel) {
                payload.variation_label = variationLabel;
            }

            $.ajax({
                url: 'api.php?action=add-to-cart',
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Added to cart!', 'success');
                        updateCartUI();
                        updateCartDropdown(); // Update dropdown immediately
                    } else {
                        showAlert(response.message, 'error');
                    }
                }
            });
        }
        
        // Update cart quantity
        function updateCartQuantity(cartKey, productId, quantity) {
            if (quantity <= 0) {
                removeFromCart(cartKey, productId);
                return;
            }
            
            $.ajax({
                url: 'api.php?action=update-cart',
                method: 'POST',
                data: JSON.stringify({ cart_key: cartKey, product_id: productId, quantity: quantity }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        updateCartUI();
                        updateCartDropdown(); // Update dropdown immediately
                    } else {
                        showAlert(response.message, 'error');
                        updateCartUI();
                    }
                }
            });
        }
        
        // Remove from cart
        function removeFromCart(cartKey, productId) {
            $.ajax({
                url: 'api.php?action=remove-from-cart',
                method: 'POST',
                data: JSON.stringify({ cart_key: cartKey, product_id: productId }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Removed from cart', 'info');
                        updateCartUI();
                        updateCartDropdown(); // Update dropdown immediately
                    }
                }
            });
        }
        
        // Generate receipt HTML
        function generateReceiptHTML(orderData) {
            const order = orderData;
            const orderDate = new Date(order.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let html = `
                <div style="max-width: 800px; margin: 0 auto; padding: 40px; background: white; font-family: Arial, sans-serif;">
                    <div style="text-align: center; border-bottom: 3px solid #2e7d32; padding-bottom: 20px; margin-bottom: 30px;">
                        <h1 style="color: #2e7d32; margin-bottom: 5px;">JAKISAWA SHOP</h1>
                        <p style="color: #666; font-style: italic;">Natural Healing, Traditional Care</p>
                        <p style="font-size: 12px; color: #666;">Kasarani, Nairobi - Kenya | Phone: 0792546080 / +254 720 793609</p>
                    </div>
                    
                    <h2 style="text-align: center; margin: 20px 0;">ORDER RECEIPT</h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <h3 style="color: #2e7d32; font-size: 14px; margin-bottom: 10px; border-bottom: 2px solid #2e7d32; padding-bottom: 5px;">ORDER INFORMATION</h3>
                            <p><strong>Order Number:</strong> ${order.order_number}</p>
                            <p><strong>Order Date:</strong> ${orderDate}</p>
                            <p><strong>Payment Method:</strong> ${order.payment_method || 'Cash'}</p>
                            ${order.transaction_id ? `<p><strong>Transaction ID:</strong> ${order.transaction_id}</p>` : ''}
                            <p><strong>Payment Status:</strong> <span style="color: #ff9800;">${order.payment_status.toUpperCase()}</span></p>
                            <p><strong>Order Status:</strong> <span style="color: #2e7d32;">${order.order_status.toUpperCase()}</span></p>
                        </div>
                        
                        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <h3 style="color: #2e7d32; font-size: 14px; margin-bottom: 10px; border-bottom: 2px solid #2e7d32; padding-bottom: 5px;">CUSTOMER INFORMATION</h3>
                            <p><strong>Name:</strong> ${order.customer_name}</p>
                            <p><strong>Email:</strong> ${order.customer_email}</p>
                            <p><strong>Phone:</strong> ${order.customer_phone}</p>
                            ${order.customer_alt_phone ? `<p><strong>Alt Phone:</strong> ${order.customer_alt_phone}</p>` : ''}
                        </div>
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 30px;">
                        <h3 style="color: #2e7d32; font-size: 14px; margin-bottom: 10px; border-bottom: 2px solid #2e7d32; padding-bottom: 5px;">SHIPPING ADDRESS</h3>
                        <p>${order.shipping_address}</p>
                        ${order.shipping_city ? `<p>${order.shipping_city}${order.shipping_postal_code ? ', ' + order.shipping_postal_code : ''}</p>` : ''}
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; margin: 30px 0;">
                        <thead>
                            <tr style="background: #2e7d32; color: white;">
                                <th style="padding: 12px; text-align: left;">#</th>
                                <th style="padding: 12px; text-align: left;">Product</th>
                                <th style="padding: 12px; text-align: center;">Qty</th>
                                <th style="padding: 12px; text-align: right;">Unit Price</th>
                                <th style="padding: 12px; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (order.items && order.items.length > 0) {
                order.items.forEach((item, index) => {
                    html += `
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 12px;">${index + 1}</td>
                            <td style="padding: 12px;">${item.product_name}</td>
                            <td style="padding: 12px; text-align: center;">${item.quantity}</td>
                            <td style="padding: 12px; text-align: right;">KES ${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td style="padding: 12px; text-align: right;">KES ${parseFloat(item.total_price).toFixed(2)}</td>
                        </tr>
                    `;
                });
            }
            
            html += `
                        </tbody>
                    </table>
                    
                    ${order.discount_amount > 0 ? `
                        <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; border-radius: 5px;">
                            <strong style="color: #d32f2f; font-size: 16px;">ðŸŽ‰ You saved KES ${parseFloat(order.discount_amount).toFixed(2)} on this order!</strong>
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #2e7d32;">
                        <table style="width: 100%; max-width: 400px; margin-left: auto;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px; text-align: right; color: #666;">Subtotal:</td>
                                <td style="padding: 10px; text-align: right; font-weight: bold;">KES ${parseFloat(order.subtotal).toFixed(2)}</td>
                            </tr>
                            ${order.discount_amount > 0 ? `
                                <tr style="border-bottom: 1px solid #ddd; color: #d32f2f;">
                                    <td style="padding: 10px; text-align: right; font-weight: bold;">Discount:</td>
                                    <td style="padding: 10px; text-align: right; font-weight: bold;">-KES ${parseFloat(order.discount_amount).toFixed(2)}</td>
                                </tr>
                            ` : ''}
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px; text-align: right; color: #666;">Shipping:</td>
                                <td style="padding: 10px; text-align: right; font-weight: bold;">KES ${parseFloat(order.shipping_cost).toFixed(2)}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px; text-align: right; color: #666;">Tax (16%):</td>
                                <td style="padding: 10px; text-align: right; font-weight: bold;">KES ${parseFloat(order.tax_amount).toFixed(2)}</td>
                            </tr>
                            <tr style="background: #2e7d32; color: white;">
                                <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px;">TOTAL:</td>
                                <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px;">KES ${parseFloat(order.total_amount).toFixed(2)}</td>
                            </tr>
                        </table>
                    </div>
                    
                    ${order.notes ? `
                        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-top: 30px;">
                            <h3 style="color: #2e7d32; font-size: 14px; margin-bottom: 10px;">ORDER NOTES</h3>
                            <p>${order.notes}</p>
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 40px; text-align: center; padding-top: 20px; border-top: 2px solid #ddd; font-size: 12px; color: #666;">
                        <p style="font-size: 18px; color: #2e7d32; font-weight: bold; margin-bottom: 10px;">Thank you for your order!</p>
                        <p>For any questions or concerns, please contact us at:</p>
                        <p>Phone: 0792546080 / +254 720 793609 | Email: jakisawa@jakisawashop.co.ke</p>
                        <p style="margin-top: 15px; font-size: 11px;">This is a computer-generated receipt. No signature required.</p>
                    </div>
                </div>
            `;
            
            return html;
        }
        
        // Generate and download PDF
        function generateAndDownloadPDF() {
            if (!currentOrderData) {
                hideLoadingOverlay();
                showAlert('Order data not available', 'error');
                return;
            }

            if (!isJsPDFLoaded()) {
                showAlert('PDF library loading, please wait...', 'info');
                setTimeout(generateAndDownloadPDF, 500);
                return;
            }

            const receiptHTML = generateReceiptHTML(currentOrderData);
            $('#receipt-hidden').html(receiptHTML);
            
            const element = document.getElementById('receipt-hidden');
            
            if (!element) {
                hideLoadingOverlay();
                showAlert('Error preparing receipt', 'error');
                return;
            }
            
            // Small delay to ensure DOM is updated
            setTimeout(() => {
                html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    windowWidth: 800,
                    windowHeight: element.scrollHeight
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    
                    try {
                        // Use window.jspdf.jsPDF
                        const pdf = new window.jspdf.jsPDF({
                            orientation: 'p',
                            unit: 'mm',
                            format: 'a4'
                        });
                        
                        const pdfWidth = pdf.internal.pageSize.getWidth();
                        const pdfHeight = pdf.internal.pageSize.getHeight();
                        const imgWidth = canvas.width;
                        const imgHeight = canvas.height;
                        const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
                        const imgX = (pdfWidth - imgWidth * ratio) / 2;
                        const imgY = 0;
                        
                        pdf.addImage(imgData, 'PNG', imgX, imgY, imgWidth * ratio, imgHeight * ratio);
                        pdf.save(`Receipt_${currentOrderData.order_number}.pdf`);
                        
                        hideLoadingOverlay();
                        showAlert('Receipt downloaded successfully!', 'success');
                    } catch (pdfError) {
                        console.error('PDF creation error:', pdfError);
                        hideLoadingOverlay();
                        showAlert('Error creating PDF', 'error');
                    }
                }).catch(error => {
                    console.error('HTML2Canvas error:', error);
                    hideLoadingOverlay();
                    showAlert('Failed to generate PDF. Please try again.', 'error');
                });
            }, 100);
        }
        
        // Download receipt as PDF
        function downloadReceipt(orderNumber = null) {
            const orderNum = orderNumber || currentOrderNumber;
            
            if (!orderNum) {
                showAlert('No order number provided', 'error');
                return;
            }
            
            showLoadingOverlay('Generating receipt PDF...');
            
            // Fetch order data if not already available
            if (!currentOrderData || currentOrderData.order_number !== orderNum) {
                $.ajax({
                    url: `api.php?action=order&order_number=${encodeURIComponent(orderNum)}`,
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            currentOrderData = response.data;
                            generateAndDownloadPDF();
                        } else {
                            hideLoadingOverlay();
                            showAlert('Order not found', 'error');
                        }
                    },
                    error: function() {
                        hideLoadingOverlay();
                        showAlert('Failed to fetch order data', 'error');
                    }
                });
            } else {
                generateAndDownloadPDF();
            }
        }
        
        // Place order
        function placeOrder(event) {
            event.preventDefault();

            if (!IS_AUTHENTICATED) {
                showAlert('Please sign in to place an order.', 'error');
                setTimeout(() => {
                    window.location.href = LOGIN_URL;
                }, 1200);
                return;
            }
            
            const formData = {
                customer_name: $('#customer-name').val(),
                customer_email: $('#customer-email').val(),
                customer_phone: $('#customer-phone').val(),
                customer_alt_phone: $('#customer-alt-phone').val(),
                shipping_address: $('#shipping-address').val(),
                shipping_city: $('#shipping-city').val(),
                shipping_postal_code: $('#shipping-postal-code').val(),
                payment_method: $('#payment-method').val(),
                transaction_id: $('#transaction-id').val(),
                notes: $('#order-notes').val()
            };
            
            // Validate required fields
            if (!formData.customer_name || !formData.customer_email || !formData.customer_phone || !formData.shipping_address) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }
            
            showLoadingOverlay('Placing your order...');
            
            $.ajax({
                url: 'api.php?action=place-order',
                method: 'POST',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        currentOrderNumber = response.order_number;
                        
                        // Fetch full order data
                        $.ajax({
                            url: `api.php?action=order&order_number=${encodeURIComponent(response.order_number)}`,
                            method: 'GET',
                            success: function(orderResponse) {
                                if (orderResponse.success) {
                                    currentOrderData = orderResponse.data;
                                    
                                    // Update loading text
                                    $('#loadingText').text('Generating receipt...');
                                    
                                    // Generate and download PDF
                                    generateAndDownloadPDF();
                                    
                                    // Navigate to tracking page after PDF generation starts
                                    setTimeout(() => {
                                        $('#order-form')[0].reset();
                                        $('#order-number').val(response.order_number);
                                        showSection('track');
                                        trackOrder(response.order_number);
                                    }, 1500);
                                } else {
                                    hideLoadingOverlay();
                                    showAlert('Error fetching order details', 'error');
                                }
                            },
                            error: function() {
                                hideLoadingOverlay();
                                showAlert('Failed to fetch order data', 'error');
                            }
                        });
                    } else {
                        hideLoadingOverlay();
                        showAlert(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Order placement error:', error);
                    hideLoadingOverlay();
                    showAlert('Network error. Please try again.', 'error');
                }
            });
        }
        
        // Track order
        function trackOrder(orderNumber = null) {
            const orderNum = orderNumber || $('#order-number').val().trim();
            
            if (!orderNum) {
                showTrackingMessage('Please enter an order number to track.', 'error');
                $('#tracking-results').hide();
                return;
            }
            
            clearTrackingMessage();
            showLoadingOverlay('Loading order details...');
            
            $.ajax({
                url: `api.php?action=order&order_number=${encodeURIComponent(orderNum)}`,
                method: 'GET',
                success: function(response) {
                    hideLoadingOverlay();
                    
                    if (response.success) {
                        currentOrderNumber = orderNum;
                        currentOrderData = response.data;
                        clearTrackingMessage();
                        displayOrderDetails(response.data);
                    } else {
                        showTrackingMessage(`Order "${orderNum}" was not found. Please check the order number and try again.`, 'error', true);
                        $('#tracking-results').hide();
                    }
                },
                error: function() {
                    hideLoadingOverlay();
                    showTrackingMessage('Unable to load order details right now. Please try again.', 'error');
                    $('#tracking-results').hide();
                }
            });
        }

        // Update customer profile
        function saveProfile(event) {
            event.preventDefault();

            if (!IS_AUTHENTICATED) {
                showAlert('Please sign in to manage profile.', 'error');
                return;
            }

            const payload = {
                full_name: $('#profile-full-name').val().trim(),
                email: $('#profile-email').val().trim(),
                phone: $('#profile-phone').val().trim(),
                address: $('#profile-address').val().trim(),
                city: $('#profile-city').val().trim(),
                postal_code: $('#profile-postal-code').val().trim(),
                current_password: $('#profile-current-password').val(),
                new_password: $('#profile-new-password').val(),
                confirm_password: $('#profile-confirm-password').val()
            };

            $.ajax({
                url: 'api.php?action=update-profile',
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message || 'Profile updated successfully.', 'success');

                        if (response.user) {
                            $('#profile-full-name').val(response.user.full_name || '');
                            $('#profile-email').val(response.user.email || '');
                            $('#profile-phone').val(response.user.phone || '');
                            $('#profile-address').val(response.user.address || '');
                            $('#profile-city').val(response.user.city || '');
                            $('#profile-postal-code').val(response.user.postal_code || '');
                        }

                        $('#profile-current-password').val('');
                        $('#profile-new-password').val('');
                        $('#profile-confirm-password').val('');
                    } else {
                        showAlert(response.message || 'Failed to update profile.', 'error');
                    }
                },
                error: function() {
                    showAlert('Failed to update profile. Please try again.', 'error');
                }
            });
        }
        
        // Display order details
        function displayOrderDetails(order) {
            const orderDate = new Date(order.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            $('#order-info').html(`
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <p><strong>Order Number:</strong> ${order.order_number}</p>
                        <p><strong>Order Date:</strong> ${orderDate}</p>
                        <p><strong>Payment Method:</strong> ${order.payment_method || 'Cash'}</p>
                        <p><strong>Payment Status:</strong> <span style="color: #ff9800;">${order.payment_status.toUpperCase()}</span></p>
                    </div>
                    <div>
                        <p><strong>Customer:</strong> ${order.customer_name}</p>
                        <p><strong>Email:</strong> ${order.customer_email}</p>
                        <p><strong>Phone:</strong> ${order.customer_phone}</p>
                        <p><strong>Total Amount:</strong> <strong style="color: #2e7d32;">KES ${parseFloat(order.total_amount).toFixed(2)}</strong></p>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <p><strong>Shipping Address:</strong> ${order.shipping_address}</p>
                </div>
            `);
            
            // Display order status
            const statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
            const statusLabels = {
                pending: 'Pending',
                processing: 'Processing',
                shipped: 'Shipped',
                delivered: 'Delivered'
            };
            const statusIcons = {
                pending: 'fa-clock',
                processing: 'fa-cogs',
                shipped: 'fa-shipping-fast',
                delivered: 'fa-check-circle'
            };
            
            const currentStatusIndex = statusSteps.indexOf(order.order_status);
            
            let statusHtml = '';
            statusSteps.forEach((step, index) => {
                const stepClass = index < currentStatusIndex ? 'completed' : 
                                 index === currentStatusIndex ? 'active' : '';
                                 
                statusHtml += `
                    <div class="status-step ${stepClass}">
                        <div class="status-icon">
                            <i class="fas ${statusIcons[step]}"></i>
                        </div>
                        <div>${statusLabels[step]}</div>
                    </div>
                `;
            });
            
            $('#order-status-tracker').html(statusHtml);
            
            // Display order items
            let itemsHtml = '';
            if (order.items && order.items.length > 0) {
                order.items.forEach(item => {
                    itemsHtml += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>KES ${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td>KES ${parseFloat(item.total_price).toFixed(2)}</td>
                        </tr>
                    `;
                });
            }
            
            itemsHtml += `
                <tr style="font-weight: bold; background: #f5f5f5;">
                    <td colspan="3" style="text-align: right;">Total:</td>
                    <td>KES ${parseFloat(order.total_amount).toFixed(2)}</td>
                </tr>
            `;
            
            $('#order-items-list').html(itemsHtml);
            
            $('#tracking-results').show();
        }
        
        // Event listeners
        $(document).ready(function() {
            const mobileBreakpoint = 768;

            function filterProducts() {
                const query = ($('#product-search').val() || '').trim().toLowerCase();
                const selectedCategory = $('#product-category-filter').val();
                const selectedSort = $('#product-sort').val();

                const cards = $('#products-container .product-card').toArray();

                cards.forEach((card, idx) => {
                    const $card = $(card);
                    if (!$card.attr('data-index')) {
                        $card.attr('data-index', String(idx));
                    }

                    const productName = String($card.data('name') || '').toLowerCase();
                    const categoryId = String($card.data('category') || '');
                    const matchesSearch = !query || productName.includes(query);
                    const matchesCategory = !selectedCategory || categoryId === selectedCategory;

                    $card.toggle(matchesSearch && matchesCategory);
                });

                const visibleCards = $('#products-container .product-card:visible').toArray();
                visibleCards.sort((a, b) => {
                    const $a = $(a);
                    const $b = $(b);
                    const nameA = String($a.data('name') || '');
                    const nameB = String($b.data('name') || '');
                    const priceA = parseFloat($a.data('price') || 0);
                    const priceB = parseFloat($b.data('price') || 0);
                    const indexA = parseInt(String($a.attr('data-index') || '0'), 10);
                    const indexB = parseInt(String($b.attr('data-index') || '0'), 10);

                    switch (selectedSort) {
                        case 'price_low':
                            return priceA - priceB;
                        case 'price_high':
                            return priceB - priceA;
                        case 'name_asc':
                            return nameA.localeCompare(nameB);
                        case 'name_desc':
                            return nameB.localeCompare(nameA);
                        default:
                            return indexA - indexB;
                    }
                });

                visibleCards.forEach((card) => $('#products-container').append(card));
                $('#products-empty-message').toggle(visibleCards.length === 0);
            }

            // Navigation
            $('nav a[data-section], .logo[data-section]').on('click', function(e) {
                e.preventDefault();
                const section = $(this).data('section');
                if (section) {
                    showSection(section);
                }
            });

            $(document).on('click', '.recent-order-track', function() {
                const orderNumber = $(this).data('order-number');
                if (!orderNumber) {
                    return;
                }
                $('#order-number').val(orderNumber);
                trackOrder(orderNumber);
            });

            $('#menuToggle').on('click', function(e) {
                e.stopPropagation();
                const isOpen = $('#mainNavList').toggleClass('show').hasClass('show');
                $('#menuToggleIcon')
                    .toggleClass('fa-bars', !isOpen)
                    .toggleClass('fa-times', isOpen);
            });

            $('#product-search').on('input', filterProducts);
            $('#product-category-filter').on('change', filterProducts);
            $('#product-sort').on('change', filterProducts);
            $('#product-filter-reset').on('click', function() {
                $('#product-search').val('');
                $('#product-category-filter').val('');
                $('#product-sort').val('');
                filterProducts();
            });

            $(document).on('change', '.variation-select', function() {
                const selectedPrice = parseFloat($(this).find('option:selected').data('price') || 0);
                if (selectedPrice > 0) {
                    const $card = $(this).closest('.product-card');
                    $card.attr('data-price', selectedPrice.toFixed(2));
                    $card.find('.js-product-price').text(`KES ${selectedPrice.toFixed(2)}`);
                    filterProducts();
                }
            });

            const widgetClosed = localStorage.getItem('contact_widget_closed') === '1';
            if (widgetClosed) {
                $('.contact-widget').hide();
            }
            $('#close-contact-widget').on('click', function() {
                $('.contact-widget').fadeOut(150);
                localStorage.setItem('contact_widget_closed', '1');
            });
            
            $('[data-section]').on('click', function(e) {
                e.preventDefault();
                const section = $(this).data('section');
                if (section) {
                    showSection(section);
                }
            });
            
            // Cart toggle click - show dropdown
            $('#cartToggle').on('click', function(e) {
                e.stopPropagation();
                $('#cartDropdown').toggleClass('show');
                if ($('#cartDropdown').hasClass('show')) {
                    updateCartDropdown();
                }
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if ($(window).width() <= mobileBreakpoint &&
                    !$(e.target).closest('nav').length) {
                    $('#mainNavList').removeClass('show');
                    $('#menuToggleIcon').removeClass('fa-times').addClass('fa-bars');
                }

                if (!$(e.target).closest('#cartToggle').length) {
                    $('#cartDropdown').removeClass('show');
                }
            });
            
            // Prevent dropdown from closing when clicking inside it
            $('#cartDropdown').on('click', function(e) {
                e.stopPropagation();
            });
            
            // Product actions
            $(document).on('click', '.add-to-cart', function() {
                const productId = $(this).data('id');
                const $card = $(this).closest('.product-card');
                const $variation = $card.find('.variation-select');
                const variationLabel = $variation.length ? String($variation.val() || '').trim() : '';
                addToCart(productId, 1, variationLabel);
            });
            
            // Cart actions
            $(document).on('click', '.decrease-quantity', function() {
                const cartKey = String($(this).data('key') || '');
                const productId = $(this).data('id');
                const input = $(this).closest('.cart-item-quantity').find('.quantity-input');
                const currentQty = parseInt(input.val());
                updateCartQuantity(cartKey, productId, currentQty - 1);
            });
            
            $(document).on('click', '.increase-quantity', function() {
                const cartKey = String($(this).data('key') || '');
                const productId = $(this).data('id');
                const input = $(this).closest('.cart-item-quantity').find('.quantity-input');
                const currentQty = parseInt(input.val());
                updateCartQuantity(cartKey, productId, currentQty + 1);
            });
            
            $(document).on('change', '.quantity-input', function() {
                const cartKey = String($(this).data('key') || '');
                const productId = $(this).data('id');
                const quantity = parseInt($(this).val());
                if (!isNaN(quantity) && quantity > 0) {
                    updateCartQuantity(cartKey, productId, quantity);
                }
            });
            
            $(document).on('click', '.remove-item', function() {
                const cartKey = String($(this).data('key') || '');
                const productId = $(this).data('id');
                removeFromCart(cartKey, productId);
            });
            
            // Remove from dropdown
            $(document).on('click', '.remove-from-dropdown', function(e) {
                e.stopPropagation();
                const cartKey = String($(this).data('key') || '');
                const productId = $(this).data('id');
                removeFromCart(cartKey, productId);
            });
            
            $('#checkout-btn').on('click', function() {
                showSection('order');
                $('#cartDropdown').removeClass('show');
            });
            
            // Order form
            $('#order-form').on('submit', placeOrder);
            $('#profile-form').on('submit', saveProfile);
            
            // Track order
            $('#track-btn').on('click', function() {
                trackOrder();
            });

            $(document).on('click', '#tracking-products-btn', function() {
                showSection('products');
            });
            
            $('#order-number').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    trackOrder();
                }
            });
            
            // Initial cart load
            updateCartUI();
            filterProducts();

            const sectionParam = new URLSearchParams(window.location.search).get('section');
            if (sectionParam === 'products' || sectionParam === 'cart' || sectionParam === 'order' || sectionParam === 'track' || sectionParam === 'profile') {
                showSection(sectionParam);
            }

            if (IS_AUTHENTICATED) {
                loadRecentOrders();
            }
            
            // Check if jsPDF is loaded
            console.log('jsPDF loaded:', isJsPDFLoaded());
        });
    </script>
</body>
</html>

<?php
function getProductIcon($category_id) {
    switch($category_id) {
        case 1: return 'fa-mug-hot';
        case 2: return 'fa-tint';
        case 3: return 'fa-capsules';
        case 4: return 'fa-flask';
        default: return 'fa-leaf';
    }
}
?>


