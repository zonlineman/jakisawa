<?php
global $stats;
?>
<div class="col-md-3">
    <div class="card stat-card shadow-sm border-primary h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Remedies</h6>
                    <h3 class="mb-0"><?php echo number_format((float)($stats['total_products'] ?? 0)); ?></h3>
                    <small class="text-muted">All remedies</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-leaf"></i>
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
                    <h6 class="text-muted mb-2">Stock Value</h6>
                    <h3 class="mb-0">KES <?php echo number_format((float)($stats['total_stock_value'] ?? 0), 0); ?></h3>
                    <small class="text-muted">Total inventory value</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
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
                    <h6 class="text-muted mb-2">Low Stock</h6>
                    <h3 class="mb-0"><?php echo number_format((float)($stats['low_stock_count'] ?? 0)); ?></h3>
                    <small class="text-muted">At or below reorder level</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
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
                    <h6 class="text-muted mb-2">Featured</h6>
                    <h3 class="mb-0"><?php echo number_format((float)($stats['featured_count'] ?? 0)); ?></h3>
                    <small class="text-muted">Featured remedies</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>
</div>
