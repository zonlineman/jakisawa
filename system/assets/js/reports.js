// reports.js - Reports & Analytics JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize reports page functionality
    initReportsPage();
    
    // Initialize charts
    initReportCharts();
    
    // Setup event listeners
    setupReportsEventListeners();
    
    // Generate initial report
    generateReport();
});

function initReportsPage() {
    console.log('Reports page initialized');
    
    // Initialize tooltips safely (avoid duplicate instance errors)
    if (window.bootstrap && window.bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
        });
    }
    
    // Set default date range (last 30 days)
    const endDate = document.getElementById('endDate');
    const startDate = document.getElementById('startDate');
    
    if (endDate && !endDate.value) {
        const today = new Date();
        endDate.valueAsDate = today;
        
        if (startDate && !startDate.value) {
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            startDate.valueAsDate = thirtyDaysAgo;
        }
    }
}

function initReportCharts() {
    // Initialize sales trend chart
    initSalesTrendChart();
    
    // Initialize payment methods chart
    initPaymentMethodsChart();
    
    // Initialize top products chart
    initTopProductsChart();
}

function initSalesTrendChart() {
    const ctx = document.getElementById('salesTrendChart')?.getContext('2d');
    if (!ctx) return;
    
    // Sample data - in real implementation, this would come from PHP
    const chartData = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Sales Revenue',
            data: [12000, 19000, 15000, 25000, 22000, 30000],
            borderColor: '#2e7d32',
            backgroundColor: 'rgba(46, 125, 50, 0.1)',
            tension: 0.4,
            fill: true
        }]
    };
    
    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Sales Revenue Trend'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo CURRENCY; ?> ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function initPaymentMethodsChart() {
    const ctx = document.getElementById('paymentMethodsChart')?.getContext('2d');
    if (!ctx) return;
    
    const chartData = {
        labels: ['M-Pesa', 'Cash', 'Card', 'Bank Transfer'],
        datasets: [{
            data: [45, 30, 15, 10],
            backgroundColor: [
                '#2e7d32',
                '#4caf50',
                '#8bc34a',
                '#cddc39'
            ]
        }]
    };
    
    new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'Payment Methods Distribution'
                }
            }
        }
    });
}

function initTopProductsChart() {
    const ctx = document.getElementById('topProductsChart')?.getContext('2d');
    if (!ctx) return;
    
    const chartData = {
        labels: ['Herbal Tea', 'Coffee Blend', 'Green Tea', 'Chai Mix', 'Matcha'],
        datasets: [{
            label: 'Units Sold',
            data: [120, 98, 85, 70, 65],
            backgroundColor: 'rgba(255, 152, 0, 0.7)',
            borderColor: '#ff9800',
            borderWidth: 1
        }]
    };
    
    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Top Selling Products'
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

function setupReportsEventListeners() {
    // Generate report button
    const generateBtn = document.getElementById('generateReportBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', generateReport);
    }
    
    // Export buttons
    document.querySelectorAll('.export-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const format = this.getAttribute('data-format');
            exportReport(format);
        });
    });
    
    // Report type switching
    document.querySelectorAll('.report-type-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            switchReportType(this.getAttribute('data-type'));
        });
    });
    
    // Quick date range buttons
    document.querySelectorAll('.quick-date-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            setQuickDateRange(this.getAttribute('data-range'));
        });
    });
}

function generateReport() {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    const reportType = document.getElementById('reportType')?.value;
    
    if (!startDate || !endDate) {
        showToast('Please select a date range', 'error');
        return;
    }
    
    // Show loading state
    const generateBtn = document.getElementById('generateReportBtn');
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
    generateBtn.disabled = true;
    
    // In a real implementation, this would be an AJAX call to fetch report data
    setTimeout(() => {
        // Simulate API call
        fetchReportData(startDate, endDate, reportType);
        
        // Restore button
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    }, 1500);
}

function fetchReportData(startDate, endDate, reportType) {
    // This would be replaced with actual AJAX call
    console.log('Fetching report data:', { startDate, endDate, reportType });
    
    // Show success message
    showToast('Report generated successfully!', 'success');
    
    // Update report metrics
    updateReportMetrics();
    
    // Refresh charts with new data
    refreshCharts();
}

function updateReportMetrics() {
    // Update metric cards with new data
    const metrics = {
        totalSales: Math.floor(Math.random() * 1000) + 500,
        totalRevenue: Math.floor(Math.random() * 50000) + 25000,
        avgOrderValue: Math.floor(Math.random() * 500) + 100,
        newCustomers: Math.floor(Math.random() * 50) + 20
    };
    
    // Update metric displays with animation
    animateCounter('totalSales', metrics.totalSales);
    animateCounter('totalRevenue', metrics.totalRevenue);
    animateCounter('avgOrderValue', metrics.avgOrderValue);
    animateCounter('newCustomers', metrics.newCustomers);
}

