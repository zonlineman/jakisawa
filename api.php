<?php
// Start session FIRST
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config AFTER session_start
require_once 'config.php';
$orderNotificationHelper = __DIR__ . '/system/includes/order_notifications.php';
if (file_exists($orderNotificationHelper)) {
    require_once $orderNotificationHelper;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// DYNAMIC SHIPPING CALCULATION
function calculateShipping($subtotal) {
    $baseShipping = 300.00; // Minimum shipping cost
    
    // Progressive shipping rates
    if ($subtotal <= 1000) {
        return $baseShipping; // KES 300 for orders up to KES 1,000
    } elseif ($subtotal <= 5000) {
        return $baseShipping + (($subtotal - 1000) * 0.05); // +5% of amount above 1000
    } elseif ($subtotal <= 10000) {
        return $baseShipping + 200 + (($subtotal - 5000) * 0.03); // +3% of amount above 5000
    } else {
        return $baseShipping + 350 + (($subtotal - 10000) * 0.02); // +2% of amount above 10000
    }
}

function buildCartItemKey($productId, $variationLabel = '') {
    $productId = (int)$productId;
    $variationLabel = strtolower(trim((string)$variationLabel));
    if ($variationLabel === '') {
        return $productId . ':default';
    }
    return $productId . ':' . substr(sha1($variationLabel), 0, 12);
}

function getCartItemKey(array $item) {
    $existing = trim((string)($item['cart_key'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }
    return buildCartItemKey((int)($item['id'] ?? 0), (string)($item['variation_label'] ?? ''));
}

// Helper function to check stock
function checkStock($pdo, $product_id, $quantity, $excludeCartKey = null) {
    $stmt = $pdo->prepare("SELECT stock_quantity FROM remedies WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return ['available' => false, 'message' => 'Product not found'];
    }
    
    // Check current cart for this product
    $cartQuantity = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            if ((int)($item['id'] ?? 0) === (int)$product_id) {
                if ($excludeCartKey !== null && $excludeCartKey !== '' && getCartItemKey($item) === $excludeCartKey) {
                    continue;
                }
                $cartQuantity += (float)($item['quantity'] ?? 0);
            }
        }
    }
    
    $available = $product['stock_quantity'] - $cartQuantity;
    
    if ($quantity > $available) {
        return [
            'available' => false, 
            'message' => 'Requested quantity is not available in stock.',
            'stock' => $available
        ];
    }
    
    return ['available' => true, 'stock' => $product['stock_quantity']];
}

// Helper function to calculate cart totals with DYNAMIC SHIPPING
function calculateCartTotals($cart) {
    $subtotal = 0;
    $subtotal_without_discount = 0;
    $total_discount = 0;
    $item_count = 0;
    
    foreach ($cart as $item) {
        $regular_price = isset($item['regular_price']) ? floatval($item['regular_price']) : floatval($item['price']);
        $effective_price = floatval($item['price']);
        $quantity = floatval($item['quantity']);
        
        $subtotal_without_discount += $regular_price * $quantity;
        $subtotal += $effective_price * $quantity;
        $item_count += $quantity;
        
        if ($effective_price < $regular_price) {
            $total_discount += ($regular_price - $effective_price) * $quantity;
        }
    }
    
    // DYNAMIC SHIPPING CALCULATION
    $shipping_cost = calculateShipping($subtotal);
    
    $tax_rate = 0.16;
    $tax_amount = $subtotal * $tax_rate;
    $total_amount = $subtotal + $shipping_cost + $tax_amount;
    
    return [
        'subtotal' => round($subtotal, 2),
        'subtotal_without_discount' => round($subtotal_without_discount, 2),
        'discount_amount' => round($total_discount, 2),
        'shipping_cost' => round($shipping_cost, 2),
        'tax_rate' => $tax_rate,
        'tax_amount' => round($tax_amount, 2),
        'total_amount' => round($total_amount, 2),
        'item_count' => $item_count,
        'savings' => round($total_discount, 2)
    ];
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
        $label = trim($parts[0]);
        $priceRaw = trim($parts[1]);
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
        // Backward compatibility for existing values saved without explicit price.
        $label = $line;
        $price = (float)$fallbackPrice;
    }

    if ($label === '') {
        return null;
    }
    if ($price === null || $price <= 0) {
        return null;
    }

    return [
        'label' => $label,
        'price' => round($price, 2)
    ];
}

