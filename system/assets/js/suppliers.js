const SUPPLIER_AJAX_URL = window.SUPPLIER_AJAX_URL || 'pages/actions/ajax/supplier_actions.php';
let bulkEmailRecipients = [];
let bulkSmsRecipients = [];
let communicationHealthState = { smtpReady: false, smsReady: false };

function showAlert(type, message) {
  const n = document.createElement('div');
  n.className = `alert alert-${type} alert-dismissible fade show mt-2`;
  n.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  (document.querySelector('.content-wrapper') || document.body).prepend(n);
  setTimeout(() => n.remove(), 5000);
}
const showSuccess = (m) => showAlert('success', m);
const showError = (m) => showAlert('danger', m);

function openModalById(id){
  const el = document.getElementById(id);
  if (!el) return;
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    new bootstrap.Modal(el).show();
    return;
  }
  if (typeof window.jQuery !== 'undefined' && window.jQuery(el).modal) {
    window.jQuery(el).modal('show');
    return;
  }
  // Plain JS fallback when no modal engine is present.
  el.style.display = 'block';
  el.classList.add('show');
  el.removeAttribute('aria-hidden');
  el.setAttribute('data-custom-open', '1');
  document.body.classList.add('modal-open');

  let backdrop = document.querySelector('.modal-backdrop.custom-fallback');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show custom-fallback';
    document.body.appendChild(backdrop);
  }

  el.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach((btn) => {
    btn.onclick = function() { closeModalById(id); };
  });
}

function closeModalById(id){
  const el = document.getElementById(id);
  if (!el) return;
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const inst = bootstrap.Modal.getInstance(el);
    if (inst) inst.hide();
    return;
  }
  if (typeof window.jQuery !== 'undefined' && window.jQuery(el).modal) {
    window.jQuery(el).modal('hide');
    return;
  }
  // Plain JS fallback close.
  if (el.getAttribute('data-custom-open') === '1') {
    el.style.display = 'none';
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
    el.removeAttribute('data-custom-open');
    const backdrop = document.querySelector('.modal-backdrop.custom-fallback');
    if (backdrop) backdrop.remove();
    document.body.classList.remove('modal-open');
  }
}

(function initSuppliersPage(){
  const selectAll = document.getElementById('selectAll');
  if (selectAll) selectAll.addEventListener('change', function(){ document.querySelectorAll('.supplier-checkbox').forEach(cb => cb.checked = this.checked); });
  const rows = document.querySelectorAll('#suppliersTable tbody tr');
  document.querySelectorAll('#suppliersTable thead th[data-sort-col]').forEach((th) => {
    th.addEventListener('click', () => {
      const i = parseInt(th.getAttribute('data-sort-col'), 10);
      const asc = th.getAttribute('data-sort-dir') !== 'asc';
      th.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
      [...rows].sort((a,b)=>{
        const at=(a.cells[i]?.innerText||'').trim().toLowerCase();
        const bt=(b.cells[i]?.innerText||'').trim().toLowerCase();
        const an=parseFloat(at.replace(/[^\d.-]/g,''));
        const bn=parseFloat(bt.replace(/[^\d.-]/g,''));
        const cmp = (!Number.isNaN(an)&&!Number.isNaN(bn)) ? an-bn : at.localeCompare(bt);
        return asc ? cmp : -cmp;
      }).forEach(r=>r.parentNode.appendChild(r));
    });
  });
  loadCommunicationHealth(false);
})();

function getSelectedSuppliers(){ return Array.from(document.querySelectorAll('.supplier-checkbox:checked')).map(cb=>cb.value); }
function postAjax(action, payload){
  const body = new URLSearchParams();
  body.append('action', action);
  Object.entries(payload || {}).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.forEach((item) => body.append(`${key}[]`, String(item)));
    } else if (value !== undefined && value !== null) {
      body.append(key, String(value));
    }
  });
  return fetch(SUPPLIER_AJAX_URL, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
    body: body.toString()
  }).then(async (r) => {
    const raw = await r.text();
    const clean = (raw || '').replace(/^\uFEFF/, '').trim();
    try {
      return JSON.parse(clean);
    } catch (e) {
      return {
        success: false,
        message: clean ? `Unexpected response: ${clean.slice(0, 200)}` : 'Empty server response'
      };
    }
  });
}

