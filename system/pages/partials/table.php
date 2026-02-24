<!-- Suppliers Table - Modern Production Ready -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-dark fw-semibold">
                <i class="bi bi-table me-2"></i>
                Suppliers List 
                <span class="badge bg-primary ms-2"><?php echo number_format($total_suppliers); ?></span>
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 60px;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </div>
                        </th>
                        <th style="width: 50px;">ID</th>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Contact Info</th>
                        <th class="text-center">Products</th>
                        <th class="text-center">Status</th>
                        <th>Created</th>
                        <th class="text-center" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="text-muted mt-3">No Suppliers Found</h5>
                                    <p class="text-muted mb-4">Start by adding your first supplier</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                        <i class="bi bi-plus-circle me-1"></i> Add Supplier
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php
                            $status_class = $supplier['is_active'] ? 'success' : 'secondary';
                            $status_text = $supplier['is_active'] ? 'Active' : 'Inactive';
                            $status_icon = $supplier['is_active'] ? 'check-circle-fill' : 'x-circle-fill';
                            $has_low_stock = ($supplier['low_stock_items'] ?? 0) > 0;
                            ?>
                            <tr data-supplier-id="<?php echo $supplier['id']; ?>">
                                <!-- Checkbox -->
                                <td class="text-center">
                                    <div class="form-check">
                                        <input class="form-check-input supplier-checkbox" type="checkbox" value="<?php echo $supplier['id']; ?>">
                                    </div>
                                </td>
                                
                                <!-- ID -->
                                <td>
                                    <span class="badge bg-light text-dark">#<?php echo $supplier['id']; ?></span>
                                </td>
                                
                                <!-- Supplier Name -->
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="supplier-avatar me-2">
                                            <i class="bi bi-building-fill text-primary"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                            <?php if ($has_low_stock): ?>
                                                <small class="text-danger">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> Low stock items
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Contact Person -->
                                <td class="supplier-contact">
                                    <?php if (!empty($supplier['contact_person'])): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle text-muted me-2"></i>
                                            <?php echo htmlspecialchars($supplier['contact_person']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Contact Info -->
                                <td>
                                    <div class="contact-info">
                                        <?php if (!empty($supplier['email'])): ?>
                                            <div class="mb-1">
                                                <i class="bi bi-envelope text-muted me-1"></i>
                                                <small class="supplier-email"><?php echo htmlspecialchars($supplier['email']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($supplier['phone'])): ?>
                                            <div>
                                                <i class="bi bi-telephone text-muted me-1"></i>
                                                <small class="supplier-phone"><?php echo htmlspecialchars($supplier['phone']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($supplier['email']) && empty($supplier['phone'])): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Products Count -->
                                <td class="text-center">
                                    <div class="product-count">
                                        <span class="badge <?php echo $has_low_stock ? 'bg-danger' : 'bg-info'; ?> rounded-pill">
                                            <?php echo $supplier['product_count'] ?: 0; ?> 
                                            <?php echo ($supplier['product_count'] == 1) ? 'product' : 'products'; ?>
                                        </span>
                                        <?php if ($has_low_stock): ?>
                                            <small class="d-block text-danger mt-1">
                                                <?php echo $supplier['low_stock_items']; ?> low stock
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Status -->
                                <td class="text-center supplier-status">
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <i class="bi bi-<?php echo $status_icon; ?> me-1"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                
                                <!-- Created Date -->
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo date('M d, Y', strtotime($supplier['created_at'])); ?>
                                    </small>
                                </td>
                                
                                <!-- Actions -->
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-info" 
                                            onclick="viewSupplier(<?php echo $supplier['id']; ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-warning" 
                                            onclick="editSupplier(<?php echo $supplier['id']; ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Edit Supplier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-<?php echo $supplier['is_active'] ? 'secondary' : 'success'; ?>" 
                                            onclick="toggleStatus(<?php echo $supplier['id']; ?>, <?php echo $supplier['is_active']; ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="<?php echo $supplier['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $supplier['id']; ?>)"
                                            data-bs-toggle="tooltip" 
                                            title="Delete Supplier">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_suppliers > $limit): ?>
        <div class="card-footer bg-white border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing <?php echo min($offset + 1, $total_suppliers); ?> 
                    to <?php echo min($offset + $limit, $total_suppliers); ?> 
                    of <?php echo number_format($total_suppliers); ?> suppliers
                </div>
                
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $total_pages = ceil($total_suppliers / $limit);
                        $visible_pages = 5;
                        $start_page = max(1, $page - floor($visible_pages / 2));
                        $end_page = min($total_pages, $start_page + $visible_pages - 1);
                        
                        if ($end_page - $start_page + 1 < $visible_pages) {
                            $start_page = max(1, $end_page - $visible_pages + 1);
                        }
                        ?>
                        
                        <!-- First Page -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=suppliers&p=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Previous -->
                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=suppliers&p=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=suppliers&p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Next -->
                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=suppliers&p=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        
                        <!-- Last Page -->
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=suppliers&p=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    <?php endif; ?>
</div>
</div> <!-- Close container-fluid from header -->

<style>
.supplier-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.table > tbody > tr:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.btn-group-sm .btn {
    padding: 0.375rem 0.5rem;
}

.contact-info small {
    display: block;
    line-height: 1.6;
}

.pagination .page-link {
    color: #667eea;
    border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #667eea;
    border-color: #667eea;
}

.pagination .page-link:hover {
    background-color: #f8f9fa;
    color: #667eea;
}

.empty-state {
    padding: 2rem;
}

.product-count .badge {
    font-weight: 500;
}
</style>

<script>
// Select all checkboxes functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.supplier-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});

// Individual checkbox changes affect select all
document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.supplier-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.supplier-checkbox:checked');
        document.getElementById('selectAll').checked = allCheckboxes.length === checkedCheckboxes.length;
    });
});
</script>