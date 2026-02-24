<?php
/**
 * Customer Actions Class
 * Location: /pages/actions/CustomerActions.php
 * 
 * Handles all customer-related business logic
 */

class CustomerActions {
    
    protected $pdo;
    protected $usersColumns = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    protected function getUsersColumns() {
        if (is_array($this->usersColumns)) {
            return $this->usersColumns;
        }

        $this->usersColumns = [];
        try {
            $rows = $this->pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $field = trim((string)($row['Field'] ?? ''));
                if ($field !== '') {
                    $this->usersColumns[strtolower($field)] = $field;
                }
            }
        } catch (PDOException $e) {
            error_log("Error mapping users columns: " . $e->getMessage());
        }

        return $this->usersColumns;
    }

    protected function hasUsersColumn($column) {
        $columns = $this->getUsersColumns();
        return isset($columns[strtolower((string)$column)]);
    }

    protected function customerRoleSql($alias = 'u') {
        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
        if ($safeAlias === '') {
            $safeAlias = 'u';
        }
        return "LOWER(REPLACE(REPLACE(TRIM(COALESCE({$safeAlias}.role, '')), '-', '_'), ' ', '_'))";
    }

    protected function syncOrderCustomerLinksByEmail() {
        try {
            if (
                !$this->hasUsersColumn('email')
                || !$this->hasUsersColumn('role')
                || !$this->hasUsersColumn('id')
            ) {
                return;
            }

            $verifiedFilter = $this->hasUsersColumn('email_verified')
                ? " AND COALESCE(u.email_verified, 0) = 1"
                : "";
            $customerRoleExpr = $this->customerRoleSql('u');

            $sql = "
                UPDATE orders o
                INNER JOIN users u
                    ON LOWER(TRIM(o.customer_email)) = LOWER(TRIM(u.email))
                SET o.user_id = u.id
                WHERE (o.user_id IS NULL OR o.user_id = 0)
                  AND o.customer_email IS NOT NULL
                  AND TRIM(o.customer_email) <> ''
                  AND {$customerRoleExpr} = 'customer'
                  {$verifiedFilter}
            ";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error syncing order customer links: " . $e->getMessage());
        }
    }
    
    /**
     * Get random customers (non-registered)
     */
    public function getRandomCustomers($limit = 100) {
        try {
            $query = "
                SELECT 
                    o.customer_name,
                    o.customer_email,
                    o.customer_phone,
                    o.shipping_address,
                    COUNT(o.id) as total_orders,
                    SUM(o.total_amount) as total_spent,
                    MIN(o.created_at) as first_order_date,
                    MAX(o.created_at) as last_order_date,
                    CASE 
                        WHEN MAX(o.created_at) < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'inactive'
                        WHEN MAX(o.created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'needs_followup'
                        ELSE 'active'
                    END as status
                FROM orders o
                WHERE o.user_id IS NULL AND o.customer_email IS NOT NULL
                GROUP BY o.customer_email, o.customer_name, o.customer_phone, o.shipping_address
                ORDER BY last_order_date DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching random customers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get registered customers
     */
    public function getRegisteredCustomers($limit = 100) {
        try {
            $this->syncOrderCustomerLinksByEmail();

            $customerRoleExpr = $this->customerRoleSql('u');
            $statusSelect = $this->hasUsersColumn('status') ? "u.status AS status" : "'active' AS status";
            $approvalSelect = $this->hasUsersColumn('approval_status') ? "u.approval_status AS approval_status" : "'approved' AS approval_status";
            $isActiveSelect = $this->hasUsersColumn('is_active') ? "u.is_active AS is_active" : "1 AS is_active";
            $registrationSelect = $this->hasUsersColumn('created_at') ? "u.created_at AS registration_date" : "NULL AS registration_date";
            $lastLoginSelect = $this->hasUsersColumn('last_login_at') ? "u.last_login_at AS last_login_at" : "NULL AS last_login_at";

            $query = "
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.address,
                    u.city,
                    u.postal_code,
                    {$statusSelect},
                    {$approvalSelect},
                    {$isActiveSelect},
                    {$registrationSelect},
                    {$lastLoginSelect},
                    COALESCE((
                        SELECT COUNT(*)
                        FROM orders o
                        WHERE o.user_id = u.id
                           OR (
                                (o.user_id IS NULL OR o.user_id = 0)
                                AND LOWER(TRIM(o.customer_email)) = LOWER(TRIM(u.email))
                              )
                    ), 0) AS total_orders,
                    COALESCE((
                        SELECT SUM(o.total_amount)
                        FROM orders o
                        WHERE o.user_id = u.id
                           OR (
                                (o.user_id IS NULL OR o.user_id = 0)
                                AND LOWER(TRIM(o.customer_email)) = LOWER(TRIM(u.email))
                              )
                    ), 0) AS total_spent,
                    COALESCE((
                        SELECT MAX(o.created_at)
                        FROM orders o
                        WHERE o.user_id = u.id
                           OR (
                                (o.user_id IS NULL OR o.user_id = 0)
                                AND LOWER(TRIM(o.customer_email)) = LOWER(TRIM(u.email))
                              )
                    ), NULL) AS last_order_date,
                    COALESCE((
                        SELECT AVG(o.total_amount)
                        FROM orders o
                        WHERE o.user_id = u.id
                           OR (
                                (o.user_id IS NULL OR o.user_id = 0)
                                AND LOWER(TRIM(o.customer_email)) = LOWER(TRIM(u.email))
                              )
                    ), 0) AS avg_order_value
                FROM users u
                WHERE {$customerRoleExpr} = 'customer'
                ORDER BY u.created_at DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching registered customers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get customer statistics
     */
    public function getStatistics() {
        $stats = [
            'total_registered' => 0,
            'total_random' => 0,
            'active_customers' => 0,
            'pending_approvals' => 0,
            'inactive_customers' => 0,
            'high_value_customers' => 0,
            'total_revenue' => 0,
            'avg_order_value' => 0
        ];
        
        try {
            $this->syncOrderCustomerLinksByEmail();

            $customerRoleExpr = $this->customerRoleSql('u');
            $isActiveExpr = $this->hasUsersColumn('is_active') ? "COALESCE(u.is_active, 0)" : "1";
            $approvalExpr = $this->hasUsersColumn('approval_status') ? "COALESCE(u.approval_status, 'approved')" : "'approved'";
            $statusExpr = $this->hasUsersColumn('status') ? "COALESCE(u.status, 'active')" : "'active'";

            // Customer totals
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total_registered,
                    SUM(CASE WHEN {$isActiveExpr} = 1 THEN 1 ELSE 0 END) AS active_customers,
                    SUM(CASE WHEN {$approvalExpr} = 'pending' THEN 1 ELSE 0 END) AS pending_approvals,
                    SUM(CASE WHEN {$isActiveExpr} = 0 OR {$statusExpr} = 'inactive' THEN 1 ELSE 0 END) AS inactive_customers
                FROM users u
                WHERE {$customerRoleExpr} = 'customer'
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total_registered'] = (int) ($row['total_registered'] ?? 0);
            $stats['active_customers'] = (int) ($row['active_customers'] ?? 0);
            $stats['pending_approvals'] = (int) ($row['pending_approvals'] ?? 0);
            $stats['inactive_customers'] = (int) ($row['inactive_customers'] ?? 0);

            // Optional random (guest) customers count (not used by current cards, kept for compatibility)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT customer_email)
                FROM orders
                WHERE user_id IS NULL
                  AND customer_email IS NOT NULL
                  AND customer_email <> ''
            ");
            $stmt->execute();
            $stats['total_random'] = (int) $stmt->fetchColumn();

            // Customer order/revenue metrics only (registered customer orders)
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS order_count,
                    COALESCE(SUM(o.total_amount), 0) AS total_revenue,
                    COALESCE(AVG(o.total_amount), 0) AS avg_order_value
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                WHERE {$customerRoleExpr} = 'customer'
            ");
            $stmt->execute();
            $orderStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total_revenue'] = (float) ($orderStats['total_revenue'] ?? 0);
            $stats['avg_order_value'] = (float) ($orderStats['avg_order_value'] ?? 0);

            // High-value customers by lifetime spend (> 5000)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM (
                    SELECT o.user_id
                    FROM orders o
                    INNER JOIN users u ON u.id = o.user_id
                    WHERE {$customerRoleExpr} = 'customer'
                    GROUP BY o.user_id
                    HAVING SUM(o.total_amount) > 5000
                ) t
            ");
            $stmt->execute();
            $stats['high_value_customers'] = (int) $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Error fetching statistics: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Get single customer
     */
    public function getCustomer($id, $type = 'registered') {
        try {
            if ($type === 'random') {
                $query = "
                    SELECT 
                        customer_name as name,
                        customer_email as email,
                        customer_phone as phone,
                        shipping_address as address,
                        COUNT(id) as total_orders,
                        SUM(total_amount) as total_spent
                    FROM orders
                    WHERE customer_email = ?
                    GROUP BY customer_email, customer_name, customer_phone, shipping_address
                ";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$id]);
            } else {
                $query = "
                    SELECT * FROM users u
                    WHERE u.id = ?
                      AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(u.role, '')), '-', '_'), ' ', '_')) = 'customer'
                ";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$id]);
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customer: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Approve customer
     */
    public function approveCustomer($customerId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET approval_status = 'approved', approved_at = NOW()
                WHERE id = ?
                  AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(role, '')), '-', '_'), ' ', '_')) = 'customer'
            ");
            
            if ($stmt->execute([$customerId])) {
                return ['success' => true, 'message' => 'Customer approved successfully'];
            }
            return ['success' => false, 'message' => 'Failed to approve customer'];
        } catch (PDOException $e) {
            error_log("Error approving customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Reject customer
     */
    public function rejectCustomer($customerId, $reason) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET approval_status = 'rejected', rejection_reason = ?
                WHERE id = ?
                  AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(role, '')), '-', '_'), ' ', '_')) = 'customer'
            ");
            
            if ($stmt->execute([$reason, $customerId])) {
                return ['success' => true, 'message' => 'Customer rejected successfully'];
            }
            return ['success' => false, 'message' => 'Failed to reject customer'];
        } catch (PDOException $e) {
            error_log("Error rejecting customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Activate customer
     */
    public function activateCustomer($customerId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET is_active = 1, status = 'active'
                WHERE id = ?
                  AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(role, '')), '-', '_'), ' ', '_')) = 'customer'
            ");
            
            if ($stmt->execute([$customerId])) {
                return ['success' => true, 'message' => 'Customer activated successfully'];
            }
            return ['success' => false, 'message' => 'Failed to activate customer'];
        } catch (PDOException $e) {
            error_log("Error activating customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Deactivate customer
     */
    public function deactivateCustomer($customerId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET is_active = 0, status = 'inactive'
                WHERE id = ?
                  AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(role, '')), '-', '_'), ' ', '_')) = 'customer'
            ");
            
            if ($stmt->execute([$customerId])) {
                return ['success' => true, 'message' => 'Customer deactivated successfully'];
            }
            return ['success' => false, 'message' => 'Failed to deactivate customer'];
        } catch (PDOException $e) {
            error_log("Error deactivating customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Delete customer
     */
    public function deleteCustomer($customerId) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM users 
                WHERE id = ?
                  AND LOWER(REPLACE(REPLACE(TRIM(COALESCE(role, '')), '-', '_'), ' ', '_')) = 'customer'
            ");
            
            if ($stmt->execute([$customerId])) {
                return ['success' => true, 'message' => 'Customer deleted successfully'];
            }
            return ['success' => false, 'message' => 'Failed to delete customer'];
        } catch (PDOException $e) {
            error_log("Error deleting customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Convert random to registered
     */
    public function convertToRegistered($email, $name, $phone) {
        try {
            // Check if exists
            $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Generate temp password
            $tempPassword = bin2hex(random_bytes(4));
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Create user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    email, password_hash, full_name, phone, 
                    role, approval_status, status, is_active, email_verified, created_at
                ) VALUES (?, ?, ?, ?, 'customer', 'approved', 'active', 1, 1, NOW())
            ");
            
            if ($stmt->execute([$email, $passwordHash, $name, $phone])) {
                $userId = $this->pdo->lastInsertId();
                
                // Link orders
                $updateStmt = $this->pdo->prepare("
                    UPDATE orders 
                    SET user_id = ? 
                    WHERE customer_email = ? AND user_id IS NULL
                ");
                $updateStmt->execute([$userId, $email]);
                
                return [
                    'success' => true, 
                    'message' => "Customer converted. Temporary password: $tempPassword"
                ];
            }
            return ['success' => false, 'message' => 'Failed to create account'];
        } catch (PDOException $e) {
            error_log("Error converting customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Send email
     */
    public function sendEmail($email, $subject, $message) {
        try {
            $to = trim((string) $email);
            $subj = trim((string) $subject);
            $body = trim((string) $message);

            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid recipient email'];
            }
            if ($subj === '' || $body === '') {
                return ['success' => false, 'message' => 'Subject and message are required'];
            }

            $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
            $smtpUser = defined('SMTP_USER') ? SMTP_USER : '';
            $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
            $smtpPort = defined('SMTP_PORT') ? (int) SMTP_PORT : 465;
            $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl';
            $fromEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : $smtpUser;
            $fromName = defined('SITE_NAME') ? SITE_NAME : 'JAKISAWA SHOP';

            $smtpConfigured = $smtpHost !== ''
                && $smtpUser !== ''
                && $smtpPass !== ''
                && $smtpPass !== 'your-app-password'
                && $smtpPort > 0;
            $phpMailerLoaded = $this->loadPHPMailer();

            if (!$smtpConfigured || !$phpMailerLoaded) {
                // Fallback for servers without PHPMailer/SMTP wiring.
                return $this->sendEmailViaNativeMail($to, $subj, $body, $fromEmail, $fromName);
            }

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->Port = $smtpPort;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;

            if (!empty($smtpSecure)) {
                $mail->SMTPSecure = $smtpSecure;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);

            $mail->isHTML(true);
            $mail->Subject = $subj;
            $mail->Body = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            $mail->AltBody = $body;

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Throwable $e) {
            error_log("Error sending email: " . $e->getMessage());
            $fromEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (defined('SMTP_USER') ? SMTP_USER : '');
            $fromName = defined('SITE_NAME') ? SITE_NAME : 'JAKISAWA SHOP';
            $fallback = $this->sendEmailViaNativeMail(
                trim((string) $email),
                trim((string) $subject),
                trim((string) $message),
                $fromEmail,
                $fromName
            );
            if (!empty($fallback['success'])) {
                return $fallback;
            }
            return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
        }
    }

    private function sendEmailViaNativeMail($to, $subject, $body, $fromEmail, $fromName) {
        if (!function_exists('mail')) {
            return [
                'success' => false,
                'message' => 'Email service is unavailable. PHPMailer is missing and PHP mail() is disabled.'
            ];
        }

        $safeFromEmail = trim((string)$fromEmail);
        if (!filter_var($safeFromEmail, FILTER_VALIDATE_EMAIL)) {
            $safeFromEmail = 'no-reply@localhost';
        }
        $safeFromName = trim((string)$fromName);
        $safeFromName = preg_replace('/[\r\n]+/', ' ', $safeFromName);
        if ($safeFromName === '') {
            $safeFromName = 'JAKISAWA SHOP';
        }
        $safeFromName = addcslashes($safeFromName, "\\\"");

        $encodedSubject = '=?UTF-8?B?' . base64_encode((string)$subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: "' . $safeFromName . '" <' . $safeFromEmail . '>',
            'Reply-To: ' . $safeFromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];

        $sent = @mail((string)$to, $encodedSubject, (string)$body, implode("\r\n", $headers));
        if ($sent) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        }

        return ['success' => false, 'message' => 'Failed to send email using server mail()'];
    }

    /**
     * Load PHPMailer classes from common locations.
     */
    private function loadPHPMailer() {
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return true;
        }

        $paths = [
            dirname(__DIR__, 4) . '/vendor/autoload.php',
            dirname(__DIR__, 4) . '/PHPMailer/src/PHPMailer.php',
            dirname(__DIR__, 5) . '/vendor/autoload.php',
            dirname(__DIR__, 5) . '/PHPMailer/src/PHPMailer.php',
            '/home1/public_html/PHPMailer/src/PHPMailer.php'
        ];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (substr($path, -12) === 'autoload.php') {
                require_once $path;
                if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                    return true;
                }
                continue;
            }

            $base = dirname($path);
            if (file_exists($base . '/Exception.php')) {
                require_once $base . '/Exception.php';
            }
            require_once $base . '/PHPMailer.php';
            if (file_exists($base . '/SMTP.php')) {
                require_once $base . '/SMTP.php';
            }

            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a single SMS via configured provider.
     */
    public function sendSMS($phone, $message) {
        try {
            $normalizedPhone = $this->normalizePhone($phone);
            $smsMessage = trim((string)$message);

            if ($normalizedPhone === null) {
                return ['success' => false, 'message' => 'Invalid phone number format'];
            }
            if ($smsMessage === '') {
                return ['success' => false, 'message' => 'SMS message is required'];
            }

            $provider = strtolower((string)(defined('SMS_PROVIDER') ? SMS_PROVIDER : 'none'));
            if ($provider === 'none' || $provider === '') {
                return ['success' => false, 'message' => 'SMS provider is not configured'];
            }

            if ($provider === 'africastalking') {
                return $this->sendSmsViaAfricaTalking($normalizedPhone, $smsMessage);
            }

            return ['success' => false, 'message' => 'Unsupported SMS provider configured'];
        } catch (\Throwable $e) {
            error_log('Error sending SMS: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    private function normalizePhone($phone) {
        $raw = trim((string)$phone);
        if ($raw === '') {
            return null;
        }

        // Keep only digits and plus
        $clean = preg_replace('/[^0-9+]/', '', $raw);
        if ($clean === null || $clean === '') {
            return null;
        }

        // Convert local Kenyan numbers to international format
        if (strpos($clean, '+') === 0) {
            $digits = preg_replace('/\D/', '', $clean);
            return $digits ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $clean);
        if ($digits === '') {
            return null;
        }

        // 07XXXXXXXX or 01XXXXXXXX
        if (preg_match('/^(0)(7|1)\d{8}$/', $digits)) {
            return '+254' . substr($digits, 1);
        }

        // 2547XXXXXXXX or 2541XXXXXXXX
        if (preg_match('/^254(7|1)\d{8}$/', $digits)) {
            return '+' . $digits;
        }

        // Generic fallback if already long enough
        if (strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        return null;
    }

    private function sendSmsViaAfricaTalking($phone, $message) {
        $username = defined('SMS_AT_USERNAME') ? SMS_AT_USERNAME : '';
        $apiKey = defined('SMS_AT_API_KEY') ? SMS_AT_API_KEY : '';
        $from = defined('SMS_AT_SENDER_ID') ? SMS_AT_SENDER_ID : '';
        $endpoint = defined('SMS_AT_ENDPOINT') ? SMS_AT_ENDPOINT : 'https://api.africastalking.com/version1/messaging';

        if ($username === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Africa\'s Talking SMS credentials are missing'];
        }
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL is not enabled on this server'];
        }

        $payload = [
            'username' => $username,
            'to' => $phone,
            'message' => $message
        ];
        if ($from !== '') {
            $payload['from'] = $from;
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
            return ['success' => false, 'message' => 'SMS provider rejected request (HTTP ' . $status . ')'];
        }

        $data = json_decode((string)$resp, true);
        if (is_array($data)) {
            $recipients = $data['SMSMessageData']['Recipients'] ?? [];
            if (is_array($recipients) && count($recipients) > 0) {
                $first = $recipients[0];
                $statusText = strtolower((string)($first['status'] ?? ''));
                if (strpos($statusText, 'success') !== false) {
                    return ['success' => true, 'message' => 'SMS sent successfully'];
                }
            }
        }

        return ['success' => false, 'message' => 'SMS send failed or unconfirmed by provider'];
    }

    /**
     * Communication stack health checks (SMTP + SMS).
     */
    public function getCommunicationHealth() {
        $health = [
            'smtp' => [
                'ready' => false,
                'details' => []
            ],
            'sms' => [
                'ready' => false,
                'details' => []
            ]
        ];

        $smtpHost = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
        $smtpUser = defined('SMTP_USER') ? (string)SMTP_USER : '';
        $smtpPass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
        $smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : 0;
        $smtpSecure = defined('SMTP_SECURE') ? (string)SMTP_SECURE : '';
        $phpMailerLoaded = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') || $this->loadPHPMailer();
        $nativeMailAvailable = function_exists('mail');
        $smtpConfigured = $smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '' && $smtpPass !== 'your-app-password' && $smtpPort > 0;

        $health['smtp']['details'][] = ['label' => 'SMTP host', 'ok' => $smtpHost !== '', 'value' => $smtpHost ?: 'missing'];
        $health['smtp']['details'][] = ['label' => 'SMTP user', 'ok' => $smtpUser !== '', 'value' => $smtpUser ?: 'missing'];
        $health['smtp']['details'][] = ['label' => 'SMTP password', 'ok' => ($smtpPass !== '' && $smtpPass !== 'your-app-password'), 'value' => $smtpPass !== '' ? 'set' : 'missing'];
        $health['smtp']['details'][] = ['label' => 'SMTP port', 'ok' => $smtpPort > 0, 'value' => $smtpPort > 0 ? (string)$smtpPort : 'missing'];
        $health['smtp']['details'][] = ['label' => 'SMTP secure', 'ok' => $smtpSecure !== '', 'value' => $smtpSecure ?: 'missing'];
        $health['smtp']['details'][] = ['label' => 'PHPMailer', 'ok' => $phpMailerLoaded, 'value' => $phpMailerLoaded ? 'loaded' : 'not found'];
        $health['smtp']['details'][] = ['label' => 'PHP mail() fallback', 'ok' => $nativeMailAvailable, 'value' => $nativeMailAvailable ? 'available' : 'disabled'];
        $health['smtp']['ready'] = ($smtpConfigured && $phpMailerLoaded) || $nativeMailAvailable;

        $provider = defined('SMS_PROVIDER') ? strtolower((string)SMS_PROVIDER) : 'none';
        $atUser = defined('SMS_AT_USERNAME') ? (string)SMS_AT_USERNAME : '';
        $atKey = defined('SMS_AT_API_KEY') ? (string)SMS_AT_API_KEY : '';
        $atSender = defined('SMS_AT_SENDER_ID') ? (string)SMS_AT_SENDER_ID : '';
        $curlEnabled = function_exists('curl_init');

        $health['sms']['details'][] = ['label' => 'Provider', 'ok' => ($provider !== '' && $provider !== 'none'), 'value' => $provider];
        $health['sms']['details'][] = ['label' => 'cURL extension', 'ok' => $curlEnabled, 'value' => $curlEnabled ? 'enabled' : 'disabled'];
        if ($provider === 'africastalking') {
            $health['sms']['details'][] = ['label' => 'AT username', 'ok' => $atUser !== '', 'value' => $atUser ?: 'missing'];
            $health['sms']['details'][] = ['label' => 'AT API key', 'ok' => $atKey !== '', 'value' => $atKey !== '' ? 'set' : 'missing'];
            $health['sms']['details'][] = ['label' => 'AT sender ID', 'ok' => true, 'value' => $atSender !== '' ? $atSender : '(optional/blank)'];
            $health['sms']['ready'] = $curlEnabled && $atUser !== '' && $atKey !== '';
        } else {
            $health['sms']['ready'] = false;
        }

        return $health;
    }
    
    /**
     * Get customer orders
     */
    public function getCustomerOrders($customerId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    order_number,
                    total_amount,
                    payment_status,
                    order_status,
                    created_at
                FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customer orders: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search customers
     */
    public function search($query, $type = 'registered', $limit = 10) {
        try {
            if ($type === 'random') {
                $sql = "
                    SELECT DISTINCT customer_email as id, customer_name as name, customer_email as email, customer_phone as phone
                    FROM orders
                    WHERE (customer_name LIKE ? OR customer_email LIKE ?)
                    AND user_id IS NULL
                    LIMIT ?
                ";
            } else {
                $sql = "
                    SELECT id, full_name as name, email, phone
                    FROM users
                    WHERE role = 'customer'
                    AND (full_name LIKE ? OR email LIKE ?)
                    LIMIT ?
                ";
            }
            
            $searchTerm = "%$query%";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$searchTerm, $searchTerm, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching customers: " . $e->getMessage());
            return [];
        }
    }



/**
 * Get detailed customer information
 */
public function getCustomerDetails($id, $type = 'registered') {
    try {
        if ($type === 'random') {
            $query = "
                SELECT 
                    customer_name as name,
                    customer_email as email,
                    customer_phone as phone,
                    shipping_address as address,
                    billing_address,
                    COUNT(id) as total_orders,
                    SUM(total_amount) as total_spent,
                    AVG(total_amount) as avg_order_value,
                    MIN(created_at) as first_order_date,
                    MAX(created_at) as last_order_date,
                    GROUP_CONCAT(DISTINCT order_status) as order_statuses,
                    GROUP_CONCAT(DISTINCT payment_status) as payment_statuses,
                    GROUP_CONCAT(DISTINCT payment_method) as payment_methods,
                    COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments,
                    SUM(CASE WHEN order_status = 'delivered' THEN total_amount ELSE 0 END) as completed_revenue,
                    SUM(CASE WHEN order_status = 'pending' THEN total_amount ELSE 0 END) as pending_revenue
                FROM orders
                WHERE customer_email = ?
                GROUP BY customer_email, customer_name, customer_phone, 
                         shipping_address, billing_address
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // Get order history
                $orderQuery = "
                    SELECT 
                        id,
                        order_number,
                        total_amount,
                        order_status,
                        payment_status,
                        payment_method,
                        created_at as order_date,
                        updated_at as last_updated
                    FROM orders
                    WHERE customer_email = ?
                    ORDER BY created_at DESC
                    LIMIT 50
                ";
                $orderStmt = $this->pdo->prepare($orderQuery);
                $orderStmt->execute([$id]);
                $customer['orders'] = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get shipping addresses if multiple
                $addressQuery = "
                    SELECT DISTINCT 
                        shipping_address,
                        COUNT(*) as address_usage,
                        MAX(created_at) as last_used
                    FROM orders
                    WHERE customer_email = ?
                    GROUP BY shipping_address
                    ORDER BY address_usage DESC
                ";
                $addressStmt = $this->pdo->prepare($addressQuery);
                $addressStmt->execute([$id]);
                $customer['shipping_addresses'] = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } else {
            $query = "
                SELECT 
                    u.*,
                    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
                    (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id) as total_spent,
                    (SELECT AVG(total_amount) FROM orders WHERE user_id = u.id) as avg_order_value,
                    (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date,
                    (SELECT MIN(created_at) FROM orders WHERE user_id = u.id) as first_order_date,
                    (SELECT COUNT(CASE WHEN order_status = 'pending' THEN 1 END) FROM orders WHERE user_id = u.id) as pending_orders,
                    (SELECT COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) FROM orders WHERE user_id = u.id) as completed_orders,
                    (SELECT COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) FROM orders WHERE user_id = u.id) as cancelled_orders
                FROM users u
                WHERE u.id = ? AND u.role = 'customer'
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // Get all user metadata if exists
                try {
                    $metaQuery = "SELECT meta_key, meta_value FROM user_meta WHERE user_id = ?";
                    $metaStmt = $this->pdo->prepare($metaQuery);
                    $metaStmt->execute([$id]);
                    $customer['metadata'] = $metaStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                } catch (Exception $e) {
                    $customer['metadata'] = [];
                }
                
                // Get order history
                $orderQuery = "
                    SELECT 
                        id,
                        order_number,
                        total_amount,
                        order_status,
                        payment_status,
                        payment_method,
                        shipping_address,
                        billing_address,
                        created_at as order_date,
                        updated_at as last_updated,
                        notes
                    FROM orders
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 50
                ";
                
                $orderStmt = $this->pdo->prepare($orderQuery);
                $orderStmt->execute([$id]);
                $customer['orders'] = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get shipping addresses history
                $addressQuery = "
                    SELECT DISTINCT 
                        shipping_address,
                        COUNT(*) as usage_count,
                        MAX(created_at) as last_used
                    FROM orders
                    WHERE user_id = ?
                    GROUP BY shipping_address
                    ORDER BY usage_count DESC
                ";
                $addressStmt = $this->pdo->prepare($addressQuery);
                $addressStmt->execute([$id]);
                $customer['shipping_addresses'] = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get login history if table exists
                try {
                    $loginQuery = "
                        SELECT login_time, ip_address, user_agent, success 
                        FROM user_login_history 
                        WHERE user_id = ? 
                        ORDER BY login_time DESC 
                        LIMIT 10
                    ";
                    $loginStmt = $this->pdo->prepare($loginQuery);
                    $loginStmt->execute([$id]);
                    $customer['login_history'] = $loginStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $customer['login_history'] = [];
                }
            }
        }
        
        return $customer;
        


    } 
    
    catch (PDOException $e) {
        error_log("Error getting customer details: " . $e->getMessage());
        return null;
    }
}
}

?>
