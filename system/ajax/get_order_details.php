<?php
session_start();
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$order_id = intval($_POST['order_id'] ?? 0);
$isOrderAdmin = in_array(strtolower((string)($_SESSION['admin_role'] ?? '')), ['admin', 'super_admin'], true);

if ($order_id <= 0) {
    echo '<div class="alert alert-danger">Invalid order ID</div>';
    exit;
}

try {
    // First, let's debug what tables we have
    $debug = false; // Set to true for debugging
    
    if ($debug) {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Available tables: " . implode(", ", $tables) . "<br>";
    }
    
    // Get order details - FIXED QUERY
    // Based on your database structure, you might not have 'user_id' in orders table
    // Let's check what columns exist in orders table
    
    // Try to get basic order info first
    $stmt = $pdo->prepare("
        SELECT o.* 
        FROM orders o
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo '<div class="alert alert-danger">Order not found</div>';
        exit;
    }
    
    // Debug order data
    if ($debug) {
        echo "<pre>Order data: ";
        print_r($order);
        echo "</pre>";
    }
    
    // Try to get customer info - check what customer fields exist
    $customer_name = $order['customer_name'] ?? 'N/A';
    $customer_email = $order['customer_email'] ?? 'N/A';
    $customer_phone = $order['customer_phone'] ?? 'N/A';
    
    // Get order items
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, 
               r.name as product_name,
               r.slug as alt_product_name
        FROM order_items oi
        LEFT JOIN remedies r ON oi.product_id = r.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    
    $itemsStmt->execute([$order_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($debug) {
        echo "<pre>Items data: ";
        print_r($items);
        echo "</pre>";
    }
    
    // If no items, create empty array
    if (!$items) {
        $items = [];
        
        // Try alternative query without JOIN
        $altStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $altStmt->execute([$order_id]);
        $items = $altStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    ?>
    <style>
        .order-details-modal .badge {
            font-size: 0.8em;
            padding: 5px 10px;
        }
        .order-details-modal .table th {
            background: #f8f9fa;
        }
        .order-details-modal .card {
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
        }
        .order-details-modal .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 15px;
        }
        .order-details-modal .card-body {
            padding: 15px;
        }
        .order-details-modal {
            max-width: 900px;
            margin: 0 auto;
        }
    </style>
    
    <div class="order-details-modal">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Order Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></p>
                        <p><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($order['created_at'] ?? 'now')); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php echo getStatusColor($order['order_status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($order['order_status'] ?? 'Pending'); ?>
                            </span>
                        </p>
                        <p><strong>Payment:</strong> 
                            <span class="badge bg-<?php echo getStatusColor($order['payment_status'] ?? 'pending'); ?>">
                                <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                            </span>
                        </p>
                        <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></p>
                        <p><strong>Total:</strong> KSh <?php echo number_format($order['total_amount'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user"></i> Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
                        <?php if ($isOrderAdmin): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($customer_email); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer_phone); ?></p>
                        <?php if (!empty($order['shipping_address'])): ?>
                        <p><strong>Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </p>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_notes'])): ?>
                        <p><strong>Notes:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-shopping-cart"></i> Order Items (<?php echo count($items); ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($items)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            foreach ($items as $item): 
                                $product_name = $item['product_name'] ?? $item['alt_product_name'] ?? $item['product_id'] ?? 'Product';
                                $quantity = $item['quantity'] ?? 1;
                                $unit_price = $item['unit_price'] ?? $item['price'] ?? 0;
                                $total_price = $item['total_price'] ?? ($quantity * $unit_price);
                                
                                $subtotal += $total_price;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product_name); ?></td>
                                <td><?php echo $quantity; ?></td>
                                <td>KSh <?php echo number_format($unit_price, 2); ?></td>
                                <td>KSh <?php echo number_format($total_price, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td><strong>KSh <?php echo number_format($subtotal, 2); ?></strong></td>
                            </tr>
                            <?php if (isset($order['shipping_fee']) && $order['shipping_fee'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                <td>KSh <?php echo number_format($order['shipping_fee'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($order['tax_amount']) && $order['tax_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                <td>KSh <?php echo number_format($order['tax_amount'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                <td>-KSh <?php echo number_format($order['discount_amount'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-active">
                                <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                <td><strong>KSh <?php echo number_format($order['total_amount'] ?? $subtotal, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No items found for this order</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($order['notes'])): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-sticky-note"></i> Order Notes</h6>
            </div>
            <div class="card-body">
                <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    
} catch (Exception $e) {
    // Show more detailed error for debugging
    error_log("Order details error: " . $e->getMessage());
    
    echo '<div class="alert alert-danger">';
    echo '<h5><i class="fas fa-exclamation-triangle"></i> Error Loading Order Details</h5>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<small class="text-muted">Order ID: ' . $order_id . '</small>';
    echo '</div>';
}
?>
