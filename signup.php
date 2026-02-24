<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$verificationHours = defined('VERIFICATION_LINK_EXPIRY_HOURS') ? (int)VERIFICATION_LINK_EXPIRY_HOURS : 5;
if ($verificationHours < 1) { $verificationHours = 5; }

function usersHasColumn(PDO $pdo, string $column, bool $refresh = false): bool {
    static $cache = [];
    if (!$refresh && array_key_exists($column, $cache)) { return $cache[$column]; }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column));
        $cache[$column] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('usersHasColumn check failed for "' . $column . '": ' . $e->getMessage());
        $cache[$column] = false;
    }
    return $cache[$column];
}

function ensureVerificationTokenExpiryColumn(PDO $pdo): bool {
    if (usersHasColumn($pdo, 'verification_token_expires')) { return true; }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN verification_token_expires DATETIME NULL"); }
    catch (Throwable $e) { error_log('Could not add verification_token_expires column: ' . $e->getMessage()); }
    return usersHasColumn($pdo, 'verification_token_expires', true);
}

$hasVerificationTokenExpiry = ensureVerificationTokenExpiryColumn($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName       = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirmPassword= $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Full name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id, email_verified, role FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $existingRole = normalizeUserRole($existingUser['role'] ?? '');
            $knownRoles = ['customer', 'admin', 'staff', 'super_admin'];
            if ($existingRole === '' || !in_array($existingRole, $knownRoles, true)) {
                $roleStmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id = ? LIMIT 1");
                $roleStmt->execute([(int)$existingUser['id']]);
            }
            if ((int)($existingUser['email_verified'] ?? 0) === 1) {
                $success = 'This email is already registered and verified. Please sign in.';
                header('Refresh: 2; url=login.php?email=' . urlencode($email));
            } else {
                $token = generateVerificationToken();
                if ($hasVerificationTokenExpiry) {
                    $verifyStmt = $pdo->prepare("UPDATE users SET verification_token = ?, verification_token_expires = DATE_ADD(NOW(), INTERVAL {$verificationHours} HOUR) WHERE id = ? LIMIT 1");
                    $verifyStmt->execute([$token, (int)$existingUser['id']]);
                } else {
                    $verifyStmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ? LIMIT 1");
                    $verifyStmt->execute([$token, (int)$existingUser['id']]);
                }
                $mailSent = sendCustomerVerificationEmail($email, $fullName !== '' ? $fullName : 'Customer', $token);
                if ($mailSent) {
                    $success = "A fresh verification link has been sent to {$email} (valid for {$verificationHours} hours). You can sign in now.";
                } else {
                    $error = 'Your account exists but email is not verified. We could not send the verification email right now. Please contact support.';
                }
            }
        } else {
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $token = generateVerificationToken();
            if ($hasVerificationTokenExpiry) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, phone, password_hash, role, status, email_verified, verification_token, verification_token_expires, created_at)
                    VALUES (?, ?, ?, ?, 'customer', 'active', 0, ?, DATE_ADD(NOW(), INTERVAL {$verificationHours} HOUR), NOW())
                ");
                $insertStmt->execute([$fullName, $email, $phone, $hash, $token]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, phone, password_hash, role, status, email_verified, verification_token, created_at)
                    VALUES (?, ?, ?, ?, 'customer', 'active', 0, ?, NOW())
                ");
                $insertStmt->execute([$fullName, $email, $phone, $hash, $token]);
            }

            unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_role']);
            unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['phone'], $_SESSION['role']);

            $mailSent = sendCustomerVerificationEmail($email, $fullName, $token);
            if ($mailSent) {
                $success = "Account created! A verification link has been sent to {$email} (valid for {$verificationHours} hours). You can sign in and continue shopping.";
            } else {
                $error = 'Account created, but the verification email could not be sent. Please contact support or use the password reset option.';
            }
        }
    }
}
$prefillEmail = trim($_GET['email'] ?? '');
$prefillName  = trim($_GET['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account – JAKISAWA SHOP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary: #ff9800;
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
        .brand { text-align: center; margin-bottom: 20px; }
        .brand i { font-size: 2.5rem; color: var(--primary); }
        .brand h1 { font-size: 1.8rem; color: var(--primary); margin-top: 6px; }
        .brand span { color: var(--secondary); }
        .card {
            width: 100%; max-width: 440px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 32px 28px;
        }
        .card h2 { font-size: 1.4rem; color: var(--primary-dark); margin-bottom: 20px; text-align: center; }
        .alert {
            padding: 12px 14px; border-radius: 8px; margin-bottom: 14px;
            font-size: 0.93rem; display: flex; gap: 10px; align-items: flex-start;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-error { background: #fdecea; color: #b42318; border: 1px solid #f5c2c7; }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: #444; margin-bottom: 5px; }
        .input-wrap { position: relative; }
        .input-wrap i.field-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.9rem; }
        .input-wrap input {
            width: 100%; padding: 11px 36px 11px 36px;
            border: 1.5px solid #ddd; border-radius: 8px; font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .input-wrap input:focus { outline: none; border-color: var(--primary); }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #aaa; padding: 0;
        }
        .strength-bar { height: 4px; border-radius: 4px; margin-top: 6px; background: #eee; overflow: hidden; }
        .strength-bar-inner { height: 100%; border-radius: 4px; width: 0; transition: width 0.3s, background 0.3s; }
        .strength-label { font-size: 0.78rem; color: #888; margin-top: 3px; }
        .btn {
            display: block; width: 100%; padding: 12px;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s, transform 0.1s;
            text-align: center; text-decoration: none;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-ghost { background: transparent; border: 1.5px solid #ddd; color: #555; margin-top: 10px; }
        .btn-ghost:hover { background: #f5f5f5; }
        .divider { text-align: center; color: #bbb; font-size: 0.85rem; margin: 18px 0 14px; position: relative; }
        .divider::before, .divider::after { content: ''; display: inline-block; width: 38%; height: 1px; background: #e0e0e0; vertical-align: middle; margin: 0 8px; }
        .footer-links { text-align: center; margin-top: 18px; font-size: 0.88rem; color: #888; }
        .footer-links a { color: var(--primary); text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
        .terms { font-size: 0.8rem; color: #999; text-align: center; margin-top: 10px; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="brand">
        <i class="fas fa-leaf"></i>
        <h1>JAKISAWA <span>SHOP</span></h1>
    </div>

    <div class="card">
        <h2><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Create Account</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="" autocomplete="on" id="signup-form">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <div class="input-wrap">
                    <i class="fas fa-user field-icon"></i>
                    <input id="full_name" name="full_name" type="text"
                           value="<?php echo htmlspecialchars($prefillName); ?>"
                           placeholder="Your full name" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope field-icon"></i>
                    <input id="email" name="email" type="email"
                           value="<?php echo htmlspecialchars($prefillEmail); ?>"
                           placeholder="you@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-wrap">
                    <i class="fas fa-phone field-icon"></i>
                    <input id="phone" name="phone" type="tel" placeholder="e.g. 0712 345 678">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <div class="input-wrap">
                    <i class="fas fa-lock field-icon"></i>
                    <input id="password" name="password" type="password"
                           placeholder="At least 8 characters" required
                           autocomplete="new-password" oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-pw" onclick="togglePassword('password', this)" title="Show/hide">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-bar-inner" id="strength-bar"></div></div>
                <div class="strength-label" id="strength-label"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <div class="input-wrap">
                    <i class="fas fa-lock field-icon"></i>
                    <input id="confirm_password" name="confirm_password" type="password"
                           placeholder="Repeat password" required
                           autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password', this)" title="Show/hide">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
            <p class="terms">By creating an account you agree to our terms & conditions. We'll never share your information.</p>
        </form>

        <div class="divider">already registered?</div>
        <a href="login.php" class="btn btn-ghost">
            <i class="fas fa-sign-in-alt"></i> Sign In Instead
        </a>
        <a href="reset_password.php" class="btn btn-ghost" style="margin-top:8px;">
            <i class="fas fa-key"></i> Forgot / Reset Password
        </a>
        <a href="index.php" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Back to Shop
        </a>
    </div>

    <div class="footer-links">
        Need help? Call us on <strong>0792546080</strong>
    </div>

    <script>
        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function checkStrength(val) {
            const bar = document.getElementById('strength-bar');
            const label = document.getElementById('strength-label');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { pct: '20%', color: '#f44336', text: 'Weak' },
                { pct: '45%', color: '#ff9800', text: 'Fair' },
                { pct: '70%', color: '#ffc107', text: 'Good' },
                { pct: '100%', color: '#4caf50', text: 'Strong' },
            ];
            const lvl = levels[Math.max(0, score - 1)] || { pct: '0%', color: '#eee', text: '' };
            bar.style.width = val.length === 0 ? '0%' : lvl.pct;
            bar.style.background = lvl.color;
            label.textContent = val.length === 0 ? '' : lvl.text;
            label.style.color = lvl.color;
        }
    </script>
</body>
</html>