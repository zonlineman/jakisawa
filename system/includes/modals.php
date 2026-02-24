<?php
/**
 * All Modal Windows for Admin Dashboard - Aligned with Database Structure
 * This file contains all modal dialogs using your database tables and fields
 */
?>

<!-- ========== ORDER MODALS ========== -->

<!-- Edit Order Status Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Order Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="orderStatusForm" method="POST">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" id="editOrderId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <div class="form-control bg-light" id="orderNumberDisplay"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <div class="form-control bg-light" id="orderCustomerName"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Payment Status</label>
                                <div class="form-control bg-light" id="orderCurrentPaymentStatus"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Order Status</label>
                                <div class="form-control bg-light" id="orderCurrentOrderStatus"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Payment Status <span class="text-danger">*</span></label>
                                <select name="payment_status" id="paymentStatus" class="form-control" required>
                                    <option value="">Select Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Order Status</label>
                                <select name="order_status" id="orderStatus" class="form-control">
                                    <option value="">Select Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Order Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Order details will be loaded here via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printOrderDetails()">
                    <i class="fas fa-print me-1"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========== INVENTORY MODALS ========== -->

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="productForm" method="POST">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" id="productId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SKU</label>
                                <div class="form-control bg-light" id="productSku"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <div class="form-control bg-light" id="productName"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Current Stock <span class="text-danger">*</span></label>
                            <input type="number" name="stock_quantity" id="productStock" class="form-control" required min="0" step="0.001">
                            <small class="text-muted">Units in stock (decimal allowed)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" id="productReorderLevel" class="form-control" min="0" step="0.001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit Price (<?php echo CURRENCY; ?>) <span class="text-danger">*</span></label>
                            <input type="number" name="unit_price" id="productUnitPrice" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cost Price (<?php echo CURRENCY; ?>)</label>
                            <input type="number" name="cost_price" id="productCostPrice" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Price (<?php echo CURRENCY; ?>)</label>
                            <input type="number" name="discount_price" id="productDiscountPrice" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <div class="form-control bg-light" id="productCategory"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier</label>
                            <div class="form-control bg-light" id="productSupplier"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="productIsFeatured">
                                <label class="form-check-label" for="productIsFeatured">
                                    Featured Product
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="productStatus" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">description</label>
                        <textarea name="description" id="productdescription" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ingredients</label>
                        <textarea name="ingredients" id="productIngredients" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Usage Instructions</label>
                        <textarea name="usage_instructions" id="productUsageInstructions" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Restock Modal -->
