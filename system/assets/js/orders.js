// Enhanced print function with options
const SYSTEM_BASE_URL = (() => {
    const configured = (typeof window !== 'undefined' && typeof window.SYSTEM_BASE_URL === 'string')
        ? window.SYSTEM_BASE_URL.trim()
        : '';
    if (configured !== '') {
        return configured.replace(/\/+$/, '');
    }

    if (typeof window !== 'undefined') {
        const path = String(window.location.pathname || '');
        const marker = '/system/';
        const markerIndex = path.indexOf(marker);
        if (markerIndex !== -1) {
            return path.substring(0, markerIndex) + '/system';
        }
    }

    return '/system';
})();

function systemUrl(path) {
    const cleanPath = String(path || '').replace(/^\/+/, '');
    return cleanPath === '' ? SYSTEM_BASE_URL : `${SYSTEM_BASE_URL}/${cleanPath}`;
}

function withProjectBase(path) {
    const raw = String(path || '');
    if (!raw.startsWith('/')) {
        return raw;
    }

    if (SYSTEM_BASE_URL === '/system') {
        return raw;
    }

    const projectBase = SYSTEM_BASE_URL.replace(/\/system$/, '');
    if (!projectBase) {
        return raw;
    }

    if (raw === projectBase || raw.startsWith(projectBase + '/')) {
        return raw;
    }

    return projectBase + raw;
}

function printInvoice(orderId, options = {}) {
    const defaults = {
        format: 'html',
        template: 'modern',
        autoPrint: false,
        newWindow: true
    };
    
    const settings = { ...defaults, ...options };
    
    let url = `${systemUrl('ajax/print_invoice.php')}?order_id=${orderId}`;
    url += `&format=${settings.format}`;
    url += `&template=${settings.template}`;
    
    if (settings.autoPrint) {
        url += '&autoprint=1';
    }
    
    if (settings.newWindow) {
        const windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes';
        window.open(url, '_blank', windowFeatures);
    } else {
        window.location.href = url;
    }
}

