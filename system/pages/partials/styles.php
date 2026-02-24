<style>
/* ============================================
   JAKISAWA SHOP - SUPPLIERS MODULE
   Modern Production-Ready Styles
   ============================================ */

/* Root Variables */
:root {
    --primary-color: #23202913;
    --primary-dark: #121314d3;
    --secondary-color: #764ba2;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --border-radius: 10px;
    --transition-speed: 0.3s;
    --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    --box-shadow-lg: 0 5px 20px rgba(0, 0, 0, 0.15);
}

/* ============================================
   GLOBAL STYLES
   ============================================ */

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #0d0d0e;
    color: #333;
}

.container-fluid {
    padding: 2rem;
}

/* ============================================
   CARD STYLES
   ============================================ */

.card {
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    transition: all var(--transition-speed);
    border: none;
}

.card:hover {
    box-shadow: var(--box-shadow-lg);
}

.card-header {
    border-bottom: 2px solid #f0f0f0;
    font-weight: 600;
}

.card.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card.shadow-lg {
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
}

/* ============================================
   STATISTICS CARDS
   ============================================ */

.stat-card {
    border-radius: var(--border-radius);
    border: none;
    transition: transform var(--transition-speed);
    height: 100%;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.bg-primary-light {
    background-color: rgba(102, 126, 234, 0.1);
    color: var(--primary-color);
}

.bg-success-light {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

.bg-info-light {
    background-color: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

/* ============================================
   STATUS BADGES
   ============================================ */

.active-status {
    color: var(--success-color);
    font-weight: 600;
}

.inactive-status {
    color: #6c757d;
    font-weight: 600;
}

.low-stock {
    color: var(--danger-color);
    font-weight: 500;
}

.badge {
    font-weight: 500;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
}

.badge.rounded-pill {
    border-radius: 50rem;
}

/* ============================================
   BUTTONS
   ============================================ */

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all var(--transition-speed);
    padding: 0.5rem 1rem;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn:active {
    transform: translateY(0);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, #6a42a0 100%);
}

.btn-group-sm .btn {
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
}

.btn-outline-primary {
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    color: white;
}

/* ============================================
   FORM CONTROLS
   ============================================ */

.form-control,
.form-select {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 0.625rem 0.875rem;
    transition: all var(--transition-speed);
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.input-group-text {
    background-color: var(--light-color);
    border: 1px solid #dee2e6;
    border-radius: 8px 0 0 8px;
}

.form-check-input {
    border-radius: 4px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
    border-radius: 2rem;
}

.form-switch .form-check-input:checked {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

/* ============================================
   TABLE STYLES
   ============================================ */

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: var(--light-color);
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    color: #6c757d;
}

.table tbody tr {
    transition: all var(--transition-speed);
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.table tbody td {
    vertical-align: middle;
    padding: 1rem 0.75rem;
    border-bottom: 1px solid #f0f0f0;
}

.table-responsive {
    border-radius: var(--border-radius);
}

/* ============================================
   MODAL STYLES
   ============================================ */

.modal-content {
    border-radius: 15px;
    border: none;
    overflow: hidden;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: none;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #dee2e6;
}

.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

/* ============================================
   PAGINATION STYLES
   ============================================ */

.pagination {
    margin-bottom: 0;
}

.pagination .page-link {
    color: var(--primary-color);
    border: 1px solid #dee2e6;
    border-radius: 6px;
    margin: 0 3px;
    padding: 0.5rem 0.75rem;
    transition: all var(--transition-speed);
}

.pagination .page-link:hover {
    background-color: var(--light-color);
    color: var(--primary-dark);
    border-color: var(--primary-color);
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-color: var(--primary-color);
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    background-color: white;
    border-color: #dee2e6;
}

/* ============================================
   TOAST NOTIFICATIONS
   ============================================ */

.ajax-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 999999;
    min-width: 300px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow-lg);
}

.toast {
    border: none;
}

.toast-header {
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

/* ============================================
   LOADING STATES
   ============================================ */

.btn-loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-right-color: transparent;
    animation: spin 0.6s linear infinite;
}

.spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.25em;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ============================================
   ALERTS
   ============================================ */

.alert {
    border-radius: var(--border-radius);
    border: none;
    padding: 1rem 1.25rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

/* ============================================
   UTILITY CLASSES
   ============================================ */

.text-primary {
    color: var(--primary-color) !important;
}

.text-muted {
    color: #6c757d !important;
}

.fw-semibold {
    font-weight: 600 !important;
}

.fw-bold {
    font-weight: 700 !important;
}

.border-0 {
    border: none !important;
}

.rounded {
    border-radius: var(--border-radius) !important;
}

.shadow-sm {
    box-shadow: var(--box-shadow) !important;
}

.shadow-lg {
    box-shadow: var(--box-shadow-lg) !important;
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
    
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
}

/* ============================================
   PRINT STYLES
   ============================================ */

@media print {
    .no-print,
    .btn,
    .pagination,
    .modal-footer {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }
    
    .table {
        font-size: 10pt;
    }
}

/* ============================================
   ANIMATION CLASSES
   ============================================ */

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.slide-up {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================================
   CUSTOM SCROLLBAR
   ============================================ */

::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* ============================================
   ACCESSIBILITY
   ============================================ */

.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

.btn:focus,
.form-control:focus,
.form-select:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* ============================================
   DARK MODE SUPPORT (Optional)
   ============================================ */

@media (prefers-color-scheme: dark) {
    /* Uncomment to enable dark mode */
    /*
    body {
        background-color: #1a1a1a;
        color: #e0e0e0;
    }
    
    .card {
        background-color: #2d2d2d;
        color: #e0e0e0;
    }
    
    .table {
        color: #e0e0e0;
    }
    */
}
</style>