function toggleSupplierStatus(id, currentStatus){
  const op = currentStatus ? 'deactivate' : 'activate';
  if(!confirm(currentStatus ? 'Deactivate this supplier?' : 'Activate this supplier?')) return;
  postAjax(op, { supplier_id:id }).then(r=>{ if(r.success){showSuccess(r.message||'Updated'); setTimeout(()=>location.reload(), 600);} else showError(r.message||'Update failed'); }).catch(()=>showError('Update failed'));
}
function confirmDelete(id){
  const input = document.getElementById('deleteId');
  if (!input) return;
  input.value = id;
  const cb = document.getElementById('confirmDelete');
  if (cb) cb.checked = false;
  const btn = document.getElementById('confirmDeleteBtn');
  if (btn) btn.disabled = true;
  openModalById('deleteConfirmModal');
}
function bulkActivate(){ runBulkAction('activate', 'Activate selected suppliers?'); }
function bulkDeactivate(){ runBulkAction('deactivate', 'Deactivate selected suppliers?'); }
function bulkDelete(){ runBulkAction('delete', 'Delete selected suppliers? This cannot be undone.'); }
function runBulkAction(operation, confirmText){
  const ids = getSelectedSuppliers(); if(!ids.length){ showError('Please select suppliers'); return; }
  if(!confirm(confirmText)) return;
  postAjax('bulk_action', { operation, ids }).then(r=>{ if(r.success){ showSuccess(r.message||'Bulk action completed'); setTimeout(()=>location.reload(),700);} else showError(r.message||'Bulk action failed'); }).catch(()=>showError('Bulk action failed'));
}

function sendEmail(email){
  bulkEmailRecipients = [];
  const to = document.getElementById('supplierEmailTo');
  to.value = email || ''; to.readOnly = false; to.setAttribute('data-bulk','0');
  document.getElementById('supplierEmailSubject').value = '';
  document.getElementById('supplierEmailMessage').value = '';
  openModalById('supplierEmailModal');
}
function bulkSendEmail(){
  const ids = getSelectedSuppliers(); if(!ids.length){ showError('Please select suppliers'); return; }
  const emails = [];
  ids.forEach(id=>{ const em=(document.querySelector(`#suppliersTable tr[data-supplier-id="${id}"] .supplier-email`)?.textContent||'').trim(); if(em && em!=='-' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) emails.push(em); });
  bulkEmailRecipients = Array.from(new Set(emails)); if(!bulkEmailRecipients.length){ showError('No valid email addresses in selection'); return; }
  const to = document.getElementById('supplierEmailTo');
  to.value = bulkEmailRecipients.join(', '); to.readOnly = true; to.setAttribute('data-bulk','1');
  document.getElementById('supplierEmailSubject').value=''; document.getElementById('supplierEmailMessage').value='';
  openModalById('supplierEmailModal');
}
function submitEmail(){
  const to = document.getElementById('supplierEmailTo');
  const subject = document.getElementById('supplierEmailSubject').value.trim();
  const message = document.getElementById('supplierEmailMessage').value.trim();
  const isBulk = to.getAttribute('data-bulk') === '1';
  if(!subject || !message){ showError('Subject and message are required'); return; }
  const payload = isBulk ? { emails: bulkEmailRecipients, subject, message } : { email: to.value.trim(), subject, message };
  postAjax(isBulk ? 'send_bulk_email' : 'send_email', payload).then(r=>{ if(r.success){ closeModalById('supplierEmailModal'); showSuccess(r.message||'Email sent'); } else showError(r.message||'Email failed'); }).catch(()=>showError('Email failed'));
}

function sendSMS(phone){
  bulkSmsRecipients = [];
  const to = document.getElementById('supplierSmsTo');
  to.value = phone || ''; to.readOnly = false; to.setAttribute('data-bulk','0');
  document.getElementById('supplierSmsMessage').value='';
  openModalById('supplierSmsModal');
}
function bulkSendSMS(){
  const ids = getSelectedSuppliers(); if(!ids.length){ showError('Please select suppliers'); return; }
  const phones=[]; ids.forEach(id=>{ const ph=(document.querySelector(`#suppliersTable tr[data-supplier-id="${id}"] .supplier-phone`)?.textContent||'').trim(); if(ph && ph!=='-') phones.push(ph); });
  bulkSmsRecipients = Array.from(new Set(phones)); if(!bulkSmsRecipients.length){ showError('No phone numbers in selection'); return; }
  const to = document.getElementById('supplierSmsTo');
  to.value = bulkSmsRecipients.join(', '); to.readOnly = true; to.setAttribute('data-bulk','1');
  document.getElementById('supplierSmsMessage').value='';
  openModalById('supplierSmsModal');
}
function submitSMS(){
  const to = document.getElementById('supplierSmsTo');
  const message = document.getElementById('supplierSmsMessage').value.trim();
  const isBulk = to.getAttribute('data-bulk') === '1';
  if(!message){ showError('SMS message is required'); return; }
  const payload = isBulk ? { phones: bulkSmsRecipients, message } : { phone: to.value.trim(), message };
  postAjax(isBulk ? 'send_bulk_sms' : 'send_sms', payload).then(r=>{ if(r.success){ closeModalById('supplierSmsModal'); showSuccess(r.message||'SMS sent'); } else showError(r.message||'SMS failed'); }).catch(()=>showError('SMS failed'));
}

