<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_GET['edit_id'] ?? 0);
$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$isRemedyAdmin = in_array($role, ['admin', 'super_admin'], true);
$systemBase = defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system';
?>

<div class="container-fluid py-3">
    <div class="top-bar">
        <h1 class="page-title mb-0">
            <i class="fas fa-edit"></i>
            Edit Remedy
        </h1>
        <div class="btn-toolbar d-flex gap-2">
            <a href="?page=remedies" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Remedies
            </a>
            <a href="?page=inventory" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-boxes me-1"></i>Back to Inventory
            </a>
        </div>
    </div>

    <?php if ($editId <= 0): ?>
        <div class="alert alert-warning mb-0">
            Remedy ID is missing. Open an item from Remedies or Inventory, then click Edit.
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold">
                Full Remedy Editor
            </div>
            <div class="card-body text-center py-4" id="standaloneEditHost">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-3 mb-0">Loading remedy editor for ID <?php echo $editId; ?> (including size and price variations)...</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/modals/edit_remedy.php'; ?>

<script>
    window.SYSTEM_BASE_URL = <?php echo json_encode($systemBase); ?>;
    window.IS_REMEDY_ADMIN = <?php echo $isRemedyAdmin ? 'true' : 'false'; ?>;
</script>
<script src="<?php echo htmlspecialchars($systemBase . '/assets/js/remedies.js', ENT_QUOTES); ?>?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/../assets/js/remedies.js')); ?>"></script>

<?php if ($editId > 0): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const host = document.getElementById('standaloneEditHost');
        if (!host) return;

        if (typeof window.openRemedyEditor !== 'function') {
            host.innerHTML = '<div class="alert alert-danger mb-0">Editor script failed to load. Please hard refresh (Ctrl+F5).</div>';
            return;
        }

        window.openRemedyEditor(<?php echo $editId; ?>);
    });
</script>
<?php endif; ?>