function resolveOrderItemImageUrl(imageUrl) {
    const raw = String(imageUrl || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/uploads/')) return systemUrl(raw);
    if (raw.startsWith('uploads/')) return systemUrl(raw);
    if (raw.startsWith('/')) return withProjectBase(raw);
    return withProjectBase('/' + raw);
}

// Global variables
if (typeof currentOrderId === 'undefined') {
    var currentOrderId = null;
}
if (typeof selectedOrders === 'undefined') {
    var selectedOrders = [];
}

// View order details
function viewOrderDetails(orderId) {
    currentOrderId = orderId;
    
    $('#orderDetailsContent').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading order details...</p>
        </div>
    `);
    
    $('#orderDetailsModal').modal('show');
    
    // Fetch order details via AJAX
    $.ajax({
        url: 'pages/ajax/get_order_details.php',
        method: 'GET',
        data: { order_id: orderId },
        success: function(response) {
            if (response.success) {
                displayOrderDetails(response);
            } else {
                $('#orderDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${response.error || 'Failed to load order details'}
                    </div>
                `);
            }
        },
        error: function() {
            $('#orderDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading order details
                </div>
            `);
        }
    });
}

// Display order details
function displayOrderDetails(data) {
    const order = data.order;
    const items = data.items;
    const auditLogs = data.audit_logs || [];
    
    let itemsHtml = '';
    items.forEach(item => {
        itemsHtml += `
            <tr>
                <td>${item.product_name}</td>
                <td>${item.product_sku}</td>
                <td>${formatCurrency(item.unit_price)}</td>
                <td>${item.quantity}</td>
                <td>${formatCurrency(item.total_price)}</td>
            </tr>
        `;
    });
    
    let auditHtml = '';
    auditLogs.forEach(log => {
        auditHtml += `
            <div class="log-item">
                <small class="text-muted">${formatDateTime(log.created_at)}</small>
                <div>${log.action} by ${log.user_name || 'System'}</div>
            </div>
        `;
    });
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Information</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Order Number:</dt>
                            <dd class="col-sm-8"><strong>${order.order_number}</strong></dd>
                            
                            <dt class="col-sm-4">Customer:</dt>
                            <dd class="col-sm-8">${order.customer_name}</dd>
                            
                            <dt class="col-sm-4">Email:</dt>
                            <dd class="col-sm-8">${order.customer_email}</dd>
                            
                            <dt class="col-sm-4">Phone:</dt>
                            <dd class="col-sm-8">${order.customer_phone || 'N/A'}</dd>
                            
                            <dt class="col-sm-4">Payment Method:</dt>
                            <dd class="col-sm-8">${order.payment_method}</dd>
                            
                            <dt class="col-sm-4">Payment Status:</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-${getStatusColor(order.payment_status)}">
                                    ${order.payment_status}
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Order Status:</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-${getStatusColor(order.order_status)}">
                                    ${order.order_status}
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Created:</dt>
                            <dd class="col-sm-8">${formatDateTime(order.created_at)}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Address Information</h6>
                    </div>
                    <div class="card-body">
                        <h6>Shipping Address:</h6>
                        <p>${order.shipping_address.replace(/\n/g, '<br>')}</p>
                        
                        <h6 class="mt-3">Billing Address:</h6>
                        <p>${(order.billing_address || order.shipping_address).replace(/\n/g, '<br>')}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Items (${items.length})</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                <td><strong>${formatCurrency(order.subtotal)}</strong></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">Shipping:</td>
                                <td>${formatCurrency(order.shipping_cost)}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">Tax:</td>
                                <td>${formatCurrency(order.tax_amount)}</td>
                            </tr>
                            <tr class="table-active">
                                <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                <td><strong class="text-success">${formatCurrency(order.total_amount)}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        ${order.notes ? `
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Order Notes</h6>
            </div>
            <div class="card-body">
                <p>${order.notes.replace(/\n/g, '<br>')}</p>
            </div>
        </div>
        ` : ''}
        
        ${auditLogs.length > 0 ? `
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Activity Log</h6>
            </div>
            <div class="card-body">
                ${auditHtml}
            </div>
        </div>
        ` : ''}
    `;
    
    $('#orderDetailsContent').html(html);
    $('#orderCodeTitle').text(order.order_number);
}

// Update order status
function updateOrderStatus() {
    const orderId = $('#statusOrderId').val();
    const status = $('#statusSelect').val();
    const notes = $('#statusNotes').val();
    const statusType = $('#statusType').val();
    
    $.ajax({
        url: 'pages/actions/orders/update_status.php',
        method: 'POST',
        data: {
            order_id: orderId,
            status: status,
            status_type: statusType,
            notes: notes
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Status updated successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Update the badge
                const badgeClass = getStatusColorClass(status);
                const badgeHTML = `<span class="badge bg-${badgeClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                
                if (statusType === 'payment') {
                    $(`#paymentStatusBadge_${orderId}`).html(badgeHTML);
                } else {
                    $(`#orderStatusBadge_${orderId}`).html(badgeHTML);
                }
                
                updateRowPriority(orderId);
                
                $('#statusUpdateModal').modal('hide');
                $('#statusUpdateForm')[0].reset();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update status'
            });
        }
    });
}

// Delete order
function deleteOrder(orderId, orderNumber) {
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete order ${orderNumber}. This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'pages/actions/orders/delete_order.php',
                method: 'POST',
                data: {
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Order deleted successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        $(`#orderRow_${orderId}`).fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to delete order'
                    });
                }
            });
        }
    });
}

// Helper functions
function getStatusColorClass(status) {
    const colors = {
        'pending': 'warning',
        'processing': 'info',
        'shipped': 'primary',
        'delivered': 'success',
        'cancelled': 'secondary',
        'paid': 'success',
        'failed': 'danger',
        'refunded': 'danger'
    };
    return colors[status] || 'secondary';
}

