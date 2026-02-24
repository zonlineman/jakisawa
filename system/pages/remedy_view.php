<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function normalizeLabel(string $key): string
{
    return ucwords(str_replace('_', ' ', trim($key)));
}

function renderDbValue($value): string
{
    if ($value === null || $value === '') {
        return '<span class="text-muted">-</span>';
    }
    if (is_numeric($value)) {
        return htmlspecialchars((string)$value);
    }
    return nl2br(htmlspecialchars((string)$value));
}

function resolveRemedyImageUrl($imagePath): string
{
    $value = trim((string)$imagePath);
    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (strpos($value, '/') === 0) {
        return projectPathUrl($value);
    }

    if (strpos($value, 'uploads/') === 0) {
        return systemUrl($value);
    }

    return projectPathUrl($value);
}

$remedy = null;
$seoData = [];
$salesStats = [
    'orders_count' => 0,
    'units_sold' => 0,
    'total_sales' => 0,
];
$recentOrders = [];
$stockHistory = [];

if ($id > 0 && $conn) {
    $stmt = $conn->prepare("
        SELECT r.*, c.name AS category_name, s.name AS supplier_name
        FROM remedies r
        LEFT JOIN categories c ON c.id = r.category_id
        LEFT JOIN suppliers s ON s.id = r.supplier_id
        WHERE r.id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $remedy = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    if ($remedy) {
        if (function_exists('tableExists') && tableExists('remedy_seo_marketing')) {
            $seoStmt = $conn->prepare("SELECT * FROM remedy_seo_marketing WHERE remedy_id = ? LIMIT 1");
            if ($seoStmt) {
                $seoStmt->bind_param('i', $id);
                $seoStmt->execute();
                $seoRes = $seoStmt->get_result();
                $seoData = $seoRes ? ($seoRes->fetch_assoc() ?: []) : [];
                $seoStmt->close();
            }
        }

        $salesStmt = $conn->prepare("
            SELECT
                COUNT(DISTINCT order_id) AS orders_count,
                COALESCE(SUM(quantity), 0) AS units_sold,
                COALESCE(SUM(total_price), 0) AS total_sales
            FROM order_items
            WHERE product_id = ?
        ");
        if ($salesStmt) {
            $salesStmt->bind_param('i', $id);
            $salesStmt->execute();
            $salesRes = $salesStmt->get_result();
            $salesStats = $salesRes ? ($salesRes->fetch_assoc() ?: $salesStats) : $salesStats;
            $salesStmt->close();
        }

        $ordersStmt = $conn->prepare("
            SELECT oi.order_id, o.order_number, o.customer_name, o.payment_status, o.order_status, oi.quantity, oi.unit_price, oi.total_price, o.created_at
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.product_id = ?
            ORDER BY o.created_at DESC
            LIMIT 15
        ");
        if ($ordersStmt) {
            $ordersStmt->bind_param('i', $id);
            $ordersStmt->execute();
            $ordersRes = $ordersStmt->get_result();
            $recentOrders = $ordersRes ? $ordersRes->fetch_all(MYSQLI_ASSOC) : [];
            $ordersStmt->close();
        }

        if (function_exists('tableExists') && tableExists('stock_ledger')) {
            $histStmt = $conn->prepare("
                SELECT movement_type, qty_change, balance_after, source_ref, movement_at, notes
                FROM stock_ledger
                WHERE remedy_id = ?
                ORDER BY movement_at DESC, id DESC
                LIMIT 20
            ");
            if ($histStmt) {
                $histStmt->bind_param('i', $id);
                $histStmt->execute();
                $histRes = $histStmt->get_result();
                $stockHistory = $histRes ? $histRes->fetch_all(MYSQLI_ASSOC) : [];
                $histStmt->close();
            }
        } else {
            $auditStmt = $conn->prepare("
                SELECT action AS movement_type, created_at AS movement_at, new_values AS notes
                FROM audit_log
                WHERE table_name IN ('remedies','products') AND record_id = ? AND action LIKE 'stock_update:%'
                ORDER BY created_at DESC
                LIMIT 20
            ");
            if ($auditStmt) {
                $auditStmt->bind_param('i', $id);
                $auditStmt->execute();
                $auditRes = $auditStmt->get_result();
                $stockHistory = $auditRes ? $auditRes->fetch_all(MYSQLI_ASSOC) : [];
                $auditStmt->close();
            }
        }
    }
}
?>
<style>
    .remedy-view-page .top-bar .btn-toolbar {
        flex-wrap: wrap;
    }

    .remedy-view-page .snapshot-image,
    .remedy-view-page .snapshot-placeholder {
        width: 140px;
        height: 140px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        object-fit: cover;
    }

    .remedy-view-page .snapshot-placeholder {
        background: #f8f9fa;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }

    .remedy-view-page .kv-table th {
        width: 220px;
        white-space: nowrap;
        background: #f8fafc;
    }

    .remedy-view-page .table-orders {
        min-width: 720px;
    }

    .remedy-view-page .table-stock {
        min-width: 780px;
    }

    @media (max-width: 992px) {
        .remedy-view-page {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .remedy-view-page .top-bar {
            gap: 0.75rem;
        }

        .remedy-view-page .top-bar .btn-toolbar {
            width: 100%;
            justify-content: flex-start;
        }
    }

    @media (max-width: 768px) {
        .remedy-view-page .top-bar .btn-toolbar .btn {
            flex: 1 1 calc(50% - 0.4rem);
            min-width: 0;
            text-align: center;
        }

        .remedy-view-page .snapshot-image,
        .remedy-view-page .snapshot-placeholder {
            width: 112px;
            height: 112px;
        }

        .remedy-view-page .kv-table,
        .remedy-view-page .kv-table tbody,
        .remedy-view-page .kv-table tr,
        .remedy-view-page .kv-table th,
        .remedy-view-page .kv-table td {
            display: block;
            width: 100% !important;
        }

        .remedy-view-page .kv-table tr {
            border-bottom: 1px solid #e9edf3;
        }

        .remedy-view-page .kv-table th {
            border-bottom: 0;
            padding-bottom: 0.2rem;
            font-size: 0.82rem;
            color: #495057;
            background: transparent;
            white-space: normal;
        }

        .remedy-view-page .kv-table td {
            border-top: 0;
            padding-top: 0;
            padding-bottom: 0.7rem;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    }

    @media (max-width: 576px) {
        .remedy-view-page .top-bar .btn-toolbar .btn {
            flex: 1 1 100%;
        }

        .remedy-view-page .card-body {
            padding: 0.9rem;
        }

        .remedy-view-page .table-orders,
        .remedy-view-page .table-orders thead,
        .remedy-view-page .table-orders tbody,
        .remedy-view-page .table-orders tr,
        .remedy-view-page .table-orders th,
        .remedy-view-page .table-orders td,
        .remedy-view-page .table-stock,
        .remedy-view-page .table-stock thead,
        .remedy-view-page .table-stock tbody,
        .remedy-view-page .table-stock tr,
        .remedy-view-page .table-stock th,
        .remedy-view-page .table-stock td {
            display: block;
            width: 100%;
        }

        .remedy-view-page .table-orders thead,
        .remedy-view-page .table-stock thead {
            display: none;
        }

        .remedy-view-page .table-orders tr,
        .remedy-view-page .table-stock tr {
            border-bottom: 1px solid #e9edf3;
            padding: 0.35rem 0;
        }

        .remedy-view-page .table-orders td,
        .remedy-view-page .table-stock td {
            border: 0;
            padding: 0.42rem 0.55rem 0.42rem 7.9rem;
            position: relative;
            min-height: 2rem;
            text-align: left;
            overflow-wrap: anywhere;
        }

        .remedy-view-page .table-orders td::before,
        .remedy-view-page .table-stock td::before {
            content: attr(data-label);
            position: absolute;
            left: 0.55rem;
            top: 0.42rem;
            width: 7rem;
            font-weight: 600;
            color: #495057;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
    }
</style>
<div class="container-fluid py-3 remedy-view-page">
    <div class="top-bar">
        <h1 class="page-title">
            <i class="fas fa-capsules"></i>
            Remedy Details
        </h1>
        <div class="btn-toolbar d-flex gap-2">
            <a href="?page=inventory" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Inventory
            </a>
            <?php if ($remedy): ?>
            <a href="?page=edit_remedy&id=<?php echo (int)$remedy['id']; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit me-1"></i>Edit Remedy
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$remedy): ?>
        <div class="alert alert-warning mb-0">Remedy not found.</div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Quick Snapshot</div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <?php if (!empty($remedy['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars(resolveRemedyImageUrl((string)$remedy['image_url'])); ?>" alt="<?php echo htmlspecialchars((string)$remedy['name']); ?>" class="snapshot-image">
                            <?php else: ?>
                                <div class="snapshot-placeholder">
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <table class="table table-sm table-bordered mb-0 kv-table">
                            <tr><th>Name</th><td><?php echo htmlspecialchars((string)($remedy['name'] ?? '')); ?></td></tr>
                            <tr><th>SKU</th><td><?php echo htmlspecialchars((string)($remedy['sku'] ?? '')); ?></td></tr>
                            <tr><th>Category</th><td><?php echo htmlspecialchars((string)($remedy['category_name'] ?? '-')); ?></td></tr>
                            <tr><th>Supplier</th><td><?php echo htmlspecialchars((string)($remedy['supplier_name'] ?? '-')); ?></td></tr>
                            <tr><th>Stock</th><td><?php echo number_format((float)($remedy['stock_quantity'] ?? 0), 3); ?></td></tr>
                            <tr><th>Unit Price</th><td>KES <?php echo number_format((float)($remedy['unit_price'] ?? 0), 2); ?></td></tr>
                            <tr><th>Status</th><td><?php echo ((int)($remedy['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">Full Database Record (remedies)</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 kv-table">
                                <tbody>
                                <?php foreach ($remedy as $key => $value): ?>
                                    <tr>
                                        <th><?php echo htmlspecialchars(normalizeLabel((string)$key)); ?></th>
                                        <td><?php echo renderDbValue($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if (!empty($seoData)): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold">SEO / Marketing Data</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 kv-table">
                                <tbody>
                                <?php foreach ($seoData as $key => $value): ?>
                                    <tr>
                                        <th><?php echo htmlspecialchars(normalizeLabel((string)$key)); ?></th>
                                        <td><?php echo renderDbValue($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white fw-semibold">Orders</div>
                            <div class="card-body">
                                <div class="mb-2"><strong><?php echo (int)$salesStats['orders_count']; ?></strong> total orders</div>
                                <div class="mb-2"><strong><?php echo number_format((float)$salesStats['units_sold'], 3); ?></strong> units sold</div>
                                <div><strong>KES <?php echo number_format((float)$salesStats['total_sales'], 2); ?></strong> total sales</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white fw-semibold">Recent Order Items</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 table-orders">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentOrders)): ?>
                                                <tr><td colspan="6" class="text-muted text-center">No order items found.</td></tr>
                                            <?php else: foreach ($recentOrders as $ord): ?>
                                                <tr>
                                                    <td data-label="Order"><?php echo htmlspecialchars((string)($ord['order_number'] ?? $ord['order_id'])); ?></td>
                                                    <td data-label="Customer"><?php echo htmlspecialchars((string)($ord['customer_name'] ?? '-')); ?></td>
                                                    <td data-label="Qty"><?php echo number_format((float)($ord['quantity'] ?? 0), 3); ?></td>
                                                    <td data-label="Total">KES <?php echo number_format((float)($ord['total_price'] ?? 0), 2); ?></td>
                                                    <td data-label="Status"><?php echo htmlspecialchars((string)($ord['order_status'] ?? '-')); ?> / <?php echo htmlspecialchars((string)($ord['payment_status'] ?? '-')); ?></td>
                                                    <td data-label="Date"><?php echo htmlspecialchars((string)($ord['created_at'] ?? '-')); ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-white fw-semibold">Stock Movement History</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 table-stock">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Qty Change</th>
                                        <th>Balance</th>
                                        <th>Reference</th>
                                        <th>Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($stockHistory)): ?>
                                        <tr><td colspan="6" class="text-muted text-center">No stock history found.</td></tr>
                                    <?php else: foreach ($stockHistory as $h): ?>
                                        <tr>
                                            <td data-label="Type"><?php echo htmlspecialchars((string)($h['movement_type'] ?? '-')); ?></td>
                                            <td data-label="Qty Change"><?php echo isset($h['qty_change']) ? htmlspecialchars((string)$h['qty_change']) : '-'; ?></td>
                                            <td data-label="Balance"><?php echo isset($h['balance_after']) ? htmlspecialchars((string)$h['balance_after']) : '-'; ?></td>
                                            <td data-label="Reference"><?php echo htmlspecialchars((string)($h['source_ref'] ?? '-')); ?></td>
                                            <td data-label="Date"><?php echo htmlspecialchars((string)($h['movement_at'] ?? '-')); ?></td>
                                            <td data-label="Notes"><?php echo htmlspecialchars((string)($h['notes'] ?? '-')); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
