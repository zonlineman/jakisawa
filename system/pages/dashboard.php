<?php
// Define paths
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}
require_once dirname(__DIR__, 2) . '/config/paths.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', SYSTEM_BASE_URL);
}

// Include required files
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/role_permissions.php';
require_once BASE_PATH . '/includes/order_notifications.php';


// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = getDBConnection();
    
    switch ($action) {
        case 'update_order_status':
            $order_id = intval($_POST['order_id']);
            $new_status = $conn->real_escape_string($_POST['status']);
            
            // Valid statuses
            $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status'];
                break;
            }
            
            $beforeOrder = null;
            $beforeStmt = $conn->prepare("SELECT order_status FROM orders WHERE id = ? LIMIT 1");
            if ($beforeStmt) {
                $beforeStmt->bind_param("i", $order_id);
                $beforeStmt->execute();
                $beforeOrder = $beforeStmt->get_result()->fetch_assoc();
                $beforeStmt->close();
            }
            if (!$beforeOrder) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Order not found'];
                break;
            }

            // Update order status
            $query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $new_status, $order_id);
            
            if ($stmt->execute()) {
                $notifyResult = sendOrderLifecycleNotification(
                    $pdo,
                    $order_id,
                    'status_update',
                    [
                        'order_status' => [
                            'old' => (string)($beforeOrder['order_status'] ?? ''),
                            'new' => $new_status
                        ]
                    ]
                );
                logAudit(
                    'order_status_update',
                    'orders',
                    $order_id,
                    null,
                    "Order status updated to '$new_status'",
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $notifyText = (!empty($notifyResult['email']['success']) || !empty($notifyResult['sms']['success']))
                    ? ' Customer notification sent.'
                    : '';
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Order status updated successfully.' . $notifyText];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update order status'];
            }
            $stmt->close();
            break;
            
        case 'update_product_stock':
            $product_id = intval($_POST['product_id']);
            $new_stock = floatval($_POST['stock_quantity']);
            
            if ($new_stock < 0) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Stock cannot be negative'];
                break;
            }
            
            $query = "UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("di", $new_stock, $product_id);
            
            if ($stmt->execute()) {
                logAudit(
                    'stock_update',
                    'remedies',
                    $product_id,
                    null,
                    ['stock_quantity' => $new_stock],
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Stock updated successfully'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update stock'];
            }
            $stmt->close();
            break;
            
        case 'delete_product':
            $product_id = intval($_POST['product_id']);
            
            // Soft delete (mark as inactive)
            $query = "UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                logAudit(
                    'remedy_deactivated',
                    'remedies',
                    $product_id,
                    null,
                    'Remedy deactivated',
                    ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null),
                    ($_SERVER['REMOTE_ADDR'] ?? null)
                );
                
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Product deactivated successfully'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete product'];
            }
            $stmt->close();
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get data for dashboard
$stats = getDashboardStats();
$recent_orders = getRecentOrders(10);
$low_stock_products = getLowStockProducts(10);
$recent_customers = getRecentCustomers(10);
$sales_chart_data = getSalesChartData();
$top_products = getTopProducts(5);
?>

        <!-- Header -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </h1>
            <div class="btn-toolbar">
                <span class="text-muted"><?php echo date('F j, Y'); ?></span>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']['text']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card shadow-sm border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Today's Revenue</h6>
                                <h3 class="mb-0">KSH<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                                <small class="text-muted">From <?php echo $stats['today_orders']; ?> orders</small>
                            </div>
                            <div class="stat-icon bg-primary-light">
                                <i class="bi bi-currency-exchange"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card shadow-sm border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Monthly Revenue</h6>
                                <h3 class="mb-0">KSH<?php echo number_format($stats['month_revenue'], 2); ?></h3>
                                <small class="text-muted">Total this month</small>
                            </div>
                            <div class="stat-icon bg-success-light">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card shadow-sm border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Products</h6>
                                <h3 class="mb-0"><?php echo $stats['total_products']; ?></h3>
                                <small class="text-muted"><?php echo $stats['low_stock']; ?> low stock</small>
                            </div>
                            <div class="stat-icon bg-warning-light">
                                <i class="bi bi-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card shadow-sm border-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Customers</h6>
                                <h3 class="mb-0"><?php echo $stats['total_customers']; ?></h3>
                                <small class="text-muted">Registered users</small>
                            </div>
                            <div class="stat-icon bg-danger-light">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Tables -->
        <div class="row">
            <!-- Sales Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Sales Overview (Last 7 Days)</h6>
                        <select class="form-select form-select-sm w-auto">
                            <option>7 Days</option>
                            <option>30 Days</option>
                            <option>90 Days</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0">Top Selling Products</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_products as $index => $product): ?>
                                <div class="list-group-item border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo number_format($product['total_sold']); ?> sold</div>
                                            <small class="text-success">KSH<?php echo number_format($product['revenue'], 2); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Orders</h6>
                        <a href="admin_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="admin_order_details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($order['order_number']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                            <td>KSH<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($order['order_status']) {
                                                    case 'pending': $status_class = 'badge-pending'; break;
                                                    case 'processing': $status_class = 'badge-processing'; break;
                                                    case 'shipped': $status_class = 'badge-shipped'; break;
                                                    case 'delivered': $status_class = 'badge-delivered'; break;
                                                    case 'cancelled': $status_class = 'badge-cancelled'; break;
                                                }
                                                ?>
                                                <span class="badge-status <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_order_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <select name="status" class="form-select form-select-sm w-auto d-inline" onchange="this.form.submit()">
                                                        <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                        <option value="shipped" <?php echo $order['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                        <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                        <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancel</option>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Products -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Low Stock Products</h6>
                        <a href="admin_inventory.php" class="btn btn-sm btn-outline-warning">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="list-group-item border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?php echo $product['stock_quantity'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                                <?php echo number_format($product['stock_quantity'], 3); ?>
                                            </div>
                                            <small>Reorder: <?php echo number_format($product['reorder_level'], 3); ?></small>
                                        </div>
                                    </div>
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="action" value="update_product_stock">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="stock_quantity" class="form-control" placeholder="New stock" step="0.001" min="0">
                                            <button type="submit" class="btn btn-primary">Update</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Customers -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Recent Customers</h6>
                        <a href="admin_customers.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($recent_customers as $customer): ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="card border">
                                        <div class="card-body text-center">
                                            <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center mx-auto mb-3" 
                                                 style="width: 60px; height: 60px; font-size: 24px; font-weight: bold;">
                                                <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                                            </div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h6>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($customer['email']); ?></p>
                                            <?php if ($customer['phone']): ?>
                                                <p class="small mb-3"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($customer['phone']); ?></p>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                Joined <?php echo date('M j, Y', strtotime($customer['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Developer Info Footer -->
        <div class="row mt-1 mb-2">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-code-slash text-primary"></i>
                            <strong>Developer:</strong>
                            <span class="text-muted">John Arumansi</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a href="tel:0741351755" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-telephone me-1"></i>0741351755
                            </a>
                            <span class="badge bg-warning text-dark">HIRE DEVELOPER</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($sales_chart_data as $data): ?>
                        '<?php echo date("M j", strtotime($data['date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Revenue (KSH)',
                    data: [
                        <?php foreach ($sales_chart_data as $data): ?>
                            <?php echo $data['revenue'] ?? 0; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'KSH' + context.parsed.y.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSH' + value.toLocaleString('en-US', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                });
                            }
                        }
                    }
                }
            }
        });
        
        // Auto refresh dashboard every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>
