// ========== GLOBAL FUNCTIONS ==========

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    // Remove existing toasts
    document.querySelectorAll('.toast-container').forEach(container => container.remove());
    
    // Create toast container
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
    toastContainer.style.zIndex = '9999';
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    
    toastContainer.appendChild(toast);
    document.body.appendChild(toastContainer);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    // Remove after hiding
    toast.addEventListener('hidden.bs.toast', function () {
        toastContainer.remove();
    });
}

/**
 * Get status color
 */
function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'processing': 'info',
        'paid': 'success',
        'refunded': 'danger'
    };
    return colors[status] || 'secondary';
}

/**
 * Debug helper function
 */
function debugUserAction(action, userId) {
    console.log(`User action called: ${action} for user ID: ${userId}`);
    return true;
}

// ========== DASHBOARD FUNCTIONS ==========

/**
 * Initialize sales chart
 */
let salesChart = null;
const salesData = {
    '7d': {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        data: [1250, 1890, 2100, 1780, 2450, 1950, 2300]
    },
    '30d': {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        data: [8200, 9500, 7800, 10200]
    },
    '90d': {
        labels: ['Month 1', 'Month 2', 'Month 3'],
        data: [28500, 31200, 29500]
    }
};

function initSalesChart(period = '7d') {
    const ctx = document.getElementById('salesChart')?.getContext('2d');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (salesChart) {
        salesChart.destroy();
    }
    
    // Create new chart
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData[period].labels,
            datasets: [{
                label: 'Sales ($)',
                data: salesData[period].data,
                backgroundColor: 'rgba(106, 17, 203, 0.1)',
                borderColor: 'rgba(106, 17, 203, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgba(106, 17, 203, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 14 },
                    bodyFont: { size: 13 },
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return `Sales: $${context.parsed.y.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
}

/**
 * Activity feed for dashboard
 */
const activityTypes = [
    { type: 'order', icon: 'shopping-cart', color: 'primary', text: 'New order #ORD-{id} placed' },
    { type: 'customer', icon: 'user-check', color: 'success', text: 'New customer registered' },
    { type: 'stock', icon: 'box', color: 'warning', text: '"{product}" stock running low' },
    { type: 'payment', icon: 'credit-card', color: 'info', text: 'Payment received for order #{id}' },
    { type: 'shipping', icon: 'truck', color: 'secondary', text: 'Order #{id} shipped to customer' },
    { type: 'review', icon: 'star', color: 'warning', text: 'New 5-star review received' }
];

const products = ['Herbal Tea', 'Coffee Blend', 'Green Tea', 'Chai Mix', 'Matcha Powder', 'Herbal Infusion'];

function getTimeAgo() {
    const times = [
        'just now', 
        '1 minute ago', 
        '2 minutes ago', 
        '5 minutes ago', 
        '10 minutes ago', 
        '15 minutes ago', 
        '30 minutes ago', 
        '1 hour ago', 
        '2 hours ago'
    ];
    return times[Math.floor(Math.random() * times.length)];
}

function generateActivity() {
    const activityType = activityTypes[Math.floor(Math.random() * activityTypes.length)];
    const orderId = 'ORD-' + (20240000 + Math.floor(Math.random() * 100));
    const product = products[Math.floor(Math.random() * products.length)];
    
    let text = activityType.text
        .replace('{id}', orderId.slice(-4))
        .replace('{product}', product);
    
    const activityItem = document.createElement('div');
    activityItem.className = 'activity-item new';
    activityItem.innerHTML = `
        <div class="activity-icon">
            <i class="fas fa-${activityType.icon} text-${activityType.color}"></i>
        </div>
        <div class="activity-content">
            <div class="activity-text">${text}</div>
            <div class="activity-time">${getTimeAgo()}</div>
        </div>
    `;
    
    return activityItem;
}

function addActivity() {
    const activityList = document.getElementById('activity-list');
    if (!activityList) return;
    
    const newActivity = generateActivity();
    activityList.insertBefore(newActivity, activityList.firstChild);
    
    if (activityList.children.length > 10) {
        activityList.removeChild(activityList.lastChild);
    }
    
    setTimeout(() => {
        newActivity.classList.remove('new');
    }, 5000);
    
    const pulseDot = document.querySelector('.pulse-dot');
    if (pulseDot) {
        pulseDot.style.animation = 'none';
        setTimeout(() => {
            pulseDot.style.animation = 'pulse 1.5s infinite';
        }, 10);
    }
}

function startActivityUpdates() {
    addActivity();
    setInterval(() => {
        if (Math.random() > 0.3) {
            addActivity();
        }
    }, 5000 + Math.random() * 10000);
}

/**
 * Animate counter values
 */
function animateCounter(element, finalValue, duration = 2000) {
    if (!element) return;
    
    const start = 0;
    const increment = finalValue / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= finalValue) {
            element.textContent = finalValue.toLocaleString();
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current).toLocaleString();
        }
    }, 16);
}

/**
 * Quick restock function
 */
function quickRestock(productId) {
    if (confirm('Would you like to restock this product?')) {
        // In real implementation, this would open a restock modal
        console.log('Opening restock form for product #' + productId);
        showToast('Opening restock form for product #' + productId, 'info');
    }
}

/**
 * Generate daily report
 */
function generateDailyReport() {
    const btn = event?.target?.closest('button');
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('Daily report generated successfully!', 'success');
        }, 2000);
    }
}

/**
 * Refresh recent sales
 */
function refreshRecentSales() {
    const btn = event?.target?.closest('button');
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            showToast('Recent sales refreshed!', 'success');
        }, 1000);
    }
}

// ========== ORDERS PAGE FUNCTIONS ==========

/**
 * Apply filters for orders page
 */
function applyFilters() {
    const search = document.getElementById('orderSearchInput')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const startDate = document.getElementById('startDate')?.value || '';
    const endDate = document.getElementById('endDate')?.value || '';
    
    let url = '?page=orders';
    
    if (search) url += '&search=' + encodeURIComponent(search);
    if (status) url += '&status=' + encodeURIComponent(status);
    if (startDate) url += '&start_date=' + encodeURIComponent(startDate);
    if (endDate) url += '&end_date=' + encodeURIComponent(endDate);
    
    window.location.href = url;
}

function resetFilters() {
    window.location.href = '?page=orders';
}

function clearSearch() {
    const searchInput = document.getElementById('orderSearchInput');
    if (searchInput) {
        searchInput.value = '';
        applyFilters();
    }
}

/**
 * Order management functions
 */
function viewOrderDetails(orderId) {
    console.log('Viewing order details for: ' + orderId);
    // In real implementation: window.location.href = 'order_details.php?id=' + orderId;
}

function updateOrderStatus(orderId, status) {
    if (confirm('Update order ' + orderId + ' status to ' + status + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=orders';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'update_order_status';
        form.appendChild(actionInput);
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'sale_code';
        orderIdInput.value = orderId;
        form.appendChild(orderIdInput);
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'payment_status';
        statusInput.value = status;
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteOrder(orderId, customerName) {
    if (confirm('Are you sure you want to delete order ' + orderId + ' for customer ' + customerName + '?\n\nThis action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=orders';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_order';
        form.appendChild(actionInput);
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'sale_code';
        orderIdInput.value = orderId;
        form.appendChild(orderIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function createNewOrder() {
    window.location.href = '?page=orders&action=create';
}

function printOrder(orderId) {
    window.open('print_invoice.php?id=' + orderId, '_blank');
}

function resendNotification(orderId) {
    if (confirm('Resend notification for order ' + orderId + '?')) {
        fetch('resend_notification.php?id=' + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Notification resent successfully!', 'success');
                } else {
                    showToast('Failed to resend notification: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
    }
}

/**
 * Bulk actions for orders
 */
function toggleAllSelection(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    const bulkBar = document.getElementById('bulkSelectionBar');
    
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    
    if (checkbox.checked && checkboxes.length > 0) {
        bulkBar.style.display = 'block';
        updateSelectedCount();
    } else {
        bulkBar.style.display = 'none';
    }
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('.order-checkbox:checked').length;
    const countElement = document.getElementById('selectedCount');
    const bulkBar = document.getElementById('bulkSelectionBar');
    
    if (countElement) {
        countElement.textContent = selected;
    }
    
    if (bulkBar) {
        if (selected > 0) {
            bulkBar.style.display = 'block';
        } else {
            bulkBar.style.display = 'none';
        }
    }
}

function selectAllOrders() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = true;
    });
    updateSelectedCount();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    const selectAll = document.getElementById('selectAllCheckbox');
    
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    if (selectAll) {
        selectAll.checked = false;
    }
    
    updateSelectedCount();
}

function bulkUpdateStatus(status) {
    const selected = document.querySelectorAll('.order-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one order.');
        return;
    }
    
    const orderIds = Array.from(selected).map(cb => cb.value);
    if (confirm('Update ' + selected.length + ' order(s) to ' + status + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=orders';
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_update_status';
        form.appendChild(actionInput);
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = status;
        form.appendChild(statusInput);
        
        orderIds.forEach(orderId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order_ids[]';
            input.value = orderId;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteSelectedOrders() {
    const selected = document.querySelectorAll('.order-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one order to delete.');
        return;
    }
    
    if (confirm('Are you sure you want to delete ' + selected.length + ' selected order(s)?\n\nThis action cannot be undone.')) {
        const orderIds = Array.from(selected).map(cb => cb.value);
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=orders';        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete_orders';
        form.appendChild(actionInput);
        
        orderIds.forEach(orderId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order_ids[]';
            input.value = orderId;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

function exportOrders(format) {
    const search = document.getElementById('orderSearchInput')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const startDate = document.getElementById('startDate')?.value || '';
    const endDate = document.getElementById('endDate')?.value || '';
    
    const exportBtn = event?.target?.closest('.dropdown-item');
    if (exportBtn) {
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
        
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            showToast('Export completed successfully!', 'success');
            
            // In real implementation:
            // window.open('export_orders.php?format=' + format + '&search=' + search + '&status=' + status + '&start_date=' + startDate + '&end_date=' + endDate, '_blank');
        }, 1500);
    }
}

function refreshOrders() {
    const btn = document.getElementById('refreshBtn');
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

function changeItemsPerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

// ========== CUSTOMERS PAGE FUNCTIONS ==========

/**
 * Customer Growth Chart
 */
function initCustomerGrowthChart() {
    const ctx = document.getElementById('customerGrowthChart')?.getContext('2d');
    if (!ctx) return;
    
    // This would come from PHP in real implementation
    const growthData = window.customerGrowthData || [];
    
    const labels = growthData.map(item => item.month).reverse();
    const data = growthData.map(item => parseInt(item.new_customers)).reverse();
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'New Customers',
                data: data,
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
}

/**
 * Customer Filter Functions
 */
function applyCustomerFilters() {
    const search = document.getElementById('customerSearchInput')?.value || '';
    const activity = document.getElementById('activityFilter')?.value || '';
    const loyalty = document.getElementById('loyaltyFilter')?.value || '';
    
    let url = '?page=customers';
    
    if (search) url += '&search=' + encodeURIComponent(search);
    if (activity) url += '&date=' + encodeURIComponent(activity);
    if (loyalty) url += '&loyalty=' + encodeURIComponent(loyalty);
    
    window.location.href = url;
}

function resetCustomerFilters() {
    window.location.href = '?page=customers';
}

function clearCustomerSearch() {
    const searchInput = document.getElementById('customerSearchInput');
    if (searchInput) {
        searchInput.value = '';
        applyCustomerFilters();
    }
}

/**
 * Customer Selection Functions
 */
function toggleAllCustomerSelection(checkbox) {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCustomerCount();
}

function updateSelectedCustomerCount() {
    const selected = document.querySelectorAll('.customer-checkbox:checked').length;
    if (selected > 0) {
        showToast(`${selected} customer(s) selected`, 'info');
    }
}

/**
 * Customer Action Functions
 */
function viewCustomerDetails(customerEmail) {
    console.log('Viewing details for:', decodeURIComponent(customerEmail));
    // window.location.href = 'customer_details.php?email=' + customerEmail;
}

function sendCustomerEmail(email, name) {
    const subject = encodeURIComponent('Update from Herbal Remedies System');
    const body = encodeURIComponent(`Dear ${name},\n\nWe hope this email finds you well!\n\nBest regards,\nHerbal Remedies System Team`);
    window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
}

function sendCustomerSMS(phone, name) {
    const message = encodeURIComponent(`Hi ${name}, this is a message from Herbal Remedies System`);
    window.open(`sms:${phone}?body=${message}`, '_blank');
}

function editCustomer(customerEmail) {
    console.log('Editing customer:', decodeURIComponent(customerEmail));
    // openEditCustomerModal(decodeURIComponent(customerEmail));
}

function createOrderForCustomer(email, name) {
    console.log('Creating order for:', name, email);
    // window.location.href = 'create_order.php?customer_email=' + encodeURIComponent(email) + '&customer_name=' + encodeURIComponent(name);
}

function createNewCustomer() {
    console.log('Opening new customer form');
    // openAddCustomerModal();
}

function viewCustomerHistory(customerEmail) {
    console.log('Viewing history for:', decodeURIComponent(customerEmail));
    // Implement customer history view
}

/**
 * Bulk Actions for Customers
 */
function bulkSendEmail() {
    const selected = document.querySelectorAll('.customer-checkbox:checked');
    if (selected.length === 0) {
        showToast('Please select at least one customer.', 'warning');
        return;
    }
    
    const emails = Array.from(selected).map(cb => cb.value).join(',');
    const subject = encodeURIComponent('Special Offer from Herbal Remedies System');
    const body = encodeURIComponent('Dear valued customer,\n\nWe have a special offer just for you!\n\nBest regards,\nHerbal Remedies System Team');
    
    window.location.href = `mailto:?bcc=${emails}&subject=${subject}&body=${body}`;
}

function bulkSendSMS() {
    showToast('Bulk SMS feature requires integration with SMS service.', 'info');
}

function exportSelectedCustomers() {
    const selected = document.querySelectorAll('.customer-checkbox:checked');
    if (selected.length === 0) {
        showToast('Please select at least one customer to export.', 'warning');
        return;
    }
    
    showToast(`Preparing export for ${selected.length} customer(s)...`, 'info');
    // exportCustomersCSV(emails);
}

function exportCustomers(format) {
    const search = document.getElementById('customerSearchInput')?.value || '';
    const activity = document.getElementById('activityFilter')?.value || '';
    
    const exportBtn = event?.target?.closest('.dropdown-item');
    if (exportBtn) {
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
        
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            showToast(`Customers exported as ${format.toUpperCase()} successfully!`, 'success');
            
            // In real implementation:
            // window.open(`export_customers.php?format=${format}&search=${search}&date=${date}`, '_blank');
        }, 1500);
    }
}

function exportCustomerReport() {
    showToast('Generating customer report...', 'info');
}

function viewCustomerAnalytics() {
    showToast('Opening customer analytics dashboard...', 'info');
}

function createCustomerSegment() {
    showToast('Creating customer segment...', 'info');
}

function sendBulkEmail() {
    showToast('Opening bulk email composer...', 'info');
}

function refreshCustomers() {
    const btn = document.getElementById('refreshCustomersBtn');
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

function changeCustomerItemsPerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

// ========== USER MANAGEMENT FUNCTIONS ==========

/**
 * User Modal Functions
 */
function showAddUserModal() {
    try {
        const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
        modal.show();
    } catch (e) {
        console.error('Error showing add user modal:', e);
        alert('Error opening add user form');
    }
}

function showEditUserModal(userId, name, email, username, role, phone) {
    try {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserName').value = name || '';
        document.getElementById('editUserEmail').value = email;
        document.getElementById('editUserUsername').value = username;
        document.getElementById('editUserRole').value = role;
        document.getElementById('editUserPhone').value = phone || '';
        
        const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    } catch (e) {
        console.error('Error showing edit user modal:', e);
        alert('Error opening edit user form');
    }
}

function showChangePasswordModal(userId, userName) {
    try {
        document.getElementById('passwordUserId').value = userId;
        document.getElementById('passwordUserName').textContent = userName;
        
        const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
        modal.show();
    } catch (e) {
        console.error('Error showing change password modal:', e);
        alert('Error opening password change form');
    }
}

function showApproveUserModal(userId, userName) {
    try {
        document.getElementById('approveUserId').value = userId;
        document.getElementById('approveUserName').textContent = userName;
        
        const modal = new bootstrap.Modal(document.getElementById('approveUserModal'));
        modal.show();
    } catch (e) {
        console.error('Error showing approve user modal:', e);
        alert('Error opening approval form');
    }
}

/**
 * User Action Functions
 */
function deleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"?\n\nThis action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=user_management';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        form.appendChild(actionInput);
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectUser(userId, userName) {
    if (confirm(`Are you sure you want to reject "${userName}"'s registration request?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=user_management';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reject_user';
        form.appendChild(actionInput);
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function unlockUser(userId, userName) {
    if (confirm(`Are you sure you want to unlock "${userName}"'s account?\n\nThis will reset their failed login attempts to 0.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname + '?page=user_management';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'unlock_user';
        form.appendChild(actionInput);
        
        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function exportUsers() {
    const searchQuery = document.getElementById('searchInput')?.value || '';
    const roleFilter = document.getElementById('roleFilter')?.value || 'all';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    
    const exportBtn = event?.target;
    if (exportBtn) {
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
        exportBtn.disabled = true;
        
        const downloadLink = document.createElement('a');
        downloadLink.href = `?ajax=export_users&search=${encodeURIComponent(searchQuery)}&role=${encodeURIComponent(roleFilter)}&status=${encodeURIComponent(statusFilter)}`;
        downloadLink.download = `users_export_${new Date().toISOString().slice(0, 10)}.csv`;
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        
        downloadLink.click();
        
        setTimeout(() => {
            document.body.removeChild(downloadLink);
            showToast('Export completed successfully!', 'success');
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 100);
    }
}

// ========== INVENTORY FUNCTIONS ==========

function editInventoryItem(itemId, itemName, stock, price) {
    document.getElementById('inventoryItemId').value = itemId;
    document.getElementById('inventoryProductName').textContent = itemName;
    document.getElementById('inventoryStock').value = stock;
    document.getElementById('inventoryPrice').value = price;
    
    const modal = new bootstrap.Modal(document.getElementById('editInventoryModal'));
    modal.show();
}

// ========== COMMON FUNCTIONS ==========

function editOrderStatus(orderId) {
    document.getElementById('editOrderId').value = orderId;
    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

function viewOrder(orderId) {
    console.log('Viewing order: ' + orderId);
    // Implement view order functionality
}

// ========== DOM READY FUNCTIONS ==========

// ============================================
// ADMIN DASHBOARD MAIN JAVASCRIPT
// ============================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Admin dashboard initialized');
    
    // ============================================
    // SECTION 1: INITIALIZE ALL EVENT LISTENERS
    // ============================================
    initializeEventListeners();
    
    // ============================================
    // SECTION 2: DASHBOARD INITIALIZATION
    // ============================================
    initializeDashboard();
    
    // ============================================
    // SECTION 3: ORDERS PAGE SPECIFIC
    // ============================================
    initializeOrdersPage();
    
    // ============================================
    // SECTION 4: CUSTOMERS PAGE SPECIFIC
    // ============================================
    initializeCustomersPage();
    
    // ============================================
    // SECTION 5: FORM VALIDATIONS
    // ============================================
    initializeFormValidations();
    
    // ============================================
    // SECTION 6: FIX PAGE SCROLLING ISSUES
    // ============================================
    fixPageScrolling();
});

// ============================================
// FUNCTION: Initialize all event listeners
// ============================================
function initializeEventListeners() {
    console.log('📋 Initializing event listeners...');
    
    // Image preview listener
    const imageInput = document.getElementById('remedy_image');
    if (imageInput) {
        imageInput.addEventListener('change', handleImagePreview);
        console.log('  ✓ Image preview listener added');
    } else {
        console.log('  ⚠ Image input not found (may be in modal)');
    }
    
    // Select all checkboxes listener
    const selectAllCheckbox = document.getElementById('selectAllCheckboxes');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleAllCheckboxes);
        console.log('  ✓ Select all checkbox listener added');
    } else {
        console.log('  ⚠ Select all checkbox not found');
    }
    
    // Track order button listener
    const trackBtn = document.getElementById('track-btn');
    if (trackBtn) {
        trackBtn.addEventListener('click', trackOrder);
        console.log('  ✓ Track button listener added');
    } else {
        console.log('  ⚠ Track button not found');
    }
}

// ============================================
// FUNCTION: Initialize dashboard components
// ============================================
function initializeDashboard() {
    console.log('📊 Initializing dashboard...');
    
    // Live Clock
    startLiveClock();
    
    // Animate stat cards on scroll
    animateStatCards();
    
    // Initialize tooltips
    initTooltips();
    
    // Auto-hide notifications
    autoHideNotifications();
    
    // Sales chart
    initSalesChartIfExists();
    
    // Activity feed
    initActivityFeed();
    
    // Animate dashboard stats
    animateDashboardStats();
    
    // Weather widget (simulated)
    initWeatherWidget();
}

// ============================================
// FUNCTION: Initialize orders page
// ============================================
function initializeOrdersPage() {
    // Auto-apply filters when Enter is pressed in search
    const orderSearchInput = document.getElementById('orderSearchInput');
    if (orderSearchInput) {
        orderSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
    
    // Initialize date pickers with today as default for end date
    const endDate = document.getElementById('endDate');
    if (endDate && !endDate.value) {
        endDate.valueAsDate = new Date();
    }
    
    // Listen for checkbox changes for bulk selection
    const orderCheckboxes = document.querySelectorAll('.order-checkbox');
    orderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
}

// ============================================
// FUNCTION: Initialize customers page
// ============================================
function initializeCustomersPage() {
    // Auto-apply filters when Enter is pressed in customer search
    const customerSearchInput = document.getElementById('customerSearchInput');
    if (customerSearchInput) {
        customerSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyCustomerFilters();
            }
        });
    }
    
    // Customer growth chart
    const customerGrowthChartElement = document.getElementById('customerGrowthChart');
    if (customerGrowthChartElement) {
        initCustomerGrowthChart();
    }
}

// ============================================
// FUNCTION: Initialize form validations
// ============================================
function initializeFormValidations() {
    // Add user form validation
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
            }
        });
    }
    
    // Change password form validation
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
}

// ============================================
// FUNCTION: Fix page scrolling issues
// ============================================
function fixPageScrolling() {
    // Remove stuck Bootstrap backdrops
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    
    // Restore body scrolling & clicking
    document.body.classList.remove('modal-open');
    document.body.style.overflow = 'auto';
    document.body.style.pointerEvents = 'auto';
    
    // Safety net: if any modal opens and breaks
    document.addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.style.overflow = 'auto';
    });
    
    // Debug user management
    console.log('🔍 Debug: Checking user action buttons...');
    document.querySelectorAll('[onclick*="deleteUser"], [onclick*="editUser"], [onclick*="unlockUser"]').forEach(btn => {
        console.log('  Found user action button:', btn.outerHTML);
    });
}

// ============================================
// HELPER FUNCTIONS
// ============================================

// Live Clock
function startLiveClock() {
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.textContent = timeString;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
}

// Animate stat cards on scroll
function animateStatCards() {
    const animatedElements = document.querySelectorAll('.animated');
    if (animatedElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const delay = entry.target.dataset.delay || 0;
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                    }, delay);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        animatedElements.forEach(el => observer.observe(el));
    }
}

// Initialize Bootstrap tooltips
function initTooltips() {
    if (!window.bootstrap || !window.bootstrap.Tooltip) {
        return;
    }
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
    });
}

// Auto-hide notifications
function autoHideNotifications() {
    setTimeout(function() {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        });
    }, 5000);
}

// Initialize sales chart if element exists
function initSalesChartIfExists() {
    const salesChartElement = document.getElementById('salesChart');
    if (salesChartElement) {
        initSalesChart();
        
        // Chart period switching
        document.querySelectorAll('.chart-controls button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.chart-controls button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const period = this.getAttribute('data-period');
                initSalesChart(period);
                
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
        
        // Auto-refresh chart data (every 2 minutes)
        setInterval(() => {
            if (typeof salesData !== 'undefined') {
                Object.keys(salesData).forEach(period => {
                    salesData[period].data = salesData[period].data.map(value => {
                        const variation = value * 0.05 * (Math.random() > 0.5 ? 1 : -1);
                        return Math.max(1000, Math.round(value + variation));
                    });
                });
                
                if (typeof salesChart !== 'undefined' && salesChart) {
                    const activeButton = document.querySelector('.chart-controls button.active');
                    if (activeButton) {
                        const activePeriod = activeButton.getAttribute('data-period');
                        salesChart.data.datasets[0].data = salesData[activePeriod].data;
                        salesChart.update('none');
                    }
                }
            }
        }, 120000);
    }
}

// Initialize activity feed
function initActivityFeed() {
    if (document.getElementById('activity-list')) {
        startActivityUpdates();
        
        // Click to refresh activity feed
        const activityHeader = document.querySelector('.activity-header');
        if (activityHeader) {
            activityHeader.addEventListener('click', function(e) {
                if (e.target.closest('.activity-indicator')) {
                    addActivity();
                    
                    const indicator = e.target.closest('.activity-indicator');
                    indicator.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        indicator.style.transform = 'scale(1)';
                    }, 300);
                }
            });
        }
    }
}

// Animate dashboard stats counters
function animateDashboardStats() {
    setTimeout(() => {
        const totalSalesElement = document.getElementById('total-sales');
        const pendingOrdersElement = document.getElementById('pending-orders');
        const totalProductsElement = document.getElementById('total-products');
        const lowStockElement = document.getElementById('low-stock');
        
        if (totalSalesElement) animateCounter(totalSalesElement, parseInt(totalSalesElement.textContent.replace(/,/g, '')) || 0);
        if (pendingOrdersElement) animateCounter(pendingOrdersElement, parseInt(pendingOrdersElement.textContent.replace(/,/g, '')) || 0);
        if (totalProductsElement) animateCounter(totalProductsElement, parseInt(totalProductsElement.textContent.replace(/,/g, '')) || 0);
        if (lowStockElement) animateCounter(lowStockElement, parseInt(lowStockElement.textContent.replace(/,/g, '')) || 0);
    }, 1000);
}

// Counter animation helper
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target.toLocaleString();
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current).toLocaleString();
        }
    }, 20);
}

// Initialize weather widget (simulated)
function initWeatherWidget() {
    setTimeout(() => {
        const weatherWidget = document.getElementById('weather-widget');
        if (weatherWidget) {
            weatherWidget.innerHTML = `
                <div class="weather-content">
                    <div class="weather-main">
                        <i class="fas fa-sun fa-2x"></i>
                        <div class="weather-temp">24°C</div>
                    </div>
                    <div class="weather-info">
                        <div class="weather-location">Nairobi, Kenya</div>
                        <div class="weather-desc">Sunny</div>
                    </div>
                </div>
            `;
        }
    }, 1500);
}

// ============================================
// EVENT HANDLER FUNCTIONS
// ============================================

// Handle image preview
function handleImagePreview(e) {
    const preview = document.getElementById('image_preview');
    const previewImg = preview ? preview.querySelector('img') : null;
    
    if (this.files && this.files[0] && preview && previewImg) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(this.files[0]);
    } else if (preview) {
        preview.style.display = 'none';
    }
}

// Toggle all checkboxes
function toggleAllCheckboxes(source) {
    const checkboxes = document.getElementsByClassName('remedy-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    if (typeof togglePrintButton === 'function') {
        togglePrintButton();
    }
}

// Track order function
function trackOrder() {
    const orderNumber = document.getElementById('order-number')?.value;
    if (!orderNumber) {
        alert('Please enter an order number');
        return;
    }
    window.location.href = `track-order.php?number=${orderNumber}`;
}

// Update selected count for bulk actions
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const countElement = document.getElementById('selected-count');
    if (countElement) {
        countElement.textContent = checkboxes.length;
    }
}

// Apply filters (placeholder)
function applyFilters() {
    console.log('Applying order filters...');
    // Add your filter logic here
}

// Apply customer filters (placeholder)
function applyCustomerFilters() {
    console.log('Applying customer filters...');
    // Add your filter logic here
}

// Initialize sales chart (placeholder - implement as needed)
function initSalesChart(period = 'weekly') {
    console.log(`Initializing sales chart with period: ${period}`);
    // Add your chart initialization logic here
}

// Initialize customer growth chart (placeholder)
function initCustomerGrowthChart() {
    console.log('Initializing customer growth chart');
    // Add your chart initialization logic here
}

// Start activity updates (placeholder)
function startActivityUpdates() {
    console.log('Starting activity feed updates');
    // Add your activity feed logic here
}

// Add activity (placeholder)
function addActivity() {
    console.log('Adding new activity');
    // Add your activity logic here
}

// ============================================
// EXPOSE FUNCTIONS TO GLOBAL SCOPE
// ============================================
window.toggleAllCheckboxes = toggleAllCheckboxes;
window.trackOrder = trackOrder;
window.handleImagePreview = handleImagePreview;
window.applyFilters = applyFilters;
window.applyCustomerFilters = applyCustomerFilters;
