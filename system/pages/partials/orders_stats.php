<?php
$stats = getDashboardStats();
?>
<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon sales">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_sales'] ?? 0); ?></h3>
                <p>Total Orders</p>
                <div class="stat-trend">
                    <i class="fas fa-chart-line"></i>
                    <span>All time</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['today_sales'] ?? 0); ?></h3>
                <p>Today's Orders</p>
                <div class="stat-trend">
                    <i class="fas fa-clock"></i>
                    <span>Last 24 hours</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['pending_orders'] ?? 0); ?></h3>
                <p>Pending Payment</p>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-circle text-warning"></i>
                    <span class="text-warning">Requires attention</span>
                </div>
            </div>
            <?php if (($stats['pending_orders'] ?? 0) > 0): ?>
            <div class="stat-notification">
                <span class="notification-dot"></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon revenue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
                <p>Total Revenue</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up text-success"></i>
                    <span class="text-success">All time</span>
                </div>
            </div>
        </div>
    </div>
</div>