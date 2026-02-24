<?php
// pages/create_order.php

define('BASE_PATH', dirname(__DIR__));
require_once dirname(__DIR__, 2) . '/config/paths.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', SYSTEM_BASE_URL);
}

require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/role_permissions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user info
$user_id = $_SESSION['admin_id'] ?? 0;
$user_name = $_SESSION['admin_name'] ?? 'Admin';
$user_role = $_SESSION['admin_role'] ?? 'staff';

// Get products for dropdown - FIXED QUERY
try {
    $productsStmt = $pdo->query("
        SELECT id, name, unit_price as price, stock_quantity 
        FROM remedies 
        WHERE is_active = 1 
        ORDER BY name
    ");
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
    $error = "Could not load products: " . $e->getMessage();
}

$page_title = "Create New Order - JAKISAWA SHOP Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ff0054;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            min-height: 100vh;
            display: flex;
        }
        
        .main-content {
            flex: 1;
            margin-left: 10px;
            padding: 10px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .order-items-table {
            margin-top: 20px;
        }
        
        .order-items-table th {
            background: #f8f9fa;
        }
        
        .btn-remove-item {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .totals-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .totals-row.grand-total {
            border-top: 2px solid var(--primary);
            border-bottom: none;
            font-weight: bold;
            font-size: 1.3rem;
            color: var(--primary);
            margin-top: 10px;
            padding-top: 15px;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 15px;
            background: #f8f9fa;
            border-radius: 20px;
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>

    
        <?php include BASE_PATH . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">
                    <i class="fas fa-plus-circle"></i>
                    Create New Order
                </h1>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                </div>
            </div>
            
            <div class="content-card">
                <form id="createOrderForm">
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-user"></i> Customer Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Email <span class="text-danger">*</span></label>
                                <input type="email" name="customer_email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Phone <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Alternative Phone</label>
                                <input type="tel" name="customer_alt_phone" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-truck"></i> Shipping Information</h3>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Shipping Address <span class="text-danger">*</span></label>
                                <textarea name="shipping_address" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="shipping_city" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="shipping_postal_code" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-shopping-cart"></i> Order Items</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Select Product</label>
                                <select id="productSelect" class="form-control">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-price="<?php echo $product['price']; ?>"
                                                data-stock="<?php echo $product['stock_quantity']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> 
                                            - KES <?php echo number_format($product['price'], 2); ?> 
                                            (Stock: <?php echo $product['stock_quantity']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" id="productQuantity" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-primary w-100" onclick="addOrderItem()">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive order-items-table">
                            <table class="table table-bordered" id="orderItemsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th width="100">Quantity</th>
                                        <th width="120">Unit Price</th>
                                        <th width="120">Total</th>
                                        <th width="80">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="orderItemsBody">
                                    <tr id="noItemsRow">
                                        <td colspan="5" class="text-center text-muted">
                                            No items added yet
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totals -->
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="totals-box">
                                    <div class="totals-row">
                                        <span>Subtotal:</span>
                                        <span id="subtotalDisplay">KES 0.00</span>
                                    </div>
                                    <div class="totals-row">
                                        <span>Shipping Fee:</span>
                                        <span>
                                            <input type="number" name="shipping_fee" id="shippingFee" 
                                                   class="form-control form-control-sm" 
                                                   value="0" min="0" step="0.01" 
                                                   onchange="calculateTotals()" style="width: 120px; display: inline;">
                                        </span>
                                    </div>
                                    <div class="totals-row">
                                        <span>Discount:</span>
                                        <span>
                                            <input type="number" name="discount_amount" id="discountAmount" 
                                                   class="form-control form-control-sm" 
                                                   value="0" min="0" step="0.01" 
                                                   onchange="calculateTotals()" style="width: 120px; display: inline;">
                                        </span>
                                    </div>
                                    <div class="totals-row grand-total">
                                        <span>TOTAL:</span>
                                        <span id="grandTotalDisplay">KES 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment & Status -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-credit-card"></i> Payment & Status</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-control">
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash on Delivery</option>
                                    <option value="card">Credit/Debit Card</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-control">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Order Status</label>
                                <select name="order_status" class="form-control">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control" 
                                       placeholder="M-Pesa code or bank reference">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-sticky-note"></i> Additional Notes</h3>
                        <textarea name="notes" class="form-control" rows="4" 
                                  placeholder="Any special instructions or notes about this order..."></textarea>
                    </div>
                    
                    <!-- Hidden Fields -->
                    <input type="hidden" name="subtotal" id="subtotalHidden" value="0">
                    <input type="hidden" name="total_amount" id="totalAmountHidden" value="0">
                    <input type="hidden" name="order_items" id="orderItemsHidden" value="[]">
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Create Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    let orderItems = [];
    
    $(document).ready(function() {
        // Initialize Select2
        $('#productSelect').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search products...'
        });
        
        // Form submission
        $('#createOrderForm').submit(function(e) {
            e.preventDefault();
            
            if (orderItems.length === 0) {
                Swal.fire('Error', 'Please add at least one item to the order', 'error');
                return;
            }
            
            // Update hidden field with order items
            $('#orderItemsHidden').val(JSON.stringify(orderItems));
            
            // Get form data
            const formData = $(this).serialize();
            
            // Submit via AJAX
            $.ajax({
                url: '<?php echo BASE_URL; ?>/ajax/create_order.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message || 'Order created successfully',
                            confirmButtonText: 'View Orders'
                        }).then(() => {
                            // Redirect to admin dashboard with orders page
                 window.location.href = '../admin_dashboard.php?page=orders';
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to create order', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Could not create order. Please try again. ' + error, 'error');
                }
            });
        });
    });
    
    function addOrderItem() {
        const productSelect = $('#productSelect');
        const selectedOption = productSelect.find(':selected');
        const productId = productSelect.val();
        const quantity = parseInt($('#productQuantity').val()) || 1;
        
        if (!productId) {
            Swal.fire('Error', 'Please select a product', 'error');
            return;
        }
        
        const productName = selectedOption.data('name');
        const unitPrice = parseFloat(selectedOption.data('price'));
        const stock = parseInt(selectedOption.data('stock'));
        
        if (quantity > stock) {
            Swal.fire('Warning', `Only ${stock} units available in stock`, 'warning');
            return;
        }
        
        // Check if item already exists
        const existingItem = orderItems.find(item => item.product_id == productId);
        if (existingItem) {
            const newQuantity = existingItem.quantity + quantity;
            if (newQuantity > stock) {
                Swal.fire('Warning', `Cannot add ${quantity} more. Only ${stock} units total available in stock`, 'warning');
                return;
            }
            existingItem.quantity = newQuantity;
            existingItem.total = existingItem.quantity * existingItem.unit_price;
        } else {
            orderItems.push({
                product_id: productId,
                product_name: productName,
                quantity: quantity,
                unit_price: unitPrice,
                total: quantity * unitPrice
            });
        }
        
        renderOrderItems();
        calculateTotals();
        
        // Reset selection
        productSelect.val('').trigger('change');
        $('#productQuantity').val(1);
    }
    
    function removeOrderItem(index) {
        orderItems.splice(index, 1);
        renderOrderItems();
        calculateTotals();
    }
    
    function updateItemQuantity(index, newQuantity) {
        if (newQuantity < 1) return;
        
        const productId = orderItems[index].product_id;
        const productSelect = $('#productSelect');
        const selectedOption = productSelect.find(`option[value="${productId}"]`);
        const stock = parseInt(selectedOption.data('stock'));
        
        if (newQuantity > stock) {
            Swal.fire('Warning', `Only ${stock} units available in stock`, 'warning');
            return;
        }
        
        orderItems[index].quantity = newQuantity;
        orderItems[index].total = newQuantity * orderItems[index].unit_price;
        
        renderOrderItems();
        calculateTotals();
    }
    
    function renderOrderItems() {
        const tbody = $('#orderItemsBody');
        tbody.empty();
        
        if (orderItems.length === 0) {
            tbody.append(`
                <tr id="noItemsRow">
                    <td colspan="5" class="text-center text-muted">No items added yet</td>
                </tr>
            `);
            return;
        }
        
        orderItems.forEach((item, index) => {
            tbody.append(`
                <tr>
                    <td>${item.product_name}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm" 
                               value="${item.quantity}" min="1" 
                               onchange="updateItemQuantity(${index}, parseInt(this.value))">
                    </td>
                    <td>KES ${item.unit_price.toFixed(2)}</td>
                    <td><strong>KES ${item.total.toFixed(2)}</strong></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderItem(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }
    
    function calculateTotals() {
        const subtotal = orderItems.reduce((sum, item) => sum + item.total, 0);
        const shippingFee = parseFloat($('#shippingFee').val()) || 0;
        const discount = parseFloat($('#discountAmount').val()) || 0;
        const grandTotal = subtotal + shippingFee - discount;
        
        $('#subtotalDisplay').text('KES ' + subtotal.toFixed(2));
        $('#grandTotalDisplay').text('KES ' + grandTotal.toFixed(2));
        
        $('#subtotalHidden').val(subtotal.toFixed(2));
        $('#totalAmountHidden').val(grandTotal.toFixed(2));
    }
    </script>
</body>
</html>
