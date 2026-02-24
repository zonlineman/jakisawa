/**
 * REMEDIES.JS - COMPLETE FIX FOR EMPTY DROPDOWNS
 * Version: 3.1 - Fixed category loading issue
 */

console.log('âœ… remedies.js loaded');

const SYSTEM_BASE_URL = (() => {
    const configured = (typeof window !== 'undefined' && typeof window.SYSTEM_BASE_URL === 'string')
        ? window.SYSTEM_BASE_URL.trim()
        : '';
    if (configured !== '') {
        return configured.replace(/\/+$/, '');
    }

    if (typeof window !== 'undefined') {
        const path = String(window.location.pathname || '');
        const marker = '/system/';
        const markerIndex = path.indexOf(marker);
        if (markerIndex !== -1) {
            return path.substring(0, markerIndex) + '/system';
        }
    }

    return '/system';
})();

function systemUrl(path) {
    const cleanPath = String(path || '').replace(/^\/+/, '');
    return cleanPath === '' ? SYSTEM_BASE_URL : `${SYSTEM_BASE_URL}/${cleanPath}`;
}

function withProjectBase(path) {
    const raw = String(path || '');
    if (!raw.startsWith('/')) {
        return raw;
    }

    if (SYSTEM_BASE_URL === '/system') {
        return raw;
    }

    const projectBase = SYSTEM_BASE_URL.replace(/\/system$/, '');
    if (!projectBase) {
        return raw;
    }

    if (raw === projectBase || raw.startsWith(projectBase + '/')) {
        return raw;
    }

    return projectBase + raw;
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('âœ… Remedies page loaded');
    initializeFormHandlers();
});

function initializeFormHandlers() {
    const imageInput = document.getElementById('edit_remedy_image');
    if (imageInput) {
        imageInput.addEventListener('change', handleImagePreview);
    }
    
    const form = document.getElementById('editRemedyForm');
    if (form) {
        form.addEventListener('submit', submitEditForm);
    }
}

