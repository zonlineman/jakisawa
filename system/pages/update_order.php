<?php
// ajax/update_order.php

header('Content-Type: application/json');

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/order_notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$servitorId = (int)($_SESSION['admin_id'] ?? 0);

// Only admin and super admin can edit full orders (prices/items).
$rawRole = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $rawRole)));
if ($normalizedRole === 'superadmin') {
    $normalizedRole = 'super_admin';
}

if (!in_array($normalizedRole, ['admin', 'super_admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only admin or super admin can edit orders']);
    exit;
}

try {
    // Validate required fields
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    if (!$order_id) {
        throw new Exception('Order ID is required');
    }
    
    $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'shipping_city'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    $customerUserId = ensureCustomerOrderUser(
        $pdo,
        (string)($_POST['customer_email'] ?? ''),
        (string)($_POST['customer_name'] ?? ''),
        (string)($_POST['customer_phone'] ?? ''),
        $servitorId,
        'admin_order'
    );
    
    // Get order items
    $orderItems = json_decode($_POST['order_items'] ?? '[]', true);
    $removedItems = json_decode($_POST['removed_items'] ?? '[]', true);
    if (!is_array($orderItems)) {
        $orderItems = [];
    }
    if (!is_array($removedItems)) {
        $removedItems = [];
    }
    
    // Calculate totals
    $shippingFee = floatval($_POST['shipping_fee'] ?? 0);
    $discountAmount = floatval($_POST['discount_amount'] ?? 0);
    if ($shippingFee < 0 || $discountAmount < 0) {
        throw new Exception('Shipping fee and discount must be non-negative');
    }
    
    // Begin transaction
    $pdo->beginTransaction();

    // Ensure order exists
    $orderExistsStmt = $pdo->prepare("SELECT id, order_status, payment_status FROM orders WHERE id = ? LIMIT 1");
    $orderExistsStmt->execute([$order_id]);
    $beforeOrder = $orderExistsStmt->fetch(PDO::FETCH_ASSOC);
    if (!$beforeOrder) {
        throw new Exception('Order not found');
    }
    
    // Update order
    $stmt = $pdo->prepare("
        UPDATE orders SET
            customer_name = ?,
            customer_email = ?,
            customer_phone = ?,
            customer_alt_phone = ?,
            shipping_address = ?,
            shipping_city = ?,
            shipping_postal_code = ?,
            subtotal = ?,
            shipping_fee = ?,
            discount_amount = ?,
            total_amount = ?,
            payment_method = ?,
            payment_status = ?,
            order_status = ?,
            transaction_id = ?,
            notes = ?,
            user_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['customer_name'],
        $_POST['customer_email'],
        $_POST['customer_phone'],
        $_POST['customer_alt_phone'] ?? null,
        $_POST['shipping_address'],
        $_POST['shipping_city'],
        $_POST['shipping_postal_code'] ?? null,
        0, // recalculated after item updates
        $shippingFee,
        $discountAmount,
        0, // recalculated after item updates
        $_POST['payment_method'] ?? 'mpesa',
        $_POST['payment_status'] ?? 'pending',
        $_POST['order_status'] ?? 'pending',
        $_POST['transaction_id'] ?? null,
        $_POST['notes'] ?? null,
        $customerUserId,
        $order_id
    ]);
    
    // Handle removed items
    if (!empty($removedItems)) {
        foreach ($removedItems as $itemId) {
            $itemId = (int)$itemId;
            if ($itemId <= 0) {
                continue;
            }
            // Get item details before deleting (for stock restoration)
            $getItemStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE id = ? AND order_id = ?");
            $getItemStmt->execute([$itemId, $order_id]);
            $removedItem = $getItemStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($removedItem) {
                // Restore stock
                $restoreStockStmt = $pdo->prepare("
                    UPDATE remedies 
                    SET stock_quantity = stock_quantity + ? 
                    WHERE id = ?
                ");
                $restoreStockStmt->execute([$removedItem['quantity'], $removedItem['product_id']]);
                
                // Delete item
                $deleteItemStmt = $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                $deleteItemStmt->execute([$itemId, $order_id]);
            }
        }
    }
    
    // Handle order items (update existing and add new)
    foreach ($orderItems as $item) {
        $qty = (int)($item['quantity'] ?? 0);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        if ($qty <= 0) {
            throw new Exception('Each order item must have quantity greater than zero');
        }
        if ($unitPrice < 0) {
            throw new Exception('Unit price cannot be negative');
        }
        $lineTotal = round($qty * $unitPrice, 2);

        if (isset($item['id']) && $item['id']) {
            // Existing item - update it
            // First, get original quantity to calculate stock difference
            $itemId = (int)$item['id'];
            $getOriginalStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE id = ? AND order_id = ?");
            $getOriginalStmt->execute([$itemId, $order_id]);
            $original = $getOriginalStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($original) {
                $quantityDiff = $qty - (int)$original['quantity'];
                
                // Update stock if quantity changed
                if ($quantityDiff > 0) {
                    $stockCheckStmt = $pdo->prepare("SELECT stock_quantity FROM remedies WHERE id = ?");
                    $stockCheckStmt->execute([$original['product_id']]);
                    $availableStock = (int)$stockCheckStmt->fetchColumn();
                    if ($availableStock < $quantityDiff) {
                        throw new Exception('Insufficient stock for one or more items');
                    }
                }
                if ($quantityDiff != 0) {
                    $updateStockStmt = $pdo->prepare("
                        UPDATE remedies 
                        SET stock_quantity = stock_quantity - ? 
                        WHERE id = ?
                    ");
                    $updateStockStmt->execute([$quantityDiff, $original['product_id']]);
                }
                
                // Update order item
                $updateItemStmt = $pdo->prepare("
                    UPDATE order_items 
                    SET quantity = ?, 
                        unit_price = ?, 
                        total_price = ?
                    WHERE id = ?
                ");
                $updateItemStmt->execute([
                    $qty,
                    $unitPrice,
                    $lineTotal,
                    $itemId
                ]);
            }
        } else {
            // New item - insert it
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new Exception('Invalid product selected');
            }

            $productStmt = $pdo->prepare("SELECT name, stock_quantity FROM remedies WHERE id = ? AND is_active = 1");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception('A selected product was not found');
            }
            if ((int)$product['stock_quantity'] < $qty) {
                throw new Exception('Insufficient stock for one or more new items');
            }

            $insertItemStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    quantity,
                    unit_price,
                    total_price
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertItemStmt->execute([
                $order_id,
                $productId,
                $product['name'],
                $qty,
                $unitPrice,
                $lineTotal
            ]);
            
            // Reduce stock
            $reduceStockStmt = $pdo->prepare("
                UPDATE remedies 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ?
            ");
            $reduceStockStmt->execute([$qty, $productId]);
        }
    }

    $subTotalStmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM order_items WHERE order_id = ?");
    $subTotalStmt->execute([$order_id]);
    $subtotal = (float)$subTotalStmt->fetchColumn();
    if ($subtotal <= 0) {
        throw new Exception('Order must have at least one item');
    }
    $totalAmount = round(max(0, $subtotal + $shippingFee - $discountAmount), 2);

    $recalcStmt = $pdo->prepare("UPDATE orders SET subtotal = ?, total_amount = ?, user_id = ?, updated_at = NOW() WHERE id = ?");
    $recalcStmt->execute([$subtotal, $totalAmount, $customerUserId, $order_id]);

    auditLogPdo(
        $pdo,
        'order_edited',
        'orders',
        $order_id,
        null,
        'Order edited',
        (int)($_SESSION['admin_id'] ?? 0),
        $_SERVER['REMOTE_ADDR'] ?? null
    );
    
    // Commit transaction
    $pdo->commit();

    $changes = [];
    $newOrderStatus = (string)($_POST['order_status'] ?? 'pending');
    $newPaymentStatus = (string)($_POST['payment_status'] ?? 'pending');
    $oldOrderStatus = (string)($beforeOrder['order_status'] ?? '');
    $oldPaymentStatus = (string)($beforeOrder['payment_status'] ?? '');

    if ($oldOrderStatus !== $newOrderStatus) {
        $changes['order_status'] = ['old' => $oldOrderStatus, 'new' => $newOrderStatus];
    }
    if ($oldPaymentStatus !== $newPaymentStatus) {
        $changes['payment_status'] = ['old' => $oldPaymentStatus, 'new' => $newPaymentStatus];
    }

    $notification = null;
    if (!empty($changes)) {
        $notification = sendOrderLifecycleNotification($pdo, $order_id, 'status_update', $changes);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully',
        'notification' => $notification
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