function formatCurrency(amount) {
    return 'KSh ' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDateTime(dateTime) {
    const date = new Date(dateTime);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Initialize when document is ready
$(document).ready(function() {
    attachCheckboxListeners();
});

function attachCheckboxListeners() {
    $('#selectAllOrders').off('change').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.orderCheckbox').prop('checked', isChecked);
        updateSelectedOrders();
    });
    
    $('.orderCheckbox').off('change').on('change', function() {
        updateSelectedOrders();
    });
}

function updateSelectedOrders() {
    selectedOrders.clear();
    $('.orderCheckbox:checked').each(function() {
        selectedOrders.add($(this).val());
    });
    $('#selectedOrdersCount').text(selectedOrders.size);
}

// THIS WAS ADDED LATER
// orders.js - Complete Orders Management JavaScript

/**
 * View Order Details in Modal
 */
function viewOrderDetails(orderId) {
    console.log('Fetching order details for ID:', orderId);
    
    // Show modal with loading state
    $('#orderDetailsModal').modal('show');
    $('#orderDetailsBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading order details...</p>
        </div>
    `);
    
    // Fetch order details via AJAX
    $.ajax({
        url: 'ajax/get_order_details.php',
        method: 'GET',
        data: { order_id: orderId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayOrderDetails(response.order, response.items);
            } else {
                showError('Failed to load order details: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error loading order details. Please try again.');
        }
    });
}

/**
 * Display Order Details in Modal
 */
function displayOrderDetails(order, items) {
    let itemsHtml = '';
    let subtotal = 0;
    
    items.forEach(item => {
        const itemTotal = parseFloat(item.total_price);
        subtotal += itemTotal;
        
        itemsHtml += `
            <tr>
                <td>
                    ${item.image_url ? `<img src="${resolveOrderItemImageUrl(item.image_url)}" alt="${item.product_name}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 5px;" class="me-2">` : ''}
                    ${item.product_name}
                </td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">KSH ${parseFloat(item.unit_price).toFixed(2)}</td>
                <td class="text-end"><strong>KSH ${itemTotal.toFixed(2)}</strong></td>
            </tr>
        `;
    });
    
    const shippingCost = parseFloat(order.shipping_cost || 0);
    const tax = parseFloat(order.tax_amount || 0);
    const total = parseFloat(order.total_amount || 0);
    
    const paymentStatusClass = order.payment_status === 'paid' ? 'success' : 
                               order.payment_status === 'failed' ? 'danger' : 'warning';
    
    const orderStatusClass = order.order_status === 'delivered' ? 'success' : 
                            order.order_status === 'cancelled' ? 'danger' : 'info';
    
    const html = `
        <div class="order-details-container">
            <!-- Order Header -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Order Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Order Number:</strong></td>
                            <td>${order.order_number}</td>
                        </tr>
                        <tr>
                            <td><strong>Order Date:</strong></td>
                            <td>${formatDate(order.created_at)}</td>
                        </tr>
                        <tr>
                            <td><strong>Payment Status:</strong></td>
                            <td><span class="badge bg-${paymentStatusClass}">${order.payment_status.toUpperCase()}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Order Status:</strong></td>
                            <td><span class="badge bg-${orderStatusClass}">${order.order_status.toUpperCase()}</span></td>
                        </tr>
                        ${order.payment_method ? `
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td>${order.payment_method}</td>
                        </tr>
                        ` : ''}
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="fas fa-user me-2"></i>Customer Information</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>${order.customer_name}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>${order.customer_email}</td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>${order.customer_phone || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Delivery Address:</strong></td>
                            <td>${order.delivery_address || 'N/A'}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <hr>
            
            <!-- Order Items -->
            <h5 class="mb-3"><i class="fas fa-shopping-bag me-2"></i>Order Items</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td class="text-end">KSH ${subtotal.toFixed(2)}</td>
                        </tr>
                        ${shippingCost > 0 ? `
                        <tr>
                            <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                            <td class="text-end">KSH ${shippingCost.toFixed(2)}</td>
                        </tr>
                        ` : ''}
                        ${tax > 0 ? `
                        <tr>
                            <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                            <td class="text-end">KSH ${tax.toFixed(2)}</td>
                        </tr>
                        ` : ''}
                        <tr class="table-active">
                            <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-primary">KSH ${total.toFixed(2)}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            ${order.notes ? `
            <hr>
            <div class="mt-3">
                <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                <p class="text-muted">${order.notes}</p>
            </div>
            ` : ''}
            
            <!-- Action Buttons -->
            <div class="mt-4 d-flex gap-2 justify-content-end">
                <button class="btn btn-warning" onclick="updatePaymentStatus(${order.id})">
                    <i class="fas fa-dollar-sign me-1"></i> Update Payment
                </button>
                <button class="btn btn-primary" onclick="updateOrderStatus(${order.id})">
                    <i class="fas fa-edit me-1"></i> Update Status
                </button>
                <button class="btn btn-success" onclick="printInvoice(${order.id})">
                    <i class="fas fa-print me-1"></i> Print Invoice
                </button>
            </div>
        </div>
    `;
    
    $('#orderDetailsBody').html(html);
}

/**
 * Update Payment Status
 */
function updatePaymentStatus(orderId) {
    $('#updatePaymentModal').modal('show');
    $('#payment_order_id').val(orderId);
}

/**
 * Update Order Status
 */
function updateOrderStatus(orderId) {
    $('#updateOrderModal').modal('show');
    $('#order_order_id').val(orderId);
}

/**
 * Handle Payment Status Update Form Submission
 */
$('#updatePaymentForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: 'actions/orders/update_status.php',
        method: 'POST',
        data: formData + '&status_type=payment',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to update payment status. Please try again.'
            });
        }
    });
});

