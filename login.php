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
            $error = 'No account found with this email address.';
            $showSignupOption = true;
            $showForgotOption = true;
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect password. Please try again or reset your password.';
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
    <title>Sign In – JAKISAWA SHOP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary: #ff9800;
            --danger: #d32f2f;
            --warning-bg: #fff8e1;
            --warning-border: #ffe082;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #f9f9f9 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .brand i { font-size: 2.5rem; color: var(--primary); }
        .brand h1 { font-size: 1.8rem; color: var(--primary); margin-top: 6px; }
        .brand span { color: var(--secondary); }
        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 32px 28px;
        }
        .card h2 {
            font-size: 1.4rem;
            color: var(--primary-dark);
            margin-bottom: 20px;
            text-align: center;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 0.93rem;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-error { background: #fdecea; color: #b42318; border: 1px solid #f5c2c7; }
        .alert-info  { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .alert-warn  { background: var(--warning-bg); color: #5d4037; border: 1px solid var(--warning-border); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: #444; margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.95rem; }
        .input-wrap input {
            width: 100%;
            padding: 11px 12px 11px 36px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 0.97rem;
            transition: border-color 0.2s;
        }
        .input-wrap input:focus { outline: none; border-color: var(--primary); }
        .password-wrap { position: relative; }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #aaa; padding: 0;
        }
        .btn {
            display: block; width: 100%; padding: 12px;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s, transform 0.1s;
            text-align: center; text-decoration: none;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .divider { text-align: center; color: #bbb; font-size: 0.85rem; margin: 18px 0 14px; position: relative; }
        .divider::before, .divider::after { content: ''; display: inline-block; width: 38%; height: 1px; background: #e0e0e0; vertical-align: middle; margin: 0 8px; }
        .action-links { display: flex; flex-direction: column; gap: 10px; }
        .btn-outline { background: transparent; border: 1.5px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: #e8f5e9; }
        .btn-ghost { background: transparent; border: 1.5px solid #ddd; color: #555; }
        .btn-ghost:hover { background: #f5f5f5; }
        .btn-danger-outline { background: transparent; border: 1.5px solid var(--danger); color: var(--danger); }
        .btn-danger-outline:hover { background: #fdecea; }
        .footer-links { text-align: center; margin-top: 22px; font-size: 0.88rem; color: #888; }
        .footer-links a { color: var(--primary); text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
        .forgot-inline {
            display: block; text-align: right; font-size: 0.82rem;
            color: var(--primary); text-decoration: none; margin-top: 4px;
        }
        .forgot-inline:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="brand">
        <i class="fas fa-leaf"></i>
        <h1>JAKISAWA <span>SHOP</span></h1>
    </div>

    <div class="card">
        <h2><i class="fas fa-sign-in-alt" style="color:var(--primary);margin-right:8px;"></i>Customer Sign In</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($showSignupOption): ?>
            <div class="alert alert-warn">
                <i class="fas fa-user-plus"></i>
                <span>No account found for this email. <a href="signup.php?email=<?php echo urlencode($prefillEmail); ?>">Create one here</a>.</span>
            </div>
        <?php endif; ?>

        <?php if ($showVerifyOption): ?>
            <div class="alert alert-warn">
                <i class="fas fa-envelope-open"></i>
                <span>Email not verified yet. <a href="signup.php?email=<?php echo urlencode($prefillEmail); ?>">Resend verification link</a>.</span>
            </div>
        <?php endif; ?>

        <form method="post" action="" autocomplete="on">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input id="email" name="email" type="email"
                           value="<?php echo htmlspecialchars($prefillEmail); ?>"
                           placeholder="you@example.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap password-wrap">
                    <i class="fas fa-lock"></i>
                    <input id="password" name="password" type="password"
                           placeholder="Your password" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword('password', this)" title="Show/hide password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <a href="reset_password.php<?php echo $prefillEmail !== '' ? '?email=' . urlencode($prefillEmail) : ''; ?>"
                   class="forgot-inline">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <?php if ($showForgotOption): ?>
        <div class="divider">or</div>
        <div class="action-links">
            <a href="reset_password.php<?php echo $prefillEmail !== '' ? '?email=' . urlencode($prefillEmail) : ''; ?>"
               class="btn btn-danger-outline">
                <i class="fas fa-key"></i> Reset Password
            </a>
        </div>
        <?php endif; ?>

        <div class="divider">new here?</div>
        <div class="action-links">
            <a href="signup.php" class="btn btn-outline">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
            <a href="index.php" class="btn btn-ghost">
                <i class="fas fa-arrow-left"></i> Back to Shop
            </a>
        </div>
    </div>

    <div class="footer-links">
        Need help? <a href="index.php">Visit our shop</a> or call <strong>0792546080</strong>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>