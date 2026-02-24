<!-- View Supplier Modal - Modern Production Ready -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-labelledby="viewSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-gradient-info text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="icon-box me-3">
                        <i class="bi bi-eye-fill fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="viewSupplierModalLabel">Supplier Details</h5>
                        <small class="opacity-75">Complete supplier information</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-0">
                <div id="supplierDetails">
                    <!-- Loading State -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-3">Loading supplier details...</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Close
                </button>
                <button type="button" class="btn btn-info text-white" onclick="printSupplierDetails()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.icon-box {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    border-radius: 15px;
    overflow: hidden;
}

.supplier-detail-card {
    border: none;
    border-radius: 10px;
    overflow: hidden;
}

.detail-row {
    border-bottom: 1px solid #f0f0f0;
    padding: 0.75rem 0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #6c757d;
    min-width: 150px;
}

.detail-value {
    color: #212529;
}

.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.product-table-wrapper {
    max-height: 400px;
    overflow-y: auto;
}
</style>

<!-- Print logic comes from assets/js/suppliers.js -->
