            </div> <!-- Close main content div -->
        </div> <!-- Close row div -->
    </div> <!-- Close container-fluid div -->
    
    <!-- Footer -->
    <footer class="footer mt-4 py-3 border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        <i class="fas fa-church text-primary me-1"></i>
                        JAKISAWA  Admin Panel
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted">
                        Version 1.0 | 
                        <i class="fas fa-database text-info mx-1"></i>
                        <?php echo date('Y'); ?> 
                        <i class="fas fa-copyright ms-1"></i>
                    </span>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-success me-1"></i>
                        Logged in as: <?php echo $_SESSION['admin_name'] ?? 'Administrator'; ?> 
                        | Last activity: 
                        <?php 
                        if(isset($_SESSION['last_activity'])) {
                            echo date('H:i:s', $_SESSION['last_activity']);
                        } else {
                            echo '--:--:--';
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables (only include if needed on page) -->
    <?php if (isset($include_datatables) && $include_datatables === true): ?>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <?php endif; ?>
    
    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

    <!-- Custom Admin JavaScript -->
    <script>
        // Initialize tooltips
        $(function () {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    window.bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }
        });
        
        // Auto-update session time
        function updateSessionTime() {
            $.ajax({
                url: 'ajax/update-session.php',
                type: 'POST',
                success: function() {
                    // Session updated
                }
            });
        }
        
        // Update session every 5 minutes
        setInterval(updateSessionTime, 300000);
        
        // Confirm before critical actions
        $(document).on('click', '.confirm-action', function(e) {
            if (!confirm('Are you sure you want to proceed? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert-auto-hide').fadeOut('slow');
            }, 5000);
        });
        
        // Sidebar active link highlighting
        $(document).ready(function() {
            var currentPage = window.location.pathname.split('/').pop();
            
            $('.sidebar a').each(function() {
                var linkHref = $(this).attr('href');
                if (linkHref === currentPage) {
                    $(this).addClass('active');
                }
            });
            
            // Make current page link unclickable
            $('.sidebar a.active').on('click', function(e) {
                e.preventDefault();
            });
        });
        
        // Form validation helper
        function validateRequiredFields(formId) {
            var isValid = true;
            $('#' + formId + ' [required]').each(function() {
                if ($(this).val().trim() === '') {
                    $(this).addClass('is-invalid');
                    isValid = false;
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            return isValid;
        }
        
        // Logout confirmation
        $('a[href="logout.php"]').on('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Print page functionality
        function printPage() {
            window.print();
        }
        
        // Export data function
        function exportData(format) {
            alert('Exporting data as ' + format + '...');
            // Implement actual export logic here
        }
    </script>
    
    <!-- Page-specific scripts can be added here -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>

        <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JavaScript -->
    <script>
        window.SYSTEM_BASE_URL = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;
    </script>
    <script src="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/js/orders.js', ENT_QUOTES); ?>"></script>
    <?php
    $footerIconTooltipsUrl = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/js/icon-tooltips.js';
    ?>
    <script src="<?php echo htmlspecialchars($footerIconTooltipsUrl, ENT_QUOTES); ?>?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/../assets/js/icon-tooltips.js')); ?>"></script>

</body>

</html>
