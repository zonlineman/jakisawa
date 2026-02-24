<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paths.php';

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

date_default_timezone_set('Africa/Nairobi');

define('APP_ROOT', __DIR__);
define('APP_ENV', 'production');

if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    die('Direct access not permitted');
}

define('SITENAME', 'JAKISAWA SHOP');
define('SITE_NAME', 'JAKISAWA SHOP');
define('CURRENCY', 'KES');
define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('SUPPORT_EMAIL', 'support@jakisawashop.co.ke');
define('APP_NAME', 'JAKISAWA SHOP');

if (!function_exists('normalizeUserRole')) {
    function normalizeUserRole($role) {
        $normalized = strtolower(trim((string)$role));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized);

        if ($normalized === 'superadmin') {
            $normalized = 'super_admin';
        } elseif ($normalized === 'administrator') {
            $normalized = 'admin';
        }

        return $normalized;
    }
}

if (!function_exists('isCustomerRole')) {
    function isCustomerRole($role) {
        return normalizeUserRole($role) === 'customer';
    }
}

if (!function_exists('isAdminStaffOrSuperRole')) {
    function isAdminStaffOrSuperRole($role) {
        $normalized = normalizeUserRole($role);
        return in_array($normalized, ['admin', 'staff', 'super_admin'], true);
    }
}

// SMTP Settings
define('SMTP_HOST', 'mail.jakisawashop.co.ke');
define('SMTP_USER', 'support@jakisawashop.co.ke');
define('SMTP_PASS', '#@Support,2026');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
if (!defined('VERIFICATION_LINK_EXPIRY_HOURS')) define('VERIFICATION_LINK_EXPIRY_HOURS', 5);

// SMS Settings
if (!defined('SMS_PROVIDER')) define('SMS_PROVIDER', 'none');
if (!defined('SMS_AT_USERNAME')) define('SMS_AT_USERNAME', '');
if (!defined('SMS_AT_API_KEY')) define('SMS_AT_API_KEY', '');
if (!defined('SMS_AT_SENDER_ID')) define('SMS_AT_SENDER_ID', '');
if (!defined('SMS_AT_ENDPOINT')) define('SMS_AT_ENDPOINT', 'https://api.africastalking.com/version1/messaging');

function generateOrderNumber($pdo) {
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "ORD{$date}{$random}";
}

function generateVerificationToken() {
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return sha1(uniqid((string)mt_rand(), true));
    }
}

function getCustomerBaseUrl() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function getCustomerPublicPath($relativePath) {
    $relativePath = ltrim((string)$relativePath, '/');
    $base = (defined('PROJECT_BASE_URL') && PROJECT_BASE_URL !== '') ? PROJECT_BASE_URL : '';
    $customerSubdirExists = is_dir(__DIR__ . '/customer');
    if ($customerSubdirExists) {
        return $base . '/' . $relativePath;
    }
    return $base . '/' . $relativePath;
}

function sendCustomerVerificationEmail($toEmail, $toName, $token) {
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '' || $token === '') {
        return false;
    }

    $expiryHours = defined('VERIFICATION_LINK_EXPIRY_HOURS') ? (int)VERIFICATION_LINK_EXPIRY_HOURS : 5;
    if ($expiryHours < 1) {
        $expiryHours = 5;
    }

    // Load PHPMailer
    $phpmailerBase = '/home1/jakisawa/public_html/PHPMailer/src';
    require_once $phpmailerBase . '/Exception.php';
    require_once $phpmailerBase . '/PHPMailer.php';
    require_once $phpmailerBase . '/SMTP.php';

    $verifyUrl = getCustomerBaseUrl() . getCustomerPublicPath('verify-email.php') . '?email='
               . urlencode($toEmail) . '&token=' . urlencode($token);
    $safeName  = htmlspecialchars($toName ?: 'Customer', ENT_QUOTES, 'UTF-8');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($toEmail, $safeName);
        $mail->addReplyTo(SUPPORT_EMAIL, SITE_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your email - ' . SITE_NAME;
        $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #222;'>
                <h2 style='color:#2e7d32;'>Verify your email address</h2>
                <p>Hello {$safeName},</p>
                <p>Thanks for creating your account. Click below to verify your email:</p>
                <p style='margin: 24px 0;'>
                    <a href='{$verifyUrl}' style='background:#2e7d32;color:#fff;
                    padding:12px 18px;border-radius:6px;text-decoration:none;'>
                    Verify Email</a>
                </p>
                <p>Or copy this link: <a href='{$verifyUrl}'>{$verifyUrl}</a></p>
                <p>This verification link expires in {$expiryHours} hours.</p>
                <p>This link was requested for your account on " . SITE_NAME . ".</p>
            </body>
            </html>
        ";
        $mail->AltBody = "Hello " . ($toName ?: 'Customer') . ",\n\n"
            . "Verify your email using this link:\n{$verifyUrl}\n\n"
            . "This verification link expires in {$expiryHours} hours.";

        $startedAt = microtime(true);
        $mail->send();
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        if ($elapsedMs >= 4000) {
            error_log('Verification email SMTP send latency: ' . $elapsedMs . 'ms to ' . $toEmail);
        }
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

function getDBConnection() {
    static $conn = null;
    if ($conn === null) {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            error_log("Database connection failed: " . mysqli_connect_error());
            return false;
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

function safeQuery($sql) {
    $conn = getDBConnection();
    if (!$conn) return false;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("Query failed: " . mysqli_error($conn) . " | SQL: $sql");
        return false;
    }
    return $result;
}
?>
