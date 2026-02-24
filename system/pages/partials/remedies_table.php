<?php
// remedies_table.php - Remedies table partial
global $remedies, $total;
$isRemedyAdmin = in_array(strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '')), ['admin', 'super_admin'], true);

if (!function_exists('resolveRemedyImageUrl')) {
    function resolveRemedyImageUrl($imagePath): string
    {
        $value = trim((string)$imagePath);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (strpos($value, '/') === 0) {
            return projectPathUrl($value);
        }

        if (strpos($value, 'uploads/') === 0) {
            return systemUrl($value);
        }

        return projectPathUrl($value);
    }
}
?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0 remedies-table">
        <thead>
            <tr>
                <th style="width: 36px;">
                    <input type="checkbox" id="selectAllCheckboxes" onclick="toggleAllCheckboxes(this)">
                </th>
                <th style="width: 80px;">Image</th>
                <th class="col-name">Name</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Price</th>
                <th>Status</th>
                <th class="actions-column">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($remedies)): ?>
                <?php foreach ($remedies as $remedy): ?>
                    <?php 
                    // Stock status
                    if ($remedy['stock_quantity'] <= 0) {
                        $stockStatus = 'Out of Stock';
                        $stockColor = '#dc3545';
                    } elseif ($remedy['stock_quantity'] <= ($remedy['reorder_level'] ?? 10)) {
                        $stockStatus = 'Low Stock';
                        $stockColor = '#ffc107';
                    } else {
                        $stockStatus = 'In Stock';
                        $stockColor = '#28a745';
                    }
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="remedy-checkbox" value="<?php echo $remedy['id']; ?>">
                        </td>
                        <td class="col-image">
                            <div class="placeholder-image">
                                <?php if (!empty($remedy['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars(resolveRemedyImageUrl($remedy['image_url'])); ?>" 
                                         alt="<?php echo htmlspecialchars($remedy['name']); ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <i class="fas fa-capsules" style="font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-name">
                            <div class="fw-medium"><?php echo htmlspecialchars($remedy['name'] ?? ''); ?></div>
                            <small class="text-muted">SKU: <?php echo htmlspecialchars($remedy['sku'] ?? ''); ?></small>
                            <?php if (!empty($remedy['supplier_name'])): ?>
                                <small class="text-muted d-block d-none d-lg-block">Supplier: <?php echo htmlspecialchars($remedy['supplier_name']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($remedy['is_featured'])): ?>
                                <span class="badge bg-warning mt-1"><i class="fas fa-star"></i> Featured</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($remedy['category_name'] ?? 'Uncategorized'); ?></td>
                        <td>
                            <div class="d-flex flex-column align-items-start">
                                <div style="color: <?php echo $stockColor; ?>; font-weight: bold;">
                                    <?php echo number_format($remedy['stock_quantity'] ?? 0, 3); ?>
                                </div>
                                <span class="badge mt-1" style="background-color: <?php echo $stockColor; ?>;">
                                    <?php echo $stockStatus; ?>
                                </span>
                            </div>
                            <small class="text-muted d-block">Reorder: <?php echo number_format($remedy['reorder_level'] ?? 10, 3); ?></small>
                        </td>
                        <td>
                            <div class="text-primary fw-bold">KSH <?php echo number_format($remedy['unit_price'] ?? 0, 2); ?></div>
                            <?php if (!empty($remedy['discount_price'])): ?>
                                <small class="text-danger">
                                    <s>KSH <?php echo number_format($remedy['discount_price'], 2); ?></s>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($remedy['is_active'])): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-column">
                            <div class="d-flex flex-wrap gap-1">
                                <a class="btn btn-sm btn-info"
                                   href="?page=remedy_view&id=<?php echo (int)$remedy['id']; ?>"
                                   onclick="if (typeof window.openRemedyViewer === 'function') { window.openRemedyViewer(<?php echo (int)$remedy['id']; ?>); return false; }">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-warning"
                                   href="?page=edit_remedy&id=<?php echo (int)$remedy['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a class="btn btn-sm btn-primary"
                                   href="?page=inventory&search=<?php echo urlencode((string)($remedy['name'] ?? '')); ?>"
                                   onclick="if (typeof window.openStockModal === 'function') { window.openStockModal(<?php echo (int)$remedy['id']; ?>, '<?php echo addslashes($remedy['name'] ?? ''); ?>', <?php echo (float)($remedy['stock_quantity'] ?? 0); ?>, <?php echo (int)($remedy['supplier_id'] ?? 0); ?>); return false; }">
                                    <i class="fas fa-boxes"></i>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="return toggleFeatured(<?php echo $remedy['id']; ?>, <?php echo !empty($remedy['is_featured']) ? 1 : 0; ?>)">
                                                <i class="fas fa-star <?php echo !empty($remedy['is_featured']) ? 'text-warning' : 'text-muted'; ?> me-2"></i>
                                                <?php echo !empty($remedy['is_featured']) ? 'Remove Featured' : 'Mark as Featured'; ?>
                                            </a>
                                        </li>
                                        <?php if ($isRemedyAdmin): ?>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="return toggleActive(<?php echo $remedy['id']; ?>, <?php echo !empty($remedy['is_active']) ? 1 : 0; ?>)">
                                                <i class="fas fa-power-off <?php echo !empty($remedy['is_active']) ? 'text-success' : 'text-danger'; ?> me-2"></i>
                                                <?php echo !empty($remedy['is_active']) ? 'Deactivate' : 'Activate'; ?>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="generateBarcode(<?php echo $remedy['id']; ?>); return false;">
                                                <i class="fas fa-barcode me-2"></i> Print Barcode
                                            </a>
                                        </li>
                                        <?php if ($isRemedyAdmin): ?>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteRemedyConfirm(<?php echo $remedy['id']; ?>, '<?php echo addslashes($remedy['name'] ?? ''); ?>'); return false;">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-heartbeat fa-2x text-muted mb-3"></i>
                        <p class="text-muted">No remedies found</p>
                        <?php if (empty($search) && empty($categoryFilter) && empty($statusFilter)): ?>
                            <a href="add_remedy.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Add Your First Remedy
                            </a>
                        <?php else: ?>
                            <a href="admin_dashboard.php?page=remedies" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Barcode Print Section -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="float-end">
                <button onclick="printSelectedBarcodes()" class="btn btn-success btn-sm" id="printBarcodesBtn" style="display: none;">
                    <i class="fas fa-print me-2"></i> Print Selected Barcodes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript functions for remedies table
function toggleAllCheckboxes(source) {
    const checkboxes = document.getElementsByClassName('remedy-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    togglePrintButton();
}

function togglePrintButton() {
    const checkboxes = document.getElementsByClassName('remedy-checkbox');
    const printBtn = document.getElementById('printBarcodesBtn');
    let checkedCount = 0;
    
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            checkedCount++;
        }
    }
    
    if (printBtn) {
        printBtn.style.display = checkedCount > 0 ? 'inline-block' : 'none';
        printBtn.innerHTML = `<i class="fas fa-print me-2"></i> Print ${checkedCount} Selected Barcode${checkedCount !== 1 ? 's' : ''}`;
    }
}

// Add event listeners to checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.getElementsByClassName('remedy-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].addEventListener('change', togglePrintButton);
    }
});

