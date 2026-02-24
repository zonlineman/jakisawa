<?php
session_start();
require_once 'config.php';

$status = 'error';
$message = 'Invalid verification link.';
$verifiedEmail = '';

$verificationHours = defined('VERIFICATION_LINK_EXPIRY_HOURS') ? (int)VERIFICATION_LINK_EXPIRY_HOURS : 5;
if ($verificationHours < 1) { $verificationHours = 5; }

function verifyUsersHasColumn(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) { return $cache[$column]; }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column));
        $cache[$column] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('verifyUsersHasColumn failed for "' . $column . '": ' . $e->getMessage());
        $cache[$column] = false;
    }
    return $cache[$column];
}

$hasVerificationExpiry = verifyUsersHasColumn($pdo, 'verification_token_expires');

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($email !== '' && $token !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $verifiedEmail = $email;

    if ($hasVerificationExpiry) {
        $stmt = $pdo->prepare("
            SELECT id, email_verified, verification_token_expires
            FROM users
            WHERE email = ?
              AND verification_token = ?
              AND (verification_token_expires IS NULL OR verification_token_expires >= NOW())
            LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ? AND verification_token = ? LIMIT 1");
    }
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ((int)($user['email_verified'] ?? 0) === 1) {
            $status  = 'already';
            $message = 'Your email is already verified. You can sign in now.';
        } else {
            if ($hasVerificationExpiry) {
                $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ? LIMIT 1");
            } else {
                $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ? LIMIT 1");
            }
            $upd->execute([(int)$user['id']]);
            $status  = 'ok';
            $message = 'Email verified successfully! Your account is now fully active.';
        }
    } else {
        // Check if token exists but expired
        if ($hasVerificationExpiry) {
            $expiredCheck = $pdo->prepare("SELECT id, email_verified, verification_token_expires FROM users WHERE email = ? AND verification_token = ? LIMIT 1");
            $expiredCheck->execute([$email, $token]);
            $expiredMatch = $expiredCheck->fetch(PDO::FETCH_ASSOC);
            if ($expiredMatch && (int)($expiredMatch['email_verified'] ?? 0) !== 1
                && !empty($expiredMatch['verification_token_expires'])
                && strtotime((string)$expiredMatch['verification_token_expires']) < time()) {
                $status  = 'expired';
                $message = "This verification link has expired (links are valid for {$verificationHours} hours). Please request a new one by signing up again.";
            }
        }

        // Check if already verified by another means
        if (!in_array($status, ['expired'])) {
            $emailCheck = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ? LIMIT 1");
            $emailCheck->execute([$email]);
            $existing = $emailCheck->fetch(PDO::FETCH_ASSOC);
            if ($existing && (int)($existing['email_verified'] ?? 0) === 1) {
                $status  = 'already';
                $message = 'Your email is already verified. You can sign in now.';
            } else {
                $status  = 'error';
                $message = 'This verification link is invalid or has already been used. Please sign up again to get a fresh link.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification – JAKISAWA SHOP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #2e7d32; --primary-dark: #1b5e20; --secondary: #ff9800; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #f9f9f9 100%);
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 20px;
        }
        .brand { text-align: center; margin-bottom: 20px; }
        .brand i { font-size: 2.5rem; color: var(--primary); }
        .brand h1 { font-size: 1.8rem; color: var(--primary); margin-top: 6px; }
        .brand span { color: var(--secondary); }
        .card {
            width: 100%; max-width: 420px; background: #fff;
            border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 36px 28px; text-align: center;
        }
        .icon { font-size: 3.5rem; margin-bottom: 16px; }
        .icon-success { color: var(--primary); }
        .icon-warning { color: #f57c00; }
        .icon-error   { color: #c62828; }
        .card h2 { font-size: 1.4rem; color: var(--primary-dark); margin-bottom: 10px; }
        .card p  { color: #555; font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px; }
        .alert {
            padding: 12px 14px; border-radius: 8px; margin-bottom: 20px;
            font-size: 0.93rem; display: flex; gap: 10px; align-items: flex-start; text-align: left;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .alert-warn    { background: #fff8e1; color: #5d4037; border: 1px solid #ffe082; }
        .alert-error   { background: #fdecea; color: #b42318; border: 1px solid #f5c2c7; }
        .btn {
            display: block; width: 100%; padding: 12px; border: none;
            border-radius: 8px; font-size: 1rem; font-weight: 600;
            cursor: pointer; text-align: center; text-decoration: none;
            transition: background 0.2s;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-ghost { background: transparent; border: 1.5px solid #ddd; color: #555; margin-top: 10px; }
        .btn-ghost:hover { background: #f5f5f5; }
        .footer-links { margin-top: 18px; font-size: 0.88rem; color: #888; }
        .footer-links a { color: var(--primary); text-decoration: none; }
    </style>
</head>
<body>
    <div class="brand">
        <i class="fas fa-leaf"></i>
        <h1>JAKISAWA <span>SHOP</span></h1>
    </div>

    <div class="card">
        <?php if ($status === 'ok'): ?>
            <div class="icon icon-success"><i class="fas fa-check-circle"></i></div>
            <h2>Email Verified!</h2>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <a href="login.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign In Now
            </a>
            <a href="index.php" class="btn btn-ghost"><i class="fas fa-store"></i> Go to Shop</a>

        <?php elseif ($status === 'already'): ?>
            <div class="icon icon-success"><i class="fas fa-shield-alt"></i></div>
            <h2>Already Verified</h2>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <a href="login.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </a>

        <?php elseif ($status === 'expired'): ?>
            <div class="icon icon-warning"><i class="fas fa-clock"></i></div>
            <h2>Link Expired</h2>
            <div class="alert alert-warn">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <a href="signup.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-primary">
                <i class="fas fa-envelope"></i> Resend Verification
            </a>
            <a href="reset_password.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-ghost">
                <i class="fas fa-key"></i> Reset Password Instead
            </a>

        <?php else: ?>
            <div class="icon icon-error"><i class="fas fa-times-circle"></i></div>
            <h2>Verification Failed</h2>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <a href="signup.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Sign Up / Resend Link
            </a>
            <a href="login.php" class="btn btn-ghost"><i class="fas fa-sign-in-alt"></i> Try Signing In</a>
            <a href="reset_password.php<?php echo $verifiedEmail !== '' ? '?email=' . urlencode($verifiedEmail) : ''; ?>"
               class="btn btn-ghost">
                <i class="fas fa-key"></i> Reset Password
            </a>
        <?php endif; ?>
    </div>

    <div class="footer-links">
        Need help? Call <strong>0792546080</strong> &nbsp;|&nbsp; <a href="index.php">Back to Shop</a>
    </div>
</body>
</html>