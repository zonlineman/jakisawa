<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$showSignupOption = false;
$showForgotOption = false;
$showVerifyOption = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email, phone, role, password_hash, email_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Account not found.';
            $showSignupOption = true;
            $showForgotOption = true;
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid password.';
            $showForgotOption = true;
        } else {
            $sessionRole = normalizeUserRole($user['role'] ?? '');
            $knownRoles = ['customer', 'admin', 'staff', 'super_admin'];
            if ($sessionRole === '' || !in_array($sessionRole, $knownRoles, true)) {
                $roleFixStmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id = ? LIMIT 1");
                $roleFixStmt->execute([(int)$user['id']]);
                $sessionRole = 'customer';
            }

            $isEmailVerified = !isset($user['email_verified']) || (int)$user['email_verified'] === 1;
            $isAdminLikeRole = in_array($sessionRole, ['admin', 'staff', 'super_admin'], true);
            if (!$isEmailVerified && $isAdminLikeRole) {
                $error = 'Email not verified. Please verify your email before signing in.';
                $showVerifyOption = true;
            } else {
                unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_role']);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'] ?? '';
                $_SESSION['role'] = $sessionRole;

                $cartItemCount = 0;
                if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $item) {
                        $cartItemCount += (int)($item['quantity'] ?? 0);
                    }
                }

                header('Location: ' . ($cartItemCount > 0 ? 'index.php?section=order' : 'index.php'));
                exit();
            }
        }
    }
}
$prefillEmail = trim($_POST['email'] ?? ($_GET['email'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Sign In</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
        .wrap { max-width:420px; margin:50px auto; background:#fff; border:1px solid #ddd; border-radius:8px; padding:24px; }
        h1 { margin-top:0; font-size:24px; }
        label { display:block; margin:12px 0 6px; }
        input { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        button { margin-top:16px; width:100%; padding:11px; border:0; border-radius:6px; background:#2e7d32; color:#fff; cursor:pointer; }
        .err { background:#fdecea; color:#b42318; border:1px solid #f5c2c7; padding:10px; border-radius:6px; margin-bottom:10px; }
        .ok { background:#ecfdf3; color:#067647; border:1px solid #abefc6; padding:10px; border-radius:6px; margin-bottom:10px; }
        .links { margin-top:14px; font-size:14px; text-align:center; }
        a { color:#2e7d32; text-decoration:none; }
        .muted { margin-top:10px; font-size:13px; color:#666; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Customer Sign In</h1>
        <?php if ($error !== ''): ?>
            <div class="err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($showSignupOption): ?>
            <div class="ok">No account for this email. <a href="signup.php?email=<?php echo urlencode($prefillEmail); ?>">Create one here</a>.</div>
        <?php endif; ?>
        <?php if ($showForgotOption): ?>
            <div class="ok">Forgot password? <a href="system/forgot_password.php?email=<?php echo urlencode($prefillEmail); ?>">Send reset link</a>.</div>
        <?php endif; ?>
        <?php if ($showVerifyOption): ?>
            <div class="ok">Need a fresh verification email? <a href="signup.php?email=<?php echo urlencode($prefillEmail); ?>">Resend verification</a>.</div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="ok"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($prefillEmail); ?>" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            <button type="submit">Sign In</button>
        </form>
        <div class="links">
            <a href="signup.php">Create customer account</a> | <a href="system/forgot_password.php">Forgot password</a> | <a href="index.php">Back to shop</a>
        </div>
    </div>
</body>
</html>
