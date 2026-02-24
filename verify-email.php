<?php
session_start();
require_once 'config.php';

$status = 'error';
$message = 'Invalid verification link.';

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($email !== '' && $token !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ? AND verification_token = ? LIMIT 1");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ((int)($user['email_verified'] ?? 0) === 1) {
            $status = 'ok';
            $message = 'Your email is already verified. You can sign in.';
        } else {
            $upd = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ? LIMIT 1");
            $upd->execute([(int)$user['id']]);
            $status = 'ok';
            $message = 'Email verified successfully. You can now sign in.';
        }
    } else {
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
