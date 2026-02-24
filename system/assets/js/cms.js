/**
 * CMS JavaScript - Customer Management System
 * Location: /pages/assets/js/cms.js
 * 
 * Handles all AJAX interactions and UI events
 * Fixed: Modal handling, function definitions, safety checks
 */

// ============================================================================
// ESSENTIAL CHECKS - RUN FIRST
// ============================================================================

// Ensure jQuery is available
if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
    var $ = jQuery;
}

// Check Bootstrap is loaded
if (typeof bootstrap === 'undefined') {
    console.error('Bootstrap 5 is not loaded. Make sure bootstrap.bundle.js is included.');
    window.showError = function(msg) { alert('Error: ' + msg); };
} else {
    console.log('✓ Bootstrap 5 loaded');
}

// Check jQuery is loaded
if (typeof $ === 'undefined') {
    console.error('jQuery is not loaded. Make sure jQuery is included.');
} else {
    console.log('✓ jQuery loaded');
}

// Setup URLs with fallback
const BASE_URL_VAR = typeof BASE_URL !== 'undefined' ? BASE_URL : '-ministry/system';
const AJAX_URL_VAR = typeof AJAX_URL !== 'undefined' ? AJAX_URL : BASE_URL_VAR + '/ajax/customer_actions.php';
let bulkEmailRecipients = [];
let bulkSmsRecipients = [];
let communicationHealthState = { smtpReady: false, smsReady: false };

// ============================================================================
// SAFE MODAL WRAPPER FUNCTIONS
// ============================================================================

/**
 * Show error - works even if Bootstrap hasn't loaded
 */
window.showError = function(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) alertDiv.remove();
    }, 5000);
};

/**
 * Safe modal show - CRITICAL FOR FIXING BACKDROP ERROR
 */
window.showModal = function(elementId) {
    try {
        const element = document.getElementById(elementId);
        if (!element) {
            console.error(`[ERROR] Modal element not found: ${elementId}`);
            showError(`Modal element not found: ${elementId}`);
            return false;
        }
        
        const modal = new bootstrap.Modal(element, {
            keyboard: true,
            backdrop: 'static',
            focus: true
        });
        modal.show();
        console.log(`✓ Showed modal: ${elementId}`);
        return true;
    } catch (e) {
        console.error(`[ERROR] Failed to show modal ${elementId}:`, e);
        showError(`Failed to open modal. Check console.`);
        return false;
    }
};

/**
 * Safe modal hide
 */
window.hideModal = function(elementId) {
    try {
        const element = document.getElementById(elementId);
        if (!element) {
            console.error(`[ERROR] Modal element not found: ${elementId}`);
            return false;
        }
        
        const modal = bootstrap.Modal.getInstance(element);
        if (modal) {
            modal.hide();
            console.log(`✓ Hid modal: ${elementId}`);
            return true;
        }
        return false;
    } catch (e) {
        console.error(`[ERROR] Failed to hide modal ${elementId}:`, e);
        return false;
    }
};

// ============================================================================
// UTILITIES - DEFINED EARLY SO OTHER FUNCTIONS CAN USE THEM
// ============================================================================

/**
 * Show alert
 */
window.showAlert = function(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.cms-content') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) alertDiv.remove();
    }, 5000);
};

/**
 * Show success message
 */
window.showSuccess = function(message) {
    showAlert('success', message);
};

/**
 * Show error message - also defined as window.showError at top
 */
window.showError = window.showError || function(message) {
    showAlert('danger', message);
};

/**
 * Escape HTML
 */
window.escapeHtml = function(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

/**
 * Format number
 */
window.formatNumber = function(num) {
    return parseFloat(num || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
};

/**
 * Approve customer
 */
function approveCustomer(customerId) {
    if (!confirm('Approve this customer?')) return;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'approve',
            customer_id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error approving customer. Please check the console.');
        }
    });
}

/**
 * Reject customer
 */
function rejectCustomer(customerId) {
    const element = document.getElementById('modalReject');
    if (!element) {
        showError('Modal not found: modalReject');
        return;
    }
    
    document.getElementById('rejectId').value = customerId;
    showModal('modalReject');
}

function submitReject() {
    const customerId = document.getElementById('rejectId').value;
    const reason = document.getElementById('rejectReason').value;
    
    if (!reason.trim()) {
        showError('Please provide a reason');
        return;
    }
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'reject',
            customer_id: customerId,
            reason: reason
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                hideModal('modalReject');
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error rejecting customer');
        }
    });
}

/**
 * Activate customer - Fixed with better error handling
 */
