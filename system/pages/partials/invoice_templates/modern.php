<?php
// modern.php - Modern invoice template
$shopName = !empty($company['name']) ? $company['name'] : 'JAKISAWA SHOP';
$shopAddress = !empty($company['address']) ? $company['address'] : 'Nairobi Information HSE, Room 405, Fourth Floor';
$shopPhone = !empty($company['phone']) ? $company['phone'] : '0792546080 / +254 720 793609';
$shopEmail = !empty($company['email']) ? $company['email'] : 'support@jakisawashop.co.ke';
$shopWebsite = !empty($company['website']) ? $company['website'] : 'https://www.jakisawashop.co.ke/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?> - JAKISAWA SHOP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            border-bottom: 2px solid #4361ee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            max-height: 80px;
        }
        
        .invoice-title {
            color: #4361ee;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .badge {
            font-size: 0.9em;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .totals-table {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .totals-table tr:last-child {
            background: #e3f2fd;
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(67, 97, 238, 0.1);
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
        }
        
        .footer-note {
            border-top: 1px dashed #dee2e6;
            padding-top: 20px;
            margin-top: 40px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .signature-area {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .watermark {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="watermark">INVOICE</div>
    
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <?php if (file_exists($company['logo'])): ?>
                    <img src="<?php echo $company['logo']; ?>" alt="Logo" class="company-logo">
                    <?php else: ?>
                    <h1 class="invoice-title"><?php echo htmlspecialchars($shopName); ?></h1>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <p class="mb-1">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <?php echo htmlspecialchars($shopAddress); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-phone text-primary me-2"></i>
                            <?php echo htmlspecialchars($shopPhone); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <?php echo htmlspecialchars($shopEmail); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-globe text-primary me-2"></i>
                            <?php echo htmlspecialchars($shopWebsite); ?>
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 text-end">
                    <h2 class="display-6 fw-bold mb-3">INVOICE</h2>
                    <div class="mb-3">
                        <span class="badge bg-primary fs-6">#<?php echo $order['order_number']; ?></span>
                    </div>
                    
                    <table class="table table-borderless text-end">
                        <tr>
                            <th>Invoice Date:</th>
                            <td><?php echo date('F d, Y', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Due Date:</th>
                            <td><?php echo date('F d, Y', strtotime($order['created_at'] . ' +30 days')); ?></td>
                        </tr>
                        <tr>
                            <th>Payment Status:</th>
                            <td>
                                <span class="badge bg-<?php echo getStatusColor($order['payment_status']); ?>">
                                    <?php echo strtoupper($order['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Order Status:</th>
                            <td>
                                <span class="badge bg-<?php echo getStatusColor($order['order_status']); ?>">
                                    <?php echo strtoupper($order['order_status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Bill To / Ship To -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Bill To</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></h6>
                        <p class="mb-1"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p class="mb-1"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        <p class="mb-0">
                            <?php echo htmlspecialchars($order['customer_address']); ?><br>
                            <?php echo htmlspecialchars($order['customer_city']); ?>, 
                            <?php echo htmlspecialchars($order['customer_postal_code']); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Ship To</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></h6>
                        <?php if (!empty($order['shipping_address'])): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        <?php else: ?>
                        <p class="mb-0 text-muted">Same as billing address</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['shipping_method'])): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-shipping-fast me-1"></i>
                                Shipping Method: <?php echo htmlspecialchars($order['shipping_method']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="mb-4">
            <h5 class="mb-3"><i class="fas fa-shopping-cart me-2"></i>Order Items</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Description</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <?php if (!empty($item['product_sku'])): ?>
                                <small class="text-muted">SKU: <?php echo $item['product_sku']; ?></small>
                                <?php endif; ?>
                                <?php if (!empty($item['variant'])): ?>
                                <div><small>Variant: <?php echo $item['variant']; ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">KSh <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-end fw-bold">KSh <?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Totals -->
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <div class="totals-table">
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-end"><strong>Subtotal:</strong></td>
                            <td class="text-end" width="150">KSh <?php echo number_format($subtotal, 2); ?></td>
                        </tr>
                        
                        <?php if ($shipping > 0): ?>
                        <tr>
                            <td class="text-end"><strong>Shipping Fee:</strong></td>
                            <td class="text-end">KSh <?php echo number_format($shipping, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($tax > 0): ?>
                        <tr>
                            <td class="text-end"><strong>Tax (VAT):</strong></td>
                            <td class="text-end">KSh <?php echo number_format($tax, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($discount > 0): ?>
                        <tr>
                            <td class="text-end"><strong>Discount:</strong></td>
                            <td class="text-end text-danger">-KSh <?php echo number_format($discount, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="border-top">
                            <td class="text-end"><strong>TOTAL AMOUNT:</strong></td>
                            <td class="text-end"><strong>KSh <?php echo number_format($total, 2); ?></strong></td>
                        </tr>
                        
                        <?php if ($order['payment_status'] === 'paid'): ?>
                        <tr>
                            <td class="text-end"><strong>Amount Paid:</strong></td>
                            <td class="text-end text-success">KSh <?php echo number_format($total, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Balance Due:</strong></td>
                            <td class="text-end">KSh 0.00</td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td class="text-end"><strong>Amount Paid:</strong></td>
                            <td class="text-end">KSh 0.00</td>
                        </tr>
                        <tr>
                            <td class="text-end"><strong>Balance Due:</strong></td>
                            <td class="text-end text-danger"><strong>KSh <?php echo number_format($total, 2); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Payment Method:</strong> <?php echo strtoupper($order['payment_method'] ?? 'Not Specified'); ?></p>
                        <?php if (!empty($order['transaction_id'])): ?>
                        <p><strong>Transaction ID:</strong> <?php echo $order['transaction_id']; ?></p>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-university me-1"></i>
                                <strong>Bank Transfer:</strong><br>
                                Account Name: <?php echo $company['name']; ?><br>
                                Bank: <?php echo $company['bank_name']; ?><br>
                                Account: <?php echo $company['bank_account']; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="signature-area">
                    <div class="row">
                        <div class="col-6 text-center">
                            <p class="border-top pt-3">
                                ________________________<br>
                                <small class="text-muted">Customer Signature</small>
                            </p>
                        </div>
                        <div class="col-6 text-center">
                            <p class="border-top pt-3">
                                ________________________<br>
                                <small class="text-muted">Authorized Signature</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Notes -->
        <div class="footer-note">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-info-circle me-2"></i>Terms & Conditions</h6>
                    <small>
                        1. Payment is due within 30 days of invoice date.<br>
                        2. Late payments are subject to a 5% monthly fee.<br>
                        3. Goods remain property of <?php echo $company['name']; ?> until paid in full.<br>
                        4. Returns accepted within 14 days with original receipt.
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <h6><i class="fas fa-thank-you me-2"></i>Thank You!</h6>
                    <small>
                        We appreciate your business. For any questions regarding this invoice,<br>
                        please contact our billing department at <?php echo htmlspecialchars($shopEmail); ?>
                    </small>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            Invoice generated on: <?php echo date('F d, Y h:i A'); ?><br>
                            Invoice ID: <?php echo $order['order_number']; ?>-<?php echo time(); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Print Controls -->
    <div class="container mt-3 no-print">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Print Options</h5>
                        <div class="btn-group" role="group">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print me-2"></i>Print Invoice
                            </button>
                            <button onclick="downloadPDF()" class="btn btn-success">
                                <i class="fas fa-file-pdf me-2"></i>Download PDF
                            </button>
                            <a href="print_invoice.php?order_id=<?php echo $order_id; ?>&format=html&template=classic" 
                               class="btn btn-info">
                                <i class="fas fa-file-alt me-2"></i>Classic View
                            </a>
                            <a href="print_invoice.php?order_id=<?php echo $order_id; ?>&format=html&template=simple" 
                               class="btn btn-warning">
                                <i class="fas fa-file me-2"></i>Simple View
                            </a>
                        </div>
                        <div class="mt-3">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function downloadPDF() {
        // In production, use a PDF generation library like TCPDF, mPDF, or Dompdf
        // For now, we'll just print which can save as PDF
        window.print();
    }
    
    // Auto-print option
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') {
        window.addEventListener('load', function() {
            window.print();
        });
    }
    </script>
</body>
</html>
