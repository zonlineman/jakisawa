<!-- Bootstrap CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
if (window.bootstrap && window.bootstrap.Tooltip) {
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl)
    })
}

// View supplier details

function viewSupplier(id) {
    fetch(`pages/actions/ajax/get_suppliers_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            const supplier = data.data;
            const sales = data.sales_stats;
            
            let productsHtml = '';
            if (data.products && data.products.length > 0) {
                productsHtml = `
                    <h5 class="mt-4">Products from this Supplier</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.products.forEach(product => {
                    const stockClass = product.stock_quantity <= product.reorder_level ? 'text-danger' : '';
                    productsHtml += `
                        <tr>
                            <td>${product.sku || '-'}</td>
                            <td>${product.name}</td>
                            <td>${product.category_name || '-'}</td>
                            <td>KES ${parseFloat(product.unit_price).toFixed(2)}</td>
                            <td class="${stockClass}">${product.stock_quantity}</td>
                            <td>${product.is_active ? 'Active' : 'Inactive'}</td>
                        </tr>
                    `;
                });
                
                productsHtml += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                productsHtml = '<p class="text-muted">No products from this supplier yet.</p>';
            }
            
            // Format sales stats
            const salesHtml = sales.total_sales > 0 ? `
                <tr>
                    <th>Total Sales:</th>
                    <td>KES ${parseFloat(sales.total_sales).toFixed(2)}</td>
                </tr>
                <tr>
                    <th>Units Sold:</th>
                    <td>${sales.total_units_sold}</td>
                </tr>
                <tr>
                    <th>Total Orders:</th>
                    <td>${sales.order_count}</td>
                </tr>
            ` : '';
            
            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <th style="width: 150px;">ID:</th>
                                <td>${supplier.id}</td>
                            </tr>
                            <tr>
                                <th>Name:</th>
                                <td><strong>${supplier.name}</strong></td>
                            </tr>
                            <tr>
                                <th>Contact Person:</th>
                                <td>${supplier.contact_person || '-'}</td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td>${supplier.email || '-'}</td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td>${supplier.phone || '-'}</td>
                            </tr>
                            <tr>
                                <th>Address:</th>
                                <td>${supplier.address || '-'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <th style="width: 150px;">Status:</th>
                                <td><span class="${supplier.is_active ? 'text-success' : 'text-muted'}">
                                    ${supplier.is_active ? 'Active' : 'Inactive'}
                                </span></td>
                            </tr>
                            <tr>
                                <th>Total Products:</th>
                                <td>${supplier.product_count || 0}</td>
                            </tr>
                            <tr>
                                <th>Low Stock Items:</th>
                                <td>${supplier.low_stock_count || 0}</td>
                            </tr>
                            <tr>
                                <th>Total Stock:</th>
                                <td>${supplier.total_stock || 0}</td>
                            </tr>
                            ${salesHtml}
                            <tr>
                                <th>Created:</th>
                                <td>${new Date(supplier.created_at).toLocaleDateString()}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                ${productsHtml}
            `;
            
            document.getElementById('supplierDetails').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('viewSupplierModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load supplier details');
        });
}
// Edit supplier 

function editSupplier(id, event) {
    // If event is not passed, get it from window.event (for older browsers)
    if (!event && window.event) {
        event = window.event;
    }
    
    // Store the clicked button for loading state
    let editBtn = null;
    if (event && event.target) {
        // Find the closest edit button
        editBtn = event.target.closest('.btn-warning');
    }
    
    // Store original button state if we found a button
    let originalHTML = '';
    if (editBtn) {
        originalHTML = editBtn.innerHTML;
        editBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        editBtn.disabled = true;
    }
    
    console.log('Loading supplier ID:', id);
    
    fetch(`pages/actions/ajax/get_suppliers_details.php?id=${id}`)
        .then(response => {
            // Reset button state if we have a button
            if (editBtn) {
                editBtn.innerHTML = originalHTML;
                editBtn.disabled = false;
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load supplier data');
            }
            
            // IMPORTANT: Data is nested in data.data
            const supplier = data.data;
            
            if (!supplier) {
                throw new Error('No supplier data received');
            }
            
            // Fill form fields
            document.getElementById('editId').value = supplier.id;
            document.getElementById('editName').value = supplier.name || '';
            document.getElementById('editContactPerson').value = supplier.contact_person || '';
            document.getElementById('editEmail').value = supplier.email || '';
            document.getElementById('editPhone').value = supplier.phone || '';
            document.getElementById('editAddress').value = supplier.address || '';
            document.getElementById('editIsActive').checked = supplier.is_active == 1;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
            modal.show();
            
        })
        .catch(error => {
            // Reset button state if we have a button
            if (editBtn) {
                editBtn.innerHTML = originalHTML;
                editBtn.disabled = false;
            }
            
            console.error('Edit supplier error:', error);
            alert('Error loading supplier: ' + error.message);
        });
}

// Fallback method if AJAX fails
function attemptFallbackEdit(id) {
    // Try to get data from data attributes in table row
    const row = document.querySelector(`tr[data-supplier-id="${id}"]`);
    if (row) {
        const name = row.querySelector('.supplier-name')?.textContent || '';
        const contactPerson = row.querySelector('.supplier-contact')?.textContent || '';
        const email = row.querySelector('.supplier-email')?.textContent || '';
        const phone = row.querySelector('.supplier-phone')?.textContent || '';
        const address = row.querySelector('.supplier-address')?.textContent || '';
        const isActive = row.querySelector('.supplier-status')?.textContent?.includes('Active') || false;
        
        // Fill form with available data
        document.getElementById('editId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editContactPerson').value = contactPerson || '';
        document.getElementById('editEmail').value = email || '';
        document.getElementById('editPhone').value = phone || '';
        document.getElementById('editAddress').value = address || '';
        document.getElementById('editIsActive').checked = isActive;
        
        // Show warning toast
        showToast('warning', 'Loaded limited data. Some fields may be empty.');
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
        modal.show();
    } else {
        // Last resort: redirect to dedicated edit page
        if (confirm('Cannot load supplier data. Would you like to try the edit page instead?')) {
            window.location.href = `edit_supplier.php?id=${id}`;
        }
    }
}

// Delete confirmation
function confirmDelete(id) {
    document.getElementById('deleteId').value = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Toggle supplier status
function toggleStatus(id, currentStatus) {
    if (confirm(`Are you sure you want to ${currentStatus ? 'deactivate' : 'activate'} this supplier?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=suppliers';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_supplier_status';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Toast notification function
function showToast(type, message) {
    const existingToasts = document.querySelectorAll('.ajax-toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${type} border-0 ajax-toast`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'error' ? 'bi-x-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toastEl);
    
    const toast = new bootstrap.Toast(toastEl, {
        delay: 3000,
        autohide: true
    });
    toast.show();
    
    toastEl.addEventListener('hidden.bs.toast', function () {
        toastEl.remove();
    });
}
</script>