function animateCounter(elementId, finalValue) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const startValue = parseInt(element.textContent.replace(/[^0-9]/g, '')) || 0;
    const duration = 1000;
    const startTime = Date.now();
    
    const updateCounter = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = Math.floor(startValue + (finalValue - startValue) * progress);
        
        if (elementId.includes('Revenue')) {
            element.textContent = '<?php echo CURRENCY; ?> ' + currentValue.toLocaleString();
        } else {
            element.textContent = currentValue.toLocaleString();
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    };
    
    updateCounter();
}

function refreshCharts() {
    // In real implementation, update chart data with new report data
    console.log('Refreshing charts with new data');
    
    // Show visual feedback
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.style.opacity = '0.7';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 500);
    });
}

function exportReport(format) {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    const reportType = document.getElementById('reportType')?.value;
    
    if (!startDate || !endDate) {
        showToast('Please generate a report first', 'error');
        return;
    }
    
    const exportBtn = event.target.closest('.export-btn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
    
    // Simulate export process
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        showToast(`Report exported as ${format.toUpperCase()} successfully!`, 'success');
        
        // In real implementation, trigger download
        // window.open(`export_report.php?format=${format}&start_date=${startDate}&end_date=${endDate}&type=${reportType}`, '_blank');
    }, 2000);
}

function switchReportType(type) {
    // Update active button
    document.querySelectorAll('.report-type-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('active');
        }
    });
    
    // Update report type select
    const reportTypeSelect = document.getElementById('reportType');
    if (reportTypeSelect) {
        reportTypeSelect.value = type;
    }
    
    // Show/hide relevant sections based on report type
    updateReportSections(type);
    
    // Regenerate report if dates are set
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    
    if (startDate && endDate) {
        generateReport();
    }
}

function updateReportSections(type) {
    // Hide all report sections
    document.querySelectorAll('.report-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show relevant sections based on type
    switch(type) {
        case 'sales':
            document.getElementById('salesSection').style.display = 'block';
            document.getElementById('productsSection').style.display = 'block';
            document.getElementById('paymentSection').style.display = 'block';
            break;
        case 'inventory':
            document.getElementById('inventorySection').style.display = 'block';
            document.getElementById('stockSection').style.display = 'block';
            break;
        case 'customers':
            document.getElementById('customersSection').style.display = 'block';
            document.getElementById('demographicsSection').style.display = 'block';
            break;
    }
}

function setQuickDateRange(range) {
    const endDate = new Date();
    const startDate = new Date();
    
    switch(range) {
        case 'today':
            startDate.setHours(0, 0, 0, 0);
            break;
        case 'yesterday':
            startDate.setDate(endDate.getDate() - 1);
            startDate.setHours(0, 0, 0, 0);
            endDate.setDate(endDate.getDate() - 1);
            endDate.setHours(23, 59, 59, 999);
            break;
        case 'week':
            startDate.setDate(endDate.getDate() - 7);
            break;
        case 'month':
            startDate.setMonth(endDate.getMonth() - 1);
            break;
        case 'quarter':
            startDate.setMonth(endDate.getMonth() - 3);
            break;
        case 'year':
            startDate.setFullYear(endDate.getFullYear() - 1);
            break;
    }
    
    // Format dates as YYYY-MM-DD
    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };
    
    // Update date inputs
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput) startDateInput.value = formatDate(startDate);
    if (endDateInput) endDateInput.value = formatDate(endDate);
    
    // Generate report automatically
    generateReport();
}

function showToast(message, type = 'success') {
    // Use existing toast function or create one
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
        return;
    }
    
    // Fallback toast implementation
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Utility function to format currency
function formatCurrency(amount) {
    return '<?php echo CURRENCY; ?> ' + parseFloat(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Print report function
function printReport() {
    const printBtn = document.getElementById('printReportBtn');
    const originalText = printBtn.innerHTML;
    printBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Preparing...';
    
    setTimeout(() => {
        window.print();
        printBtn.innerHTML = originalText;
    }, 1000);
}

// Save report as template
function saveReportTemplate() {
    const templateName = prompt('Enter a name for this report template:');
    if (!templateName) return;
    
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    const reportType = document.getElementById('reportType')?.value;
    
    // In real implementation, save via AJAX
    console.log('Saving template:', { templateName, startDate, endDate, reportType });
    
    showToast('Report template saved successfully!', 'success');
}

// Load saved templates
function loadReportTemplates() {
    // In real implementation, fetch saved templates via AJAX
    const templates = [
        { id: 1, name: 'Monthly Sales Report', type: 'sales', range: 'month' },
        { id: 2, name: 'Inventory Status', type: 'inventory', range: 'week' },
        { id: 3, name: 'Customer Analysis', type: 'customers', range: 'quarter' }
    ];
    
    showTemplateSelector(templates);
}

function showTemplateSelector(templates) {
    // Create modal or dropdown to select template
    alert('Template loading feature would show here. Templates: ' + 
          templates.map(t => t.name).join(', '));
}
