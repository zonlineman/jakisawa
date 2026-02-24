<?php
/**
 * CMS Statistics Partial
 * Location: /pages/partials/cms_statistics.php
 */
?>

<div class="cms-statistics">
    <div class="stat-item">
        <span class="stat-label">Registered</span>
        <span class="stat-value"><?php echo number_format($statistics['total_registered']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Random</span>
        <span class="stat-value"><?php echo number_format($statistics['total_random']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Active</span>
        <span class="stat-value"><?php echo number_format($statistics['active_customers']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Pending</span>
        <span class="stat-value"><?php echo number_format($statistics['pending_approvals']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Inactive</span>
        <span class="stat-value"><?php echo number_format($statistics['inactive_customers']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">High-Value</span>
        <span class="stat-value"><?php echo number_format($statistics['high_value_customers']); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Total Revenue</span>
        <span class="stat-value">Ksh <?php echo number_format($statistics['total_revenue'], 0); ?></span>
    </div>
    
    <div class="stat-item">
        <span class="stat-label">Avg Order</span>
        <span class="stat-value">Ksh <?php echo number_format($statistics['avg_order_value'], 0); ?></span>
    </div>
</div>