<!-- Add Supplier Modal - Production Ready -->
<style>
/* Keep supplier forms inside viewport on all screen sizes */
#addSupplierModal .modal-dialog,
#editSupplierModal .modal-dialog {
    max-width: min(96vw, 900px);
    margin: 1rem auto;
}

#addSupplierModal .bg-gradient-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
}

#editSupplierModal .bg-gradient-warning {
    background: linear-gradient(135deg, #f59f00 0%, #f08c00 100%) !important;
}

#addSupplierModal .icon-box,
#editSupplierModal .icon-box {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.2);
}

#addSupplierModal .modal-content,
#editSupplierModal .modal-content {
    max-height: calc(100vh - 2rem);
    overflow: hidden;
}

#addSupplierModal .modal-body,
#editSupplierModal .modal-body {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
    overflow-x: hidden;
}

@media (max-width: 991px) {
    #addSupplierModal,
    #editSupplierModal {
        padding-top: 0 !important;
    }

    #addSupplierModal .modal-dialog,
    #editSupplierModal .modal-dialog {
        max-width: calc(100vw - 0.75rem);
        margin: calc(62px + 0.5rem) auto 0.25rem !important;
        height: calc(100dvh - 62px - 0.75rem);
        min-height: 0 !important;
        display: flex;
        align-items: stretch !important;
    }

    #addSupplierModal .modal-dialog.modal-dialog-centered,
    #editSupplierModal .modal-dialog.modal-dialog-centered {
        align-items: flex-start !important;
        min-height: 0 !important;
    }

    #addSupplierModal .modal-content,
    #editSupplierModal .modal-content {
        height: 100%;
        max-height: none;
    }

    #addSupplierModal .modal-body,
    #editSupplierModal .modal-body {
        flex: 1 1 auto;
        max-height: none;
    }
}

@media (max-width: 576px) {
    #addSupplierModal .modal-dialog,
    #editSupplierModal .modal-dialog {
        max-width: calc(100vw - 0.5rem);
        margin: calc(62px + 0.25rem) auto 0.25rem !important;
        height: calc(100dvh - 62px - 0.5rem);
    }

    #addSupplierModal .modal-body,
    #editSupplierModal .modal-body {
        max-height: none;
        padding: 1rem !important;
    }
}
</style>

<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-gradient-primary text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="icon-box me-3">
                        <i class="bi bi-truck fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="addSupplierModalLabel">Add New Supplier</h5>
                        <small class="opacity-75">Fill in the supplier information below</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <form method="POST" action="pages/actions/supplier/supplier_actions.php" class="needs-validation" id="addSupplierForm" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_supplier">
                    
                    <!-- Supplier Information Section -->
                    <div class="section-header mb-3">
                        <h6 class="text-primary mb-0">
                            <i class="bi bi-info-circle me-2"></i>Supplier Information
                        </h6>
                        <hr class="mt-2">
                    </div>
                    
                    <div class="row g-3">
                        <!-- Supplier Name -->
                        <div class="col-md-6">
                            <label for="addName" class="form-label required">
                                <i class="bi bi-building text-muted"></i> Supplier Name
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="addName"
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
                            <label for="addContactPerson" class="form-label">
                                <i class="bi bi-person text-muted"></i> Contact Person
                            </label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="addContactPerson"
                                name="contact_person" 
                                placeholder="Full name"
                                maxlength="100">
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="addEmail" class="form-label">
                                <i class="bi bi-envelope text-muted"></i> Email Address
                            </label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="addEmail"
                                name="email" 
                                placeholder="jakisawa@jakisawashop.co.ke"
                                maxlength="100">
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="addPhone" class="form-label">
                                <i class="bi bi-telephone text-muted"></i> Phone Number
                            </label>
                            <input 
                                type="tel" 
                                class="form-control" 
                                id="addPhone"
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
                            <label for="addAddress" class="form-label">
                                <i class="bi bi-geo-alt text-muted"></i> Physical Address
                            </label>
                            <textarea 
                                class="form-control" 
                                id="addAddress"
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
                                            id="addIsActive" 
                                            checked>
                                        <label class="form-check-label" for="addIsActive">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation for Add Supplier
(function() {
    'use strict';
    const form = document.getElementById('addSupplierForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
        
        // Reset validation on modal close
        const modal = document.getElementById('addSupplierModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                form.reset();
                form.classList.remove('was-validated');
            });
        }
    }
})();
</script>