function parseRemedyVariationOptions($customSizesRaw, $customSachetsRaw, $fallbackPrice) {
    $options = [];
    $seen = [];
    $sources = [(string)$customSizesRaw, (string)$customSachetsRaw];

    foreach ($sources as $source) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$source);
        foreach ($lines as $line) {
            $parsed = parseRemedyVariationLine($line, $fallbackPrice);
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

function getUsersColumnMapForCustomerApi(PDO $pdo) {
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $cached = [];
    try {
        $rows = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $field = trim((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $cached[strtolower($field)] = $field;
            }
        }
    } catch (Exception $e) {
        error_log('Could not map users columns: ' . $e->getMessage());
    }

    return $cached;
}

function ensureSignedInVerifiedCustomerAccount(PDO $pdo, $sessionUserId, array $input = []) {
    $sessionUserId = (int)$sessionUserId;
    if ($sessionUserId <= 0) {
        throw new RuntimeException('Please sign in before placing an order.');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$sessionUserId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new RuntimeException('Customer account was not found. Please sign in again.');
    }

    $currentRoleRaw = (string)($account['role'] ?? '');
    $normalizedRole = normalizeUserRole($currentRoleRaw);
    $knownRoles = ['customer', 'admin', 'staff', 'super_admin'];
    $isAdminLikeRole = in_array($normalizedRole, ['admin', 'staff', 'super_admin'], true);

    if (
        array_key_exists('email_verified', $account)
        && (int)$account['email_verified'] !== 1
        && $isAdminLikeRole
    ) {
        throw new RuntimeException('Email not verified. Verify your email before placing an order.');
    }

    $accountEmail = strtolower(trim((string)($account['email'] ?? '')));
    $inputEmail = strtolower(trim((string)($input['customer_email'] ?? '')));
    $customerEmail = $accountEmail !== '' ? $accountEmail : $inputEmail;
    if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid customer email is required for this order.');
    }

    $customerName = trim((string)($input['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = trim((string)($account['full_name'] ?? ''));
    }
    if ($customerName === '') {
        $local = (string)strstr($customerEmail, '@', true);
        $customerName = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        if ($customerName === '') {
            $customerName = 'Customer';
        }
    }

    $customerPhone = trim((string)($input['customer_phone'] ?? ''));
    if ($customerPhone === '') {
        $customerPhone = trim((string)($account['phone'] ?? ''));
    }

    $columns = getUsersColumnMapForCustomerApi($pdo);

    $updates = [];
    $params = [];
    $setField = static function($column, $value) use (&$updates, &$params) {
        $updates[] = "`{$column}` = ?";
        $params[] = $value;
    };

    if ($normalizedRole === '' || !in_array($normalizedRole, $knownRoles, true)) {
        if (isset($columns['role'])) {
            $setField($columns['role'], 'customer');
        }
        $normalizedRole = 'customer';
    }

    if (
        isset($columns['email_verified'])
        && (int)($account['email_verified'] ?? 0) !== 1
        && !$isAdminLikeRole
    ) {
        $setField($columns['email_verified'], 1);
    }

    if ($normalizedRole === 'customer') {
        if (isset($columns['status']) && strtolower((string)($account['status'] ?? '')) !== 'active') {
            $setField($columns['status'], 'active');
        }
        if (isset($columns['approval_status']) && strtolower((string)($account['approval_status'] ?? '')) !== 'approved') {
            $setField($columns['approval_status'], 'approved');
        }
        if (isset($columns['is_active']) && (int)($account['is_active'] ?? 0) !== 1) {
            $setField($columns['is_active'], 1);
        }
    }

    if (isset($columns['full_name']) && $customerName !== '' && trim((string)($account['full_name'] ?? '')) !== $customerName) {
        $setField($columns['full_name'], $customerName);
    }
    if (isset($columns['phone']) && $customerPhone !== '' && trim((string)($account['phone'] ?? '')) !== $customerPhone) {
        $setField($columns['phone'], $customerPhone);
    }
    if (isset($columns['updated_at'])) {
        $updates[] = "`{$columns['updated_at']}` = NOW()";
    }

    if (!empty($updates)) {
        $params[] = $sessionUserId;
        $updateSql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? LIMIT 1";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($params);
    }

    if (isset($_SESSION)) {
        $_SESSION['user_id'] = $sessionUserId;
        $_SESSION['email'] = $customerEmail;
        $_SESSION['full_name'] = $customerName;
        $_SESSION['phone'] = $customerPhone;
        if ($normalizedRole !== '') {
            $_SESSION['role'] = $normalizedRole;
        }
    }

    return [
        'id' => $sessionUserId,
        'email' => $customerEmail,
        'full_name' => $customerName,
        'phone' => $customerPhone,
        'role' => $normalizedRole
    ];
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || $input === null) {
    $input = $_REQUEST;
}

// Get action from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Find 'api' in segments
$apiIndex = array_search('api', $segments);
if ($apiIndex !== false && isset($segments[$apiIndex + 1])) {
    $action = $segments[$apiIndex + 1];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($input['action'])) {
    $action = $input['action'];
} else {
    $action = '';
}

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'products':
                    $variationQueryParts = getRemedyVariationQueryParts($pdo);
                    $variationSelect = $variationQueryParts['select'];
                    $variationJoin = $variationQueryParts['join'];

                    $stmt = $pdo->prepare("
                        SELECT r.*, c.name as category_name {$variationSelect}
                        FROM remedies r 
                        LEFT JOIN categories c ON r.category_id = c.id 
                        {$variationJoin}
                        WHERE r.is_active = 1
                        ORDER BY r.name
                    ");
                    $stmt->execute();
                    $products = $stmt->fetchAll();
                    
                    // Get cart quantities for current session
                    $cartQuantities = [];
                    if (isset($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $item) {
                            $id = (int)($item['id'] ?? 0);
                            if ($id <= 0) {
                                continue;
                            }
                            if (!isset($cartQuantities[$id])) {
                                $cartQuantities[$id] = 0;
                            }
                            $cartQuantities[$id] += (float)($item['quantity'] ?? 0);
                        }
                    }
                    
                    // Calculate available stock and add effective price
                    foreach ($products as &$product) {
                        $inCart = isset($cartQuantities[$product['id']]) ? $cartQuantities[$product['id']] : 0;
                        $product['available_stock'] = $product['stock_quantity'] - $inCart;
                        
                        // Add effective price
                        if (!empty($product['discount_price']) && $product['discount_price'] > 0) {
                            $product['effective_price'] = $product['discount_price'];
                            $product['regular_price'] = $product['unit_price'];
                            $product['savings'] = $product['unit_price'] - $product['discount_price'];
                        } else {
                            $product['effective_price'] = $product['unit_price'];
                            $product['regular_price'] = $product['unit_price'];
                            $product['savings'] = 0;
                        }
                        $product['variation_options'] = parseRemedyVariationOptions(
                            $product['custom_sizes'] ?? '',
                            $product['custom_sachets'] ?? '',
                            (float)$product['effective_price']
                        );
                    }
                    
                    $response = ['success' => true, 'data' => $products];
                    break;
                    
                case 'product':
                    // Get single product details
                    if (isset($_GET['id'])) {
                        $variationQueryParts = getRemedyVariationQueryParts($pdo);
                        $variationSelect = $variationQueryParts['select'];
                        $variationJoin = $variationQueryParts['join'];

                        $stmt = $pdo->prepare("
                            SELECT r.*, c.name as category_name, s.name as supplier_name {$variationSelect}
                            FROM remedies r 
                            LEFT JOIN categories c ON r.category_id = c.id 
                            LEFT JOIN suppliers s ON r.supplier_id = s.id
                            {$variationJoin}
                            WHERE r.id = ?
                        ");
                        $stmt->execute([$_GET['id']]);
                        $product = $stmt->fetch();
                        
                        if ($product) {
                            // Add effective price
                            if (!empty($product['discount_price']) && $product['discount_price'] > 0) {
                                $product['effective_price'] = $product['discount_price'];
                                $product['regular_price'] = $product['unit_price'];
                                $product['savings'] = $product['unit_price'] - $product['discount_price'];
                                $product['savings_percentage'] = round(($product['savings'] / $product['unit_price']) * 100, 1);
                            } else {
                                $product['effective_price'] = $product['unit_price'];
                                $product['regular_price'] = $product['unit_price'];
                                $product['savings'] = 0;
                                $product['savings_percentage'] = 0;
                            }
                            $product['variation_options'] = parseRemedyVariationOptions(
                                $product['custom_sizes'] ?? '',
                                $product['custom_sachets'] ?? '',
                                (float)$product['effective_price']
                            );
                            
                            $response = ['success' => true, 'data' => $product];
                        } else {
                            $response = ['success' => false, 'message' => 'Product not found'];
                        }
                    }
                    break;
                    
                case 'categories':
                    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
                    $response = ['success' => true, 'data' => $stmt->fetchAll()];
                    break;
                    
                case 'cart':
                    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
                    $cartSummary = calculateCartTotals($cart);
                    
                    // Add effective price info to each item
                    foreach ($cart as &$item) {
                        if (!isset($item['regular_price'])) {
                            $item['regular_price'] = $item['price'];
                        }
                        if (!isset($item['variation_label'])) {
                            $item['variation_label'] = '';
                        }
                        $item['cart_key'] = getCartItemKey($item);
                    }
                    unset($item);
                    $_SESSION['cart'] = $cart;
                    
                    $response = [
                        'success' => true, 
                        'data' => $cart,
                        'summary' => $cartSummary
                    ];
                    break;
                    
                case 'order':
                    if (isset($_GET['order_number'])) {
                        $orderNumber = $_GET['order_number'];
                        
                        $stmt = $pdo->prepare("
                            SELECT o.* 
                            FROM orders o 
                            WHERE o.order_number = ?
                        ");
                        $stmt->execute([$orderNumber]);
                        $order = $stmt->fetch();
                        
                        if ($order) {
                            // Get order items
                            $stmt = $pdo->prepare("
                                SELECT * FROM order_items 
                                WHERE order_id = ?
                            ");
                            $stmt->execute([$order['id']]);
                            $order['items'] = $stmt->fetchAll();
                            
                            $response = ['success' => true, 'data' => $order];
                        } else {
                            $response = ['success' => false, 'message' => 'Order not found'];
                        }
                    }
                    break;

                case 'my-orders':
                    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
                    $sessionEmail = trim((string)($_SESSION['email'] ?? ''));
                    $limit = isset($_GET['limit']) ? max(1, min(20, (int)$_GET['limit'])) : 10;

                    if ($sessionUserId <= 0 && $sessionEmail === '') {
                        $response = ['success' => false, 'message' => 'Please sign in to view order history'];
                        break;
                    }

                    $where = '';
                    $params = [];
                    if ($sessionUserId > 0 && $sessionEmail !== '') {
                        $where = "(user_id = :uid OR customer_email = :email)";
                        $params[':uid'] = $sessionUserId;
                        $params[':email'] = $sessionEmail;
                    } elseif ($sessionUserId > 0) {
                        $where = "user_id = :uid";
                        $params[':uid'] = $sessionUserId;
                    } else {
                        $where = "customer_email = :email";
                        $params[':email'] = $sessionEmail;
                    }

                    $historyStmt = $pdo->prepare("
                        SELECT id, order_number, total_amount, payment_status, order_status, created_at
                        FROM orders
                        WHERE $where
                        ORDER BY created_at DESC
                        LIMIT $limit
                    ");
                    foreach ($params as $k => $v) {
                        $historyStmt->bindValue($k, $v);
                    }
                    $historyStmt->execute();

                    $response = [
                        'success' => true,
                        'data' => $historyStmt->fetchAll(PDO::FETCH_ASSOC)
                    ];
                    break;
                    
                default:
                    $response = ['success' => false, 'message' => 'Unknown action'];
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'update-profile':
                    $sessionUserId = isset($_SESSION['user_id'])
                        ? (int)$_SESSION['user_id']
                        : 0;

                    if ($sessionUserId <= 0) {
                        $response = ['success' => false, 'message' => 'Please sign in to update your profile.'];
                        break;
                    }

                    $fullName = trim((string)($input['full_name'] ?? ''));
                    $email = trim((string)($input['email'] ?? ''));
                    $phone = trim((string)($input['phone'] ?? ''));
                    $address = trim((string)($input['address'] ?? ''));
                    $city = trim((string)($input['city'] ?? ''));
                    $postalCode = trim((string)($input['postal_code'] ?? ''));
                    $currentPassword = (string)($input['current_password'] ?? '');
                    $newPassword = (string)($input['new_password'] ?? '');
                    $confirmPassword = (string)($input['confirm_password'] ?? '');

                    if ($fullName === '' || $email === '') {
                        $response = ['success' => false, 'message' => 'Full name and email are required.'];
                        break;
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $response = ['success' => false, 'message' => 'Please enter a valid email address.'];
                        break;
                    }

                    $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
                    $emailCheckStmt->execute([$email, $sessionUserId]);
                    if ($emailCheckStmt->fetch()) {
                        $response = ['success' => false, 'message' => 'That email is already in use by another account.'];
                        break;
                    }

                    $userStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
                    $userStmt->execute([$sessionUserId]);
                    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$userRow) {
                        $response = ['success' => false, 'message' => 'User account not found.'];
                        break;
                    }

                    $newPasswordHash = null;
                    $passwordChangeRequested = ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '');
                    if ($passwordChangeRequested) {
                        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                            $response = ['success' => false, 'message' => 'To change password, fill current, new, and confirm password fields.'];
                            break;
                        }
                        if (!password_verify($currentPassword, $userRow['password_hash'])) {
                            $response = ['success' => false, 'message' => 'Current password is incorrect.'];
                            break;
                        }
                        if (strlen($newPassword) < 8) {
                            $response = ['success' => false, 'message' => 'New password must be at least 8 characters.'];
                            break;
                        }
                        if ($newPassword !== $confirmPassword) {
                            $response = ['success' => false, 'message' => 'New password confirmation does not match.'];
                            break;
                        }
                        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    }

                    if ($newPasswordHash !== null) {
                        $updateStmt = $pdo->prepare("
                            UPDATE users
                            SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ?, password_hash = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$fullName, $email, $phone, $address, $city, $postalCode, $newPasswordHash, $sessionUserId]);
                    } else {
                        $updateStmt = $pdo->prepare("
                            UPDATE users
                            SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$fullName, $email, $phone, $address, $city, $postalCode, $sessionUserId]);
                    }

                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                    $_SESSION['phone'] = $phone;

                    $response = [
                        'success' => true,
                        'message' => $newPasswordHash !== null ? 'Profile and password updated successfully.' : 'Profile updated successfully.',
                        'user' => [
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => $phone,
                            'address' => $address,
                            'city' => $city,
                            'postal_code' => $postalCode
                        ]
                    ];
                    break;

                case 'add-to-cart':
                    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
                    $quantity = isset($input['quantity']) ? (float)$input['quantity'] : 1;
                    $variationLabel = trim((string)($input['variation_label'] ?? ''));
                    
                    if ($product_id <= 0 || $quantity <= 0) {
                        $response = ['success' => false, 'message' => 'Invalid product or quantity'];
                        break;
                    }
                    
                    $variationQueryParts = getRemedyVariationQueryParts($pdo);
                    $variationSelect = $variationQueryParts['select'];
                    $variationJoin = $variationQueryParts['join'];

                    // Get product details including variation definitions.
                    $stmt = $pdo->prepare("
                        SELECT r.id, r.name, r.unit_price, r.discount_price, r.sku, r.stock_quantity
                               {$variationSelect}
                        FROM remedies r
                        {$variationJoin}
                        WHERE r.id = ? AND r.is_active = 1
                    ");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if (!$product) {
                        $response = ['success' => false, 'message' => 'Product not found'];
                        break;
                    }

                    $baseEffectivePrice = !empty($product['discount_price']) && $product['discount_price'] > 0
                        ? floatval($product['discount_price'])
                        : floatval($product['unit_price']);

                    $variationOptions = parseRemedyVariationOptions(
                        $product['custom_sizes'] ?? '',
                        $product['custom_sachets'] ?? '',
                        $baseEffectivePrice
                    );

                    $selectedVariation = null;
                    if ($variationLabel !== '') {
                        foreach ($variationOptions as $option) {
                            if (strtolower(trim((string)$option['label'])) === strtolower($variationLabel)) {
                                $selectedVariation = $option;
                                break;
                            }
                        }
                        if ($selectedVariation === null) {
                            $response = ['success' => false, 'message' => 'Selected variation is no longer available.'];
                            break;
                        }
                    } elseif (!empty($variationOptions)) {
                        $selectedVariation = $variationOptions[0];
                    }

                    if ($selectedVariation !== null) {
                        $variationLabel = (string)$selectedVariation['label'];
                    }

                    $effectivePrice = $selectedVariation !== null
                        ? (float)$selectedVariation['price']
                        : $baseEffectivePrice;
                    $regularPrice = $selectedVariation !== null
                        ? $effectivePrice
                        : floatval($product['unit_price']);
                    $discountPrice = $selectedVariation !== null
                        ? null
                        : (!empty($product['discount_price']) ? floatval($product['discount_price']) : null);

                    $cartKey = buildCartItemKey($product_id, $variationLabel);

                    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    // Check if this exact product variation already exists in cart.
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if (getCartItemKey($item) === $cartKey) {
                            $newQuantity = (float)$item['quantity'] + $quantity;
                            $stockCheck = checkStock($pdo, $product_id, $newQuantity, $cartKey);
                            if (!$stockCheck['available']) {
                                $response = ['success' => false, 'message' => $stockCheck['message']];
                                break 2;
                            }
                            
                            $item['quantity'] = $newQuantity;
                            $item['price'] = $effectivePrice;
                            $item['regular_price'] = $regularPrice;
                            $item['discount_price'] = $discountPrice;
                            $item['variation_label'] = $variationLabel;
                            $item['cart_key'] = $cartKey;
                            $found = true;
                            break;
                        }
                    }
                    unset($item);
                    
                    if (!$found) {
                        $stockCheck = checkStock($pdo, $product_id, $quantity);
                        if (!$stockCheck['available']) {
                            $response = ['success' => false, 'message' => $stockCheck['message']];
                            break;
                        }

                        $_SESSION['cart'][] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'price' => $effectivePrice,
                            'regular_price' => $regularPrice,
                            'discount_price' => $discountPrice,
                            'quantity' => $quantity,
                            'sku' => $product['sku'] ?? '',
                            'variation_label' => $variationLabel,
                            'cart_key' => $cartKey
                        ];
                    }
                    
                    $cartSummary = calculateCartTotals($_SESSION['cart']);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Added to cart',
                        'cart_count' => $cartSummary['item_count'],
                        'summary' => $cartSummary
                    ];
                    break;
                    
                case 'update-cart':
                    $cart_key = trim((string)($input['cart_key'] ?? ''));
                    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
                    $quantity = isset($input['quantity']) ? (float)$input['quantity'] : 0;
                    
                    if ($cart_key === '' && $product_id <= 0) {
                        $response = ['success' => false, 'message' => 'Invalid cart item'];
                        break;
                    }
                    
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    
                    $newCart = [];
                    $updated = false;
                    foreach ($_SESSION['cart'] as $item) {
                        $itemKey = getCartItemKey($item);
                        $item['cart_key'] = $itemKey;
                        $matchesTarget = ($cart_key !== '')
                            ? ($itemKey === $cart_key)
                            : ((int)$item['id'] === $product_id);

                        if ($matchesTarget) {
                            $updated = true;
                            if ($quantity > 0) {
                                $stockCheck = checkStock($pdo, (int)$item['id'], $quantity, $itemKey);
                                if (!$stockCheck['available']) {
                                    $response = ['success' => false, 'message' => $stockCheck['message']];
                                    break 2;
                                }
                                
                                $item['quantity'] = $quantity;
                                $newCart[] = $item;
                            }
                        } else {
                            $newCart[] = $item;
                        }
                    }

                    if (!$updated) {
                        $response = ['success' => false, 'message' => 'Cart item not found'];
                        break;
                    }
                    
                    $_SESSION['cart'] = array_values($newCart);
                    $cartSummary = calculateCartTotals($_SESSION['cart']);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Cart updated',
                        'summary' => $cartSummary
                    ];
                    break;
                    
                case 'remove-from-cart':
                    $cart_key = trim((string)($input['cart_key'] ?? ''));
                    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
                    
                    if ($cart_key === '' && $product_id <= 0) {
                        $response = ['success' => false, 'message' => 'Invalid cart item'];
                        break;
                    }

                    if (isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($product_id, $cart_key) {
                            $itemKey = getCartItemKey($item);
                            if ($cart_key !== '') {
                                return $itemKey !== $cart_key;
                            }
                            return (int)$item['id'] !== $product_id;
                        });
                        $_SESSION['cart'] = array_values($_SESSION['cart']);
                    }
                    
                    $cartSummary = calculateCartTotals($_SESSION['cart']);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Removed from cart',
                        'summary' => $cartSummary
                    ];
                    break;
                    
                case 'place-order':
                    $sessionUserId = isset($_SESSION['user_id'])
                        ? $_SESSION['user_id']
                        : null;
                    if (!$sessionUserId) {
                        $response = [
                            'success' => false,
                            'message' => 'Please sign in before placing an order'
                        ];
                        break;
                    }

                    try {
                        $resolvedCustomer = ensureSignedInVerifiedCustomerAccount($pdo, (int)$sessionUserId, $input);
                    } catch (RuntimeException $e) {
                        $response = [
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                        break;
                    }

                    $input['customer_email'] = $resolvedCustomer['email'];
                    if (empty(trim((string)($input['customer_name'] ?? '')))) {
                        $input['customer_name'] = $resolvedCustomer['full_name'];
                    }
                    if (empty(trim((string)($input['customer_phone'] ?? '')))) {
                        $input['customer_phone'] = $resolvedCustomer['phone'];
                    }

                    // Validate required fields
                    $required = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address'];
                    foreach ($required as $field) {
                        if (empty($input[$field])) {
                            $response = ['success' => false, 'message' => "Missing required field: $field"];
                            break 2;
                        }
                    }
                    
                    if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
                        $response = ['success' => false, 'message' => 'Cart is empty'];
                        break;
                    }
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Calculate totals with discount tracking
                        $subtotal_without_discount = 0;
                        $discount_amount = 0;
                        $subtotal = 0;
                        
                        foreach ($_SESSION['cart'] as $item) {
                            $regular_price = floatval($item['regular_price']);
                            $effective_price = floatval($item['price']);
                            $qty = floatval($item['quantity']);
                            
                            $subtotal_without_discount += $regular_price * $qty;
                            $subtotal += $effective_price * $qty;
                            
                            if ($effective_price < $regular_price) {
                                $discount_amount += ($regular_price - $effective_price) * $qty;
                            }
                        }
                        
                        // DYNAMIC SHIPPING
                        $shipping_cost = calculateShipping($subtotal);
                        
                        $tax_rate = 0.16;
                        $tax_amount = $subtotal * $tax_rate;
                        $total_amount = $subtotal + $shipping_cost + $tax_amount;
                        
                        // Generate order number
                        $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                        
                        // Insert order with discount tracking
                        $stmt = $pdo->prepare("
                            INSERT INTO orders (
                                order_number, user_id, customer_name, customer_email, 
                                customer_phone, customer_alt_phone, shipping_address, 
                                shipping_city, shipping_postal_code,
                                billing_address, billing_city, billing_postal_code,
                                payment_method, transaction_id, payment_status, order_status,
                                subtotal, shipping_cost, tax_amount, discount_amount, 
                                total_amount, notes
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?
                            )
                        ");
                        
                        $user_id = (int)$resolvedCustomer['id'];
                        $billing_address = isset($input['billing_address']) && !empty($input['billing_address']) 
                            ? $input['billing_address'] 
                            : $input['shipping_address'];
                        
                        $stmt->execute([
                            $order_number,
                            $user_id,
                            $input['customer_name'],
                            $input['customer_email'],
                            $input['customer_phone'],
                            $input['customer_alt_phone'] ?? null,
                            $input['shipping_address'],
                            $input['shipping_city'] ?? null,
                            $input['shipping_postal_code'] ?? null,
                            $billing_address,
                            $input['billing_city'] ?? null,
                            $input['billing_postal_code'] ?? null,
                            $input['payment_method'] ?? 'cash',
                            $input['transaction_id'] ?? null,
                            'pending',
                            'pending',
                            $subtotal,
                            $shipping_cost,
                            $tax_amount,
                            $discount_amount,
                            $total_amount,
                            $input['notes'] ?? null
                        ]);
                        
                        $order_id = $pdo->lastInsertId();
                        
                        // Insert order items and update stock
                        foreach ($_SESSION['cart'] as $item) {
                            $stmt = $pdo->prepare("
                                INSERT INTO order_items (
                                    order_id, product_id, product_name, product_sku,
                                    unit_price, quantity, total_price
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $item_total = floatval($item['price']) * floatval($item['quantity']);
                            $productName = (string)($item['name'] ?? '');
                            $variationLabel = trim((string)($item['variation_label'] ?? ''));
                            if ($variationLabel !== '') {
                                $productName .= ' (' . $variationLabel . ')';
                            }
                            $stmt->execute([
                                $order_id,
                                $item['id'],
                                $productName,
                                $item['sku'],
                                $item['price'],
                                $item['quantity'],
                                $item_total
                            ]);
                            
                            // Update product stock
                            $stmt = $pdo->prepare("
                                UPDATE remedies 
                                SET stock_quantity = stock_quantity - ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$item['quantity'], $item['id']]);
                        }
                        
                        // Create audit log
                        $stmt = $pdo->prepare("
                            INSERT INTO audit_log (action, table_name, record_id, user_id)
                            VALUES ('order_created', 'orders', ?, ?)
                        ");
                        $stmt->execute([$order_id, $user_id]);
                        
                        // Commit transaction
                        $pdo->commit();

                        $notification = null;
                        if (function_exists('sendOrderLifecycleNotification')) {
                            $notification = sendOrderLifecycleNotification($pdo, (int)$order_id, 'checkout', []);
                        }
                        
                        // Clear cart
                        unset($_SESSION['cart']);
                        
                        $response = [
                            'success' => true,
                            'message' => 'Order placed successfully',
                            'order_number' => $order_number,
                            'order_id' => $order_id,
                            'order_summary' => [
                                'subtotal' => $subtotal,
                                'discount' => $discount_amount,
                                'shipping' => $shipping_cost,
                                'tax' => $tax_amount,
                                'total' => $total_amount
                            ],
                            'notification' => $notification
                        ];
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'clear-cart':
                    unset($_SESSION['cart']);
                    $response = ['success' => true, 'message' => 'Cart cleared'];
                    break;
                    
                default:
                    $response = ['success' => false, 'message' => 'Unknown action'];
            }
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Method not allowed'];
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

echo json_encode($response);
