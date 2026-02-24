<?php
/**
 * Random Customers Table Partial
 * Location: /pages/partials/cms_random_customers.php
 */
?>

<div class="cms-section">
    <div class="section-controls">
        <input type="text" id="searchRandom" class="search-input" placeholder="Search...">
        <select id="filterRandom" class="form-select" style="width: 150px;">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="needs_followup">Needs Follow-up</option>
        </select>
    </div>
    
    <?php if (empty($randomCustomers)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No random customers found</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="cms-table" id="tableRandom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>First Order</th>
                        <th>Last Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($randomCustomers as $customer): ?>
                    <tr data-status="<?php echo $customer['status']; ?>">
                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($customer['customer_email']); ?></code></td>
                        <td><?php echo htmlspecialchars($customer['customer_phone']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $customer['total_orders']; ?></span></td>
                        <td>Ksh <?php echo number_format($customer['total_spent'], 0); ?></td>
                        <td><small><?php echo date('M d, Y', strtotime($customer['first_order_date'])); ?></small></td>
                        <td><small><?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?></small></td>
                        <td>
                            <span class="badge badge-<?php echo $customer['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $customer['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-success" data-email="<?php echo htmlspecialchars($customer['customer_email']); ?>" data-name="<?php echo htmlspecialchars($customer['customer_name']); ?>" data-phone="<?php echo htmlspecialchars($customer['customer_phone']); ?>" onclick="convertCustomer(this.dataset.email, this.dataset.name, this.dataset.phone)">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <button class="btn btn-sm btn-info" data-type="random" data-id="<?php echo htmlspecialchars($customer['customer_email']); ?>" onclick="viewCustomer(this.dataset.type, this.dataset.id)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-email="<?php echo htmlspecialchars($customer['customer_email']); ?>" onclick="sendEmail(this.dataset.email)">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>