<?php
// Get filter parameters from URL
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$orderStatusFilter = $_GET['order_status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
?>
<div class="filter-card mb-4">
    <form method="GET" action="" id="ordersFilterForm">
        <input type="hidden" name="page" value="orders">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search orders..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           id="orderSearchInput">
                    <?php if ($searchQuery): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearOrderSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-2">
                <select name="status" class="form-control" id="statusFilter">
                    <option value="">Payment Status</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="order_status" class="form-control" id="orderStatusFilter">
                    <option value="">Order Status</option>
                    <option value="pending" <?php echo $orderStatusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $orderStatusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $orderStatusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $orderStatusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $orderStatusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <div class="row g-2">
                    <div class="col">
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($startDate); ?>"
                               placeholder="From">
                    </div>
                    <div class="col">
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($endDate); ?>"
                               placeholder="To">
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>
        </div>
        
        <!-- Quick Status Filters -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <a href="?page=orders&status=pending" 
                       class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                        <i class="fas fa-clock me-1"></i> Payment Pending
                    </a>
                    <a href="?page=orders&order_status=processing" 
                       class="btn btn-sm <?php echo $orderStatusFilter === 'processing' ? 'btn-info' : 'btn-outline-info'; ?>">
                        <i class="fas fa-cogs me-1"></i> Processing
                    </a>
                    <a href="?page=orders&status=paid" 
                       class="btn btn-sm <?php echo $statusFilter === 'paid' ? 'btn-success' : 'btn-outline-success'; ?>">
                        <i class="fas fa-check-circle me-1"></i> Paid
                    </a>
                    <a href="?page=orders&status=refunded" 
                       class="btn btn-sm <?php echo $statusFilter === 'refunded' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                        <i class="fas fa-undo me-1"></i> Refunded
                    </a>
                    <a href="?page=orders" 
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear All
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// If you have JavaScript in external file, remove the script below
// Otherwise keep it minimal
?>
<script>
// Minimal JavaScript that should be in external file
function clearOrderSearch() {
    document.getElementById('orderSearchInput').value = '';
    document.getElementById('ordersFilterForm').submit();
}
</script>