<!-- Edit Supplier Modal - Production Ready -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-gradient-warning text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="icon-box me-3">
                        <i class="bi bi-pencil-square fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="editSupplierModalLabel">Edit Supplier</h5>
                        <small class="opacity-75">Update supplier information</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <form method="POST" action="pages/actions/supplier/supplier_actions.php" class="needs-validation" id="editSupplierForm" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_supplier">
                    <input type="hidden" name="id" id="editId">
                    
                    <!-- Supplier Information Section -->
                    <div class="section-header mb-3">
                        <h6 class="text-warning mb-0">
                            <i class="bi bi-info-circle me-2"></i>Supplier Information
                        </h6>
                        <hr class="mt-2">
                    </div>
                    
                    <div class="row g-3">
                        <!-- Supplier Name -->
                        <div class="col-md-6">
                            <label for="editName" class="form-label required">
                                <i class="bi bi-building text-muted"></i> Supplier Name
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="editName"
                                name="name" 
                                placeholder="Enter supplier name"
                                required
                                maxlength="200">
                            <div class="invalid-feedback">
                                Please provide a supplier name.
                            </div>
                        </div>
                        
                        <!-- Contact Person -->
                        <div class="col-md-6">
                            <label for="editContactPerson" class="form-label">
                                <i class="bi bi-person text-muted"></i> Contact Person
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="editContactPerson"
                                name="contact_person" 
                                placeholder="Full name"
                                maxlength="100">
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">
                                <i class="bi bi-envelope text-muted"></i> Email Address
                            </label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="editEmail"
                                name="email" 
                                placeholder="jakisawa@jakisawashop.co.ke"
                                maxlength="100">
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="editPhone" class="form-label">
                                <i class="bi bi-telephone text-muted"></i> Phone Number
                            </label>
                            <input 
                                type="tel" 
                                class="form-control" 
                                id="editPhone"
                                name="phone" 
                                placeholder="07XX XXX XXX"
                                maxlength="20"
                                pattern="[0-9\s\-\+\(\)]+">
                            <div class="invalid-feedback">
                                Please provide a valid phone number.
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="col-12">
                            <label for="editAddress" class="form-label">
                                <i class="bi bi-geo-alt text-muted"></i> Physical Address
                            </label>
                            <textarea 
                                class="form-control" 
                                id="editAddress"
                                name="address" 
                                rows="3"
                                placeholder="Enter complete physical address including city and postal code"></textarea>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Include street, building, city, and postal code
                            </small>
                        </div>
                        
                        <!-- Status Toggle -->
                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch">
                                        <input 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            role="switch"
                                            name="is_active" 
                                            id="editIsActive">
                                        <label class="form-check-label" for="editIsActive">
                                            <strong>Active Supplier</strong>
                                            <small class="d-block text-muted">Enable this supplier for product assignments</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning text-white">
                        <i class="bi bi-check-circle me-1"></i> Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation for Edit Supplier
(function() {
    'use strict';
    const form = document.getElementById('editSupplierForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
        
        // Reset validation on modal close
        const modal = document.getElementById('editSupplierModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                form.reset();
                form.classList.remove('was-validated');
            });
        }
    }
})();
</script>