function loadCommunicationHealth(showToast){
  postAjax('health_check', {}).then((resp) => {
    if(!resp.success || !resp.data){
      communicationHealthState.smtpReady = false;
      communicationHealthState.smsReady = false;
      applyCommState();
      if(showToast) showError(resp.message || 'Health check failed');
      return;
    }
    renderHealth('smtpHealthBadge','smtpHealthDetails',resp.data.smtp);
    renderHealth('smsHealthBadge','smsHealthDetails',resp.data.sms);
    communicationHealthState.smtpReady = !!resp.data.smtp?.ready;
    communicationHealthState.smsReady = !!resp.data.sms?.ready;
    applyCommState();
    if(showToast) showSuccess('Health check refreshed');
  }).catch(() => {
    communicationHealthState.smtpReady = false;
    communicationHealthState.smsReady = false;
    applyCommState();
    if(showToast) showError('Could not refresh health check');
  });
}
function renderHealth(badgeId, detailsId, block){
  const b=document.getElementById(badgeId), d=document.getElementById(detailsId); if(!b||!d||!block) return;
  b.className='badge '+(block.ready?'bg-success':'bg-danger'); b.textContent=block.ready?'Ready':'Not Ready';
  d.innerHTML=(block.details||[]).map(x=>`<div><span class="${x.ok?'text-success':'text-danger'}">${x.ok?'OK':'Missing'}</span> - ${String(x.label||'')}: ${String(x.value||'')}</div>`).join('');
}
function applyCommState(){
  setActionState('.btn-email-action', communicationHealthState.smtpReady, 'Email service not ready');
  setActionState('.btn-sms-action', communicationHealthState.smsReady, 'SMS service not ready');
  toggleCommBadge('smtpUnavailableBadge', communicationHealthState.smtpReady); toggleCommBadge('emailModalUnavailable', communicationHealthState.smtpReady);
  toggleCommBadge('smsUnavailableBadge', communicationHealthState.smsReady); toggleCommBadge('smsModalUnavailable', communicationHealthState.smsReady);
}
function setActionState(sel, enabled, title){
  document.querySelectorAll(sel).forEach(btn=>{
    btn.disabled = !enabled;
    btn.classList.toggle('service-muted', !enabled);
    if(!enabled) btn.title = title;
  });
}
function toggleCommBadge(id, ready){ const el=document.getElementById(id); if(el) el.classList.toggle('d-none', ready); }

