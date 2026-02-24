<?php
// File: forgot_password.php - WORKING VERSION
session_start();
require_once 'includes/database.php';
require_once 'includes/config.php';

$message = '';
$error = '';
$prefillEmail = trim($_GET['email'] ?? '');
$conn = getDBConnection();

function ensureResetColumns($conn): void {
    $columns = [];
    $result = mysqli_query($conn, "DESCRIBE users");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = strtolower((string)$row['Field']);
        }
    }

    if (!in_array('reset_token', $columns, true)) {
        @mysqli_query($conn, "ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) NULL");
    }
    if (!in_array('reset_expires', $columns, true)) {
        @mysqli_query($conn, "ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL");
    }
}

function sendResetLinkEmail($toEmail, $toName, $resetUrl): bool {
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '' || $resetUrl === '') {
        return false;
    }

    $phpmailerBase = dirname(__DIR__) . '/PHPMailer/src';
    if (is_dir($phpmailerBase)) {
        require_once $phpmailerBase . '/Exception.php';
        require_once $phpmailerBase . '/PHPMailer.php';
        require_once $phpmailerBase . '/SMTP.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_USER, SITE_NAME);
            $mail->addAddress($toEmail, $toName ?: 'User');
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Link - ' . SITE_NAME;
            $mail->Body = "
                <p>Hello " . htmlspecialchars($toName ?: 'User') . ",</p>
                <p>We received a request to reset your password.</p>
                <p><a href=\"" . htmlspecialchars($resetUrl) . "\">Click here to reset your password</a></p>
                <p>This link expires in 1 hour.</p>
                <p>If you did not request this, you can ignore this email.</p>
            ";
            $mail->AltBody = "Reset your password using this link: {$resetUrl}";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Password reset mail failed: ' . $e->getMessage());
        }
    }

    // Fallback to native mail()
    $subject = 'Password Reset Link - ' . SITE_NAME;
    $headers = "From: " . SUPPORT_EMAIL . "\r\nReply-To: " . SUPPORT_EMAIL . "\r\n";
    $body = "Hello {$toName},\n\nReset your password using this link:\n{$resetUrl}\n\nThis link expires in 1 hour.";
    return @mail($toEmail, $subject, $body, $headers);
}

ensureResetColumns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $prefillEmail = $email;
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if user exists
        $sql = "SELECT id, email, full_name FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            try {
                $resetToken = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $resetToken = sha1(uniqid('reset_', true));
            }

            $expiry = date('Y-m-d H:i:s', time() + 3600);
            $update_sql = "UPDATE users SET reset_token = ?, reset_expires = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssi", $resetToken, $expiry, $user['id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
                $scheme = $isHttps ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/system')), '/');
                $resetUrl = $scheme . '://' . $host . $scriptDir . '/reset_password.php?token=' . urlencode($resetToken);

                sendResetLinkEmail($user['email'], $user['full_name'] ?? 'User', $resetUrl);
                $message = "If an account exists for this email, a reset link has been sent.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            // Do not reveal account existence.
            $message = "If an account exists for this email, a reset link has been sent.";
        }
        mysqli_stmt_close($stmt);
    }
}

// HTML Template
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - JAKISAWA SHOP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .forgot-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            margin: auto;
        }
        .forgot-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .forgot-body {
            padding: 25px;
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container">
        <div class="forgot-box">
            <div class="forgot-header">
                <h3><i class="bi bi-key"></i> Reset Password</h3>
                <p class="mb-0">Enter your email to reset password</p>
            </div>
            
            <div class="forgot-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle me-2"></i>Reset Link Sent</h5>
                        <?php echo $message; ?>
                        <hr>
                        <a href="login.php" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($prefillEmail); ?>"
                                   placeholder="Enter your registered email" autofocus>
                            <small class="text-muted">A password reset link will be sent to this address.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mb-3">
                            <i class="bi bi-send me-2"></i>Reset Password
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </form>
                    
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle me-2"></i>How it works:</h6>
                            <ul class="mb-0">
                                <li>Enter your email address</li>
                                <li>We send a secure reset link to your registered email</li>
                                <li>The reset link expires in 1 hour</li>
                                <li>Set your new password on the reset page</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