function activateCustomer(customerId) {
    if (!confirm('Activate this customer?')) return;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'activate',
            customer_id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error activating customer. Check console.');
        }
    });
}

/**
 * Deactivate customer - Fixed with better error handling
 */
function deactivateCustomer(customerId) {
    if (!confirm('Deactivate this customer?')) return;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'deactivate',
            customer_id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error deactivating customer. Check console.');
        }
    });
}

/**
 * Delete customer
 */
function deleteCustomer(customerId, customerName) {
    const element = document.getElementById('modalDelete');
    if (!element) {
        showError('Modal not found: modalDelete');
        return;
    }
    
    document.getElementById('deleteId').value = customerId;
    document.getElementById('deleteMessage').textContent = 
        `Are you sure you want to delete "${customerName}"? This cannot be undone.`;
    showModal('modalDelete');
}

function submitDelete() {
    const customerId = document.getElementById('deleteId').value;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'delete',
            customer_id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                hideModal('modalDelete');
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error deleting customer');
        }
    });
}

/**
 * Convert random to registered
 */
function convertCustomer(email, name, phone) {
    const element = document.getElementById('modalConvert');
    if (!element) {
        showError('Modal not found: modalConvert');
        return;
    }
    
    document.getElementById('convertEmail').value = email;
    document.getElementById('convertName').value = name;
    document.getElementById('convertPhone').value = phone;
    showModal('modalConvert');
}

function submitConvert() {
    const email = document.getElementById('convertEmail').value;
    const name = document.getElementById('convertName').value;
    const phone = document.getElementById('convertPhone').value;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'convert',
            email: email,
            name: name,
            phone: phone
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                hideModal('modalConvert');
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error converting customer');
        }
    });
}

// ============================================================================
// EMAIL FUNCTIONS
// ============================================================================

/**
 * Send email
 */
function sendEmail(email) {
    if (!communicationHealthState.smtpReady) {
        showError('Email service is not ready. Check Communication Health panel.');
        return;
    }
    const element = document.getElementById('modalEmail');
    if (!element) {
        showError('Modal not found: modalEmail');
        return;
    }
    bulkEmailRecipients = [];
    document.getElementById('emailTo').value = email;
    document.getElementById('emailTo').removeAttribute('readonly');
    document.getElementById('emailTo').setAttribute('data-bulk', '0');
    document.getElementById('emailSubject').value = '';
    document.getElementById('emailMessage').value = '';
    showModal('modalEmail');
}

function submitEmail() {
    const email = document.getElementById('emailTo').value;
    const subject = document.getElementById('emailSubject').value;
    const message = document.getElementById('emailMessage').value;
    const isBulkMode = document.getElementById('emailTo').getAttribute('data-bulk') === '1';
    
    if (!subject || !message || (!email && !isBulkMode)) {
        showError('Please fill in all fields');
        return;
    }

    const payload = isBulkMode ? {
        action: 'send_bulk_email',
        emails: bulkEmailRecipients,
        subject: subject,
        message: message
    } : {
        action: 'send_email',
        email: email,
        subject: subject,
        message: message
    };

    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                hideModal('modalEmail');
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error sending email');
        }
    });
}

function bulkSendEmail() {
    if (!communicationHealthState.smtpReady) {
        showError('Email service is not ready. Check Communication Health panel.');
        return;
    }
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }

    const emails = [];
    selected.forEach((id) => {
        const row = document.querySelector(`#tableRegistered tr[data-id="${id}"]`);
        if (!row) return;
        const cells = row.querySelectorAll('td');
        if (cells.length < 4) return;
        const em = (cells[3].textContent || '').trim();
        if (em && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            emails.push(em);
        }
    });

    const unique = Array.from(new Set(emails));
    if (unique.length === 0) {
        showError('No valid email addresses found in selected customers');
        return;
    }

    const element = document.getElementById('modalEmail');
    if (!element) {
        showError('Modal not found: modalEmail');
        return;
    }

    bulkEmailRecipients = unique;
    document.getElementById('emailTo').value = unique.join(', ');
    document.getElementById('emailTo').setAttribute('readonly', 'readonly');
    document.getElementById('emailTo').setAttribute('data-bulk', '1');
    document.getElementById('emailSubject').value = '';
    document.getElementById('emailMessage').value = '';
    showModal('modalEmail');
}

// ============================================================================
// SMS FUNCTIONS
// ============================================================================

