<!-- Search and Filter Section - Modern Production Ready -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-gradient-primary text-white py-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-funnel-fill me-2"></i>
            <h6 class="mb-0 fw-semibold">Search & Filter Suppliers</h6>
        </div>
    </div>
    <div class="card-body p-4">
        <form method="GET" action="" class="needs-validation" novalidate>
            <input type="hidden" name="page" value="suppliers">
            
            <div class="row g-3">
                <!-- Search Input -->
                <div class="col-lg-5 col-md-6">
                    <label for="searchInput" class="form-label text-muted small mb-1">
                        <i class="bi bi-search"></i> Search Suppliers
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input 
                            type="text" 
                            class="form-control border-start-0 ps-0" 
                            id="searchInput"
                            name="search" 
                            placeholder="Name, contact person, email, phone..." 
                            value="<?php echo htmlspecialchars($search); ?>"
                            autocomplete="off">
                        <?php if (!empty($search)): ?>
                            <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('searchInput').value=''; this.form.submit();">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status Filter -->
                <div class="col-lg-3 col-md-6">
                    <label for="statusFilter" class="form-label text-muted small mb-1">
                        <i class="bi bi-toggle-on"></i> Status
                    </label>
                    <select class="form-select" id="statusFilter" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>
                            <i class="bi bi-check-circle"></i> Active Only
                        </option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>
                            <i class="bi bi-x-circle"></i> Inactive Only
                        </option>
                    </select>
                </div>
                
                <!-- Action Buttons -->
                <div class="col-lg-4 col-md-12">
                    <label class="form-label text-muted small mb-1 d-none d-md-block">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <a href="?page=suppliers" class="btn btn-outline-secondary flex-fill">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Export to Excel">
                            <i class="bi bi-file-earmark-excel"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Active Filters Display -->
            <?php if (!empty($search) || $status !== ''): ?>
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="text-muted small">Active Filters:</span>
                        
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary">
                                <i class="bi bi-search me-1"></i>
                                Search: "<?php echo htmlspecialchars($search); ?>"
                                <a href="?page=suppliers&status=<?php echo $status; ?>" class="text-primary ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($status === '1'): ?>
                            <span class="badge bg-success-subtle text-success border border-success">
                                <i class="bi bi-check-circle me-1"></i>
                                Active Only
                                <a href="?page=suppliers&search=<?php echo urlencode($search); ?>" class="text-success ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php elseif ($status === '0'): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning">
                                <i class="bi bi-x-circle me-1"></i>
                                Inactive Only
                                <a href="?page=suppliers&search=<?php echo urlencode($search); ?>" class="text-warning ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <a href="?page=suppliers" class="text-danger text-decoration-none small ms-2">
                            <i class="bi bi-x-circle me-1"></i>Clear All
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.input-group-text {
    background-color: #f8f9fa;
}

.badge {
    padding: 0.5rem 0.75rem;
    font-weight: 500;
}
</style>