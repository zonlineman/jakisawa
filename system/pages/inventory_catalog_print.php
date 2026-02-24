<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
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

$searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$whereConditions = ['r.is_active = 1'];
$params = [];
$paramTypes = '';

if ($searchQuery !== '') {
    $whereConditions[] = "(r.name LIKE ? OR r.sku LIKE ? OR r.description LIKE ?)";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sss';
}

if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $whereConditions[] = "r.category_id = ?";
    $params[] = (int)$_GET['category'];
    $paramTypes .= 'i';
}

if (isset($_GET['stock_filter'])) {
    switch ((string)$_GET['stock_filter']) {
        case 'critical':
            $whereConditions[] = "(r.stock_quantity > 0 AND r.stock_quantity <= r.reorder_level)";
            break;
        case 'low':
            $whereConditions[] = "(r.stock_quantity > 0 AND r.stock_quantity <= (r.reorder_level * 1.5))";
            break;
        case 'out_of_stock':
            $whereConditions[] = "r.stock_quantity = 0";
            break;
        case 'adequate':
            $whereConditions[] = "r.stock_quantity > (r.reorder_level * 1.5)";
            break;
    }
}

if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $whereConditions[] = "r.unit_price >= ?";
    $params[] = (float)$_GET['min_price'];
    $paramTypes .= 'd';
}

if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $whereConditions[] = "r.unit_price <= ?";
    $params[] = (float)$_GET['max_price'];
    $paramTypes .= 'd';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$query = "SELECT
            r.id, r.sku, r.name, r.description,
            r.stock_quantity, r.reorder_level,
            r.unit_price, r.discount_price,
            r.image_url,
            c.name AS category_name
          FROM remedies r
          LEFT JOIN categories c ON r.category_id = c.id
          $whereClause
          ORDER BY c.name ASC, r.name ASC";

$rows = [];
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $res = mysqli_query($conn, $query);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
    }
}

$generatedAt = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Catalog</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; background: #f4f7fb; color: #111827; }
        .page { max-width: 1120px; margin: 16px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .header { padding: 18px 20px; color: #fff; background: linear-gradient(135deg, #1d4ed8, #2563eb); }
        .header h1 { margin: 0; font-size: 24px; }
        .header .meta { margin-top: 6px; font-size: 12px; opacity: .95; }
        .content { padding: 16px 20px; }
        .summary { margin-bottom: 10px; color: #4b5563; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; font-size: 12px; word-wrap: break-word; overflow-wrap: anywhere; white-space: normal; }
        th { background: #f8fafc; text-align: left; color: #111827; }
        .imgbox {
            width: 96px;
            height: 96px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            object-fit: cover;
            object-position: center;
            display: block;
            margin: 0 auto;
            background: #f8fafc;
        }
        .pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-weight: 600; font-size: 11px; }
        .in { background: #dcfce7; color: #166534; }
        .out { background: #fee2e2; color: #991b1b; }
        .foot { padding: 10px 20px; background: #f9fafb; color: #6b7280; font-size: 11px; display: flex; justify-content: space-between; gap: 12px; }
        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff; }
            .page { border: none; border-radius: 0; margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <h1>JAKISAWA SHOP - Inventory Catalog</h1>
        <div class="meta">Generated: <?php echo htmlspecialchars($generatedAt); ?></div>
    </div>
    <div class="content">
        <div class="summary">
            Total Items: <strong><?php echo number_format(count($rows)); ?></strong>
            <?php if ($searchQuery !== ''): ?>
                | Search: <strong><?php echo htmlspecialchars($searchQuery); ?></strong>
            <?php endif; ?>
        </div>
        <table>
            <colgroup>
                <col style="width:15%">
                <col style="width:16%">
                <col style="width:10%">
                <col style="width:10%">
                <col style="width:11%">
                <col style="width:38%">
            </colgroup>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Remedy</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;">No remedies found for current filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php $inStock = (float)$row['stock_quantity'] > 0; ?>
                    <tr>
                        <td>
                            <?php if (!empty($row['image_url'])): ?>
                                <img class="imgbox" src="<?php echo htmlspecialchars(resolveRemedyImageUrl((string)$row['image_url'])); ?>" alt="">
                            <?php else: ?>
                                <div class="imgbox"></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700;"><?php echo htmlspecialchars((string)$row['name']); ?></div>
                            <div style="color:#6b7280;">SKU: <?php echo htmlspecialchars((string)$row['sku']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars((string)($row['category_name'] ?? 'Uncategorized')); ?></td>
                        <td>
                            <span class="pill <?php echo $inStock ? 'in' : 'out'; ?>">
                                <?php echo $inStock ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                        </td>
                        <td>
                            <div><strong>KES <?php echo number_format((float)$row['unit_price'], 2); ?></strong></div>
                            <?php if (!empty($row['discount_price']) && (float)$row['discount_price'] > 0): ?>
                                <div style="color:#6b7280;">Discount: KES <?php echo number_format((float)$row['discount_price'], 2); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)($row['description'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="foot">
        <span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span>
        <span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span>
    </div>
</div>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 120);
});
</script>
</body>
</html>