function sendSMS(phone) {
    if (!communicationHealthState.smsReady) {
        showError('SMS service is not ready. Check Communication Health panel.');
        return;
    }
    const element = document.getElementById('modalSMS');
    if (!element) {
        showError('Modal not found: modalSMS');
        return;
    }

    bulkSmsRecipients = [];
    document.getElementById('smsTo').value = (phone || '').trim();
    document.getElementById('smsTo').removeAttribute('readonly');
    document.getElementById('smsTo').setAttribute('data-bulk', '0');
    document.getElementById('smsMessage').value = '';
    showModal('modalSMS');
}

function submitSMS() {
    const smsToInput = document.getElementById('smsTo');
    const message = (document.getElementById('smsMessage').value || '').trim();
    const isBulkMode = smsToInput.getAttribute('data-bulk') === '1';
    const toRaw = (smsToInput.value || '').trim();

    if (!message) {
        showError('Please enter SMS message');
        return;
    }

    const payload = isBulkMode ? {
        action: 'send_bulk_sms',
        phones: bulkSmsRecipients,
        message: message
    } : {
        action: 'send_sms',
        phone: toRaw,
        message: message
    };

    if (!isBulkMode && !toRaw) {
        showError('Please enter recipient phone number');
        return;
    }

    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                hideModal('modalSMS');
                showSuccess(response.message);
            } else {
                showError(response.message || 'Failed to send SMS');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error sending SMS');
        }
    });
}

function bulkSendSMS() {
    if (!communicationHealthState.smsReady) {
        showError('SMS service is not ready. Check Communication Health panel.');
        return;
    }
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }

    const phones = [];
    selected.forEach((id) => {
        const row = document.querySelector(`#tableRegistered tr[data-id="${id}"]`);
        if (!row) return;
        const cells = row.querySelectorAll('td');
        if (cells.length < 5) return;
        const ph = (cells[4].textContent || '').trim();
        if (ph) phones.push(ph);
    });

    const unique = Array.from(new Set(phones));
    if (unique.length === 0) {
        showError('No phone numbers found in selected customers');
        return;
    }

    const element = document.getElementById('modalSMS');
    if (!element) {
        showError('Modal not found: modalSMS');
        return;
    }

    bulkSmsRecipients = unique;
    document.getElementById('smsTo').value = unique.join(', ');
    document.getElementById('smsTo').setAttribute('readonly', 'readonly');
    document.getElementById('smsTo').setAttribute('data-bulk', '1');
    document.getElementById('smsMessage').value = '';
    showModal('modalSMS');
}

// ============================================================================
// VIEW FUNCTIONS
// ============================================================================

/**
 * View customer details - FIXED with safe modal handling
 */
function viewCustomer(type, identifier) {
    const element = document.getElementById('modalDetails');
    if (!element) {
        showError('Modal not found: modalDetails');
        return;
    }
    
    document.getElementById('detailsContent').innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
    showModal('modalDetails');
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'GET',
        data: {
            action: 'get_customer',
            type: type,
            id: identifier
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const customer = response.data;
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> ${escapeHtml(customer.name || customer.full_name || customer.customer_name || 'N/A')}</p>
                            <p><strong>Email:</strong> ${escapeHtml(customer.email || customer.customer_email || 'N/A')}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(customer.phone || customer.customer_phone || 'N/A')}</p>
                            <p><strong>Address:</strong> ${escapeHtml(customer.address || customer.shipping_address || 'N/A')}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Orders:</strong> ${customer.total_orders || 0}</p>
                            <p><strong>Total Spent:</strong> Ksh ${formatNumber(customer.total_spent || 0)}</p>
                            <p><strong>Avg Order:</strong> Ksh ${formatNumber((customer.total_spent || 0) / (customer.total_orders || 1))}</p>
                        </div>
                    </div>
                `;
                document.getElementById('detailsContent').innerHTML = html;
            } else {
                document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger">' + (response.message || 'No data available') + '</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger">Error loading details. Check console for details.</div>';
        }
    });
}

/**
 * View orders - FIXED with safe modal handling
 */
function viewOrders(customerId) {
    const element = document.getElementById('modalOrders');
    if (!element) {
        showError('Modal not found: modalOrders');
        return;
    }
    
    document.getElementById('ordersContent').innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
    showModal('modalOrders');
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'GET',
        data: {
            action: 'get_orders',
            customer_id: customerId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                let html = '<table class="table table-sm"><thead><tr><th>Order</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                response.data.forEach(order => {
                    html += `
                        <tr>
                            <td>${escapeHtml(order.order_number)}</td>
                            <td>Ksh ${formatNumber(order.total_amount)}</td>
                            <td><span class="badge bg-secondary">${escapeHtml(order.order_status)}</span></td>
                            <td>${new Date(order.created_at).toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                html += '</tbody></table>';
                document.getElementById('ordersContent').innerHTML = html;
            } else {
                document.getElementById('ordersContent').innerHTML = '<div class="alert alert-info">No orders found</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            document.getElementById('ordersContent').innerHTML = '<div class="alert alert-danger">Error loading orders. Check console for details.</div>';
        }
    });
}

