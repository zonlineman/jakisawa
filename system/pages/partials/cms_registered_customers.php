<?php
/**
 * Registered Customers Table Partial
 * Location: /pages/partials/cms_registered_customers.php
 */
?>

<div class="cms-section">
    <div class="section-controls">
        <input type="text" id="searchRegistered" class="search-input" placeholder="Search...">
        <select id="filterRegistered" class="form-select" style="width: 150px;">
            <option value="">All Status</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
        </select>
        
        <div class="bulk-actions">
            <button class="btn btn-sm btn-outline-success" onclick="bulkApprove()">
                <i class="fas fa-check"></i> Approve
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="bulkDeactivate()">
                <i class="fas fa-ban"></i> Deactivate
            </button>
        </div>
    </div>
    
    <?php if (empty($registeredCustomers)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No registered customers found</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="cms-table" id="tableRegistered">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Registered</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registeredCustomers as $customer): ?>
                    <tr data-id="<?php echo $customer['id']; ?>" data-approval="<?php echo $customer['approval_status']; ?>">
                        <td><input type="checkbox" class="customer-checkbox" value="<?php echo $customer['id']; ?>"></td>
                        <td>#<?php echo $customer['id']; ?></td>
                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($customer['email']); ?></code></td>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $customer['status']; ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-approval-<?php echo $customer['approval_status']; ?>">
                                <?php echo ucfirst($customer['approval_status']); ?>
                            </span>
                        </td>
                        <td><small><?php echo date('M d, Y', strtotime($customer['registration_date'])); ?></small></td>
                        <td><?php echo $customer['total_orders']; ?></td>
                        <td>Ksh <?php echo number_format($customer['total_spent'], 0); ?></td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($customer['approval_status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" data-id="<?php echo $customer['id']; ?>" onclick="approveCustomer(this.dataset.id)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-id="<?php echo $customer['id']; ?>" onclick="rejectCustomer(this.dataset.id)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-info" data-type="registered" data-id="<?php echo $customer['id']; ?>" onclick="viewCustomer(this.dataset.type, this.dataset.id)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($customer['is_active']): ?>
                                    <button class="btn btn-sm btn-warning" data-id="<?php echo $customer['id']; ?>" onclick="deactivateCustomer(this.dataset.id)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success" data-id="<?php echo $customer['id']; ?>" onclick="activateCustomer(this.dataset.id)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($customer['total_orders'] > 0): ?>
                                    <button class="btn btn-sm btn-primary" data-id="<?php echo $customer['id']; ?>" onclick="viewOrders(this.dataset.id)">
                                        <i class="fas fa-shopping-cart"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger" data-id="<?php echo $customer['id']; ?>" data-name="<?php echo htmlspecialchars($customer['full_name']); ?>" onclick="deleteCustomer(this.dataset.id, this.dataset.name)">
                                    <i class="fas fa-trash"></i>
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