<div class="modal fade" id="quickRestockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Quick Restock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickRestockForm" method="POST">
                <input type="hidden" name="action" value="quick_restock">
                <input type="hidden" name="product_id" id="restockProductId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <div class="form-control bg-light" id="restockProductName"></div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Current Stock</label>
                            <div class="form-control bg-light" id="restockCurrentStock"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reorder Level</label>
                            <div class="form-control bg-light" id="restockReorderLevel"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="0.001" step="0.001" value="10">
                        <small class="text-muted">Quantity to add (decimal allowed)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier (Optional)</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php
                            $conn = getDBConnection();
                            $suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
                            if ($suppliers) {
                                while ($supplier = mysqli_fetch_assoc($suppliers)) {
                                    echo '<option value="' . $supplier['id'] . '">' . htmlspecialchars($supplier['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this restock..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Restock Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== USER MANAGEMENT MODALS ========== -->

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm" method="POST">
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter full name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="jakisawa@jakisawashop.co.ke">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="staff">Staff Member</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+254 XXX XXX XXX">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Full address"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" placeholder="Postal code">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_welcome_email" id="sendWelcomeEmail" checked>
                            <label class="form-check-label" for="sendWelcomeEmail">
                                Send welcome email with login details
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="editUserFullName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="editUserRole" class="form-control" required>
                                <option value="admin">Administrator</option>
                                <option value="staff">Staff Member</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="editUserPhone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="editUserAddress" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="editUserCity" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" id="editUserPostalCode" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="editUserStatus" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Created At</label>
                            <div class="form-control bg-light" id="editUserCreatedAt"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="changePasswordForm" method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="passwordUserId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <div class="form-control bg-light" id="passwordUserName"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="force_password_change" id="forcePasswordChange">
                            <label class="form-check-label" for="forcePasswordChange">
                                Require password change on next login
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Details Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- User details will be loaded here via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading user details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printUserDetails()">
                    <i class="fas fa-print me-1"></i> Print Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <h5 id="deleteItemName">Are you sure?</h5>
                    <p class="text-muted" id="deleteConfirmationText">This action cannot be undone.</p>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This will permanently delete the item and all associated data.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" id="deleteAction">
                    <input type="hidden" name="id" id="deleteItemId">
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========== CUSTOMER MODALS ========== -->

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Customer Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customerDetailsContent">
                <!-- Customer details will be loaded here via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading customer details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="sendEmailToCustomer()">
                    <i class="fas fa-envelope me-1"></i> Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Send Email to Customer Modal -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Send Email</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sendEmailForm" method="POST">
                <input type="hidden" name="action" value="send_customer_email">
                <input type="hidden" name="customer_email" id="customerEmail">
                <input type="hidden" name="customer_name" id="customerName">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <div class="form-control bg-light" id="emailRecipient"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required value="Message from <?php echo SITE_NAME; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="6" required placeholder="Type your message here..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Template</label>
                        <select name="template" class="form-control" onchange="loadEmailTemplate(this.value)">
                            <option value="">Custom Message</option>
                            <option value="welcome">Welcome Email</option>
                            <option value="promotion">Special Promotion</option>
                            <option value="order_update">Order Update</option>
                            <option value="newsletter">Newsletter</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_signature" id="includeSignature" checked>
                            <label class="form-check-label" for="includeSignature">
                                Include company signature
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== PRODUCT/REMEDY MODALS ========== -->

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addProductForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" name="sku" class="form-control" required placeholder="e.g., GT001">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="Enter product name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $conn = getDBConnection();
                                    $categories = mysqli_query($conn, "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
                                    if ($categories) {
                                        while ($category = mysqli_fetch_assoc($categories)) {
                                            echo '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-control">
                                    <option value="">Select Supplier</option>
                                    <?php
                                    $suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
                                    if ($suppliers) {
                                        while ($supplier = mysqli_fetch_assoc($suppliers)) {
                                            echo '<option value="' . $supplier['id'] . '">' . htmlspecialchars($supplier['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Cost Price (<?php echo CURRENCY; ?>)</label>
                                <input type="number" name="cost_price" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Unit Price (<?php echo CURRENCY; ?>) <span class="text-danger">*</span></label>
                                <input type="number" name="unit_price" class="form-control" required min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Discount Price (<?php echo CURRENCY; ?>)</label>
                                <input type="number" name="discount_price" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Initial Stock <span class="text-danger">*</span></label>
                                <input type="number" name="stock_quantity" class="form-control" required min="0" step="0.001" value="0">
                                <small class="text-muted">Initial quantity (decimal allowed)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" name="reorder_level" class="form-control" min="0" step="0.001" value="10">
                                <small class="text-muted">When stock reaches this level</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Product description..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ingredients</label>
                        <textarea name="ingredients" class="form-control" rows="2" placeholder="List of ingredients..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Usage Instructions</label>
                        <textarea name="usage_instructions" class="form-control" rows="2" placeholder="How to use the product..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured">
                                    <label class="form-check-label" for="isFeatured">
                                        Featured Product
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                    <label class="form-check-label" for="isActive">
                                        Active (Available for sale)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== EXPORT MODALS ========== -->

<!-- Export Data Modal -->
<div class="modal fade" id="exportDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="exportDataForm" method="GET">
                <input type="hidden" name="ajax" value="export_data">
                <input type="hidden" name="export_type" id="exportType">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Export Format <span class="text-danger">*</span></label>
                        <select name="format" class="form-control" required>
                            <option value="csv">CSV (Comma Separated Values)</option>
                            <option value="excel">Excel (.xlsx)</option>
                            <option value="pdf">PDF Document</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="date" name="start_date" class="form-control" placeholder="Start Date">
                            </div>
                            <div class="col-md-6">
                                <input type="date" name="end_date" class="form-control" placeholder="End Date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Include Columns</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;" id="exportColumns">
                            <!-- Columns will be populated dynamically -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-1"></i> Export Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== CATEGORY MODALS ========== -->

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_category">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter category name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required placeholder="category-name">
                        <small class="text-muted">URL-friendly version of the name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Category description..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Optional: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="categoryIsActive" checked>
                            <label class="form-check-label" for="categoryIsActive">
                                Active Category
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="editCategoryId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="editCategorySlug" class="form-control" required>
                        <small class="text-muted">URL-friendly version of the name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">description</label>
                        <textarea name="description" id="editCategorydescription" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div id="editCategoryCurrentImage" class="mb-2"></div>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editCategoryIsActive">
                            <label class="form-check-label" for="editCategoryIsActive">
                                Active Category
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== SUPPLIER MODALS ========== -->

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-truck me-2"></i>Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSupplierForm" method="POST">
                <input type="hidden" name="action" value="add_supplier">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter supplier name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Contact person name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="jakisawa@jakisawashop.co.ke">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+254 XXX XXX XXX">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Supplier address"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="supplierIsActive" checked>
                            <label class="form-check-label" for="supplierIsActive">
                                Active Supplier
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSupplierForm" method="POST">
                <input type="hidden" name="action" value="edit_supplier">
                <input type="hidden" name="supplier_id" id="editSupplierId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editSupplierName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="editSupplierContactPerson" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="editSupplierEmail" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="editSupplierPhone" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="editSupplierAddress" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editSupplierIsActive">
                            <label class="form-check-label" for="editSupplierIsActive">
                                Active Supplier
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== SYSTEM MODALS ========== -->

<!-- System Settings Modal -->
<div class="modal fade" id="systemSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-cog me-2"></i>System Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="systemSettingsForm" method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">Orders</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0" id="settingsTabContent">
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo SITE_NAME; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Currency</label>
                                <input type="text" name="currency" class="form-control" value="<?php echo CURRENCY; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Default Shipping Cost (<?php echo CURRENCY; ?>)</label>
                                <input type="number" name="default_shipping_cost" class="form-control" min="0" step="0.01" value="200.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="16.00">
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="orders" role="tabpanel">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_generate_order_number" id="autoGenerateOrderNumber" checked>
                                    <label class="form-check-label" for="autoGenerateOrderNumber">
                                        Auto-generate order numbers
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_order_confirmation" id="sendOrderConfirmation" checked>
                                    <label class="form-check-label" for="sendOrderConfirmation">
                                        Send order confirmation emails
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_shipment_notification" id="sendShipmentNotification" checked>
                                    <label class="form-check-label" for="sendShipmentNotification">
                                        Send shipment notifications
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Default Payment Method</label>
                                <select name="default_payment_method" class="form-control">
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Help Modal -->
<div class="modal fade" id="quickHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>Quick Help & Support</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-lightbulb text-warning me-2"></i>Quick Tips</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use filters to quickly find data</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Export reports for offline analysis</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Set up low stock alerts</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use bulk actions for efficiency</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-phone text-primary me-2"></i>Support</h6>
                        <p><strong>Email:</strong> jakisawa@jakisawashop.co.ke</p>
                        <p><strong>Phone:</strong> 0792546080 / +254 720 793609</p>
                        <p><strong>Hours:</strong> Mon-Fri, 8AM-5PM</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="window.open('user_manual.pdf', '_blank')">
                    <i class="fas fa-book me-1"></i> View Manual
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========== JAVASCRIPT INITIALIZATION ========== -->
<script>
// Modal initialization functions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modals
    initModals();
});

function initModals() {
    // Form validation for modals
    initModalForms();
    
    // Auto-focus first input in modals
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
}

function initModalForms() {
    // Add User Form validation
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
    
    // Change Password Form validation
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', function(e) {
            const newPassword = this.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
    
    // Delete confirmation
    document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#deleteConfirmationModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const itemName = this.getAttribute('data-item-name');
            const action = this.getAttribute('data-action');
            
            document.getElementById('deleteItemId').value = itemId;
            document.getElementById('deleteAction').value = action;
            document.getElementById('deleteItemName').textContent = `Delete "${itemName}"?`;
        });
    });
}

// Helper functions for modals
function showEditOrderModal(orderId, orderNumber, customerName, paymentStatus, orderStatus) {
    document.getElementById('editOrderId').value = orderId;
    document.getElementById('orderNumberDisplay').textContent = orderNumber;
    document.getElementById('orderCustomerName').textContent = customerName;
    document.getElementById('orderCurrentPaymentStatus').textContent = paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1);
    document.getElementById('orderCurrentOrderStatus').textContent = orderStatus.charAt(0).toUpperCase() + orderStatus.slice(1);
    document.getElementById('paymentStatus').value = paymentStatus;
    document.getElementById('orderStatus').value = orderStatus;
    
    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

function showEditProductModal(productId, sku, name, stockQuantity, unitPrice, costPrice, discountPrice, reorderLevel, categoryName, supplierName, isFeatured, isActive, description, ingredients, usageInstructions) {
    document.getElementById('productId').value = productId;
    document.getElementById('productSku').textContent = sku;
    document.getElementById('productName').textContent = name;
    document.getElementById('productStock').value = stockQuantity;
    document.getElementById('productUnitPrice').value = unitPrice;
    document.getElementById('productCostPrice').value = costPrice || '';
    document.getElementById('productDiscountPrice').value = discountPrice || '';
    document.getElementById('productReorderLevel').value = reorderLevel || '10';
    document.getElementById('productCategory').textContent = categoryName || 'Uncategorized';
    document.getElementById('productSupplier').textContent = supplierName || 'No Supplier';
    document.getElementById('productIsFeatured').checked = isFeatured == '1' || isFeatured == true;
    document.getElementById('productStatus').value = isActive ? '1' : '0';
    document.getElementById('productdescription').value = description || '';
    document.getElementById('productIngredients').value = ingredients || '';
    document.getElementById('productUsageInstructions').value = usageInstructions || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
    modal.show();
}

function showQuickRestockModal(productId, productName, currentStock, reorderLevel) {
    document.getElementById('restockProductId').value = productId;
    document.getElementById('restockProductName').textContent = productName;
    document.getElementById('restockCurrentStock').textContent = currentStock;
    document.getElementById('restockReorderLevel').textContent = reorderLevel || '10';
    
    const modal = new bootstrap.Modal(document.getElementById('quickRestockModal'));
    modal.show();
}

function showAddUserModal() {
    const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
    modal.show();
}

function showEditUserModal(userId, fullName, email, phone, role, address, city, postalCode, status, createdAt) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUserFullName').value = fullName || '';
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserPhone').value = phone || '';
    document.getElementById('editUserRole').value = role;
    document.getElementById('editUserAddress').value = address || '';
    document.getElementById('editUserCity').value = city || '';
    document.getElementById('editUserPostalCode').value = postalCode || '';
    document.getElementById('editUserStatus').value = status || 'active';
    document.getElementById('editUserCreatedAt').textContent = createdAt || 'N/A';
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function showChangePasswordModal(userId, userName) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('passwordUserName').textContent = userName;
    
    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
}

function showViewUserModal(userId) {
    // Load user details via AJAX
    fetch(`?ajax=get_user_details&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                modal.show();
            } else {
                alert('Failed to load user details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user details');
        });
}

function showCustomerDetailsModal(customerEmail) {
    // Load customer details via AJAX
    fetch(`?ajax=get_customer_details&email=${encodeURIComponent(customerEmail)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('customerDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
                modal.show();
            } else {
                alert('Failed to load customer details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading customer details');
        });
}

function showSendEmailModal(customerEmail, customerName) {
    document.getElementById('customerEmail').value = customerEmail;
    document.getElementById('customerName').value = customerName;
    document.getElementById('emailRecipient').textContent = `${customerName} <${customerEmail}>`;
    
    const modal = new bootstrap.Modal(document.getElementById('sendEmailModal'));
    modal.show();
}

function showAddProductModal() {
    const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
    modal.show();
}

function showAddCategoryModal() {
    const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    modal.show();
}

function showEditCategoryModal(categoryId, name, slug, description, image, isActive) {
    document.getElementById('editCategoryId').value = categoryId;
    document.getElementById('editCategoryName').value = name;
    document.getElementById('editCategorySlug').value = slug;
    document.getElementById('editCategorydescription').value = description || '';
    document.getElementById('editCategoryCurrentImage').innerHTML = image ? 
        `<img src="${image}" alt="${name}" class="img-thumbnail" style="max-height: 100px;">` : 
        'No image';
    document.getElementById('editCategoryIsActive').checked = isActive == '1' || isActive == true;
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

function showAddSupplierModal() {
    const modal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
    modal.show();
}

function showEditSupplierModal(supplierId, name, contactPerson, email, phone, address, isActive) {
    document.getElementById('editSupplierId').value = supplierId;
    document.getElementById('editSupplierName').value = name;
    document.getElementById('editSupplierContactPerson').value = contactPerson || '';
    document.getElementById('editSupplierEmail').value = email || '';
    document.getElementById('editSupplierPhone').value = phone || '';
    document.getElementById('editSupplierAddress').value = address || '';
    document.getElementById('editSupplierIsActive').checked = isActive == '1' || isActive == true;
    
    const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
    modal.show();
}

function showExportModal(exportType) {
    document.getElementById('exportType').value = exportType;
    // Load available columns based on export type
    loadExportColumns(exportType);
    
    const modal = new bootstrap.Modal(document.getElementById('exportDataModal'));
    modal.show();
}

function showSystemSettingsModal() {
    const modal = new bootstrap.Modal(document.getElementById('systemSettingsModal'));
    modal.show();
}

function showQuickHelpModal() {
    const modal = new bootstrap.Modal(document.getElementById('quickHelpModal'));
    modal.show();
}

function loadExportColumns(exportType) {
    // This would typically load columns via AJAX
    const columns = {
        'users': ['ID', 'Email', 'Full Name', 'Phone', 'Role', 'Address', 'City', 'Postal Code', 'Status', 'Created At'],
        'orders': ['Order Number', 'Customer Name', 'Customer Email', 'Customer Phone', 'Shipping Address', 'Payment Method', 'Payment Status', 'Order Status', 'Subtotal', 'Shipping Cost', 'Tax Amount', 'Total Amount', 'Created At'],
        'products': ['SKU', 'Name', 'Category', 'Supplier', 'Unit Price', 'Cost Price', 'Discount Price', 'Stock Quantity', 'Reorder Level', 'Is Featured', 'Is Active', 'Created At'],
        'order_items': ['Order Number', 'Product Name', 'Product SKU', 'Unit Price', 'Quantity', 'Total Price', 'Created At']
    };
    
    const columnsHtml = (columns[exportType] || columns['users']).map(col => `
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="columns[]" value="${col.toLowerCase().replace(/\s+/g, '_')}" id="col_${col}" checked>
            <label class="form-check-label" for="col_${col}">${col}</label>
        </div>
    `).join('');
    
    document.getElementById('exportColumns').innerHTML = columnsHtml;
}

function loadEmailTemplate(template) {
    const templates = {
        'welcome': `Dear {customer_name},\n\nWelcome to JAKISAWA SHOP! We're excited to have you as our customer.\n\nBest regards,\nThe JAKISAWA SHOP Team`,
        'promotion': `Dear {customer_name},\n\nWe have a special promotion just for you! Use code WELCOME10 for 10% off your next order.\n\nBest regards,\nThe JAKISAWA SHOP Team`,
        'order_update': `Dear {customer_name},\n\nThis is an update regarding your recent order. Please check your account for details.\n\nBest regards,\nThe JAKISAWA SHOP Team`,
        'newsletter': `Dear {customer_name},\n\nCheck out our latest herbal products and offers in this month's newsletter!\n\nBest regards,\nThe JAKISAWA SHOP Team`
    };
    
    const textarea = document.querySelector('#sendEmailForm textarea[name="message"]');
    if (templates[template]) {
        textarea.value = templates[template];
    } else {
        textarea.value = '';
    }
}

function printOrderDetails() {
    const printContent = document.getElementById('orderDetailsContent').innerHTML;
    const shopHeader = `
        <div style="border-bottom:2px solid #0d6efd; margin-bottom:12px; padding-bottom:10px;">
            <h2 style="margin:0; color:#0d6efd;">JAKISAWA SHOP</h2>
            <div style="font-size:12px; color:#374151; margin-top:6px; line-height:1.45;">
                <div>Address: Nairobi Information HSE, Room 405, Fourth Floor</div>
                <div>Phone: 0792546080 / +254 720 793609 | Email: support@jakisawashop.co.ke</div>
                <div>Website: https://www.jakisawashop.co.ke/</div>
            </div>
        </div>
    `;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Order Invoice</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            ${shopHeader}
            ${printContent}
            <script>
                window.onload = function() { window.print(); window.close(); }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function printUserDetails() {
    const printContent = document.getElementById('userDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>User Details</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            ${printContent}
            <script>
                window.onload = function() { window.print(); window.close(); }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function sendEmailToCustomer() {
    const customerEmail = document.getElementById('customerDetailsContent').getAttribute('data-email');
    const customerName = document.getElementById('customerDetailsContent').getAttribute('data-name');
    showSendEmailModal(customerEmail, customerName);
}
</script>
