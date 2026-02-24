<?php
// classic.php - Classic invoice template (similar structure, different styling)
$shopName = 'JAKISAWA SHOP';
$shopAddress = 'Nairobi Information HSE, Room 405, Fourth Floor';
$shopPhone = '0792546080 / +254 720 793609';
$shopEmail = 'support@jakisawashop.co.ke';
$shopWebsite = 'https://www.jakisawashop.co.ke/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?></title>
    <style>
        /* Classic styling - simpler, more traditional */
        body { font-family: 'Times New Roman', Times, serif; }
        .invoice { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 3px double #000; padding-bottom: 16px; margin-bottom: 14px; }
        .shop-meta { margin-top: 8px; font-size: 13px; line-height: 1.5; }
        .shop-meta div { margin: 2px 0; }
        .totals { border-top: 2px solid #000; border-bottom: 2px solid #000; }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <!-- Classic invoice HTML -->
    <div class="invoice">
        <div class="header">
            <h1>INVOICE</h1>
            <h3>#<?php echo $order['order_number']; ?></h3>
            <div class="shop-meta">
                <div><strong><?php echo htmlspecialchars($shopName); ?></strong></div>
                <div>Address: <?php echo htmlspecialchars($shopAddress); ?></div>
                <div>Phone: <?php echo htmlspecialchars($shopPhone); ?></div>
                <div>Email: <?php echo htmlspecialchars($shopEmail); ?></div>
                <div>Website: <?php echo htmlspecialchars($shopWebsite); ?></div>
            </div>
        </div>
        <!-- ... rest of classic template ... -->
    </div>
</body>
</html>
