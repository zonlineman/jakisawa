<?php
// This file should be included in your orders.php or wherever you need it
?>
<!-- Print Options Modal -->
<div class="modal fade" id="printOptionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-print me-2"></i>Print Invoice Options
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label class="form-label fw-bold">Template Style</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="printTemplate" id="templateModern" value="modern" checked>
                            <label class="form-check-label" for="templateModern">
                                <i class="fas fa-palette me-1"></i> Modern
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="printTemplate" id="templateClassic" value="classic">
                            <label class="form-check-label" for="templateClassic">
                                <i class="fas fa-file-alt me-1"></i> Classic
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="printTemplate" id="templateSimple" value="simple">
                            <label class="form-check-label" for="templateSimple">
                                <i class="fas fa-file me-1"></i> Simple
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Output Format</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="printFormat" id="formatHtml" value="html" checked>
                            <label class="form-check-label" for="formatHtml">
                                <i class="fas fa-globe me-1"></i> HTML (Print in Browser)
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="printFormat" id="formatPdf" value="pdf">
                            <label class="form-check-label" for="formatPdf">
                                <i class="fas fa-file-pdf me-1"></i> PDF (Download)
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoPrint" checked>
                            <label class="form-check-label" for="autoPrint">
                                <i class="fas fa-bolt me-1"></i> Auto-print
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="openNewWindow" checked>
                            <label class="form-check-label" for="openNewWindow">
                                <i class="fas fa-external-link-alt me-1"></i> New Window
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Note:</strong> PDF format requires server-side PDF generation setup. 
                        Currently using HTML print-to-PDF.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="printWithOptions()">
                    <i class="fas fa-print me-1"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#printOptionsModal .modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

#printOptionsModal .modal-header {
    background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
    color: white;
    border-radius: 15px 15px 0 0;
}

#printOptionsModal .modal-header .btn-close {
    filter: invert(1);
}

#printOptionsModal .form-check-input:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}

#printOptionsModal .form-switch .form-check-input:checked {
    background-color: #4361ee;
}

#printOptionsModal .alert {
    border-radius: 8px;
    border: none;
    background: #e3f2fd;
}
</style>

<script>
// Global variable to store current order ID
let currentPrintOrderId = null;

// Function to show print options modal
function showPrintOptions(orderId) {
    currentPrintOrderId = orderId;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('printOptionsModal'));
    modal.show();
    
    // Set default values
    document.getElementById('templateModern').checked = true;
    document.getElementById('formatHtml').checked = true;
    document.getElementById('autoPrint').checked = true;
    document.getElementById('openNewWindow').checked = true;
}

// Function to print with selected options
function printWithOptions() {
    if (!currentPrintOrderId) {
        console.error('No order ID selected for printing');
        return;
    }
    
    // Get selected options
    const template = document.querySelector('input[name="printTemplate"]:checked').value;
    const format = document.querySelector('input[name="printFormat"]:checked').value;
    const autoPrint = document.getElementById('autoPrint').checked;
    const newWindow = document.getElementById('openNewWindow').checked;
    
    // Build the URL with correct path
    const baseUrl = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;
    
    // Construct the full URL
    let url = `${baseUrl}/ajax/print_invoice.php?order_id=${currentPrintOrderId}`;
    url += `&template=${template}`;
    url += `&format=${format}`;
    
    if (autoPrint) {
        url += '&autoprint=1';
    }
    
    // Open in new window or same window
    if (newWindow) {
        const windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes,menubar=no,toolbar=no';
        window.open(url, '_blank', windowFeatures);
    } else {
        window.location.href = url;
    }
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('printOptionsModal'));
    modal.hide();
}

// Quick print function (one-click)
function quickPrintInvoice(orderId) {
    // Use default options for quick print
    const printBase = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;
    const url = `${printBase}/ajax/print_invoice.php?order_id=${orderId}&template=modern&format=html&autoprint=1`;
    window.open(url, '_blank', 'width=1200,height=800');
}
</script>