function printSelectedBarcodes() {
    const checkboxes = document.getElementsByClassName('remedy-checkbox');
    const selectedIds = [];
    
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            selectedIds.push(checkboxes[i].value);
        }
    }
    
    if (selectedIds.length === 0) {
        alert('Please select at least one remedy to print barcodes.');
        return;
    }
    
    alert('Barcode printing module is not installed in this build.');
}

function viewRemedyDetails(id) {
    if (typeof window.openRemedyViewer === 'function') {
        window.openRemedyViewer(id);
        return;
    }
    // Hard fallback to full details page
    window.location.href = `?page=remedy_view&id=${encodeURIComponent(id)}`;
}

function editRemedy(id) {
    // Dedicated editor page is the source of truth for edits.
    const current = new URL(window.location.href);
    const currentEditId = current.searchParams.get('id') || current.searchParams.get('edit_id');
    if (currentEditId && String(currentEditId) === String(id)) {
        alert('Editor could not initialize. Please clear cache (Ctrl+F5) and try again.');
        return;
    }
    window.location.href = `?page=edit_remedy&id=${encodeURIComponent(id)}`;
}

function updateStockModal(id, name, currentStock) {
    if (typeof window.openStockModal === 'function') {
        window.openStockModal(id, name, currentStock);
        return;
    }
    // Hard fallback: go to inventory page where stock modal/actions are available
    window.location.href = `?page=inventory&search=${encodeURIComponent(name || '')}`;
}

function toggleFeatured(id, currentStatus) {
    if (typeof window.toggleRemedyFeatured === 'function') {
        window.toggleRemedyFeatured(id);
        return false;
    }
    alert('Unable to change featured status right now.');
    return false;
}

function toggleActive(id, currentStatus) {
    if (typeof window.toggleRemedyActive === 'function') {
        window.toggleRemedyActive(id);
        return false;
    }
    alert('Unable to change active status right now.');
    return false;
}

function generateBarcode(id) {
    alert('Barcode generation module is not installed in this build.');
}

function deleteRemedyConfirm(id, name) {
    if (typeof window.deleteRemedyById === 'function') {
        window.deleteRemedyById(id, name);
        return false;
    }
    alert('Unable to delete remedy right now.');
    return false;
}
</script>
