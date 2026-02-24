<?php
// includes/sidebar.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Set default page if not set
$page = $_GET['page'] ?? 'dashboard';

// Get user info from session
$user_name = $_SESSION['admin_name'] ?? 'User';
$user_role = $_SESSION['admin_role'] ?? 'guest';
$user_id = $_SESSION['admin_id'] ?? 0;



$pending_orders = getPendingOrdersCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JAKISAWA SHOP</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --danger-color: #ff0054;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            top: 0;
            left: 0;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 990;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }
        
        .logo i {
            font-size: 1.8rem;
            color: var(--primary-color);
        }
        
        .logo-text {
            font-size: 1.2rem;
        }
        
        .sidebar-menu {
            padding: 15px 0;
        }
        
        .nav-item {
            margin: 5px 10px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .nav-link:hover {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        .nav-text {
            font-size: 14px;
            font-weight: 500;
        }
        
        .badge-notification {
            position: absolute;
            right: 15px;
            background: var(--danger-color);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* User Profile Section */
        .user-profile {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 18px;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: white;
            margin-bottom: 2px;
        }
        
        .user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-logout {
            width: 100%;
            padding: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1010;
            width: 42px;
            height: 42px;
            border: 0;
            border-radius: 8px;
            background: var(--sidebar-bg);
            color: #fff;
            font-size: 1.2rem;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
        }

        .mobile-menu-toggle i {
            pointer-events: none;
        }

        body.sidebar-open {
            overflow: hidden;
        }
        
        /* Mobile Responsive */
        @media (max-width: 991px) {
            .sidebar {
                width: 270px;
                transform: translateX(-100%);
                box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                display: block;
            }

            .sidebar-backdrop.show {
                opacity: 1;
                visibility: visible;
            }
            
            .logo-text, .nav-text, .user-details, .btn-logout span {
                display: inline;
            }
            
            .logo {
                justify-content: flex-start;
            }
            
            .nav-link {
                justify-content: flex-start;
                padding: 12px 15px;
            }
            
            .nav-icon {
                font-size: 18px;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 72px;
            }
            
            .badge-notification {
                top: 5px;
                right: 5px;
                font-size: 9px;
                padding: 1px 5px;
            }
            
            .user-info {
                justify-content: flex-start;
            }

            .mobile-menu-toggle {
                display: flex;
            }
        }
        
        /* Custom scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        
        /* Sub-menu indicators */
        .has-submenu::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            transition: transform 0.3s;
        }
        
        .has-submenu.active::after {
            transform: rotate(180deg);
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open sidebar menu" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <!-- Logo -->
            <div class="sidebar-header">
                <a href="?page=dashboard" class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span class="logo-text">JAKISAWA</span>
                </a>
            </div>
            
            <!-- Navigation Menu -->
            <div class="sidebar-menu">
                <!-- Dashboard -->
                <div class="nav-item">
                    <a href="../admin_dashboard.php" class="nav-link ">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>





          
    </div>


            <!-- User Profile Section -->
            <div class="user-profile">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
                        <div class="user-role"><?= htmlspecialchars(ucfirst($user_role)) ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
       
          
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        const $sidebar = $('#sidebar');
        const $sidebarBackdrop = $('#sidebarBackdrop');
        const $mobileMenuToggle = $('#mobileMenuToggle');
        const mobileMediaQuery = window.matchMedia('(max-width: 991px)');

        function setSidebarState(isOpen) {
            $sidebar.toggleClass('show', isOpen);
            $sidebarBackdrop.toggleClass('show', isOpen);
            $('body').toggleClass('sidebar-open', isOpen);
            $mobileMenuToggle.attr('aria-expanded', isOpen ? 'true' : 'false');
            $mobileMenuToggle.attr('aria-label', isOpen ? 'Close sidebar menu' : 'Open sidebar menu');
            $('#mobileMenuToggle i')
                .toggleClass('fa-bars', !isOpen)
                .toggleClass('fa-times', isOpen);
        }

        function closeSidebarMenu() {
            setSidebarState(false);
        }

        $mobileMenuToggle.on('click', function() {
            const shouldOpen = !$sidebar.hasClass('show');
            setSidebarState(shouldOpen);
        });

        $sidebarBackdrop.on('click', closeSidebarMenu);

        $('.nav-link').on('click', function() {
            if (mobileMediaQuery.matches) {
                closeSidebarMenu();
            }
        });

        // Handle submenu toggle
        $('.has-submenu').click(function(e) {
            e.preventDefault();
            $(this).toggleClass('active').next('.submenu').slideToggle();
        });

        
        function checkWindowSize() {
            if (!mobileMediaQuery.matches) {
                closeSidebarMenu();
            }
        }
        
        // Check on load and resize
        checkWindowSize();
        $(window).resize(checkWindowSize);

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebarMenu();
            }
        });
        
        // Highlight active page
        var currentPage = '<?= $page ?>';
        $('.nav-link').each(function() {
            var linkPage = $(this).attr('href').split('=')[1];
            if (linkPage === currentPage) {
                $(this).addClass('active');
            }
        });

        closeSidebarMenu();
    });
    </script>
</body>
</html>
