<?php
// SUPPLIERS PAGE - Statistics cards partial
global $stats;
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Suppliers</h6>
                        <h3 class="mb-0"><?php echo isset($stats['total_suppliers']) ? number_format($stats['total_suppliers']) : 0; ?></h3>
                        <small class="text-muted"><?php echo isset($stats['active_suppliers']) ? $stats['active_suppliers'] : 0; ?> active</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Inactive Suppliers</h6>
                        <h3 class="mb-0"><?php echo isset($stats['inactive_suppliers']) ? number_format($stats['inactive_suppliers']) : 0; ?></h3>
                        <small class="text-muted">Currently disabled</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">With Products</h6>
                        <h3 class="mb-0"><?php echo isset($stats['suppliers_with_products']) ? number_format($stats['suppliers_with_products']) : 0; ?></h3>
                        <small class="text-muted">Suppliers with products</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Low Stock Alerts</h6>
                        <h3 class="mb-0"><?php echo isset($stats['low_stock_suppliers']) ? number_format($stats['low_stock_suppliers']) : 0; ?></h3>
                        <small class="text-muted">Suppliers needing restock</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.border-primary .stat-icon { background-color: rgba(52, 152, 219, 0.1); color: #3498db; }
.border-warning .stat-icon { background-color: rgba(243, 156, 18, 0.1); color: #f39c12; }
.border-success .stat-icon { background-color: rgba(39, 174, 96, 0.1); color: #27ae60; }
.border-info .stat-icon { background-color: rgba(41, 128, 185, 0.1); color: #2980b9; }
</style>