// ============================================================================
// BULK ACTIONS
// ============================================================================

/**
 * Toggle select all
 */
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.customer-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

/**
 * Get selected customers
 */
function getSelectedCustomers() {
    return Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
}

/**
 * Bulk approve
 */
function bulkApprove() {
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }
    
    if (!confirm(`Approve ${selected.length} customers?`)) return;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'bulk_action',
            operation: 'approve',
            ids: selected
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error processing bulk action. Check console.');
        }
    });
}

/**
 * Bulk deactivate
 */
function bulkDeactivate() {
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }
    
    if (!confirm(`Deactivate ${selected.length} customers?`)) return;
    
    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'bulk_action',
            operation: 'deactivate',
            ids: selected
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error processing bulk action. Check console.');
        }
    });
}

/**
 * Bulk activate
 */
function bulkActivate() {
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }

    if (!confirm(`Activate ${selected.length} customers?`)) return;

    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'bulk_action',
            operation: 'activate',
            ids: selected
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error processing bulk action. Check console.');
        }
    });
}

/**
 * Bulk delete
 */
function bulkDelete() {
    const selected = getSelectedCustomers();
    if (selected.length === 0) {
        showError('Please select customers');
        return;
    }

    if (!confirm(`Delete ${selected.length} customers? This action cannot be undone.`)) return;

    $.ajax({
        url: AJAX_URL_VAR,
        type: 'POST',
        data: {
            action: 'bulk_action',
            operation: 'delete',
            ids: selected
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error processing bulk delete. Check console.');
        }
    });
}

// ============================================================================
// SEARCH & FILTER
// ============================================================================

/**
 * Search in tables
 */
document.addEventListener('DOMContentLoaded', function() {
    const randomSearch = document.getElementById('searchRandom');
    if (randomSearch) {
        randomSearch.addEventListener('keyup', function() {
            filterTable('tableRandom', this.value);
        });
    }
    
    const registeredSearch = document.getElementById('searchRegistered');
    if (registeredSearch) {
        registeredSearch.addEventListener('keyup', function() {
            filterTable('tableRegistered', this.value);
        });
    }
    
    // Filter by status
    const filterRandom = document.getElementById('filterRandom');
    if (filterRandom) {
        filterRandom.addEventListener('change', function() {
            filterTableByStatus('tableRandom', this.value);
        });
    }
    
    const filterRegistered = document.getElementById('filterRegistered');
    if (filterRegistered) {
        filterRegistered.addEventListener('change', function() {
            filterTableByStatus('tableRegistered', this.value, 'registered');
        });
    }

    initTableSorting('tableRegistered');
    loadCommunicationHealth(false);
});

/**
 * Filter table
 */
function filterTable(tableId, query) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    }
}

/**
 * Filter by status
 */
function filterTableByStatus(tableId, status, type = 'random') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (let row of rows) {
        const statusAttr = type === 'random' ? row.getAttribute('data-status') : row.getAttribute('data-approval');
        if (!status || statusAttr === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

/**
 * Initialize table sorting by clicking header cells that contain data-sort-col.
 */
function initTableSorting(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const headers = table.querySelectorAll('thead th[data-sort-col]');
    headers.forEach((th) => {
        th.addEventListener('click', function() {
            const colIndex = parseInt(this.getAttribute('data-sort-col'), 10);
            const type = this.getAttribute('data-sort-type') || 'text';
            const currentDir = this.getAttribute('data-sort-dir') || 'none';
            const nextDir = currentDir === 'asc' ? 'desc' : 'asc';

            headers.forEach((h) => {
                h.removeAttribute('data-sort-dir');
                const indicator = h.querySelector('.sort-indicator');
                if (indicator) indicator.textContent = '';
            });

            this.setAttribute('data-sort-dir', nextDir);
            const indicator = this.querySelector('.sort-indicator');
            if (indicator) indicator.textContent = nextDir === 'asc' ? '▲' : '▼';

            sortTableByColumn(table, colIndex, type, nextDir);
        });
    });
}

function sortTableByColumn(table, colIndex, type, dir) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        const av = getSortValue(a, colIndex, type);
        const bv = getSortValue(b, colIndex, type);
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });

    rows.forEach((row) => tbody.appendChild(row));
}