function resolveRemedyImageUrl(imageUrl) {
    const raw = String(imageUrl || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/uploads/')) return systemUrl(raw);
    if (raw.startsWith('uploads/')) return systemUrl(raw);
    if (raw.startsWith('/')) return withProjectBase(raw);
    return withProjectBase('/' + raw);
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function generateSlugFromText(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function normalizeVariationPriceForStorage(value) {
    const cleaned = String(value || '')
        .replace(/ksh|kes/ig, '')
        .replace(/,/g, '')
        .trim();
    const numeric = parseFloat(cleaned);
    if (!Number.isFinite(numeric) || numeric <= 0) {
        return '';
    }
    return String(parseFloat(numeric.toFixed(2)));
}

function parseVariationRows(rawText) {
    const source = String(rawText || '').trim();
    if (!source) {
        return [{ label: '', price: '' }];
    }

    const rows = [];
    source.split(/\r\n|\r|\n/).forEach((lineRaw) => {
        const line = String(lineRaw || '').trim();
        if (!line) {
            return;
        }

        let label = '';
        let priceRaw = '';
        if (line.includes('|')) {
            const parts = line.split('|', 2);
            label = String(parts[0] || '').trim();
            priceRaw = String(parts[1] || '').trim();
        } else {
            const match = line.match(/^(.*\S)\s+(?:ksh|kes)?\s*([0-9]+(?:\.[0-9]{1,2})?)\s*(?:ksh|kes)?$/i);
            if (match) {
                label = String(match[1] || '').trim();
                priceRaw = String(match[2] || '').trim();
            } else {
                label = line;
                priceRaw = '';
            }
        }

        rows.push({
            label: label,
            price: normalizeVariationPriceForStorage(priceRaw)
        });
    });

    return rows.length > 0 ? rows : [{ label: '', price: '' }];
}

function getEditVariationRowMarkup(kind, labelValue, priceValue) {
    const isSize = kind === 'size';
    const labelName = isSize ? 'size_label[]' : 'sachet_label[]';
    const priceName = isSize ? 'size_price[]' : 'sachet_price[]';
    const labelCaption = isSize ? 'Size Label' : 'Option Label';
    const labelPlaceholder = isSize ? 'e.g. 1kg' : 'e.g. 250g';
    const pricePlaceholder = isSize ? 'e.g. 200' : 'e.g. 50';

    return `
        <div class="edit-variation-row border rounded p-2 mb-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted">${labelCaption}</label>
                    <input type="text" class="form-control edit-variation-label" name="${labelName}" placeholder="${labelPlaceholder}" value="${escapeHtml(labelValue)}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">Price (KSH)</label>
                    <input type="number" class="form-control edit-variation-price" name="${priceName}" min="0" step="0.01" placeholder="${pricePlaceholder}" value="${escapeHtml(priceValue)}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="button" class="btn btn-outline-danger remove-edit-variation-row">Remove</button>
                </div>
            </div>
        </div>
    `;
}

function buildEditVariationRowsMarkup(kind, rows) {
    const safeRows = Array.isArray(rows) && rows.length > 0 ? rows : [{ label: '', price: '' }];
    return safeRows
        .map((row) => getEditVariationRowMarkup(kind, row.label || '', row.price || ''))
        .join('');
}

function syncEditVariationHiddenFields(form) {
    if (!form) return;

    const sizeLines = [];
    form.querySelectorAll('#editSizeRows .edit-variation-row').forEach((row) => {
        const label = String(row.querySelector('.edit-variation-label')?.value || '').trim();
        const price = normalizeVariationPriceForStorage(row.querySelector('.edit-variation-price')?.value || '');
        if (!label || !price) return;
        sizeLines.push(`${label}|${price}`);
    });

    const sachetLines = [];
    form.querySelectorAll('#editSachetRows .edit-variation-row').forEach((row) => {
        const label = String(row.querySelector('.edit-variation-label')?.value || '').trim();
        const price = normalizeVariationPriceForStorage(row.querySelector('.edit-variation-price')?.value || '');
        if (!label || !price) return;
        sachetLines.push(`${label}|${price}`);
    });

    const sizesHidden = form.querySelector('#edit_custom_sizes');
    if (sizesHidden) {
        sizesHidden.value = sizeLines.join('\n');
    }

    const sachetsHidden = form.querySelector('#edit_custom_sachets');
    if (sachetsHidden) {
        sachetsHidden.value = sachetLines.join('\n');
    }
}

function validateEditVariationRows(form, containerId) {
    const container = form ? form.querySelector(`#${containerId}`) : null;
    if (!container) {
        return { valid: true, firstInvalid: null };
    }

    let valid = true;
    let firstInvalid = null;

    container.querySelectorAll('.edit-variation-row').forEach((row) => {
        const labelInput = row.querySelector('.edit-variation-label');
        const priceInput = row.querySelector('.edit-variation-price');
        const label = String(labelInput?.value || '').trim();
        const priceRaw = String(priceInput?.value || '').trim();

        if (!label && !priceRaw) {
            return;
        }

        if (!label && labelInput) {
            labelInput.classList.add('is-invalid');
            valid = false;
            if (!firstInvalid) firstInvalid = labelInput;
        }

        const normalizedPrice = normalizeVariationPriceForStorage(priceRaw);
        if (!priceRaw || !normalizedPrice) {
            if (priceInput) {
                priceInput.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = priceInput;
            }
            valid = false;
        }
    });

    return { valid, firstInvalid };
}

function attachEditVariationHandlers() {
    const form = document.getElementById('editRemedyForm');
    if (!form) return;

    form.querySelectorAll('.add-edit-variation-row').forEach((button) => {
        button.addEventListener('click', function() {
            const containerId = String(this.getAttribute('data-target') || '');
            const kind = String(this.getAttribute('data-kind') || 'size');
            const container = document.getElementById(containerId);
            if (!container) return;

            container.insertAdjacentHTML('beforeend', getEditVariationRowMarkup(kind, '', ''));
            syncEditVariationHiddenFields(form);
        });
    });

    form.addEventListener('click', function(event) {
        const removeBtn = event.target.closest('.remove-edit-variation-row');
        if (!removeBtn) return;

        const row = removeBtn.closest('.edit-variation-row');
        const container = removeBtn.closest('.edit-variation-rows');
        if (row) row.remove();

        if (container && container.querySelectorAll('.edit-variation-row').length === 0) {
            const kind = container.id === 'editSachetRows' ? 'sachet' : 'size';
            container.insertAdjacentHTML('beforeend', getEditVariationRowMarkup(kind, '', ''));
        }

        syncEditVariationHiddenFields(form);
    });

    form.addEventListener('input', function(event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.classList.contains('edit-variation-label') || target.classList.contains('edit-variation-price')) {
            if (target.classList.contains('is-invalid')) {
                target.classList.remove('is-invalid');
            }
            syncEditVariationHiddenFields(form);
        }
    });

    syncEditVariationHiddenFields(form);
}

// ==========================================
// EDIT REMEDY - FIXED VERSION
// ==========================================
function renderRemedyEditorInHost(id, host) {
    if (!host) {
        return Promise.reject(new Error('Editor container not found.'));
    }

    host.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-3 mb-0">Loading remedy editor...</p>
        </div>
    `;

    return Promise.all([
        loadCategories(),
        loadSuppliers(),
        loadRemedy(id)
    ])
        .then(([categories, suppliers, remedy]) => {
            if (!remedy || !remedy.id) {
                throw new Error('Remedy details could not be loaded.');
            }

            host.classList.remove('text-center');
            host.classList.remove('py-4');
            host.innerHTML = buildEditForm(remedy);

            populateCategories(categories, remedy.category_id);
            populateSuppliers(suppliers, remedy.supplier_id);

            // Non-admin users can edit other fields but cannot activate/deactivate from edit form.
            if (!window.IS_REMEDY_ADMIN) {
                const activeCheckbox = document.getElementById('edit_is_active');
                if (activeCheckbox) {
                    activeCheckbox.disabled = true;
                    const wrapper = activeCheckbox.closest('.col-md-6');
                    if (wrapper) {
                        wrapper.style.display = 'none';
                    }
                }
            }

            attachImageHandlers();
            attachEditVariationHandlers();
            attachFormSubmitHandler();

            // In dedicated page mode, make cancel navigate back instead of dismissing a modal.
            const cancelButton = host.querySelector('button[data-bs-dismiss="modal"]');
            if (cancelButton) {
                cancelButton.removeAttribute('data-bs-dismiss');
                cancelButton.addEventListener('click', function() {
                    window.location.href = '?page=remedies';
                });
            }

            return remedy;
        });
}

function editRemedy(id) {
    const remedyId = encodeURIComponent(id);
    const inlineHost = document.getElementById('standaloneEditHost');
    const isDedicatedEditorPage = window.location.search.indexOf('page=edit_remedy') !== -1;

    if (isDedicatedEditorPage && inlineHost) {
        renderRemedyEditorInHost(id, inlineHost).catch((error) => {
            inlineHost.innerHTML = `<div class="alert alert-danger mb-0">Failed to load editor: ${escapeHtml(error.message || 'Unknown error')}</div>`;
        });
        return;
    }

    const nextUrl = `?page=edit_remedy&id=${remedyId}`;
    window.location.href = nextUrl;
}

// Expose a stable global alias used by fallback table scripts
window.openRemedyEditor = editRemedy;

// ==========================================
// LOAD CATEGORIES
// ==========================================
function loadCategories() {
    console.log('ðŸ“¥ Loading categories...');
    
    return fetch(systemUrl('pages/actions/ajax/get_categories.php'))
        .then(response => {
            console.log('Categories response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Categories response (not JSON):', text.substring(0, 500));
                    throw new Error('Server returned HTML instead of JSON. Check get_categories.php for errors.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Categories data:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load categories');
            }
            
            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Categories data is invalid');
            }
            
            if (data.data.length === 0) {
                console.warn('âš ï¸ No categories found in database!');
            }
            
            return data.data;
        });
}

// ==========================================
// LOAD SUPPLIERS
// ==========================================
function loadSuppliers() {
    console.log('ðŸ“¥ Loading suppliers...');
    
    return fetch(systemUrl('pages/actions/ajax/get_suppliers.php'))
        .then(response => {
            console.log('Suppliers response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Suppliers response (not JSON):', text.substring(0, 500));
                    throw new Error('Server returned HTML instead of JSON. Check get_suppliers.php for errors.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Suppliers data:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load suppliers');
            }
            
            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Suppliers data is invalid');
            }
            
            return data.data;
        });
}

// ==========================================
// LOAD REMEDY
// ==========================================
function loadRemedy(id) {
    console.log('ðŸ“¥ Loading remedy ID:', id);
    
    return fetch(`${systemUrl('pages/actions/ajax/get_remedy.php')}?id=${id}`)
        .then(response => {
            console.log('Remedy response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Remedy response (not JSON):', text.substring(0, 500));
                    throw new Error('Server returned HTML instead of JSON. Check get_remedy.php for errors.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Remedy data:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load remedy');
            }
            
            if (!data.data) {
                throw new Error('Remedy not found');
            }
            
            return data.data;
        });
}

// ==========================================
// POPULATE CATEGORIES DROPDOWN
// ==========================================
function populateCategories(categories, selectedId) {
    const select = document.getElementById('edit_category_id');
    if (!select) {
        console.error('Category select not found!');
        return;
    }
    
    select.innerHTML = '<option value="">Select Category</option>';
    
    if (!categories || categories.length === 0) {
        select.innerHTML += '<option value="" disabled>No categories available</option>';
        console.warn('âš ï¸ No categories to populate!');
        return;
    }
    
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.name;
        if (cat.id == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    
    console.log(`âœ… Populated ${categories.length} categories, selected: ${selectedId}`);
}

// ==========================================
// POPULATE SUPPLIERS DROPDOWN
// ==========================================
function populateSuppliers(suppliers, selectedId) {
    const select = document.getElementById('edit_supplier_id');
    if (!select) {
        console.error('Supplier select not found!');
        return;
    }
    
    select.innerHTML = '<option value="">Select Supplier</option>';
    
    if (!suppliers || suppliers.length === 0) {
        select.innerHTML += '<option value="" disabled>No suppliers available</option>';
        console.warn('âš ï¸ No suppliers to populate!');
        return;
    }
    
    suppliers.forEach(sup => {
        const option = document.createElement('option');
        option.value = sup.id;
        option.textContent = sup.name;
        if (sup.id == selectedId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    
    console.log(`âœ… Populated ${suppliers.length} suppliers, selected: ${selectedId}`);
}

// ==========================================
// BUILD EDIT FORM HTML
// ==========================================
function buildEditForm(remedy) {
    const hasImage = remedy.image_url && remedy.image_url.trim();
    const sizeRowsMarkup = buildEditVariationRowsMarkup('size', parseVariationRows(remedy.custom_sizes || ''));
    const sachetRowsMarkup = buildEditVariationRowsMarkup('sachet', parseVariationRows(remedy.custom_sachets || ''));
    
    return `
        <form id="editRemedyForm" enctype="multipart/form-data">
            <input type="hidden" name="remedy_id" id="edit_remedy_id" value="${remedy.id}">
            
            <!-- Image Upload Section -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-image me-2"></i>Product Image</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="current-image mb-3">
                                <img id="current_image_preview" 
                                     src="${hasImage ? resolveRemedyImageUrl(remedy.image_url) : ''}" 
                                     alt="Current image" 
                                     style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 5px; ${hasImage ? '' : 'display: none;'}"
                                     onerror="this.style.display='none'; document.getElementById('no_image_placeholder').style.display='block';">
                                <div id="no_image_placeholder" style="${hasImage ? 'display: none;' : ''}">
                                    <i class="fas fa-image fa-4x text-muted"></i>
                                    <p class="text-muted">No image</p>
                                </div>
                            </div>
                            <div id="remove_image_container" style="${hasImage ? '' : 'display: none;'}">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="remove_image" id="remove_image" value="1">
                                    <label class="form-check-label text-danger" for="remove_image">
                                        <i class="fas fa-trash"></i> Remove current image
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="edit_remedy_image" class="form-label">Upload New Image</label>
                            <input type="file" class="form-control" id="edit_remedy_image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Max size: 5MB. Allowed: JPG, PNG, GIF, WEBP
                            </small>
                            <div id="image_preview" class="mt-2" style="display: none;">
                                <p class="text-success fw-bold mb-1">New Image Preview:</p>
                                <img id="preview_img" src="" alt="Preview" style="max-width: 150px; max-height: 150px; border: 2px solid #28a745; border-radius: 5px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_sku" class="form-label">SKU *</label>
                    <input type="text" class="form-control" id="edit_sku" name="sku" value="${remedy.sku || ''}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_name" class="form-label">Name *</label>
                    <input type="text" class="form-control" id="edit_name" name="name" value="${remedy.name || ''}" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="edit_description" class="form-label">Description</label>
                <textarea class="form-control" id="edit_description" name="description" rows="3">${remedy.description || ''}</textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_slug" class="form-label">URL Slug</label>
                    <input type="text" class="form-control" id="edit_slug" name="slug" value="${escapeHtml(remedy.slug || '')}" placeholder="Auto-generated from name">
                    <small class="text-muted">Leave blank to auto-generate from remedy name.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_category_id" class="form-label">Category *</label>
                    <select class="form-select" id="edit_category_id" name="category_id" required>
                        <option value="">Loading categories...</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_supplier_id" class="form-label">Supplier</label>
                    <select class="form-select" id="edit_supplier_id" name="supplier_id">
                        <option value="">Loading suppliers...</option>
                    </select>
                </div>
            </div>

            <!-- Ingredients and Usage -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_ingredients" class="form-label">Ingredients</label>
                    <textarea class="form-control" id="edit_ingredients" name="ingredients" rows="3">${remedy.ingredients || ''}</textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="edit_usage_instructions" class="form-label">Usage Instructions</label>
                    <textarea class="form-control" id="edit_usage_instructions" name="usage_instructions" rows="3">${remedy.usage_instructions || ''}</textarea>
                </div>
            </div>

            <!-- Pricing -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-tag me-2"></i>Pricing</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_unit_price" class="form-label">Unit Price (KSH) *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_unit_price" name="unit_price" value="${remedy.unit_price || 0}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_cost_price" class="form-label">Cost Price (KSH)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_cost_price" name="cost_price" value="${remedy.cost_price || ''}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_discount_price" class="form-label">Discount Price (KSH)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_discount_price" name="discount_price" value="${remedy.discount_price || ''}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Remedy Sizes</label>
                            <input type="hidden" id="edit_custom_sizes" name="custom_sizes" value="${escapeHtml(remedy.custom_sizes || '')}">
                            <div id="editSizeRows" class="edit-variation-rows">${sizeRowsMarkup}</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1 add-edit-variation-row" data-kind="size" data-target="editSizeRows">Add Size Option</button>
                            <small class="text-muted d-block mt-1">Each row is saved as <code>Label|Price</code> (example: <code>1kg|200</code>).</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Custom Sachet Options</label>
                            <input type="hidden" id="edit_custom_sachets" name="custom_sachets" value="${escapeHtml(remedy.custom_sachets || '')}">
                            <div id="editSachetRows" class="edit-variation-rows">${sachetRowsMarkup}</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1 add-edit-variation-row" data-kind="sachet" data-target="editSachetRows">Add Sachet Option</button>
                            <small class="text-muted d-block mt-1">Use separate label and price fields; customer view will still read <code>Label|Price</code>.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>Stock Management</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_stock_quantity" class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" id="edit_stock_quantity" name="stock_quantity" value="${remedy.stock_quantity || 0}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_reorder_level" class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" id="edit_reorder_level" name="reorder_level" value="${remedy.reorder_level || 10}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO & Marketing -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>SEO & Marketing</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_seo_title" class="form-label">SEO Title</label>
                            <input type="text" class="form-control" id="edit_seo_title" name="seo_title" value="${remedy.seo_title || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_focus_keyword" class="form-label">Focus Keyword</label>
                            <input type="text" class="form-control" id="edit_focus_keyword" name="focus_keyword" value="${remedy.focus_keyword || ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_seo_meta_description" class="form-label">Meta Description</label>
                        <textarea class="form-control" id="edit_seo_meta_description" name="seo_meta_description" rows="2">${remedy.seo_meta_description || ''}</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_seo_keywords" class="form-label">SEO Keywords</label>
                        <input type="text" class="form-control" id="edit_seo_keywords" name="seo_keywords" value="${remedy.seo_keywords || ''}">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_og_title" class="form-label">OG Title</label>
                            <input type="text" class="form-control" id="edit_og_title" name="og_title" value="${remedy.og_title || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_canonical_url" class="form-label">Canonical URL</label>
                            <input type="text" class="form-control" id="edit_canonical_url" name="canonical_url" value="${remedy.canonical_url || ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_og_description" class="form-label">OG Description</label>
                        <textarea class="form-control" id="edit_og_description" name="og_description" rows="2">${remedy.og_description || ''}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_target_audience" class="form-label">Target Audience</label>
                            <input type="text" class="form-control" id="edit_target_audience" name="target_audience" value="${remedy.target_audience || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_cta_text" class="form-label">CTA Text</label>
                            <input type="text" class="form-control" id="edit_cta_text" name="cta_text" value="${remedy.cta_text || ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_cta_link" class="form-label">CTA Link</label>
                        <input type="text" class="form-control" id="edit_cta_link" name="cta_link" value="${remedy.cta_link || ''}">
                    </div>
                    <div class="mb-3">
                        <label for="edit_value_proposition" class="form-label">Value Proposition</label>
                        <textarea class="form-control" id="edit_value_proposition" name="value_proposition" rows="2">${remedy.value_proposition || ''}</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_customer_pain_points" class="form-label">Customer Pain Points</label>
                        <textarea class="form-control" id="edit_customer_pain_points" name="customer_pain_points" rows="2">${remedy.customer_pain_points || ''}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_faq_q1" class="form-label">FAQ 1 Question</label>
                            <input type="text" class="form-control" id="edit_faq_q1" name="faq_q1" value="${remedy.faq_q1 || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_faq_a1" class="form-label">FAQ 1 Answer</label>
                            <textarea class="form-control" id="edit_faq_a1" name="faq_a1" rows="2">${remedy.faq_a1 || ''}</textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_faq_q2" class="form-label">FAQ 2 Question</label>
                            <input type="text" class="form-control" id="edit_faq_q2" name="faq_q2" value="${remedy.faq_q2 || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_faq_a2" class="form-label">FAQ 2 Answer</label>
                            <textarea class="form-control" id="edit_faq_a2" name="faq_a2" rows="2">${remedy.faq_a2 || ''}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_featured" name="is_featured" value="1" ${remedy.is_featured == 1 ? 'checked' : ''}>
                        <label class="form-check-label" for="edit_is_featured">
                            <i class="fas fa-star text-warning"></i> Featured Product
                        </label>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1" ${remedy.is_active == 1 ? 'checked' : ''}>
                        <label class="form-check-label" for="edit_is_active">
                            <i class="fas fa-check-circle text-success"></i> Active
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="text-end mt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="saveRemedyBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    `;
}

// ==========================================
// ATTACH IMAGE HANDLERS
// ==========================================
function attachImageHandlers() {
    const imageInput = document.getElementById('edit_remedy_image');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = this.files[0];
            const preview = document.getElementById('image_preview');
            const previewImg = document.getElementById('preview_img');
            
            if (!file) {
                if (preview) preview.style.display = 'none';
                return;
            }
            
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Please use JPG, PNG, GIF, or WEBP.');
                this.value = '';
                if (preview) preview.style.display = 'none';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB.');
                this.value = '';
                if (preview) preview.style.display = 'none';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImg) {
                    previewImg.src = e.target.result;
                    if (preview) preview.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        });
    }
}

// ==========================================
// ATTACH FORM SUBMIT HANDLER
// ==========================================
function attachFormSubmitHandler() {
    const form = document.getElementById('editRemedyForm');
    if (form) {
        const nameInput = document.getElementById('edit_name');
        const slugInput = document.getElementById('edit_slug');

        if (nameInput && slugInput) {
            nameInput.addEventListener('blur', function() {
                if (!String(slugInput.value || '').trim()) {
                    slugInput.value = generateSlugFromText(nameInput.value);
                }
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('ðŸ“¤ Submitting form');

            if (slugInput && !String(slugInput.value || '').trim()) {
                slugInput.value = generateSlugFromText(nameInput ? nameInput.value : '');
            }
            
            // Validate category is selected
            const categorySelect = document.getElementById('edit_category_id');
            if (!categorySelect || !categorySelect.value) {
                alert('âŒ Please select a category');
                categorySelect.focus();
                return;
            }

            form.querySelectorAll('.edit-variation-label.is-invalid, .edit-variation-price.is-invalid').forEach((el) => {
                el.classList.remove('is-invalid');
            });

            const sizeValidation = validateEditVariationRows(form, 'editSizeRows');
            const sachetValidation = validateEditVariationRows(form, 'editSachetRows');
            if (!sizeValidation.valid || !sachetValidation.valid) {
                alert('âŒ Please provide both label and valid price for each variation row.');
                const firstInvalid = sizeValidation.firstInvalid || sachetValidation.firstInvalid;
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                return;
            }

            syncEditVariationHiddenFields(form);
            
            const formData = new FormData(this);
            const btn = document.getElementById('saveRemedyBtn');
            
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
                btn.disabled = true;
                
                fetch(systemUrl('pages/actions/remedies/edit_remedy.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Edit response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Edit response text:', text.substring(0, 500));
                            throw new Error('Server returned HTML instead of JSON. Check edit_remedy.php for PHP errors.');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Edit response data:', data);
                    if (data.success) {
                        alert('âœ… ' + data.message);
                        
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editRemedyModal'));
                        if (modal) modal.hide();
                        
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('âŒ Error: ' + data.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('âŒ Form submission error:', error);
                    alert('âŒ Error: ' + error.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        });
    }
}

// ==========================================
// VIEW REMEDY DETAILS
// ==========================================
function viewRemedyDetails(id) {
    console.log('View details for ID:', id);

    const modal = document.getElementById('remedyDetailsModal');
    const title = document.getElementById('remedyDetailsTitle');
    const content = document.getElementById('remedyDetailsContent');

    if (!modal) {
        alert('Details modal not found');
        return;
    }

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    if (title) title.textContent = 'Loading...';
    if (content) {
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div><p class="mt-2">Loading...</p></div>';
    }

    loadRemedyDetails(id)
        .then(payload => {
            displayRemedyDetails(payload);
        })
        .catch(error => {
            console.error('Error loading remedy details:', error);
            if (content) {
                content.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
            }
        });
}

// ==========================================
// DISPLAY REMEDY DETAILS
// ==========================================
function formatDbValue(value) {
    if (value === null || value === undefined || value === '') {
        return '<span class="text-muted">-</span>';
    }
    const asString = String(value);
    return asString
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
}

function formatLabel(key) {
    return String(key || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

function displayRemedyDetails(payload) {
    const remedy = payload?.data || {};
    const seoData = payload?.seo_data || {};
    const salesStats = payload?.sales_stats || {};
    const recentOrders = Array.isArray(payload?.recent_orders) ? payload.recent_orders : [];
    const stockHistory = Array.isArray(payload?.stock_history) ? payload.stock_history : [];

    const title = document.getElementById('remedyDetailsTitle');
    const content = document.getElementById('remedyDetailsContent');

    if (title) title.textContent = remedy.name || 'Remedy Details';

    if (!content) return;

    const stockQty = Number(remedy.stock_quantity || 0);
    const reorderLevel = Number(remedy.reorder_level || 0);
    const stockClass = stockQty <= 0 ? 'danger' :
                      stockQty <= reorderLevel ? 'warning' : 'success';

    let html = '';

    if (remedy.image_url) {
        html += `<div class="text-center mb-3">
            <img src="${resolveRemedyImageUrl(remedy.image_url)}" 
                 class="img-fluid rounded" 
                 style="max-height: 300px"
                 onerror="this.style.display='none'">
        </div>`;
    }

    html += `
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body py-2">
                        <div class="text-muted small">Orders</div>
                        <div class="fw-bold fs-5">${Number(salesStats.orders_count || 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body py-2">
                        <div class="text-muted small">Units Sold</div>
                        <div class="fw-bold fs-5">${Number(salesStats.units_sold || 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body py-2">
                        <div class="text-muted small">Sales Total</div>
                        <div class="fw-bold fs-5">KES ${Number(salesStats.total_sales || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    </div>
                </div>
            </div>
        </div>
    `;

    const remedyKeys = Object.keys(remedy).sort((a, b) => a.localeCompare(b));
    let remedyRows = '';
    remedyKeys.forEach(key => {
        remedyRows += `
            <tr>
                <th style="width: 220px;">${formatLabel(key)}</th>
                <td>${formatDbValue(remedy[key])}</td>
            </tr>
        `;
    });

    html += `
        <div class="card mt-3">
            <div class="card-header fw-semibold">Full Remedy Record (Database)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody>${remedyRows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    `;

    if (Object.keys(seoData).length > 0) {
        let seoRows = '';
        Object.keys(seoData).sort((a, b) => a.localeCompare(b)).forEach(key => {
            seoRows += `
                <tr>
                    <th style="width: 220px;">${formatLabel(key)}</th>
                    <td>${formatDbValue(seoData[key])}</td>
                </tr>
            `;
        });

        html += `
            <div class="card mt-3">
                <div class="card-header fw-semibold">SEO / Marketing Data</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <tbody>${seoRows}</tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    let orderRows = '';
    if (recentOrders.length === 0) {
        orderRows = '<tr><td colspan="6" class="text-center text-muted">No order history found.</td></tr>';
    } else {
        recentOrders.forEach(order => {
            orderRows += `
                <tr>
                    <td>${formatDbValue(order.order_number || order.order_id)}</td>
                    <td>${formatDbValue(order.customer_name)}</td>
                    <td>${formatDbValue(order.quantity)}</td>
                    <td>KES ${Number(order.total_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td>${formatDbValue(order.order_status)} / ${formatDbValue(order.payment_status)}</td>
                    <td>${formatDbValue(order.created_at)}</td>
                </tr>
            `;
        });
    }

    html += `
        <div class="card mt-3">
            <div class="card-header fw-semibold">Recent Orders (This Remedy)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
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
                        <tbody>${orderRows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    `;

    let stockRows = '';
    if (stockHistory.length === 0) {
        stockRows = '<tr><td colspan="6" class="text-center text-muted">No stock movement history found.</td></tr>';
    } else {
        stockHistory.forEach(row => {
            stockRows += `
                <tr>
                    <td>${formatDbValue(row.movement_type)}</td>
                    <td>${formatDbValue(row.qty_change)}</td>
                    <td>${formatDbValue(row.balance_after)}</td>
                    <td>${formatDbValue(row.source_ref)}</td>
                    <td>${formatDbValue(row.movement_at)}</td>
                    <td>${formatDbValue(row.notes)}</td>
                </tr>
            `;
        });
    }

    html += `
        <div class="card mt-3">
            <div class="card-header fw-semibold">Stock Movement History</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
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
                        <tbody>${stockRows}</tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <span class="badge bg-${stockClass} px-3 py-2">${stockQty <= 0 ? 'Out of Stock' : 'In Stock'}</span>
            <button class="btn btn-primary" onclick="editRemedy(${remedy.id}); bootstrap.Modal.getInstance(document.getElementById('remedyDetailsModal')).hide();">
                <i class="fas fa-edit"></i> Edit Remedy
            </button>
        </div>
    `;

    content.innerHTML = html;
}

// ==========================================
// UPDATE STOCK MODAL
// ==========================================
function updateStockModal(id, name, currentStock, supplierId) {
    const remedyId = document.getElementById('stockRemedyId');
    const remedyName = document.getElementById('stockRemedyName');
    const currentStockEl = document.getElementById('currentStock');
    const newStock = document.getElementById('newStockQuantity');
    const supplierSelect = document.getElementById('stockSupplierId');
    
    if (remedyId) remedyId.value = id;
    if (remedyName) remedyName.value = name;
    if (currentStockEl) currentStockEl.value = currentStock;
    if (newStock) newStock.value = currentStock;
    if (supplierSelect) supplierSelect.value = supplierId ? String(supplierId) : '';
    
    const modal = document.getElementById('updateStockModal');
    if (modal) {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

// Stock form handler
document.addEventListener('DOMContentLoaded', function() {
    const updateStockForm = document.getElementById('updateStockForm');
    if (updateStockForm) {
        updateStockForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = document.getElementById('updateStockBtn');
            
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
                btn.disabled = true;
                
                fetch(systemUrl('pages/actions/remedies/update_stock.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error('Server returned HTML instead of JSON');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('âœ… ' + data.message);
                        const modal = bootstrap.Modal.getInstance(document.getElementById('updateStockModal'));
                        if (modal) modal.hide();
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('âŒ Error: ' + data.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('âŒ Error: ' + error.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        });
    }
});

// Toggle functions
function toggleFeatured(id) {
    if (!confirm('Change featured status?')) return;
    fetch(`${systemUrl('pages/actions/ajax/toggle_featured.php')}?id=${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(data => data.success ? location.reload() : alert('Error: ' + data.message))
    .catch(err => alert('Error: ' + err.message));
}

function toggleActive(id) {
    if (!window.IS_REMEDY_ADMIN) {
        alert('Only admin can activate/deactivate remedies.');
        return;
    }
    if (!confirm('Change active status?')) return;
    fetch(`${systemUrl('pages/actions/ajax/toggle_active.php')}?id=${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(data => data.success ? location.reload() : alert('Error: ' + data.message))
    .catch(err => alert('Error: ' + err.message));
}

function loadRemedyDetails(id) {
    return fetch(`${systemUrl('pages/actions/ajax/get_remedy.php')}?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Remedy details response (not JSON):', text.substring(0, 500));
                    throw new Error('Server returned HTML instead of JSON. Check get_remedy.php for errors.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load remedy details');
            }
            return data;
        });
}

function deleteRemedyById(id, name) {
    if (!window.IS_REMEDY_ADMIN) {
        alert('Only admin can delete remedies.');
        return;
    }
    if (!confirm(`Are you sure you want to permanently delete "${name}"?`)) {
        return;
    }

    fetch(`${systemUrl('pages/actions/remedies/delete_remedy.php')}?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                return response.text().then(() => {
                    throw new Error('Unexpected server response');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Remedy deleted successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Delete failed'));
            }
        })
        .catch(err => {
            alert('Error deleting remedy: ' + err.message);
        });
}

// Stable aliases for partial templates/fallback handlers
window.openRemedyViewer = viewRemedyDetails;
window.openStockModal = updateStockModal;
window.toggleRemedyFeatured = toggleFeatured;
window.toggleRemedyActive = toggleActive;
window.deleteRemedyById = deleteRemedyById;

function testModal() {
    const modal = new bootstrap.Modal(document.getElementById('remedyDetailsModal'));
    document.getElementById('remedyDetailsTitle').textContent = 'Test Modal';
    document.getElementById('remedyDetailsContent').innerHTML = '<div class="alert alert-success">âœ… Modal working!</div>';
    modal.show();
}

function handleImagePreview(event) { /* Fallback */ }
function submitEditForm(event) { /* Fallback */ }



