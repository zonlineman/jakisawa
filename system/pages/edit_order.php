<?php
define('BASE_PATH', dirname(__DIR__));
require_once dirname(__DIR__, 2) . '/config/paths.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', SYSTEM_BASE_URL);
}

require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/role_permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$rawRole = (string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim((string)preg_replace('/[\s-]+/', '_', $rawRole)));
if ($normalizedRole === 'superadmin') {
    $normalizedRole = 'super_admin';
}

if (!in_array($normalizedRole, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo 'Only admin or super admin can edit orders.';
    exit;
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo 'Invalid order ID.';
    exit;
}

$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$itemsStmt = $pdo->prepare("
    SELECT oi.id, oi.order_id, oi.product_id, oi.product_name, oi.product_sku, oi.unit_price, oi.quantity, oi.total_price,
           r.stock_quantity
    FROM order_items oi
    LEFT JOIN remedies r ON r.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$productsStmt = $pdo->query("
    SELECT id, name, sku, unit_price, stock_quantity
    FROM remedies
    WHERE is_active = 1
    ORDER BY name ASC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order #<?php echo (int)$order['id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
        .edit-order-navbar {
            background: linear-gradient(135deg, #2f6f4e, #3f8b63);
            box-shadow: 0 6px 18px rgba(15, 23, 42, .14);
        }
        .edit-order-navbar .navbar-brand {
            font-weight: 700;
            letter-spacing: .2px;
        }
        .edit-order-navbar .nav-link {
            color: rgba(255, 255, 255, .92) !important;
            font-weight: 500;
        }
        .edit-order-navbar .nav-link:hover,
        .edit-order-navbar .nav-link.active {
            color: #fff !important;
        }
        .shell { max-width: 1200px; margin: 24px auto; padding: 0 12px; }
        .cardx { background: #fff; border: 1px solid #e7ecf3; border-radius: 12px; box-shadow: 0 8px 26px rgba(15,23,42,.06); }
        .cardx-h { padding: 16px 18px; border-bottom: 1px solid #edf2f7; font-weight: 700; color: #0f172a; }
        .cardx-b { padding: 16px 18px; }
        .totals-row { display:flex; justify-content:space-between; margin-bottom:8px; }
        .totals-row.total { font-size:1.1rem; font-weight:700; border-top:1px solid #e8edf4; padding-top:10px; }
        .table td, .table th { vertical-align: middle; }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark edit-order-navbar">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin_dashboard.php?page=dashboard">
            <i class="fas fa-leaf me-2"></i>JAKISAWA SHOP
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#editOrderNavbar" aria-controls="editOrderNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="editOrderNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/admin_dashboard.php?page=dashboard">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo BASE_URL; ?>/admin_dashboard.php?page=orders">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/admin_dashboard.php?page=inventory">Inventory</a>
                </li>
            </ul>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Exit
            </a>
        </div>
    </div>
</nav>
<div class="shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-pen me-2"></i>Edit Order <?php echo htmlspecialchars($order['order_number']); ?></h4>
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/admin_dashboard.php?page=orders">
            <i class="fas fa-arrow-left me-1"></i> Back to Orders
        </a>
    </div>

    <form id="editOrderForm">
        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
        <input type="hidden" name="order_items" id="orderItemsHidden" value="[]">
        <input type="hidden" name="removed_items" id="removedItemsHidden" value="[]">
        <input type="hidden" name="subtotal" id="subtotalHidden" value="0">
        <input type="hidden" name="total_amount" id="totalAmountHidden" value="0">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="cardx mb-3">
                    <div class="cardx-h">Customer & Shipping</div>
                    <div class="cardx-b">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name</label>
                                <input class="form-control" name="customer_name" required value="<?php echo htmlspecialchars($order['customer_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Email</label>
                                <input class="form-control" type="email" name="customer_email" required value="<?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Phone</label>
                                <input class="form-control" name="customer_phone" required value="<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alternative Phone</label>
                                <input class="form-control" name="customer_alt_phone" value="<?php echo htmlspecialchars($order['customer_alt_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Shipping Address</label>
                                <textarea class="form-control" name="shipping_address" rows="2" required><?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Shipping City</label>
                                <input class="form-control" name="shipping_city" required value="<?php echo htmlspecialchars($order['shipping_city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input class="form-control" name="shipping_postal_code" value="<?php echo htmlspecialchars($order['shipping_postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cardx mb-3">
                    <div class="cardx-h">Order Items (Admin/Super Admin can add/remove/update price)</div>
                    <div class="cardx-b">
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <select id="productSelect" class="form-control">
                                    <option value="">Select item to add</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                            data-price="<?php echo (float)$p['unit_price']; ?>"
                                            data-stock="<?php echo (int)$p['stock_quantity']; ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> - KES <?php echo number_format((float)$p['unit_price'], 2); ?> (Stock: <?php echo (int)$p['stock_quantity']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2"><input type="number" min="1" id="productQty" class="form-control" value="1"></div>
                            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" onclick="addItem()"><i class="fas fa-plus me-1"></i>Add</button></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="itemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th width="120">Quantity</th>
                                        <th width="160">Unit Price</th>
                                        <th width="150">Line Total</th>
                                        <th width="90">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="cardx mb-3">
                    <div class="cardx-h">Payment & Status</div>
                    <div class="cardx-b">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-control" name="payment_method">
                                <?php
                                $methods = ['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash', 'card' => 'Card'];
                                foreach ($methods as $k => $lbl):
                                ?>
                                <option value="<?php echo $k; ?>" <?php echo (($order['payment_method'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-control" name="payment_status">
                                <?php foreach (['pending','paid','failed','refunded'] as $ps): ?>
                                <option value="<?php echo $ps; ?>" <?php echo (($order['payment_status'] ?? '') === $ps) ? 'selected' : ''; ?>><?php echo ucfirst($ps); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <select class="form-control" name="order_status">
                                <?php foreach (['pending','processing','shipped','delivered','completed','cancelled'] as $os): ?>
                                <option value="<?php echo $os; ?>" <?php echo (($order['order_status'] ?? '') === $os) ? 'selected' : ''; ?>><?php echo ucfirst($os); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transaction ID</label>
                            <input class="form-control" name="transaction_id" value="<?php echo htmlspecialchars($order['transaction_id'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="cardx mb-3">
                    <div class="cardx-h">Totals</div>
                    <div class="cardx-b">
                        <div class="totals-row"><span>Subtotal</span><strong id="subtotalText">KES 0.00</strong></div>
                        <div class="totals-row">
                            <span>Shipping Fee</span>
                            <input type="number" class="form-control form-control-sm" name="shipping_fee" id="shippingFee" step="0.01" min="0" value="<?php echo (float)($order['shipping_fee'] ?? 0); ?>" style="width:130px;">
                        </div>
                        <div class="totals-row">
                            <span>Discount</span>
                            <input type="number" class="form-control form-control-sm" name="discount_amount" id="discountAmount" step="0.01" min="0" value="<?php echo (float)($order['discount_amount'] ?? 0); ?>" style="width:130px;">
                        </div>
                        <div class="totals-row total"><span>Total</span><span id="totalText">KES 0.00</span></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
const productsById = {};
<?php foreach ($products as $p): ?>
productsById[<?php echo (int)$p['id']; ?>] = {
    id: <?php echo (int)$p['id']; ?>,
    name: <?php echo json_encode($p['name']); ?>,
    unit_price: <?php echo (float)$p['unit_price']; ?>,
    stock_quantity: <?php echo (int)$p['stock_quantity']; ?>
};
<?php endforeach; ?>

let removedItems = [];
let orderItems = <?php echo json_encode(array_map(function ($i) {
    return [
        'id' => (int)$i['id'],
        'product_id' => (int)$i['product_id'],
        'product_name' => (string)$i['product_name'],
        'quantity' => (int)$i['quantity'],
        'unit_price' => (float)$i['unit_price'],
        'total' => (float)$i['total_price']
    ];
}, $orderItems), JSON_UNESCAPED_UNICODE); ?>;

function money(v) { return 'KES ' + Number(v).toFixed(2); }

function renderItems() {
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    if (!orderItems.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No items left. Add at least one item.</td></tr>';
    } else {
        orderItems.forEach((item, idx) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.product_name}</td>
                <td><input type="number" class="form-control form-control-sm" min="1" value="${item.quantity}" onchange="updateQty(${idx}, this.value)"></td>
                <td><input type="number" class="form-control form-control-sm" min="0" step="0.01" value="${item.unit_price}" onchange="updatePrice(${idx}, this.value)"></td>
                <td><strong>${money(item.total)}</strong></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${idx})"><i class="fas fa-trash"></i></button></td>
            `;
            body.appendChild(row);
        });
    }
    recalcTotals();
}

function recalcTotals() {
    const subtotal = orderItems.reduce((s, it) => s + (Number(it.quantity) * Number(it.unit_price)), 0);
    const shipping = Number(document.getElementById('shippingFee').value || 0);
    const discount = Number(document.getElementById('discountAmount').value || 0);
    const total = Math.max(0, subtotal + shipping - discount);

    document.getElementById('subtotalText').textContent = money(subtotal);
    document.getElementById('totalText').textContent = money(total);
    document.getElementById('subtotalHidden').value = subtotal.toFixed(2);
    document.getElementById('totalAmountHidden').value = total.toFixed(2);
    document.getElementById('orderItemsHidden').value = JSON.stringify(orderItems);
    document.getElementById('removedItemsHidden').value = JSON.stringify(removedItems);
}

function updateQty(idx, value) {
    const qty = Math.max(1, parseInt(value || 1, 10));
    orderItems[idx].quantity = qty;
    orderItems[idx].total = qty * Number(orderItems[idx].unit_price);
    renderItems();
}

function updatePrice(idx, value) {
    const price = Math.max(0, Number(value || 0));
    orderItems[idx].unit_price = price;
    orderItems[idx].total = Number(orderItems[idx].quantity) * price;
    renderItems();
}

function removeItem(idx) {
    const item = orderItems[idx];
    if (item.id) {
        removedItems.push(item.id);
    }
    orderItems.splice(idx, 1);
    renderItems();
}

function addItem() {
    const select = document.getElementById('productSelect');
    const productId = Number(select.value || 0);
    const qty = Math.max(1, parseInt(document.getElementById('productQty').value || 1, 10));
    if (!productId || !productsById[productId]) {
        Swal.fire('Select product', 'Choose a product to add.', 'warning');
        return;
    }
    const p = productsById[productId];
    const existing = orderItems.find(i => !i.id && Number(i.product_id) === productId);
    if (existing) {
        existing.quantity += qty;
        existing.total = existing.quantity * Number(existing.unit_price);
    } else {
        orderItems.push({
            product_id: p.id,
            product_name: p.name,
            quantity: qty,
            unit_price: Number(p.unit_price),
            total: Number(p.unit_price) * qty
        });
    }
    select.value = '';
    document.getElementById('productQty').value = '1';
    renderItems();
}

document.getElementById('shippingFee').addEventListener('input', recalcTotals);
document.getElementById('discountAmount').addEventListener('input', recalcTotals);

document.getElementById('editOrderForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!orderItems.length) {
        Swal.fire('Missing items', 'Add at least one order item.', 'warning');
        return;
    }
    recalcTotals();
    const formData = new FormData(this);
    fetch('update_order.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Saved', data.message || 'Order updated successfully', 'success')
                .then(() => window.location.href = BASE_URL + '/admin_dashboard.php?page=orders');
        } else {
            Swal.fire('Error', data.message || 'Could not update order', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Request failed', 'error'));
});

$('.form-control').filter('select').select2({ width: '100%' });
renderItems();
</script>
</body>
</html>
