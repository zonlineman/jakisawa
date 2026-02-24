<?php if (!empty($orders)): ?>
<div class="table-responsive">
    <table class="table table-hover" id="ordersTable">
        <thead>
            <tr>
                <th>
                    <?php if (hasPermission('bulk_operations')): ?>
                    <input type="checkbox" id="selectAllOrders" onclick="toggleSelectAll(this)">
                    <?php endif; ?>
                </th>
                <th>Order Number</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Date & Time</th>
                <th>Payment Status</th>
                <th>Order Status</th>
                <th>Priority</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): 
                $priorityClass = getPriorityClass($order);
            ?>
            <tr class="<?php echo $priorityClass; ?>" id="orderRow_<?php echo $order['id']; ?>">
                <td>
                    <?php if (hasPermission('bulk_operations')): ?>
                    <input type="checkbox" class="orderCheckbox" value="<?php echo $order['id']; ?>">
                    <?php endif; ?>
                </td>
                <td>
                    <div class="order-id">
                        <span class="badge bg-dark order-code"><?php echo $order['order_number'] ?? 'N/A'; ?></span>
                        <?php if ($priorityClass === 'priority-high'): ?>
                        <span class="badge bg-danger ms-1">URGENT</span>
                        <?php endif; ?>
                        <small class="text-muted d-block mt-1">
                            <i class="fas fa-receipt me-1"></i> Items: <?php echo $order['item_count'] ?? 0; ?>
                        </small>
                    </div>
                </td>
                <td>
                    <div class="customer-info">
                        <div class="fw-medium customer-name"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                        <div class="customer-contact">
                            <small class="text-muted">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?>
                            </small>
                            <?php if ($order['customer_phone']): ?>
                            <div class="mt-1">
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="amount-info">
                        <div class="text-success fw-bold order-amount">
                            <?php echo formatCurrency($order['total_amount'] ?? 0); ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-credit-card me-1"></i> 
                            <?php echo ucfirst($order['payment_method'] ?? 'Not specified'); ?>
                        </small>
                        <?php if (isset($order['shipping_cost']) && $order['shipping_cost'] > 0): ?>
                        <div class="mt-1">
                            <small class="text-info">
                                <i class="fas fa-truck"></i> Shipping: <?php echo formatCurrency($order['shipping_cost']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="datetime-info">
                        <div class="fw-medium">
                            <?php echo date('M d, Y', strtotime($order['created_at'] ?? 'now')); ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i> 
                            <?php echo date('H:i', strtotime($order['created_at'] ?? 'now')); ?>
                        </small>
                        <div class="mt-1">
                            <small class="text-muted time-ago">
                                <?php 
                                if ($order['created_at']) {
                                    $now = new DateTime();
                                    $orderDate = new DateTime($order['created_at']);
                                    $interval = $now->diff($orderDate);
                                    
                                    if ($interval->d > 0) {
                                        echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->h > 0) {
                                        echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                    } else {
                                        echo 'Just now';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </small>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="status-badge-container" id="paymentStatusBadge_<?php echo $order['id']; ?>">
                        <span class="badge bg-<?php echo getStatusColor($order['payment_status'] ?? 'pending'); ?>">
                            <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                        </span>
                        <?php if (($order['payment_status'] ?? '') === 'pending'): ?>
                        <div class="mt-1">
                            <small class="text-warning">
                                <i class="fas fa-exclamation-circle"></i> Needs attention
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="status-badge-container" id="orderStatusBadge_<?php echo $order['id']; ?>">
                        <span class="badge bg-<?php echo getStatusColor($order['order_status'] ?? 'pending'); ?>">
                            <?php echo ucfirst($order['order_status'] ?? 'pending'); ?>
                        </span>
                    </div>
                </td>
                <td>
                    <div class="priority-indicator">
                        <?php if ($priorityClass === 'priority-high'): ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-exclamation-triangle"></i> High
                        </span>
                        <div class="mt-1">
                            <small class="text-danger">
                                <i class="fas fa-clock"></i> Over 24h
                            </small>
                        </div>
                        <?php elseif ($priorityClass === 'priority-medium'): ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-clock"></i> Medium
                        </span>
                        <?php else: ?>
                        <span class="badge bg-info">
                            <i class="fas fa-check"></i> Normal
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-info" 
                                onclick="viewOrderDetails(<?php echo $order['id']; ?>)" 
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        
                        <?php if (hasPermission('update_order_status')): ?>
                        <button class="btn btn-sm btn-warning" 
                                onclick="showStatusModal(<?php echo $order['id']; ?>, 'payment')" 
                                title="Update Payment Status">
                            <i class="fas fa-money-bill"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" 
                                onclick="showStatusModal(<?php echo $order['id']; ?>, 'order')" 
                                title="Update Order Status">
                            <i class="fas fa-truck"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('update_payment')): ?>
                        <button class="btn btn-sm btn-secondary" 
                                onclick="showPaymentModal(<?php echo $order['id']; ?>)" 
                                title="Update Payment Method">
                            <i class="fas fa-credit-card"></i>
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-sm btn-dark" 
                                onclick="printInvoice(<?php echo $order['id']; ?>)" 
                                title="Print Invoice">
                            <i class="fas fa-print"></i>
                        </button>
                        
                        <?php if (hasPermission('delete_orders') && ($order['payment_status'] ?? '') === 'pending' && ($order['order_status'] ?? '') === 'pending'): ?>
                        <button class="btn btn-sm btn-danger" 
                                onclick="deleteOrder(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>')" 
                                title="Delete Order">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php include 'orders_pagination.php'; ?>

<?php else: ?>
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="fas fa-shopping-cart fa-4x text-muted"></i>
    </div>
    <h4>No Orders Found</h4>
    <p class="text-muted">No orders match your current filters.</p>
    <div class="mt-3">
        <?php if ($searchQuery || $statusFilter || $orderStatusFilter || $startDate || $endDate): ?>
        <a href="?page=orders" class="btn btn-primary">
            <i class="fas fa-times me-1"></i> Clear Filters
        </a>
        <?php endif; ?>
        <?php if (hasPermission('create_orders')): ?>
        <button class="btn btn-success" onclick="createNewOrder()">
            <i class="fas fa-plus me-1"></i> Create New Order
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>