function viewSupplier(id){
  fetch(`pages/actions/ajax/get_suppliers_details.php?id=${id}`).then(async (r)=>{
    const text = await r.text();
    const clean = text.replace(/^\uFEFF/, '').trim();
    try { return JSON.parse(clean); }
    catch (e) { throw new Error(clean.slice(0, 220) || 'Invalid server response'); }
  }).then(data=>{
    if(!data.success||!data.data){ showError(data.error||'Failed to load supplier details.'); return; }
    const s=data.data, prods=Array.isArray(data.products)?data.products:[], st=data.sales_stats||{total_sales:0,total_units_sold:0,order_count:0};
    const rows=prods.length?prods.map(p=>{
      const prev = Number(p.estimated_previous_stock || 0);
      const sold = Number(p.total_sold || 0);
      const rem = Number(p.stock_quantity || 0);
      const restockDate = p.last_restock_date ? new Date(p.last_restock_date).toLocaleString() : '-';
      const restockVal = (p.last_restock_value !== null && p.last_restock_value !== undefined && p.last_restock_value !== '') ? p.last_restock_value : '-';
      return `<tr>
        <td>${p.sku||'-'}</td>
        <td>${p.name||'-'}</td>
        <td>${p.category_name||'-'}</td>
        <td>KES ${Number(p.unit_price||0).toFixed(2)}</td>
        <td>${prev}</td>
        <td>${sold}</td>
        <td>${rem}</td>
        <td><div>${restockDate}</div><small class="text-muted">Stock set: ${restockVal}</small></td>
        <td>${Number(p.is_active)===1?'Active':'Inactive'}</td>
      </tr>`;
    }).join(''):'<tr><td colspan="9" class="text-muted">No products for this supplier.</td></tr>';

    const restockBlocks = prods.map(p => {
      const h = Array.isArray(p.restock_history) ? p.restock_history : [];
      if (!h.length) return `<div class="mb-2"><strong>${p.name||'Product'}:</strong> <span class="text-muted">No restocking records</span></div>`;
      const items = h.map(x => `<li>${x.date ? new Date(x.date).toLocaleString() : '-'} - stock_update:${x.updated_to ?? '-'}</li>`).join('');
      return `<div class="mb-3"><strong>${p.name||'Product'}</strong><ul class="mb-0">${items}</ul></div>`;
    }).join('');

    document.getElementById('supplierDetails').innerHTML = `<div class="p-4"><div class="row g-3"><div class="col-md-6"><table class="table table-sm"><tr><th>ID</th><td>${s.id}</td></tr><tr><th>Name</th><td>${s.name||'-'}</td></tr><tr><th>Contact</th><td>${s.contact_person||'-'}</td></tr><tr><th>Email</th><td>${s.email||'-'}</td></tr><tr><th>Phone</th><td>${s.phone||'-'}</td></tr><tr><th>Address</th><td>${s.address||'-'}</td></tr></table></div><div class="col-md-6"><table class="table table-sm"><tr><th>Status</th><td>${Number(s.is_active)===1?'Active':'Inactive'}</td></tr><tr><th>Products</th><td>${s.product_count??0}</td></tr><tr><th>Low Stock</th><td>${s.low_stock_count??0}</td></tr><tr><th>Total Sales</th><td>KES ${Number(st.total_sales||0).toFixed(2)}</td></tr><tr><th>Units Sold</th><td>${st.total_units_sold||0}</td></tr><tr><th>Orders</th><td>${st.order_count||0}</td></tr></table></div></div><h6 class="mt-3">Products Stock Intelligence</h6><div class="table-responsive"><table class="table table-bordered table-sm mb-0"><thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Previous Stock</th><th>Sold</th><th>Remaining</th><th>Latest Restock</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div><h6 class="mt-3">Subsequent Restocking Data</h6><div>${restockBlocks || '<span class="text-muted">No restocking records found.</span>'}</div></div>`;
    openModalById('viewSupplierModal');
  }).catch((e)=>showError(e.message || 'Failed to load supplier details.'));
}
function editSupplier(id,event){
  const btn=event?.target?.closest('button'); const h=btn?btn.innerHTML:''; if(btn){btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i>';}
  fetch(`pages/actions/ajax/get_suppliers_details.php?id=${id}`).then(async (r)=>{
    const text = await r.text();
    const clean = text.replace(/^\uFEFF/, '').trim();
    try { return JSON.parse(clean); }
    catch (e) { throw new Error(clean.slice(0, 220) || 'Invalid server response'); }
  }).then(data=>{
    if(!data.success||!data.data) throw new Error(data.error||'Failed to load supplier');
    const s=data.data; document.getElementById('editId').value=s.id||''; document.getElementById('editName').value=s.name||''; document.getElementById('editContactPerson').value=s.contact_person||''; document.getElementById('editEmail').value=s.email||''; document.getElementById('editPhone').value=s.phone||''; document.getElementById('editAddress').value=s.address||''; document.getElementById('editIsActive').checked=Number(s.is_active)===1; openModalById('editSupplierModal');
  }).catch(e=>showError(e.message||'Failed to load supplier details.')).finally(()=>{ if(btn){btn.disabled=false;btn.innerHTML=h;} });
}
function triggerPrintDocument(html, title = 'Print') {
  const old = document.getElementById('suppliers-print-frame');
  if (old) old.remove();

  const iframe = document.createElement('iframe');
  iframe.id = 'suppliers-print-frame';
  iframe.style.position = 'fixed';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
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
      showError('Unable to open print dialog.');
    }
    setTimeout(() => iframe.remove(), 250);
  };

  iframe.onload = () => setTimeout(doPrint, 120);
  setTimeout(doPrint, 420);
}

