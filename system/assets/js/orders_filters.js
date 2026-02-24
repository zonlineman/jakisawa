// Move to /assets/js/orders_filters.js
$(document).ready(function() {
    // Auto-submit on status change
    $('#statusFilter, #orderStatusFilter').change(function() {
        $('#ordersFilterForm').submit();
    });
    
    // Date validation
    $('input[type="date"]').change(function() {
        const start = $('input[name="start_date"]').val();
        const end = $('input[name="end_date"]').val();
        
        if (start && end && start > end) {
            alert('Start date cannot be after end date');
            $('input[name="start_date"]').val('');
            $('input[name="end_date"]').val('');
        }
    });
    
    // Set default dates if empty
    setDefaultDates();
});

function setDefaultDates() {
    if (!$('input[name="end_date"]').val()) {
        const today = new Date().toISOString().split('T')[0];
        $('input[name="end_date"]').val(today);
    }
    if (!$('input[name="start_date"]').val()) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        $('input[name="start_date"]').val(thirtyDaysAgo.toISOString().split('T')[0]);
    }
}