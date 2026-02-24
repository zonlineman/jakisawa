<?php
// simple.php - Minimal template for quick printing
$shopName = 'JAKISAWA SHOP';
$shopAddress = 'Nairobi Information HSE, Room 405, Fourth Floor';
$shopPhone = '0792546080 / +254 720 793609';
$shopEmail = 'support@jakisawashop.co.ke';
$shopWebsite = 'https://www.jakisawashop.co.ke/';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice #<?php echo $order['order_number']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .invoice-header { margin-bottom: 12px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .invoice-header h2 { margin: 0 0 6px 0; }
        .invoice-header p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <!-- Simple invoice HTML -->
    <div class="invoice-header">
        <h2>INVOICE: #<?php echo $order['order_number']; ?></h2>
        <p><strong><?php echo htmlspecialchars($shopName); ?></strong></p>
        <p>Address: <?php echo htmlspecialchars($shopAddress); ?></p>
        <p>Phone: <?php echo htmlspecialchars($shopPhone); ?> | Email: <?php echo htmlspecialchars($shopEmail); ?></p>
        <p>Website: <?php echo htmlspecialchars($shopWebsite); ?></p>
    </div>
    <!-- ... rest of simple template ... -->
</body>
</html>
