<?php
// ajax/create_order.php

header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$servitorId = (int)($_SESSION['admin_id'] ?? 0);


try {
    // Validate required fields
    $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'shipping_city'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Get order items
    $orderItems = json_decode($_POST['order_items'] ?? '[]', true);
    if (empty($orderItems)) {
        throw new Exception('Order must have at least one item');
    }
    
    // Generate order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Calculate totals
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $shippingFee = floatval($_POST['shipping_fee'] ?? 0);
    $discountAmount = floatval($_POST['discount_amount'] ?? 0);
    $totalAmount = floatval($_POST['total_amount'] ?? 0);
    
    // Begin transaction
    $pdo->beginTransaction();

    // Ensure order is linked to a registered customer account (not admin actor).
    $customerUserId = ensureCustomerOrderUser(
        $pdo,
        (string)($_POST['customer_email'] ?? ''),
        (string)($_POST['customer_name'] ?? ''),
        (string)($_POST['customer_phone'] ?? ''),
        $servitorId,
        'admin_order'
    );
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, 
            user_id,
            customer_name, 
            customer_email, 
            customer_phone,
            customer_alt_phone,
            shipping_address,
            shipping_city,
            shipping_postal_code,
            subtotal,
            shipping_fee,
            discount_amount,
            total_amount,
            payment_method,
            payment_status,
            order_status,
            transaction_id,
            notes,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    
    $stmt->execute([
        $orderNumber,
        $customerUserId,
        $_POST['customer_name'],
        $_POST['customer_email'],
        $_POST['customer_phone'],
        $_POST['customer_alt_phone'] ?? null,
        $_POST['shipping_address'],
        $_POST['shipping_city'],
        $_POST['shipping_postal_code'] ?? null,
        $subtotal,
        $shippingFee,
        $discountAmount,
        $totalAmount,
        $_POST['payment_method'] ?? 'mpesa',
        $_POST['payment_status'] ?? 'pending',
        $_POST['order_status'] ?? 'pending',
        $_POST['transaction_id'] ?? null,
        $_POST['notes'] ?? null
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Insert order items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            product_name,
            quantity,
            unit_price,
            total_price
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($orderItems as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['quantity'],
            $item['unit_price'],
            $item['total']
        ]);
        
        // Update product stock
        $updateStockStmt = $pdo->prepare("
            UPDATE remedies 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $orderId,
        'order_number' => $orderNumber
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
