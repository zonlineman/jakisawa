<?php
if (($pages ?? 1) > 1):
    $current_page = $page ?? 1;
?>
<div class="pagination-enhanced mt-3">
    <div class="pagination-info">
        <small class="text-muted">
            Page <?php echo $current_page; ?> of <?php echo $pages; ?> 
            | Showing <?php echo count($orders ?? []); ?> of <?php echo $total ?? 0; ?> orders
        </small>
    </div>
    <div class="pagination-controls">
        <!-- First Page -->
        <?php if ($current_page > 1): ?>
        <a href="?page=orders&p=1&status=<?php echo urlencode($statusFilter ?? ''); ?>&order_status=<?php echo urlencode($orderStatusFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>&start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" 
           class="page-link" title="First Page">
            <i class="fas fa-angle-double-left"></i>
        </a>
        <?php endif; ?>
        
        <!-- Previous Page -->
        <?php if ($current_page > 1): ?>
        <a href="?page=orders&p=<?php echo $current_page - 1; ?>&status=<?php echo urlencode($statusFilter ?? ''); ?>&order_status=<?php echo urlencode($orderStatusFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>&start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" 
           class="page-link" title="Previous Page">
            <i class="fas fa-angle-left"></i>
        </a>
        <?php endif; ?>
        
        <!-- Page Numbers -->
        <?php 
        $startPage = max(1, $current_page - 2);
        $endPage = min($pages, $current_page + 2);
        
        if ($startPage > 1): ?>
        <span class="page-link disabled">...</span>
        <?php endif; ?>
        
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <a href="?page=orders&p=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter ?? ''); ?>&order_status=<?php echo urlencode($orderStatusFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>&start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" 
           class="page-link <?php echo $i == $current_page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($endPage < $pages): ?>
        <span class="page-link disabled">...</span>
        <?php endif; ?>
        
        <!-- Next Page -->
        <?php if ($current_page < $pages): ?>
        <a href="?page=orders&p=<?php echo $current_page + 1; ?>&status=<?php echo urlencode($statusFilter ?? ''); ?>&order_status=<?php echo urlencode($orderStatusFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>&start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" 
           class="page-link" title="Next Page">
            <i class="fas fa-angle-right"></i>
        </a>
        <?php endif; ?>
        
        <!-- Last Page -->
        <?php if ($current_page < $pages): ?>
        <a href="?page=orders&p=<?php echo $pages; ?>&status=<?php echo urlencode($statusFilter ?? ''); ?>&order_status=<?php echo urlencode($orderStatusFilter ?? ''); ?>&search=<?php echo urlencode($searchQuery ?? ''); ?>&start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" 
           class="page-link" title="Last Page">
            <i class="fas fa-angle-double-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>