<?php
/**
 * CMS Header Partial
 * Location: /pages/partials/cms_header.php
 */
?>

<div class="cms-header">
    <div class="header-content">
        <h1><i class="fas fa-people-arrows"></i> Customer Management System</h1>
        <p>Manage registered and unregistered customers</p>
    </div>
    
    <div class="header-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmail">
            <i class="fas fa-envelope"></i> Send Email
        </button>
        <button type="button" class="btn btn-success" onclick="exportCSV()">
            <i class="fas fa-download"></i> Export CSV
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="<?php echo BASE_URL; ?>/admin_dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>