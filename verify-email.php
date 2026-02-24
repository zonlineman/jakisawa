<?php
session_start();
require_once 'config.php';

$status = 'error';
$message = 'Invalid verification link.';

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
$verificationHours = defined('VERIFICATION_LINK_EXPIRY_HOURS') ? (int)VERIFICATION_LINK_EXPIRY_HOURS : 5;
if ($verificationHours < 1) {
    $verificationHours = 5;
}

function verifyUsersHasColumn(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

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

if ($email !== '' && $token !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            $status = 'ok';
            $message = 'Your email is already verified. You can sign in.';
        } else {
            if ($hasVerificationExpiry) {
                $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ? LIMIT 1");
            } else {
                $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ? LIMIT 1");
            }
            $upd->execute([(int)$user['id']]);
            $status = 'ok';
            $message = 'Email verified successfully. You can now sign in.';
        }
    } else {
        if ($hasVerificationExpiry) {
            $expiredCheck = $pdo->prepare("SELECT id, email_verified, verification_token_expires FROM users WHERE email = ? AND verification_token = ? LIMIT 1");
            $expiredCheck->execute([$email, $token]);
            $expiredMatch = $expiredCheck->fetch(PDO::FETCH_ASSOC);
            if (
                $expiredMatch
                && (int)($expiredMatch['email_verified'] ?? 0) !== 1
                && !empty($expiredMatch['verification_token_expires'])
                && strtotime((string)$expiredMatch['verification_token_expires']) < time()
            ) {
                $status = 'error';
                $message = 'This verification link expired. Please sign up again to get a new link (valid for ' . $verificationHours . ' hours).';
            }
        }

        if ($status !== 'error' || $message === 'Invalid verification link.') {
            $emailCheck = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ? LIMIT 1");
            $emailCheck->execute([$email]);
            $existing = $emailCheck->fetch(PDO::FETCH_ASSOC);
            if ($existing && (int)($existing['email_verified'] ?? 0) === 1) {
                $status = 'ok';
                $message = 'Your email is already verified. You can sign in.';
            } else {
                $status = 'error';
                $message = 'Verification link is invalid or expired. Please sign in.';
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
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
        .wrap { max-width:520px; margin:50px auto; background:#fff; border:1px solid #ddd; border-radius:8px; padding:24px; }
        h1 { margin-top:0; font-size:24px; }
        .ok { background:#ecfdf3; color:#067647; border:1px solid #abefc6; padding:12px; border-radius:6px; margin-bottom:14px; }
        .err { background:#fdecea; color:#b42318; border:1px solid #f5c2c7; padding:12px; border-radius:6px; margin-bottom:14px; }
        .links a { color:#2e7d32; text-decoration:none; margin-right:10px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Email Verification</h1>
        <div class="<?php echo $status === 'ok' ? 'ok' : 'err'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <div class="links">
            <a href="login.php<?php echo $email !== '' ? '?email=' . urlencode($email) : ''; ?>">Go to Sign In</a>
            <a href="signup.php">Create Account</a>
            <a href="index.php">Back to Shop</a>
        </div>
    </div>
</body>
</html>