function printSuppliersReport(){
  const table = document.getElementById('suppliersTable');
  if (!table) {
    showError('No suppliers table found.');
    return;
  }

  const clone = table.cloneNode(true);
  clone.querySelectorAll('th:first-child, td:first-child, th:last-child, td:last-child').forEach((n) => n.remove());
  const rowsVisible = clone.querySelectorAll('tbody tr').length;
  const generated = new Date().toLocaleString();

  const html = `<!doctype html>
<html><head><meta charset="utf-8"><title>Suppliers Report</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#111}
.sheet{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.hdr{padding:16px 18px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
.hdr h2{margin:0;font-size:22px}.sub{margin-top:4px;font-size:12px;opacity:.95}
.cnt{padding:14px 18px}.meta{font-size:12px;color:#6b7280;margin-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #e5e7eb;padding:8px;font-size:12px;text-align:left}
thead th{background:#f9fafb}
.ftr{padding:10px 18px;background:#f9fafb;color:#6b7280;font-size:11px;display:flex;justify-content:space-between}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff;padding:0}.sheet{border:none;border-radius:0}}
</style></head><body>
<div class="sheet">
<div class="hdr"><h2>JAKISAWA SHOP - Suppliers Report</h2><div class="sub">Generated: ${generated}</div></div>
<div class="cnt"><div class="meta">Visible supplier rows: ${rowsVisible}</div>${clone.outerHTML}</div>
<div class="ftr"><span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span><span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span></div>
</div></body></html>`;

  triggerPrintDocument(html, 'Suppliers Report');
}

function printSupplierDetails() {
  const content = document.getElementById('supplierDetails');
  if (!content) {
    showError('Supplier details not available for printing.');
    return;
  }
  const generated = new Date().toLocaleString();
  const html = `<!doctype html>
<html><head><meta charset="utf-8"><title>Supplier Details</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#111}
.sheet{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.hdr{padding:16px 18px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
.hdr h2{margin:0;font-size:22px}.sub{margin-top:4px;font-size:12px;opacity:.95}
.cnt{padding:14px 18px}
.ftr{padding:10px 18px;background:#f9fafb;color:#6b7280;font-size:11px;display:flex;justify-content:space-between}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #e5e7eb;padding:7px;font-size:12px;text-align:left}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff;padding:0}.sheet{border:none;border-radius:0}}
</style></head><body>
<div class="sheet">
<div class="hdr"><h2>JAKISAWA SHOP - Supplier Details</h2><div class="sub">Generated: ${generated}</div></div>
<div class="cnt">${content.innerHTML}</div>
<div class="ftr"><span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span><span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span></div>
</div></body></html>`;
  triggerPrintDocument(html, 'Supplier Details');
}

function printSingleSupplier(button) {
  const row = button?.closest('tr');
  if (!row) {
    showError('Supplier row not found.');
    return;
  }
  const cells = row.querySelectorAll('td');
  if (!cells || cells.length < 8) {
    showError('Supplier row data is incomplete.');
    return;
  }

  const generated = new Date().toLocaleString();
  const html = `<!doctype html>
<html><head><meta charset="utf-8"><title>Supplier Profile</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#f3f4f6;color:#111}
.sheet{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.hdr{padding:16px 18px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
.hdr h2{margin:0;font-size:22px}.sub{margin-top:4px;font-size:12px;opacity:.95}
.cnt{padding:14px 18px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.item{border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#fafafa}
.label{font-size:12px;color:#6b7280}.value{font-size:14px;font-weight:600}
.ftr{padding:10px 18px;background:#f9fafb;color:#6b7280;font-size:11px;display:flex;justify-content:space-between}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff;padding:0}.sheet{border:none;border-radius:0}}
</style></head><body>
<div class="sheet">
<div class="hdr"><h2>JAKISAWA SHOP - Supplier Profile</h2><div class="sub">Generated: ${generated}</div></div>
<div class="cnt"><div class="grid">
<div class="item"><div class="label">Supplier ID</div><div class="value">${cells[1].innerText.trim()}</div></div>
<div class="item"><div class="label">Supplier</div><div class="value">${cells[2].innerText.trim()}</div></div>
<div class="item"><div class="label">Contact Person</div><div class="value">${cells[3].innerText.trim()}</div></div>
<div class="item"><div class="label">Contact Info</div><div class="value">${cells[4].innerText.trim()}</div></div>
<div class="item"><div class="label">Products</div><div class="value">${cells[5].innerText.trim()}</div></div>
<div class="item"><div class="label">Status</div><div class="value">${cells[6].innerText.trim()}</div></div>
<div class="item"><div class="label">Created</div><div class="value">${cells[7].innerText.trim()}</div></div>
</div></div>
<div class="ftr"><span>JAKISAWA SHOP | Nairobi Information HSE, Room 405, Fourth Floor</span><span>support@jakisawashop.co.ke | 0792546080 / +254 720 793609</span></div>
</div></body></html>`;

  triggerPrintDocument(html, 'Supplier Profile');
}

