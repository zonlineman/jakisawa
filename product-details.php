<?php
session_start();
require_once 'config.php';

// Helper function to get product image
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

// Helper function to get product image
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

// Helper function to check if image exists
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
        // Backward compatibility for entries saved before "Label|Price".
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
        // Keep defaults if introspection fails.
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
        // Keep currently detected support if introspection fails.
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

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch product details
$variationQueryParts = getRemedyVariationQueryParts($pdo);
$variationSelectColumns = $variationQueryParts['select'];
$variationJoinClause = $variationQueryParts['join'];

$stmt = $pdo->prepare("
    SELECT r.*, c.name as category_name {$variationSelectColumns}
    FROM remedies r 
    LEFT JOIN categories c ON r.category_id = c.id 
    {$variationJoinClause}
    WHERE r.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit();
}

// Calculate pricing + variation info
$variationOptions = getRemedyVariationOptions($product);
$hasVariations = !empty($variationOptions);
$defaultVariation = $hasVariations ? $variationOptions[0] : null;
$variationPrices = $hasVariations ? array_column($variationOptions, 'price') : [];
$variationMinPrice = !empty($variationPrices) ? min($variationPrices) : null;
$variationMaxPrice = !empty($variationPrices) ? max($variationPrices) : null;

$baseHasDiscount = !empty($product['discount_price']) && (float)$product['discount_price'] > 0;
$has_discount = !$hasVariations && $baseHasDiscount;
$effective_price = $has_discount ? (float)$product['discount_price'] : (float)$product['unit_price'];
$display_price = $hasVariations ? (float)$defaultVariation['price'] : $effective_price;
$savings = $has_discount ? (float)$product['unit_price'] - (float)$product['discount_price'] : 0;
$savings_percentage = ($has_discount && (float)$product['unit_price'] > 0)
    ? round(($savings / (float)$product['unit_price']) * 100, 1)
    : 0;
$delivery_window = ($product['stock_quantity'] > 0) ? '24-48 hours in major towns' : 'Ships when restocked';

// Shop information (set constants in config.php to override defaults)
$shop_name = defined('SITE_NAME') ? SITE_NAME : 'JAKISAWA SHOP';
$shop_email = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'jakisawa@jakisawashop.co.ke';
$shop_phone = defined('SHOP_PHONE') ? SHOP_PHONE : '0792546080 / +254 720 793609';
$shop_whatsapp = defined('SHOP_WHATSAPP') ? SHOP_WHATSAPP : '0792546080 / +254 720 793609';
$shop_address = defined('SHOP_ADDRESS') ? SHOP_ADDRESS : 'Nairobi Information HSE, Room 405, Fourth Floor';

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}