function getSortValue(row, colIndex, type) {
    const cells = row.querySelectorAll('td');
    if (!cells[colIndex]) return '';
    const raw = (cells[colIndex].textContent || '').trim();

    if (type === 'number') {
        const num = parseFloat(raw.replace(/[^0-9.-]/g, ''));
        return isNaN(num) ? 0 : num;
    }

    if (type === 'date') {
        const ts = Date.parse(raw);
        return isNaN(ts) ? 0 : ts;
    }

    return raw.toLowerCase();
}

/**
 * Live SMTP/SMS health check panel.
 */
function loadCommunicationHealth(showToast) {
    const panel = document.getElementById('commHealthPanel');
    if (!panel) return;

    $.ajax({
        url: AJAX_URL_VAR,
        type: 'GET',
        data: { action: 'health_check' },
        dataType: 'json',
        success: function(response) {
            if (!response.success || !response.data) {
                if (showToast) showError('Health check failed');
                return;
            }

            renderHealthBlock(
                'smtpHealthBadge',
                'smtpHealthDetails',
                response.data.smtp
            );
            renderHealthBlock(
                'smsHealthBadge',
                'smsHealthDetails',
                response.data.sms
            );

            communicationHealthState.smtpReady = !!(response.data.smtp && response.data.smtp.ready);
            communicationHealthState.smsReady = !!(response.data.sms && response.data.sms.ready);
            applyCommunicationActionStates();

            if (showToast) {
                showSuccess('Health check refreshed');
            }
        },
        error: function(xhr, status, error) {
            console.error('Health check AJAX error:', error);
            communicationHealthState.smtpReady = false;
            communicationHealthState.smsReady = false;
            applyCommunicationActionStates();
            if (showToast) showError('Could not refresh health check');
        }
    });
}

function renderHealthBlock(badgeId, detailsId, block) {
    const badge = document.getElementById(badgeId);
    const details = document.getElementById(detailsId);
    if (!badge || !details || !block) return;

    badge.className = 'badge ' + (block.ready ? 'bg-success' : 'bg-danger');
    badge.textContent = block.ready ? 'Ready' : 'Not Ready';

    const rows = (block.details || []).map((d) => {
        const icon = d.ok ? 'text-success' : 'text-danger';
        const mark = d.ok ? 'OK' : 'Missing';
        return `<div><span class="${icon}">${mark}</span> - ${escapeHtml(d.label || '')}: ${escapeHtml(d.value || '')}</div>`;
    });
    details.innerHTML = rows.join('');
}

function applyCommunicationActionStates() {
    setActionGroupEnabled('.btn-email-action', communicationHealthState.smtpReady, 'Email service not ready');
    setActionGroupEnabled('.btn-sms-action', communicationHealthState.smsReady, 'SMS service not ready');
    toggleServiceUIState(
        communicationHealthState.smtpReady,
        'smtpUnavailableBadge',
        'emailModalUnavailable',
        '.btn-email-action'
    );
    toggleServiceUIState(
        communicationHealthState.smsReady,
        'smsUnavailableBadge',
        'smsModalUnavailable',
        '.btn-sms-action'
    );
}

function setActionGroupEnabled(selector, enabled, disabledTitle) {
    const buttons = document.querySelectorAll(selector);
    buttons.forEach((btn) => {
        if (!(btn instanceof HTMLButtonElement)) return;
        btn.disabled = !enabled;
        if (!enabled) {
            btn.setAttribute('title', disabledTitle);
            btn.setAttribute('data-bs-original-title', disabledTitle);
        } else {
            const currentTitle = btn.getAttribute('title') || '';
            if (currentTitle === disabledTitle) {
                btn.setAttribute('title', '');
            }
            btn.removeAttribute('data-bs-original-title');
        }
    });
}

function toggleServiceUIState(isReady, pageBadgeId, modalBadgeId, buttonSelector) {
    const pageBadge = document.getElementById(pageBadgeId);
    const modalBadge = document.getElementById(modalBadgeId);
    if (pageBadge) pageBadge.classList.toggle('d-none', isReady);
    if (modalBadge) modalBadge.classList.toggle('d-none', isReady);

    const buttons = document.querySelectorAll(buttonSelector);
    buttons.forEach((btn) => {
        if (!(btn instanceof HTMLElement)) return;
        btn.classList.toggle('service-muted', !isReady);
    });
}

// ============================================================================
// EXPORT
// ============================================================================

/**
 * Export to CSV
 */
function exportCSV() {
    alert('CSV export feature - implement based on your needs');
    // You can implement actual CSV export here
}

// ============================================================================
// UTILITIES (Already defined at top of file)
// ============================================================================

// showAlert, showSuccess, showError, escapeHtml, formatNumber
// are all defined earlier in this file