/**
 * Handle Order Status Update Form Submission
 */
$('#updateOrderForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: 'actions/orders/update_status.php',
        method: 'POST',
        data: formData + '&status_type=order',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Failed to update order status. Please try again.'
            });
        }
    });
});

/**
 * Delete Order
 */
function deleteOrder(orderId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will mark the order as deleted. This action cannot be easily reversed!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'actions/orders/delete_order.php',
                method: 'POST',
                data: { order_id: orderId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to delete order. Please try again.'
                    });
                }
            });
        }
    });
}

/**
 * Print Invoice
 */
function printInvoice(orderId) {
    // Open print window
    window.open('print_invoice.php?order_id=' + orderId, '_blank');
}

/**
 * Export Orders
 */
function exportOrders(format) {
    const url = new URL(window.location.href);
    url.pathname = url.pathname.replace(/[^\/]*$/, 'export_orders.php');
    url.searchParams.set('format', format);
    
    window.location.href = url.toString();
}

/**
 * Clear Search
 */
function clearSearch() {
    $('#orderSearchInput').val('');
    $('#ordersFilterForm').submit();
}

/**
 * Refresh Orders
 */
function refreshOrders() {
    location.reload();
}

/**
 * Show Error Message
 */
function showError(message) {
    $('#orderDetailsBody').html(`
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>${message}
        </div>
    `);
}

/**
 * Format Date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * Initialize DataTables
 */
$(document).ready(function() {
    if ($('#ordersTable').length) {
        $('#ordersTable').DataTable({
            pageLength: 15,
            lengthMenu: [[15, 30, 50, 100], [15, 30, 50, 100]],
            order: [[4, 'desc']], // Sort by date column
            language: {
                search: "Search orders:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
            },
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 } // Disable sorting on actions column
            ]
        });
    }
    
    // Initialize tooltips safely (skip other Bootstrap component toggles)
    if (window.bootstrap && window.bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            const toggle = (el.getAttribute('data-bs-toggle') || '').toLowerCase();
            if (toggle && toggle !== 'tooltip' && toggle !== 'popover') {
                return;
            }
            window.bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }
});
