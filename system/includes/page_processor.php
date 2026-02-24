<?php
function processPageRequest($currentPage) {
    $pageData = [];
    
    switch ($currentPage) {
        case 'dashboard':
            include 'handlers/dashboard_handler.php';
            $pageData = [
                'stats' => $stats ?? [],
                'recentSales' => $recentSales ?? [],
                'lowStockProducts' => $lowStockProducts ?? [],
                'recentCustomers' => $recentCustomers ?? [],
                'salesChartData' => $salesChartData ?? [],
                'topProducts' => $topProducts ?? []
            ];
            break;
            
        case 'orders':
            include 'handlers/orders_handler.php';
            $pageData = [
                'orders' => $ordersData ?? [],
                'stats' => $stats ?? []
            ];
            break;
            
        case 'customers':
            include 'handlers/customers_handler.php';
            $pageData = [
                'customers' => $customersData ?? [],
                'stats' => $stats ?? []
            ];
            break;
            
        case 'user_management':
            include 'handlers/user_management_handler.php';
            $pageData = [
                'users' => $usersData ?? [],
                'pendingUsers' => $pendingUsersData ?? []
            ];
            break;
            
        // ... other pages ...
    }
    
    return $pageData;
}
?>