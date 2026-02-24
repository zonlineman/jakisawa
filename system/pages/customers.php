<?php
/**
 * Customers Management (Dashboard Embedded Page)
 * Admin-only page. Parent dashboard starts session and handles layout shell.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}
require_once dirname(__DIR__, 2) . '/config/paths.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', SYSTEM_BASE_URL);
}

$adminRoleNormalized = strtolower((string) ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
if (!in_array($adminRoleNormalized, ['admin', 'super_admin'], true)) {
    echo '<div class="alert alert-danger">Access denied. This page is available to administrators only.</div>';
    return;
}

require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/pages/actions/CustomerActions.php';

$customerActions = new CustomerActions($pdo);
$registeredCustomers = $customerActions->getRegisteredCustomers(200);
$statistics = $customerActions->getStatistics();
?>

<div class="top-bar">
    <h1 class="page-title">
        <i class="bi bi-people-fill"></i>
        Customers Management
    </h1>
    <div class="btn-toolbar d-flex gap-2">
        <button type="button" id="topSendEmailBtn" class="btn btn-outline-primary btn-sm btn-email-action" onclick="sendEmail('')">
            <i class="bi bi-envelope me-1"></i>Send Email
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printCustomersReport()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<style>
    .service-muted {
        opacity: 0.55;
        filter: grayscale(0.2);
        transition: opacity 0.2s ease;
    }
    .customers-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .customers-toolbar .form-control,
    .customers-toolbar .form-select {
        min-width: 170px;
        flex: 1 1 220px;
        max-width: 320px;
    }
    .customer-action-group {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 260px;
    }
    .customer-action-group .btn {
        min-width: 34px;
        height: 32px;
        padding: 4px 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    @media (max-width: 992px) {
        .customer-action-group {
            min-width: 0;
            justify-content: flex-start;
        }
    }
</style>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Registered</div>
                <div class="fs-4 fw-semibold"><?php echo number_format((int) ($statistics['total_registered'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Active</div>
                <div class="fs-4 fw-semibold"><?php echo number_format((int) ($statistics['active_customers'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pending Approval</div>
                <div class="fs-4 fw-semibold"><?php echo number_format((int) ($statistics['pending_approvals'] ?? 0)); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Revenue</div>
                <div class="fs-5 fw-semibold">Ksh <?php echo number_format((float) ($statistics['total_revenue'] ?? 0), 0); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3" id="commHealthPanel">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Communication Health Check</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadCommunicationHealth(true)">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <div class="border rounded p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>SMTP / Email</strong>
                        <span id="smtpHealthBadge" class="badge bg-secondary">Checking...</span>
                    </div>
                    <div class="mt-1">
                        <span id="smtpUnavailableBadge" class="badge bg-danger d-none">Email service unavailable</span>
                    </div>
                    <div id="smtpHealthDetails" class="small text-muted mt-2">Running checks...</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>SMS</strong>
                        <span id="smsHealthBadge" class="badge bg-secondary">Checking...</span>
                    </div>
                    <div class="mt-1">
                        <span id="smsUnavailableBadge" class="badge bg-danger d-none">SMS service unavailable</span>
                    </div>
                    <div id="smsHealthDetails" class="small text-muted mt-2">Running checks...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="customers-toolbar mb-3">
            <input type="text" id="searchRegistered" class="form-control" style="max-width: 280px;" placeholder="Search customers...">
            <select id="filterRegistered" class="form-select" style="max-width: 180px;">
                <option value="">All Approvals</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
            </select>
            <button type="button" class="btn btn-outline-success btn-sm" onclick="bulkApprove()">
                <i class="bi bi-check2-circle me-1"></i>Bulk Approve
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="bulkActivate()">
                <i class="bi bi-play-circle me-1"></i>Bulk Activate
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="bulkDeactivate()">
                <i class="bi bi-slash-circle me-1"></i>Bulk Deactivate
            </button>
            <button type="button" id="bulkSendEmailBtn" class="btn btn-outline-dark btn-sm btn-email-action" onclick="bulkSendEmail()">
                <i class="bi bi-envelope-paper me-1"></i>Bulk Email
            </button>
            <button type="button" id="bulkSendSmsBtn" class="btn btn-outline-secondary btn-sm btn-sms-action" onclick="bulkSendSMS()">
                <i class="bi bi-chat-left-text me-1"></i>Bulk SMS
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i>Bulk Delete
            </button>
        </div>

        <?php if (empty($registeredCustomers)): ?>
            <div class="alert alert-info mb-0">No registered customers found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tableRegistered">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                            <th data-sort-col="1" data-sort-type="number" style="cursor:pointer;">ID <span class="sort-indicator"></span></th>
                            <th data-sort-col="2" data-sort-type="text" style="cursor:pointer;">Name <span class="sort-indicator"></span></th>
                            <th data-sort-col="3" data-sort-type="text" style="cursor:pointer;">Email <span class="sort-indicator"></span></th>
                            <th data-sort-col="4" data-sort-type="text" style="cursor:pointer;">Phone <span class="sort-indicator"></span></th>
                            <th data-sort-col="5" data-sort-type="text" style="cursor:pointer;">Status <span class="sort-indicator"></span></th>
                            <th data-sort-col="6" data-sort-type="text" style="cursor:pointer;">Approval <span class="sort-indicator"></span></th>
                            <th data-sort-col="7" data-sort-type="date" style="cursor:pointer;">Registered <span class="sort-indicator"></span></th>
                            <th data-sort-col="8" data-sort-type="number" style="cursor:pointer;">Orders <span class="sort-indicator"></span></th>
                            <th data-sort-col="9" data-sort-type="number" style="cursor:pointer;">Total Spent <span class="sort-indicator"></span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredCustomers as $customer): ?>
                            <tr data-id="<?php echo (int) $customer['id']; ?>" data-approval="<?php echo htmlspecialchars((string) $customer['approval_status']); ?>">
                                <td><input type="checkbox" class="customer-checkbox" value="<?php echo (int) $customer['id']; ?>"></td>
                                <td>#<?php echo (int) $customer['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $customer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $customer['email']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($customer['phone'] ?? '')); ?></td>
                                <td>
                                    <?php $isActive = (int) ($customer['is_active'] ?? 0) === 1; ?>
                                    <span class="badge <?php echo $isActive ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $approval = strtolower((string) ($customer['approval_status'] ?? 'pending')); ?>
                                    <span class="badge <?php echo $approval === 'approved' ? 'bg-success' : ($approval === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                        <?php echo ucfirst($approval); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($customer['registration_date']) ? date('M d, Y', strtotime((string) $customer['registration_date'])) : '-'; ?></td>
                                <td><?php echo (int) ($customer['total_orders'] ?? 0); ?></td>
                                <td>Ksh <?php echo number_format((float) ($customer['total_spent'] ?? 0), 0); ?></td>
                                <td>
                                    <div class="customer-action-group">
                                    <?php if ($approval === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="approveCustomer(<?php echo (int) $customer['id']; ?>)" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectCustomer(<?php echo (int) $customer['id']; ?>)" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-sm btn-info" onclick="viewCustomer('registered', <?php echo (int) $customer['id']; ?>)" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-dark btn-email-action" onclick="sendEmail('<?php echo htmlspecialchars((string) $customer['email'], ENT_QUOTES); ?>')" title="Send Email">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary btn-sms-action" onclick="sendSMS('<?php echo htmlspecialchars((string) ($customer['phone'] ?? ''), ENT_QUOTES); ?>')" title="Send SMS">
                                        <i class="bi bi-chat-left-text"></i>
                                    </button>

                                    <?php if ($isActive): ?>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="deactivateCustomer(<?php echo (int) $customer['id']; ?>)" title="Deactivate">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="activateCustomer(<?php echo (int) $customer['id']; ?>)" title="Activate">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ((int) ($customer['total_orders'] ?? 0) > 0): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="viewOrders(<?php echo (int) $customer['id']; ?>)" title="Orders">
                                            <i class="bi bi-cart3"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-sm btn-secondary" onclick="printSingleCustomer(this)" title="Print Customer">
                                        <i class="bi bi-printer"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCustomer(<?php echo (int) $customer['id']; ?>, '<?php echo htmlspecialchars((string) $customer['full_name'], ENT_QUOTES); ?>')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/pages/partials/cms_modals.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const AJAX_URL = BASE_URL + '/pages/actions/ajax/customer_actions.php';

    function triggerPrintDocument(html, title = 'Print') {
        const oldFrame = document.getElementById('customers-print-frame');
        if (oldFrame) oldFrame.remove();

        const iframe = document.createElement('iframe');
        iframe.id = 'customers-print-frame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.setAttribute('aria-hidden', 'true');
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
        doc.title = title;

        let printed = false;
        const doPrint = () => {
            if (printed) return;
            printed = true;
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                console.error('Print failed:', e);
                alert('Unable to open print dialog. Please allow popups/printing for this site.');
            }
            cleanup();
        };

        const cleanup = () => {
            setTimeout(() => {
                if (iframe && iframe.parentNode) iframe.parentNode.removeChild(iframe);
            }, 250);
        };

        iframe.onload = () => {
            setTimeout(doPrint, 120);
        };

        // Fallback for browsers where iframe onload is unreliable after document.write
        setTimeout(doPrint, 420);
    }

    function printCustomersReport() {
        const table = document.getElementById('tableRegistered');
        if (!table) {
            alert('No customer table found to print.');
            return;
        }

        const statCards = document.querySelectorAll('.row.g-3.mb-3 .card .card-body');
        const stats = [];
        statCards.forEach((card) => {
            const label = (card.querySelector('.text-muted.small')?.textContent || '').trim();
            const value = (card.querySelector('.fw-semibold')?.textContent || '').trim();
            if (label && value) {
                stats.push({ label, value });
            }
        });

        const bodyRows = [];
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row) => {
            if (row.style.display === 'none') return; // print only currently visible rows
            const cells = row.querySelectorAll('td');
            if (cells.length < 10) return;
            bodyRows.push(`
                <tr>
                    <td>${cells[1].textContent.trim()}</td>
                    <td>${cells[2].textContent.trim()}</td>
                    <td>${cells[3].textContent.trim()}</td>
                    <td>${cells[4].textContent.trim()}</td>
                    <td>${cells[5].textContent.trim()}</td>
                    <td>${cells[6].textContent.trim()}</td>
                    <td>${cells[7].textContent.trim()}</td>
                    <td>${cells[8].textContent.trim()}</td>
                    <td>${cells[9].textContent.trim()}</td>
                </tr>
            `);
        });

        const now = new Date();
        const generatedAt = now.toLocaleString();

        const statHtml = stats.map((s) => `
            <div class="stat-box">
                <div class="stat-label">${s.label}</div>
                <div class="stat-value">${s.value}</div>
            </div>
        `).join('');

        const reportHtml = `
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customers Report</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 24px; font-family: "Segoe UI", Arial, sans-serif; color: #1f2937; background: #f3f4f6; }
    .report { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .header { padding: 20px 24px; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; }
    .title { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: .2px; }
    .subtitle { margin: 4px 0 0; opacity: .95; font-size: 13px; }
    .section { padding: 18px 24px; }
    .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .stat-box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; background: #fafafa; }
    .stat-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
    .stat-value { font-size: 16px; font-weight: 700; color: #111827; }
    .meta { font-size: 12px; color: #6b7280; margin-top: 8px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f9fafb; color: #374151; font-weight: 700; font-size: 12px; padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    tbody td { padding: 9px 8px; border-bottom: 1px solid #f0f0f0; font-size: 12px; vertical-align: top; }
    tbody tr:nth-child(even) { background: #fcfcfd; }
    .footer { padding: 12px 24px; background: #f9fafb; color: #6b7280; font-size: 11px; display: flex; justify-content: space-between; }
    @media print {
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { background: #fff; padding: 0; }
        .report { border: none; border-radius: 0; }
        .section { padding: 12px 14px; }
        .header { padding: 14px; }
        .title { font-size: 18px; }
        .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
</style>
</head>
<body>
    <div class="report">
        <div class="header">
            <h1 class="title">JAKISAWA SHOP - Customers Report</h1>
            <div class="subtitle">Generated: ${generatedAt}</div>
        </div>
        <div class="section">
            <div class="stats">${statHtml}</div>
            <div class="meta">Visible customer rows: ${bodyRows.length}</div>
        </div>
        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Registered</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    ${bodyRows.length ? bodyRows.join('') : '<tr><td colspan="9">No customers to print.</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="footer">
            <span>JAKISAWA SHOP</span>
            <span>Confidential Internal Report</span>
        </div>
    </div>
</body>
</html>`;
        triggerPrintDocument(reportHtml, 'Customers Report');
    }

    function printSingleCustomer(button) {
        const row = button.closest('tr');
        if (!row) return;

        const cells = row.querySelectorAll('td');
        if (cells.length < 10) return;

        const data = {
            id: cells[1].textContent.trim(),
            name: cells[2].textContent.trim(),
            email: cells[3].textContent.trim(),
            phone: cells[4].textContent.trim(),
            status: cells[5].textContent.trim(),
            approval: cells[6].textContent.trim(),
            registered: cells[7].textContent.trim(),
            orders: cells[8].textContent.trim(),
            totalSpent: cells[9].textContent.trim()
        };

        const generatedAt = new Date().toLocaleString();
        const html = `
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Print</title>
<style>
    body { margin: 0; padding: 20px; font-family: "Segoe UI", Arial, sans-serif; background: #f3f4f6; color: #111827; }
    .card { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .header { padding: 18px 20px; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; }
    .header h2 { margin: 0; font-size: 22px; }
    .header p { margin: 5px 0 0; font-size: 12px; opacity: .95; }
    .body { padding: 20px; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fafafa; }
    .label { font-size: 12px; color: #6b7280; margin-bottom: 3px; }
    .value { font-size: 14px; font-weight: 600; color: #111827; word-break: break-word; }
    .footer { padding: 12px 20px; font-size: 11px; color: #6b7280; border-top: 1px solid #eef0f2; display: flex; justify-content: space-between; }
    @media print {
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { background: #fff; padding: 0; }
        .card { border: none; border-radius: 0; max-width: 100%; }
    }
</style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2>JAKISAWA SHOP - Customer Profile</h2>
            <p>Generated: ${generatedAt}</p>
        </div>
        <div class="body">
            <div class="grid">
                <div class="item"><div class="label">Customer ID</div><div class="value">${data.id}</div></div>
                <div class="item"><div class="label">Name</div><div class="value">${data.name}</div></div>
                <div class="item"><div class="label">Email</div><div class="value">${data.email}</div></div>
                <div class="item"><div class="label">Phone</div><div class="value">${data.phone || '-'}</div></div>
                <div class="item"><div class="label">Status</div><div class="value">${data.status}</div></div>
                <div class="item"><div class="label">Approval</div><div class="value">${data.approval}</div></div>
                <div class="item"><div class="label">Registered On</div><div class="value">${data.registered}</div></div>
                <div class="item"><div class="label">Total Orders</div><div class="value">${data.orders}</div></div>
                <div class="item"><div class="label">Total Spent</div><div class="value">${data.totalSpent}</div></div>
            </div>
        </div>
        <div class="footer">
            <span>JAKISAWA SHOP</span>
            <span>Customer Record</span>
        </div>
    </div>
</body>
</html>`;
        triggerPrintDocument(html, 'Customer Print');
    }
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/cms.js?v=<?php echo time(); ?>"></script>
