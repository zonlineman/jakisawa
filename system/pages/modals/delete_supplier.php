<!-- Delete Confirmation Modal - Production Ready -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-gradient-danger text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="icon-box me-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <form method="POST" action="pages/actions/supplier/supplier_actions.php" id="deleteSupplierForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="delete_supplier">
                    <input type="hidden" name="id" id="deleteId">
                    
                    <!-- Warning Icon -->
                    <div class="text-center mb-4">
                        <div class="warning-icon-large">
                            <i class="bi bi-trash3-fill text-danger"></i>
                        </div>
                    </div>
                    
                    <!-- Warning Message -->
                    <div class="alert alert-danger border-0 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-circle-fill me-3 mt-1 fs-5"></i>
                            <div>
                                <h6 class="alert-heading mb-2">Are you absolutely sure?</h6>
                                <p class="mb-0">
                                    You are about to permanently delete this supplier from the system. 
                                    This action <strong>cannot be undone</strong>.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Important Notes -->
                    <div class="card border-warning mb-3">
                        <div class="card-body p-3">
                            <h6 class="text-warning mb-2">
                                <i class="bi bi-info-circle-fill me-2"></i>Important Notes
                            </h6>
                            <ul class="mb-0 small">
                                <li class="mb-2">
                                    <strong>Product Assignment:</strong> If this supplier has products assigned, 
                                    you must reassign them to another supplier first.
                                </li>
                                <li class="mb-2">
                                    <strong>Order History:</strong> Historical order data referencing this supplier will be preserved.
                                </li>
                                <li>
                                    <strong>Audit Trail:</strong> This deletion will be logged in the audit system.
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Confirmation Checkbox -->
                    <div class="form-check mb-3">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="confirmDelete" 
                            required>
                        <label class="form-check-label" for="confirmDelete">
                            I understand that this action is permanent and cannot be reversed
                        </label>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash3 me-1"></i> Yes, Delete Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
document.getElementById('confirmDelete')?.addEventListener('change', function() {
    document.getElementById('confirmDeleteBtn').disabled = !this.checked;
});

// Reset form when modal is closed
document.getElementById('deleteConfirmModal')?.addEventListener('hidden.bs.modal', function () {
    const form = document.getElementById('deleteSupplierForm');
    form.reset();
    document.getElementById('confirmDeleteBtn').disabled = true;
});
</script>