// Get image info
$image_url = getProductImageUrl($product['image_url'] ?? '');
$image_exists = productImageExists($product['image_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Product Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2e7d32;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .product-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding: 40px;
        }

        /* Image Container Styles */
        .image-container {
            background: #f5f5f5;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            height: 500px;
            cursor: zoom-in;
        }

        .image-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
            background: #f5f5f5;
        }

        /* Zoom Lens */
        .zoom-lens {
            position: absolute;
            border: 2px solid #2e7d32;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.3);
            pointer-events: none;
            display: none;
            z-index: 10;
        }

        /* Zoom Result */
        .zoom-result {
            position: absolute;
            top: 0;
            left: 100%;
            width: 500px;
            height: 500px;
            margin-left: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            display: none;
            z-index: 20;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .zoom-result img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
        }

        /* Modal for fullscreen zoom */
        .zoom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            cursor: zoom-out;
        }

        .zoom-modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .zoom-modal img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 5px;
        }

        .zoom-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }

        .zoom-close:hover {
            color: #2e7d32;
        }

        .zoom-controls {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 20px;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 30px;
        }

        .zoom-controls button {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px 15px;
            border-radius: 5px;
        }

        .zoom-controls button:hover {
            background: #2e7d32;
        }

        .zoom-percent {
            color: white;
            font-size: 16px;
            padding: 5px 15px;
        }

        .no-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
        }

        .no-image-placeholder i {
            font-size: 100px;
            color: #2e7d32;
            margin-bottom: 20px;
        }

        .product-details {
            padding: 20px 0;
        }

        .product-category {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .product-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .product-price {
            margin: 30px 0;
        }

        .price-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .current-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2e7d32;
        }

        .original-price {
            font-size: 1.5rem;
            text-decoration: line-through;
            color: #999;
        }

        .discount-badge {
            background: #f44336;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .savings-text {
            color: #4caf50;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .stock-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            font-weight: 500;
        }

        .conversion-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin: 20px 0;
        }

        .strip-item {
            background: #f4f8f4;
            border: 1px solid #e0ede1;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.92rem;
            color: #2a2a2a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .strip-item i {
            color: #2e7d32;
        }

        .in-stock {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .low-stock {
            background: #fff3e0;
            color: #ef6c00;
            border-left: 4px solid #ff9800;
        }

        .out-of-stock {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .description-section {
            margin: 30px 0;
        }

        .section-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2e7d32;
        }

        .description-text {
            color: #666;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .info-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #2e7d32;
        }

        .info-card h3 {
            color: #2e7d32;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .info-card h3 i {
            margin-right: 8px;
        }

        .info-card p {
            color: #666;
            line-height: 1.6;
        }

        .add-to-cart-section {
            margin: 30px 0;
            padding: 30px;
            background: #f5f5f5;
            border-radius: 10px;
        }

        .cta-header {
            margin-bottom: 15px;
        }

        .cta-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f1f1f;
        }

        .cta-subtitle {
            font-size: 0.92rem;
            color: #5f5f5f;
        }

        .trust-notes {
            margin-top: 12px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            color: #4d4d4d;
            font-size: 0.9rem;
        }

        .variation-section {
            margin: 18px 0 24px;
        }

        .variation-select {
            width: 100%;
            max-width: 420px;
            padding: 12px 14px;
            border: 1px solid #cfd9cf;
            border-radius: 8px;
            background: #fff;
            color: #1f1f1f;
            font-size: 0.95rem;
            margin-top: 8px;
        }

        .variation-note {
            margin-top: 8px;
            color: #5f5f5f;
            font-size: 0.9rem;
        }

        .variation-list {
            margin-top: 12px;
            border: 1px solid #e0ede1;
            border-radius: 8px;
            overflow: hidden;
            max-width: 420px;
            background: #fbfdfb;
        }

        .variation-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-bottom: 1px solid #e8f1e8;
            font-size: 0.92rem;
            color: #2a2a2a;
        }

        .variation-row:last-child {
            border-bottom: none;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .quantity-label {
            font-weight: 500;
            color: #333;
        }

        .quantity-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #2e7d32;
            background: white;
            color: #2e7d32;
            font-size: 1.2rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: #2e7d32;
            color: white;
        }

        .qty-value {
            width: 80px;
            text-align: center;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: #2e7d32;
            color: white;
        }

        .btn-primary:hover {
            background: #1b5e20;
        }

        .btn-secondary {
            background: white;
            color: #2e7d32;
            border: 2px solid #2e7d32;
        }

        .btn-secondary:hover {
            background: #f5f5f5;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Thumbnail Gallery */
        .thumbnail-gallery {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }

        .thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
            object-fit: cover;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #2e7d32;
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }

            .image-container {
                height: 300px;
            }

            .product-title {
                font-size: 1.8rem;
            }

            .current-price {
                font-size: 2rem;
            }

            .product-info-grid {
                grid-template-columns: 1fr;
            }

            .conversion-strip {
                grid-template-columns: 1fr;
            }

            .zoom-result {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>

        <div id="alert-container"></div>

        <div class="product-container">
            <div class="product-grid">
                <!-- Product Image with Zoom -->
                <div>
                    <div class="image-container" id="imageContainer">
                        <div class="image-wrapper">
                            <?php if ($image_url && $image_exists): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="product-image" 
                                     id="mainImage"
                                     data-zoom="<?php echo htmlspecialchars($image_url); ?>">
                                <!-- Zoom Lens -->
                                <div class="zoom-lens" id="zoomLens"></div>
                            <?php else: ?>
                                <div class="no-image-placeholder">
                                    <i class="fas fa-<?php 
                                        switch($product['category_id']) {
                                            case 1: echo 'mug-hot'; break;
                                            case 2: echo 'tint'; break;
                                            case 3: echo 'capsules'; break;
                                            case 4: echo 'flask'; break;
                                            default: echo 'leaf';
                                        }
                                    ?>"></i>
                                    <p>No image available</p>
                                    <?php if (!empty($product['image_url']) && !$image_exists): ?>
                                        <small style="color: #999;">Image file not found</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Zoom Result -->
                        <div class="zoom-result" id="zoomResult">
                            <?php if ($image_url && $image_exists): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" id="zoomImage">
                            <?php endif; ?>
                        </div>

                        <!-- Thumbnail Gallery (if multiple images existed, you could add more) -->
                        <?php if ($image_url && $image_exists): ?>
                        <div class="thumbnail-gallery">
                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                 class="thumbnail active" 
                                 onclick="changeImage('<?php echo htmlspecialchars($image_url); ?>')">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Details (rest remains the same) -->
                <div class="product-details">
                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>

                    <!-- Price -->
                    <div class="product-price">
                        <div class="price-row">
                            <div class="current-price" id="current-price-display">KES <?php echo number_format($display_price, 2); ?></div>
                            <?php if ($has_discount): ?>
                                <div class="original-price">KES <?php echo number_format($product['unit_price'], 2); ?></div>
                                <div class="discount-badge">SAVE <?php echo $savings_percentage; ?>%</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasVariations): ?>
                            <div class="savings-text">Price changes based on selected size / pack.</div>
                        <?php endif; ?>
                        <?php if ($has_discount): ?>
                            <div class="savings-text">
                                You save KES <?php echo number_format($savings, 2); ?>!
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasVariations): ?>
                    <div class="variation-section">
                        <label for="variation-select" class="quantity-label">Available Sizes / Packs</label>
                        <select id="variation-select" class="variation-select">
                            <?php foreach ($variationOptions as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['label'], ENT_QUOTES); ?>" data-price="<?php echo number_format((float)$option['price'], 2, '.', ''); ?>">
                                    <?php echo htmlspecialchars($option['label']); ?> - KES <?php echo number_format((float)$option['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="variation-note">Select your preferred size before adding to cart.</div>
                        <div class="variation-list">
                            <?php foreach ($variationOptions as $option): ?>
                                <div class="variation-row">
                                    <span><?php echo htmlspecialchars($option['label']); ?></span>
                                    <strong>KES <?php echo number_format((float)$option['price'], 2); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Stock Status -->
                    <div class="stock-status <?php echo ($product['stock_quantity'] <= 0) ? 'out-of-stock' : 'in-stock'; ?>">
                        <i class="fas fa-<?php 
                            if ($product['stock_quantity'] <= 0) echo 'times-circle';
                            else echo 'check-circle';
                        ?>"></i>
                        <?php 
                            if ($product['stock_quantity'] <= 0) {
                                echo 'Out of Stock';
                            } else {
                                echo 'In Stock';
                            }
                        ?>
                    </div>

                    <div class="conversion-strip">
                        <div class="strip-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Quality checked product</span>
                        </div>
                        <div class="strip-item">
                            <i class="fas fa-truck"></i>
                            <span>Delivery: <?php echo htmlspecialchars($delivery_window); ?></span>
                        </div>
                        <div class="strip-item">
                            <i class="fas fa-undo-alt"></i>
                            <span>Easy replacement support</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($product['description'])): ?>
                    <div class="description-section">
                        <h2 class="section-title">Description</h2>
                        <div class="description-text"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Product Info Grid -->
                    <div class="product-info-grid">
                        <?php if (!empty($product['ingredients'])): ?>
                        <div class="info-card">
                            <h3><i class="fas fa-leaf"></i> Ingredients</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['ingredients'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($product['usage_instructions'])): ?>
                        <div class="info-card">
                            <h3><i class="fas fa-info-circle"></i> Usage Instructions</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['usage_instructions'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="info-card">
                            <h3><i class="fas fa-store"></i> Shop Information</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($shop_name); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($shop_phone); ?></p>
                            <p><strong>WhatsApp:</strong> <?php echo htmlspecialchars($shop_whatsapp); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($shop_email); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($shop_address); ?></p>
                        </div>

                        <div class="info-card">
                            <h3><i class="fas fa-box"></i> Product Details</h3>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
                            <?php if ($hasVariations): ?>
                                <p>
                                    <strong>Price Range:</strong>
                                    <?php if ($variationMinPrice !== null && $variationMaxPrice !== null && (float)$variationMinPrice !== (float)$variationMaxPrice): ?>
                                        KES <?php echo number_format((float)$variationMinPrice, 2); ?> - KES <?php echo number_format((float)$variationMaxPrice, 2); ?>
                                    <?php elseif ($variationMinPrice !== null): ?>
                                        KES <?php echo number_format((float)$variationMinPrice, 2); ?>
                                    <?php else: ?>
                                        KES <?php echo number_format((float)$display_price, 2); ?>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p><strong>Price:</strong> KES <?php echo number_format((float)$product['unit_price'], 2); ?></p>
                            <?php endif; ?>
                            <p><strong>Stock:</strong> <?php echo ($product['stock_quantity'] > 0) ? 'In Stock' : 'Out of Stock'; ?></p>
                            <p><strong>Status:</strong> <?php echo $product['is_featured'] ? 'Featured' : 'Regular'; ?></p>
                        </div>
                    </div>

                    <!-- Add to Cart Section -->
                    <div class="add-to-cart-section">
                        <div class="cta-header">
                            <div class="cta-title">Ready to order?</div>
                            <div class="cta-subtitle">Choose quantity, add to cart, and complete checkout in minutes.</div>
                        </div>

                        <div class="quantity-selector">
                            <span class="quantity-label">Quantity:</span>
                            <div class="quantity-input">
                                <button class="qty-btn" id="decrease-qty" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>-</button>
                                <input
                                    type="number"
                                    id="quantity"
                                    class="qty-value"
                                    value="<?php echo $product['stock_quantity'] > 0 ? 1 : 0; ?>"
                                    min="<?php echo $product['stock_quantity'] > 0 ? 1 : 0; ?>"
                                    max="<?php echo max(0, (int)$product['stock_quantity']); ?>"
                                    <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>
                                >
                                <button class="qty-btn" id="increase-qty" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>+</button>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button class="btn btn-primary" id="add-to-cart-btn" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-cart-plus"></i>
                                Add to Cart
                            </button>
                            <button class="btn btn-secondary" id="buy-now-btn" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-bolt"></i>
                                Buy Now
                            </button>
                            <a href="index.php#cart-section" class="btn btn-secondary">
                                <i class="fas fa-shopping-cart"></i>
                                View Cart (<span id="view-cart-count"><?php echo (int)$cart_count; ?></span>)
                            </a>
                        </div>

                        <div class="trust-notes">
                            <span><i class="fas fa-lock"></i> Secure checkout</span>
                            <span><i class="fas fa-headset"></i> Fast support response</span>
                            <span><i class="fas fa-clock"></i> Quick order processing</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Zoom Modal -->
    <div class="zoom-modal" id="zoomModal" onclick="closeZoomModal()">
        <span class="zoom-close" onclick="closeZoomModal()">&times;</span>
        <img src="" alt="Zoomed product" id="modalImage">
        <div class="zoom-controls">
            <button onclick="zoomOut()"><i class="fas fa-search-minus"></i></button>
            <span class="zoom-percent" id="zoomPercent">100%</span>
            <button onclick="zoomIn()"><i class="fas fa-search-plus"></i></button>
            <button onclick="resetZoom()"><i class="fas fa-sync-alt"></i></button>
        </div>
    </div>

    <script>
        const productId = <?php echo $product_id; ?>;
        const maxStock = <?php echo $product['stock_quantity']; ?>;
        const hasVariations = <?php echo $hasVariations ? 'true' : 'false'; ?>;
        const variationSelect = document.getElementById('variation-select');
        const currentPriceDisplay = document.getElementById('current-price-display');

        function getSelectedVariationLabel() {
            if (!hasVariations || !variationSelect) return '';
            return variationSelect.value || '';
        }

        function getSelectedVariationPrice() {
            if (!hasVariations || !variationSelect) return null;
            const selectedOption = variationSelect.options[variationSelect.selectedIndex];
            if (!selectedOption) return null;
            const parsed = parseFloat(selectedOption.getAttribute('data-price') || '');
            return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
        }

        function formatKes(value) {
            return 'KES ' + Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        if (hasVariations && variationSelect && currentPriceDisplay) {
            variationSelect.addEventListener('change', function() {
                const selectedPrice = getSelectedVariationPrice();
                if (selectedPrice !== null) {
                    currentPriceDisplay.textContent = formatKes(selectedPrice);
                }
            });
        }
        
        <?php if ($image_url && $image_exists): ?>
        // Zoom functionality
        const container = document.getElementById('imageContainer');
        const mainImage = document.getElementById('mainImage');
        const lens = document.getElementById('zoomLens');
        const result = document.getElementById('zoomResult');
        const zoomImage = document.getElementById('zoomImage');
        
        if (mainImage && lens && result && zoomImage) {
            // Calculate zoom ratio
            const cx = result.offsetWidth / lens.offsetWidth;
            const cy = result.offsetHeight / lens.offsetHeight;
            
            zoomImage.style.width = mainImage.offsetWidth * cx + 'px';
            zoomImage.style.height = mainImage.offsetHeight * cy + 'px';
            
            container.addEventListener('mousemove', moveLens);
            container.addEventListener('mouseenter', showZoom);
            container.addEventListener('mouseleave', hideZoom);
            
            function moveLens(e) {
                e.preventDefault();
                
                const pos = getCursorPos(e);
                let x = pos.x - (lens.offsetWidth / 2);
                let y = pos.y - (lens.offsetHeight / 2);
                
                // Prevent lens from going outside image
                if (x > mainImage.width - lens.offsetWidth) {
                    x = mainImage.width - lens.offsetWidth;
                }
                if (x < 0) {
                    x = 0;
                }
                if (y > mainImage.height - lens.offsetHeight) {
                    y = mainImage.height - lens.offsetHeight;
                }
                if (y < 0) {
                    y = 0;
                }
                
                lens.style.left = x + 'px';
                lens.style.top = y + 'px';
                
                zoomImage.style.left = -x * cx + 'px';
                zoomImage.style.top = -y * cy + 'px';
            }
            
            function getCursorPos(e) {
                const rect = mainImage.getBoundingClientRect();
                const x = e.pageX - rect.left - window.pageXOffset;
                const y = e.pageY - rect.top - window.pageYOffset;
                return { x: x, y: y };
            }
            
            function showZoom() {
                lens.style.display = 'block';
                result.style.display = 'block';
            }
            
            function hideZoom() {
                lens.style.display = 'none';
                result.style.display = 'none';
            }
            
            // Click to open fullscreen modal
            mainImage.addEventListener('click', function() {
                openZoomModal(this.src);
            });
        }
        <?php endif; ?>

        // Fullscreen Zoom Modal
        let currentZoom = 1;
        const modalImage = document.getElementById('modalImage');
        const zoomModal = document.getElementById('zoomModal');
        const zoomPercent = document.getElementById('zoomPercent');

        function openZoomModal(src) {
            modalImage.src = src;
            zoomModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            resetZoom();
        }

        function closeZoomModal() {
            zoomModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function zoomIn() {
            currentZoom += 0.25;
            if (currentZoom > 3) currentZoom = 3;
            applyZoom();
        }

        function zoomOut() {
            currentZoom -= 0.25;
            if (currentZoom < 0.5) currentZoom = 0.5;
            applyZoom();
        }

        function resetZoom() {
            currentZoom = 1;
            applyZoom();
        }

        function applyZoom() {
            modalImage.style.transform = `scale(${currentZoom})`;
            zoomPercent.textContent = Math.round(currentZoom * 100) + '%';
        }

        // Mouse wheel zoom in modal
        zoomModal.addEventListener('wheel', function(e) {
            e.preventDefault();
            if (e.deltaY < 0) {
                zoomIn();
            } else {
                zoomOut();
            }
        });

        // Change image function (for thumbnails)
        function changeImage(src) {
            const mainImage = document.getElementById('mainImage');
            const zoomImage = document.getElementById('zoomImage');
            if (mainImage) mainImage.src = src;
            if (zoomImage) zoomImage.src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
                if (thumb.src === src) {
                    thumb.classList.add('active');
                }
            });
        }

        // Quantity controls
        $('#decrease-qty').click(function() {
            let qty = parseInt($('#quantity').val());
            if (qty > 1) {
                $('#quantity').val(qty - 1);
            }
        });

        $('#increase-qty').click(function() {
            let qty = parseInt($('#quantity').val());
            if (qty < maxStock) {
                $('#quantity').val(qty + 1);
            }
        });

        // Manual quantity input validation
        $('#quantity').on('input', function() {
            let qty = parseInt($(this).val());
            if (isNaN(qty) || qty < 1) {
                $(this).val(1);
            } else if (qty > maxStock) {
                $(this).val(maxStock);
                showAlert('Selected quantity is not available.', 'error');
            }
        });

        function addToCartAndMaybeRedirect(redirectImmediately = false) {
            const quantity = parseInt($('#quantity').val());

            if (isNaN(quantity) || quantity < 1) {
                $('#quantity').val(1);
                showAlert('Enter a valid quantity.', 'error');
                return;
            }

            if (quantity > maxStock) {
                $('#quantity').val(maxStock);
                showAlert('Selected quantity is not available.', 'error');
                return;
            }

            $('#add-to-cart-btn, #buy-now-btn').prop('disabled', true);
            
            $.ajax({
                url: 'api.php?action=add-to-cart',
                method: 'POST',
                data: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    variation_label: getSelectedVariationLabel()
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            response = { success: false, message: 'Unexpected server response.' };
                        }
                    }

                    if (response.success) {
                        showAlert(`Added ${quantity} item(s) to cart!`, 'success');

                        if (response.summary && typeof response.summary.item_count !== 'undefined') {
                            $('#view-cart-count').text(Math.round(Number(response.summary.item_count) || 0));
                        }

                        if (redirectImmediately) {
                            setTimeout(() => {
                                window.location.href = 'index.php#cart-section';
                            }, 300);
                        }
                    } else {
                        showAlert(response.message || 'Failed to add to cart. Please try again.', 'error');
                    }
                },
                error: function(xhr) {
                    let message = 'Failed to add to cart. Please try again.';
                    if (xhr && xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                message = parsed.message;
                            }
                        } catch (e) {
                            // Keep generic message when response is not valid JSON.
                        }
                    }
                    showAlert(message, 'error');
                },
                complete: function() {
                    $('#add-to-cart-btn, #buy-now-btn').prop('disabled', maxStock <= 0);
                }
            });
        }

        // Add to cart
        $('#add-to-cart-btn').click(function() {
            addToCartAndMaybeRedirect(false);
        });

        // Buy now (add + fast redirect to cart/checkout flow)
        $('#buy-now-btn').click(function() {
            addToCartAndMaybeRedirect(true);
        });

        function showAlert(message, type) {
            const alertContainer = $('#alert-container');
            const alert = $(`<div class="alert alert-${type}">${message}</div>`);
            alertContainer.html(alert);
            alert.show();

            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        }
    </script>
</body>
</html>
