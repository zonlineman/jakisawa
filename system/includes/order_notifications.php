<?php

if (!function_exists('orderNotifNormalizePhone')) {
    function orderNotifNormalizePhone($phone) {
        $raw = trim((string)$phone);
        if ($raw === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9+]/', '', $raw);
        if ($clean === null || $clean === '') {
            return null;
        }

        if (strpos($clean, '+') === 0) {
            $digits = preg_replace('/\D/', '', $clean);
            return $digits ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $clean);
        if ($digits === '') {
            return null;
        }

        if (preg_match('/^(0)(7|1)\d{8}$/', $digits)) {
            return '+254' . substr($digits, 1);
        }

        if (preg_match('/^254(7|1)\d{8}$/', $digits)) {
            return '+' . $digits;
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        return null;
    }
}

if (!function_exists('orderNotifLoadPHPMailer')) {
    function orderNotifLoadPHPMailer() {
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return true;
        }

        $paths = [
            dirname(__DIR__, 2) . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/PHPMailer/src',
            '/home1/jakisawa/public_html/PHPMailer/src'
        ];

        foreach ($paths as $path) {
            if (substr($path, -12) === 'autoload.php') {
                if (file_exists($path)) {
                    require_once $path;
                }
                if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                    return true;
                }
                continue;
            }

            if (!is_dir($path)) {
                continue;
            }
            if (file_exists($path . '/Exception.php')) {
                require_once $path . '/Exception.php';
            }
            if (file_exists($path . '/PHPMailer.php')) {
                require_once $path . '/PHPMailer.php';
            }
            if (file_exists($path . '/SMTP.php')) {
                require_once $path . '/SMTP.php';
            }
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('orderNotifSendEmail')) {
    function orderNotifSendEmail($email, $name, $subject, $htmlBody, $textBody = '') {
        $to = trim((string)$email);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email'];
        }

        $smtpHost = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
        $smtpUser = defined('SMTP_USER') ? (string)SMTP_USER : '';
        $smtpPass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
        $smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
        $smtpSecure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : 'ssl';
        $fromEmail = defined('SUPPORT_EMAIL') ? (string)SUPPORT_EMAIL : $smtpUser;
        $fromName = defined('SITE_NAME') ? (string)SITE_NAME : 'JAKISAWA SHOP';

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            return ['success' => false, 'message' => 'SMTP is not configured'];
        }

        if (!orderNotifLoadPHPMailer()) {
            return ['success' => false, 'message' => 'PHPMailer is not available'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->Port = $smtpPort;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            if ($smtpSecure !== '') {
                $mail->SMTPSecure = $smtpSecure;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to, trim((string)$name) !== '' ? trim((string)$name) : 'Customer');
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->isHTML(true);
            $mail->Subject = (string)$subject;
            $mail->Body = (string)$htmlBody;
            $mail->AltBody = $textBody !== '' ? (string)$textBody : strip_tags((string)$htmlBody);
            $mail->send();

            return ['success' => true, 'message' => 'Email sent'];
        } catch (\Throwable $e) {
            error_log('Order notification email error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('orderNotifSendSMS')) {
    function orderNotifSendSMS($phone, $message) {
        $normalizedPhone = orderNotifNormalizePhone($phone);
        $smsMessage = trim((string)$message);

        if ($normalizedPhone === null) {
            return ['success' => false, 'message' => 'Invalid phone number'];
        }
        if ($smsMessage === '') {
            return ['success' => false, 'message' => 'SMS message required'];
        }

        $provider = strtolower((string)(defined('SMS_PROVIDER') ? SMS_PROVIDER : 'none'));
        if ($provider !== 'africastalking') {
            return ['success' => false, 'message' => 'SMS provider not configured'];
        }
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL is not enabled'];
        }

        $username = defined('SMS_AT_USERNAME') ? (string)SMS_AT_USERNAME : '';
        $apiKey = defined('SMS_AT_API_KEY') ? (string)SMS_AT_API_KEY : '';
        $sender = defined('SMS_AT_SENDER_ID') ? (string)SMS_AT_SENDER_ID : '';
        $endpoint = defined('SMS_AT_ENDPOINT') ? (string)SMS_AT_ENDPOINT : 'https://api.africastalking.com/version1/messaging';

        if ($username === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'SMS credentials are missing'];
        }

        $payload = [
            'username' => $username,
            'to' => $normalizedPhone,
            'message' => $smsMessage
        ];
        if ($sender !== '') {
            $payload['from'] = $sender;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $apiKey
        ]);

        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'message' => 'SMS gateway error: ' . $curlErr];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'message' => 'SMS provider error HTTP ' . $status];
        }

        $data = json_decode((string)$resp, true);
        $recipient = $data['SMSMessageData']['Recipients'][0] ?? null;
        $statusText = strtolower((string)($recipient['status'] ?? ''));
        if (strpos($statusText, 'success') !== false) {
            return ['success' => true, 'message' => 'SMS sent'];
        }

        return ['success' => false, 'message' => 'SMS send not confirmed'];
    }
}

if (!function_exists('buildOrderChangeSummary')) {
    function buildOrderChangeSummary(array $changes) {
        $parts = [];
        foreach ($changes as $field => $delta) {
            $old = (string)($delta['old'] ?? '');
            $new = (string)($delta['new'] ?? '');
            if ($old === $new) {
                continue;
            }
            $label = $field === 'order_status' ? 'Order Status' : ($field === 'payment_status' ? 'Payment Status' : ucwords(str_replace('_', ' ', $field)));
            $parts[] = $label . ': ' . strtoupper($old !== '' ? $old : 'N/A') . ' -> ' . strtoupper($new !== '' ? $new : 'N/A');
        }
        return $parts;
    }
}

