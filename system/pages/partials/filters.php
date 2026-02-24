<?php
// filters.php - Remedies filter/search form
global $search, $categoryFilter, $statusFilter, $categories, $perPage;
?>

<form method="GET" action="admin_dashboard.php" class="row g-3 align-items-end">
    <input type="hidden" name="page" value="remedies">
    <input type="hidden" name="p" value="1">

    <div class="col-lg-4 col-md-6">
        <label for="filter-search" class="form-label mb-1">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input
                id="filter-search"
                type="text"
                name="search"
                class="form-control"
                placeholder="Name, SKU, category..."
                value="<?php echo htmlspecialchars((string)($search ?? '')); ?>"
            >
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label for="filter-category" class="form-label mb-1">Category</label>
        <select id="filter-category" name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((string)$categoryFilter === (string)$cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-lg-2 col-md-4">
        <label for="filter-status" class="form-label mb-1">Status</label>
        <select id="filter-status" name="status" class="form-select">
            <option value="">All Status</option>
            <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo ($statusFilter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            <option value="featured" <?php echo ($statusFilter === 'featured') ? 'selected' : ''; ?>>Featured</option>
        </select>
    </div>

    <div class="col-lg-1 col-md-2">
        <label for="filter-per-page" class="form-label mb-1">Show</label>
        <select id="filter-per-page" name="per_page" class="form-select">
            <?php foreach ([15, 30, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo ((int)($perPage ?? 15) === $size) ? 'selected' : ''; ?>>
                    <?php echo $size; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-lg-2 col-md-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="fas fa-filter me-1"></i> Apply
            </button>
            <a href="admin_dashboard.php?page=remedies" class="btn btn-outline-secondary" title="Clear filters">
                <i class="fas fa-rotate-right"></i>
            </a>
        </div>
    </div>
</form>
