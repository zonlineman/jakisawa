<?php
session_start();
require_once '../../includes/database.php';

$order_id = $_POST['order_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR '<br>') as items_list,
           SUM(oi.total_price) as subtotal
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ?
    GROUP BY o.id
");

$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    ?>
    <div class="order-details">
        <div class="row">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <p><strong>Order #:</strong> <?php echo $order['order_number']; ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($order['order_status']); ?></p>
                <p><strong>Payment:</strong> <?php echo ucfirst($order['payment_status']); ?></p>
            </div>
            <div class="col-md-6">
                <h6>Customer Information</h6>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
            </div>
        </div>
        
        <hr>
        
        <h6>Order Items</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$order_id]);
                    $items = $itemsStmt->fetchAll();
                    
                    foreach ($items as $item) {
                        echo "<tr>
                            <td>{$item['product_name']}</td>
                            <td>{$item['quantity']}</td>
                            <td>\${$item['unit_price']}</td>
                            <td>\${$item['total_price']}</td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6 offset-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-end">$<?php echo number_format($order['subtotal'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Shipping:</strong></td>
                        <td class="text-end">$<?php echo number_format($order['shipping_fee'] ?? 0, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tax:</strong></td>
                        <td class="text-end">$<?php echo number_format($order['tax_amount'] ?? 0, 2); ?></td>
                    </tr>
                    <tr class="border-top">
                        <td><strong>Total:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php
} else {
    echo "<p class='text-danger'>Order not found</p>";
}
?>