if (!function_exists('orderNotifCustomerTrackUrl')) {
    function orderNotifCustomerTrackUrl() {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = '';

        if (preg_match('#^(.+)/(system|customer)/#', $scriptName, $m)) {
            $basePath = rtrim((string)$m[1], '/');
        }

        $trackEntryPath = file_exists(dirname(__DIR__, 2) . '/index.php')
            ? '/index.php'
            : '/index.php';
        return $scheme . '://' . $host . $basePath . $trackEntryPath . '?section=track';
    }
}

if (!function_exists('sendOrderLifecycleNotification')) {
    function sendOrderLifecycleNotification(PDO $pdo, $orderId, $trigger = 'status_update', array $changes = []) {
        $result = [
            'email' => ['success' => false, 'message' => 'Not attempted'],
            'sms' => ['success' => false, 'message' => 'Not attempted']
        ];

        $stmt = $pdo->prepare("
            SELECT id, order_number, customer_name, customer_email, customer_phone,
                   payment_method, payment_status, order_status, total_amount,
                   shipping_address, transaction_id, created_at, updated_at
            FROM orders
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            $result['email'] = ['success' => false, 'message' => 'Order not found'];
            $result['sms'] = ['success' => false, 'message' => 'Order not found'];
            return $result;
        }

        $itemsStmt = $pdo->prepare("
            SELECT product_name, quantity, unit_price, total_price
            FROM order_items
            WHERE order_id = ?
            ORDER BY id ASC
        ");
        $itemsStmt->execute([(int)$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $orderNumber = (string)($order['order_number'] ?? ('#' . (int)$orderId));
        $customerName = (string)($order['customer_name'] ?? 'Customer');
        $paymentStatus = strtoupper((string)($order['payment_status'] ?? 'pending'));
        $orderStatus = strtoupper((string)($order['order_status'] ?? 'pending'));
        $totalAmount = number_format((float)($order['total_amount'] ?? 0), 2);
        $paymentMethod = (string)($order['payment_method'] ?? 'N/A');
        $trackingUrl = orderNotifCustomerTrackUrl();
        $changeLines = buildOrderChangeSummary($changes);

        $subject = 'Order Update - ' . $orderNumber;
        $intro = 'Your order has been updated.';
        if ($trigger === 'checkout') {
            $subject = 'Receipt / Invoice - ' . $orderNumber;
            $intro = 'Thank you for your order. Your checkout was successful.';
        }

        $itemsRows = '';
        foreach ($items as $item) {
            $itemsRows .= '<tr>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars((string)($item['product_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . (int)($item['quantity'] ?? 0) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">KES ' . number_format((float)($item['unit_price'] ?? 0), 2) . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">KES ' . number_format((float)($item['total_price'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        if ($itemsRows === '') {
            $itemsRows = '<tr><td colspan="4" style="padding:8px;border:1px solid #ddd;text-align:center;color:#666;">No items listed</td></tr>';
        }

        $changesHtml = '';
        if (!empty($changeLines)) {
            $changesHtml .= '<p style="margin:0 0 10px;"><strong>Changes:</strong></p><ul style="margin:0 0 12px 18px;padding:0;">';
            foreach ($changeLines as $line) {
                $changesHtml .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $changesHtml .= '</ul>';
        }

        $htmlBody = '
            <div style="font-family:Arial,sans-serif;color:#222;line-height:1.5;">
                <h2 style="color:#2e7d32;margin:0 0 10px;">JAKISAWA SHOP</h2>
                <p>Hello ' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . ',</p>
                <p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin:0 0 6px;"><strong>Order Number:</strong> ' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin:0 0 6px;"><strong>Order Status:</strong> ' . htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin:0 0 6px;"><strong>Payment Status:</strong> ' . htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin:0 0 6px;"><strong>Payment Method:</strong> ' . htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="margin:0 0 12px;"><strong>Total:</strong> KES ' . $totalAmount . '</p>'
                . $changesHtml .
                '<table style="border-collapse:collapse;width:100%;margin-top:10px;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            <th style="padding:8px;border:1px solid #ddd;text-align:left;">Item</th>
                            <th style="padding:8px;border:1px solid #ddd;text-align:center;">Qty</th>
                            <th style="padding:8px;border:1px solid #ddd;text-align:right;">Unit Price</th>
                            <th style="padding:8px;border:1px solid #ddd;text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>' . $itemsRows . '</tbody>
                </table>
                <p style="margin-top:14px;">Track your order here: <a href="' . htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') . '</a></p>
            </div>
        ';

        $textBody = 'JAKISAWA SHOP' . PHP_EOL
            . $intro . PHP_EOL
            . 'Order: ' . $orderNumber . PHP_EOL
            . 'Order status: ' . $orderStatus . PHP_EOL
            . 'Payment status: ' . $paymentStatus . PHP_EOL
            . 'Total: KES ' . $totalAmount . PHP_EOL
            . 'Track: ' . $trackingUrl;

        $result['email'] = orderNotifSendEmail(
            (string)($order['customer_email'] ?? ''),
            $customerName,
            $subject,
            $htmlBody,
            $textBody
        );

        $smsMessage = 'JAKISAWA: ' . $orderNumber
            . ' | Order: ' . $orderStatus
            . ' | Payment: ' . $paymentStatus
            . ' | Total KES ' . $totalAmount;
        if (!empty($changeLines)) {
            $smsMessage .= ' | Updated';
        }
        $result['sms'] = orderNotifSendSMS((string)($order['customer_phone'] ?? ''), $smsMessage);

        return $result;
    }
}
