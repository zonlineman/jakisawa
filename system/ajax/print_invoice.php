<?php
// ajax/print_invoice.php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';

$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    die('Invalid order ID');
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*,
               u.full_name AS served_by_user_name,
               u.role AS served_by_user_role,
               (
                   SELECT u2.full_name
                   FROM audit_log al
                   LEFT JOIN users u2 ON u2.id = al.user_id
                   WHERE al.table_name = 'orders'
                     AND al.record_id = o.id
                     AND al.user_id IS NOT NULL
                   ORDER BY al.created_at DESC
                   LIMIT 1
               ) AS served_by_audit_name,
               (
                   SELECT u2.role
                   FROM audit_log al
                   LEFT JOIN users u2 ON u2.id = al.user_id
                   WHERE al.table_name = 'orders'
                     AND al.record_id = o.id
                     AND al.user_id IS NOT NULL
                   ORDER BY al.created_at DESC
                   LIMIT 1
               ) AS served_by_audit_role,
               (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity, ' @ KES ', oi.unit_price) SEPARATOR '||') 
                FROM order_items oi WHERE oi.order_id = o.id) as items_list
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Order not found');
    }
    
    // Get order items
    $itemsStmt = $pdo->prepare("
        SELECT * FROM order_items WHERE order_id = ? ORDER BY id
    ");
    $itemsStmt->execute([$order_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            padding: 24px;
            font-size: 13px;
            color: #1f2937;
            background:
                radial-gradient(circle at 10% 10%, rgba(37, 99, 235, 0.08), transparent 38%),
                radial-gradient(circle at 90% 20%, rgba(16, 185, 129, 0.08), transparent 34%),
                #f4f7fb;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #d9e3ef;
            border-radius: 16px;
            padding: 30px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            margin-bottom: 30px;
            padding: 20px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #0ea5e9 100%);
            color: #f8fafc;
        }
        
        .company-info h1 {
            color: #ffffff;
            font-size: 26px;
            margin-bottom: 8px;
            letter-spacing: 0.4px;
        }
        
        .company-info p {
            color: rgba(248, 250, 252, 0.9);
            margin: 3px 0;
            font-size: 12px;
        }
        
        .invoice-info {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 6px;
            justify-content: center;
        }
        
        .invoice-info h2 {
            color: #ffffff;
            font-size: 30px;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        
        .invoice-info p {
            margin: 5px 0;
            color: #e5edff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 10px;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        
        .status-paid {
            background: #dcfce7;
            color: #14532d;
            border-color: #86efac;
        }
        
        .status-pending {
            background: #fef9c3;
            color: #854d0e;
            border-color: #fde68a;
        }
        
        .status-completed {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #93c5fd;
        }
        
        .status-processing {
            background: #ede9fe;
            color: #5b21b6;
            border-color: #c4b5fd;
        }

        .status-failed,
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .status-refunded {
            background: #e0f2fe;
            color: #075985;
            border-color: #7dd3fc;
        }
        
        .details-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin: 30px 0;
        }
        
        .detail-box h3 {
            font-size: 13px;
            color: #111827;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        
        .detail-box p {
            margin: 6px 0;
            color: #374151;
            font-size: 12px;
            line-height: 1.45;
        }

        .detail-box {
            padding: 14px 16px;
            border: 1px solid #e5ecf5;
            border-radius: 10px;
            background: linear-gradient(180deg, #fbfdff, #f8fbff);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0 8px;
            border: 1px solid #e5ecf5;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .items-table th {
            background: #eff6ff;
            color: #0f172a;
            padding: 12px 10px;
            text-align: left;
            font-weight: 700;
            border-bottom: 1px solid #dbe5f2;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #edf2f8;
            color: #374151;
            font-size: 12px;
        }

        .items-table tbody tr:nth-child(even) {
            background: #fbfdff;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            margin-top: 30px;
            float: right;
            width: 300px;
            border: 1px solid #dbe5f2;
            border-radius: 12px;
            padding: 12px 14px;
            background: #f8fbff;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 2px;
            border-bottom: 1px dashed #d4deea;
            color: #334155;
        }
        
        .totals-row.grand-total {
            border-top: 2px solid #1d4ed8;
            border-bottom: 0;
            font-weight: bold;
            font-size: 17px;
            margin-top: 10px;
            padding-top: 15px;
            color: #0f172a;
            background: #eaf2ff;
            border-radius: 8px;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 18px;
            border-top: 1px solid #dde6f2;
            text-align: center;
            color: #64748b;
            font-size: 11px;
        }
        
        .notes-section {
            margin: 30px 0;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #e6edf5;
            border-left: 4px solid #1d4ed8;
            border-radius: 10px;
        }
        
        .notes-section h4 {
            margin-bottom: 10px;
            color: #0f172a;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .notes-section p {
            color: #334155;
            line-height: 1.45;
        }
        
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            
            .invoice-container {
                border: none;
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            
            @page {
                margin: 10mm;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <?php
    $servedByName = $order['served_by_user_name']
        ?? $order['served_by_audit_name']
        ?? ($_SESSION['admin_name'] ?? null)
        ?? 'N/A';
    $servedByRole = $order['served_by_user_role']
        ?? $order['served_by_audit_role']
        ?? ($_SESSION['admin_role'] ?? null)
        ?? '';
    ?>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1>JAKISAWA SHOP</h1>
                <p>Natural Healing Solutions</p>
                <p>Address: Nairobi Information HSE, Room 405, Fourth Floor</p>
                <p>Email: jakisawa@jakisawashop.co.ke</p>
                <p>Phone: 0792546080 / +254 720 793609</p>
                <p>Website: https://www.jakisawashop.co.ke/</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                <p><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo $order['order_status']; ?>">
                        <?php echo strtoupper($order['order_status']); ?>
                    </span>
                </p>
                <p><strong>Payment:</strong> 
                    <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                        <?php echo strtoupper($order['payment_status']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <!-- Customer Details -->
        <div class="details-section">
            <div class="detail-box">
                <h3>Bill To:</h3>
                <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($order['customer_email']); ?></p>
                <p><?php echo htmlspecialchars($order['customer_phone']); ?></p>
            </div>
            
            <div class="detail-box">
                <h3>Shipping Address:</h3>
                <p><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?></p>
                <?php if (!empty($order['shipping_city'])): ?>
                    <p><?php echo htmlspecialchars($order['shipping_city']); ?></p>
                <?php endif; ?>
                <p><strong>Served By:</strong>
                    <?php echo htmlspecialchars($servedByName); ?>
                    <?php if (!empty($servedByRole)): ?>
                        (<?php echo htmlspecialchars(ucfirst((string)$servedByRole)); ?>)
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Order Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $itemNumber = 1; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $itemNumber++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                            <?php if (!empty($item['variant'])): ?>
                                <br><small>Variant: <?php echo htmlspecialchars($item['variant']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right">KES <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-right">KES <?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>KES <?php echo number_format($order['subtotal'] ?? $order['total_amount'], 2); ?></span>
            </div>
            
            <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
            <div class="totals-row">
                <span>Discount:</span>
                <span>- KES <?php echo number_format($order['discount_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($order['shipping_fee']) && $order['shipping_fee'] > 0): ?>
            <div class="totals-row">
                <span>Shipping:</span>
                <span>KES <?php echo number_format($order['shipping_fee'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($order['tax_amount']) && $order['tax_amount'] > 0): ?>
            <div class="totals-row">
                <span>Tax:</span>
                <span>KES <?php echo number_format($order['tax_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="totals-row grand-total">
                <span>TOTAL:</span>
                <span>KES <?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div style="clear: both;"></div>
        
        <!-- Notes -->
        <?php if (!empty($order['notes'])): ?>
        <div class="notes-section">
            <h4>Notes:</h4>
            <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Payment Info -->
        <?php if ($order['payment_status'] === 'paid'): ?>
        <div class="notes-section">
            <h4>Payment Information:</h4>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></p>
            <?php if (!empty($order['transaction_id'])): ?>
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?></p>
            <?php endif; ?>
            <p><strong>Payment Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['updated_at'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>For any queries regarding this invoice, please contact us at jakisawa@jakisawashop.co.ke</p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
        </div>
    </div>
    
    <script>
        // Auto print when page loads (optional)
        window.onload = function() {
            // Uncomment the line below if you want automatic printing
            // window.print();
        };
    </script>
</body>
</html>
