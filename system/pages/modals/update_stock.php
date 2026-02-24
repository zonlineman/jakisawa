<!-- ==========================================
     UPDATE STOCK MODAL
     Version: 2.0 - Production Ready
     ========================================== -->
<?php
require_once __DIR__ . '/../../includes/database.php';
$stockSuppliers = [];
$stockConn = getDBConnection();
if ($stockConn) {
    $sres = mysqli_query($stockConn, "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
    if ($sres) {
        while ($s = mysqli_fetch_assoc($sres)) {
            $stockSuppliers[] = $s;
        }
    }
}
?>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1" aria-labelledby="updateStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="updateStockModalLabel">
                    <i class="fas fa-boxes"></i> Update Stock Quantity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateStockForm" method="POST" action="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/pages/actions/remedies/update_stock.php', ENT_QUOTES); ?>">
                <div class="modal-body">
                    <input type="hidden" name="remedy_id" id="stockRemedyId">
                    
                    <div class="mb-3">
                        <label for="stockRemedyName" class="form-label">Remedy Name</label>
                        <input type="text" class="form-control" id="stockRemedyName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="currentStock" class="form-label">Current Stock</label>
                        <input type="number" class="form-control" id="currentStock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newStockQuantity" class="form-label">New Stock Quantity *</label>
                        <input type="number" class="form-control" id="newStockQuantity" name="stock_quantity" required min="0">
                        <small class="text-muted">Enter the new total stock quantity</small>
                    </div>

                    <div class="mb-3">
                        <label for="stockSupplierId" class="form-label">Supplier *</label>
                        <select class="form-select" id="stockSupplierId" name="supplier_id" required>
                            <option value="">Select supplier</option>
                            <?php foreach ($stockSuppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['id']; ?>"><?php echo htmlspecialchars((string)$supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($stockSuppliers)): ?>
                            <small class="text-danger">No active suppliers found. Add/activate a supplier first.</small>
                        <?php else: ?>
                            <small class="text-muted">Supplier is required for stock update.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stockNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="stockNotes" name="notes" rows="2" placeholder="Reason for stock update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning" id="updateStockBtn">
                        <i class="fas fa-save"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle update stock form submission
document.addEventListener('DOMContentLoaded', function() {
    const updateStockEndpoint = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/pages/actions/remedies/update_stock.php'); ?>;
    const updateStockForm = document.getElementById('updateStockForm');
    if (updateStockForm) {
        updateStockForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const supplierSelect = document.getElementById('stockSupplierId');
            if (!supplierSelect || !supplierSelect.value) {
                alert('Please select a supplier.');
                return;
            }
            
            const formData = new FormData(this);
            const btn = document.getElementById('updateStockBtn');
            
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
                btn.disabled = true;
                
                fetch(updateStockEndpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('updateStockModal'));
                        if (modal) modal.hide();
                        
                        // Reload page
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('❌ Error: ' + data.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('❌ Network error: ' + error.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        });
    }
});
</script>
