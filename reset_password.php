<?php
/**
 * reset_password.php
 * 
 * Handles the full password reset flow:
 *  Step 1 – User enters their email → sends a reset token link
 *  Step 2 – User clicks the link (token + email in URL) → sets new password
 *
 * Place this file in the same directory as login.php, signup.php, and config.php.
 * 
 * DB requirement: users table needs a `reset_token` and `reset_token_expires` column.
 * The script auto-creates them if missing.
 */

session_start();
require_once 'config.php';

// ─── Schema helpers ───────────────────────────────────────────────────────────

function resetEnsureColumn(PDO $pdo, string $column, string $definition): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column));
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    } catch (Throwable $e) {
        error_log("reset_password: Could not ensure column {$column}: " . $e->getMessage());
    }
}

resetEnsureColumn($pdo, 'reset_token',         'VARCHAR(128) NULL');
resetEnsureColumn($pdo, 'reset_token_expires',  'DATETIME NULL');

// ─── Config ───────────────────────────────────────────────────────────────────

$RESET_EXPIRY_MINUTES = defined('RESET_TOKEN_EXPIRY_MINUTES') ? (int)RESET_TOKEN_EXPIRY_MINUTES : 60;
if ($RESET_EXPIRY_MINUTES < 10) { $RESET_EXPIRY_MINUTES = 60; }

// ─── State ────────────────────────────────────────────────────────────────────

$step    = 'request';   // 'request' | 'reset' | 'done'
$error   = '';
$success = '';
$prefillEmail = trim($_GET['email'] ?? '');

// ─── Determine which step we're on ───────────────────────────────────────────

$urlToken = trim($_GET['token'] ?? '');
$urlEmail = trim($_GET['email'] ?? '');

if ($urlToken !== '' && $urlEmail !== '') {
    $step = 'reset';
}

// ─── Handle STEP 1: Request reset email ──────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Always show success message to prevent email enumeration
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime("+{$RESET_EXPIRY_MINUTES} minutes"));

            $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ? LIMIT 1");
            $upd->execute([$token, $expires, (int)$user['id']]);

            // Build reset link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'];
            $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $resetUrl = "{$protocol}://{$host}{$dir}/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token);

            $name = $user['full_name'] ?: 'Customer';

            // Send email using existing mailer from config.php if available, else fallback
            $mailSent = false;

            if (function_exists('sendPasswordResetEmail')) {
                // Project provides a dedicated function
                $mailSent = sendPasswordResetEmail($email, $name, $token);
            } elseif (function_exists('sendMail')) {
                $subject = 'JAKISAWA SHOP – Password Reset Request';
                $body    = "Hi {$name},\n\nWe received a request to reset your password.\n\nClick the link below to set a new password (valid for {$RESET_EXPIRY_MINUTES} minutes):\n\n{$resetUrl}\n\nIf you did not request this, please ignore this email – your password will remain unchanged.\n\nJAKISAWA SHOP Team\n0792546080";
                $mailSent = sendMail($email, $subject, $body);
            } else {
                // Fallback to PHP mail()
                $subject = 'JAKISAWA SHOP – Password Reset';
                $headers = "From: noreply@jakisawashop.co.ke\r\nContent-Type: text/plain; charset=UTF-8";
                $body    = "Hi {$name},\n\nClick the link below to reset your password (valid for {$RESET_EXPIRY_MINUTES} minutes):\n\n{$resetUrl}\n\nIf you did not request this, ignore this email.\n\nJAKISAWA SHOP";
                $mailSent = mail($email, $subject, $body, $headers);
            }

            if (!$mailSent) {
                // Surface the link for dev/test environments where mail isn't configured
                error_log("reset_password: mail not sent to {$email}. Reset URL: {$resetUrl}");
            }
        }

        // Always show the same message (security: don't reveal if email exists)
        $success = "If an account exists for that email, a password reset link has been sent. Please check your inbox and spam folder. The link expires in {$RESET_EXPIRY_MINUTES} minutes.";
        $step    = 'done';
    }
}

// ─── Handle STEP 2: Set new password ─────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $email      = trim($_POST['email'] ?? '');
    $token      = trim($_POST['token'] ?? '');
    $newPass    = $_POST['new_password'] ?? '';
    $confirmPass= $_POST['confirm_password'] ?? '';

    if ($email === '' || $token === '') {
        $error = 'Invalid reset link. Please request a new one.';
    } elseif (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, full_name 
            FROM users 
            WHERE email = ? 
              AND reset_token = ? 
              AND reset_token_expires >= NOW() 
            LIMIT 1
        ");
        $stmt->execute([$email, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Check if token exists but expired
            $expiredCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? LIMIT 1");
            $expiredCheck->execute([$email, $token]);
            if ($expiredCheck->fetch()) {
                $error = 'This reset link has expired. Please request a new one.';
            } else {
                $error = 'Invalid or already-used reset link. Please request a new one.';
            }
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $upd  = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, email_verified = 1 
                WHERE id = ? 
                LIMIT 1
            ");
            $upd->execute([$hash, (int)$user['id']]);

            $success = 'Password reset successfully! You can now sign in with your new password.';
            $step    = 'done';
        }
    }

    // Keep step as 'reset' if there's an error so form re-renders
    if ($error !== '') { $step = 'reset'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – JAKISAWA SHOP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --secondary: #ff9800;
            --danger: #d32f2f;
        }
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
            width: 100%; max-width: 420px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 32px 28px;
        }
        .card h2 { font-size: 1.35rem; color: var(--primary-dark); margin-bottom: 8px; text-align: center; }
        .card .subtitle { text-align: center; color: #666; font-size: 0.9rem; margin-bottom: 20px; }
        .alert {
            padding: 12px 14px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.93rem; display: flex; gap: 10px; align-items: flex-start;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-error   { background: #fdecea; color: #b42318; border: 1px solid #f5c2c7; }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .alert-info    { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: #444; margin-bottom: 5px; }
        .input-wrap { position: relative; }
        .input-wrap i.fi { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.9rem; }
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
        .steps { display: flex; gap: 8px; margin-bottom: 22px; }
        .step {
            flex: 1; text-align: center; padding: 8px 4px;
            border-radius: 8px; font-size: 0.8rem; font-weight: 600;
            background: #f0f0f0; color: #bbb;
        }
        .step.active { background: var(--primary); color: #fff; }
        .step.done   { background: #c8e6c9; color: var(--primary-dark); }
        .divider { text-align: center; color: #bbb; font-size: 0.85rem; margin: 18px 0 14px; position: relative; }
        .divider::before, .divider::after { content: ''; display: inline-block; width: 35%; height: 1px; background: #e0e0e0; vertical-align: middle; margin: 0 8px; }
        .done-icon { text-align: center; font-size: 3.5rem; color: var(--primary); margin-bottom: 12px; }
        .footer-links { text-align: center; margin-top: 18px; font-size: 0.88rem; color: #888; }
        .footer-links a { color: var(--primary); text-decoration: none; }
    </style>
</head>
<body>
    <div class="brand">
        <i class="fas fa-leaf"></i>
        <h1>JAKISAWA <span>SHOP</span></h1>
    </div>

    <div class="card">

        <!-- Progress steps -->
        <div class="steps">
            <div class="step <?php echo $step === 'request' ? 'active' : 'done'; ?>">
                <i class="fas fa-envelope"></i> 1. Enter Email
            </div>
            <div class="step <?php echo $step === 'reset' ? 'active' : ($step === 'done' && $success !== '' ? 'done' : ''); ?>">
                <i class="fas fa-lock"></i> 2. New Password
            </div>
            <div class="step <?php echo $step === 'done' ? 'active' : ''; ?>">
                <i class="fas fa-check"></i> 3. Done
            </div>
        </div>

        <!-- ── STEP 1: Request ── -->
        <?php if ($step === 'request'): ?>
        <h2><i class="fas fa-key" style="color:var(--primary);margin-right:8px;"></i>Reset Password</h2>
        <p class="subtitle">Enter your account email and we'll send you a reset link.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <form method="post" action="reset_password.php">
            <input type="hidden" name="action" value="request">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope fi"></i>
                    <input id="email" name="email" type="email"
                           value="<?php echo htmlspecialchars($prefillEmail); ?>"
                           placeholder="you@example.com" required autofocus>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div class="divider">or</div>
        <a href="login.php" class="btn btn-ghost"><i class="fas fa-sign-in-alt"></i> Back to Sign In</a>
        <a href="signup.php" class="btn btn-ghost"><i class="fas fa-user-plus"></i> Create Account</a>


        <!-- ── STEP 2: Set new password ── -->
        <?php elseif ($step === 'reset'): ?>
        <h2><i class="fas fa-lock" style="color:var(--primary);margin-right:8px;"></i>Set New Password</h2>
        <p class="subtitle">Choose a strong password for your account.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>

        <?php
            // Validate token before showing form
            $tokenValid = false;
            if ($urlEmail !== '' && $urlToken !== '') {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires >= NOW() LIMIT 1");
                $chk->execute([$urlEmail, $urlToken]);
                $tokenValid = (bool)$chk->fetch();
            }
        ?>

        <?php if ($tokenValid): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>Resetting password for <strong><?php echo htmlspecialchars($urlEmail); ?></strong></span>
        </div>
        <form method="post" action="reset_password.php">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($urlEmail); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($urlToken); ?>">

            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock fi"></i>
                    <input id="new_password" name="new_password" type="password"
                           placeholder="At least 8 characters" required
                           autocomplete="new-password" oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-pw" onclick="togglePassword('new_password', this)" title="Show/hide">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-bar-inner" id="strength-bar"></div></div>
                <div class="strength-label" id="strength-label"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock fi"></i>
                    <input id="confirm_password" name="confirm_password" type="password"
                           placeholder="Repeat new password" required
                           autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password', this)" title="Show/hide">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save New Password
            </button>
        </form>
        <?php else: ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <span>This reset link is <strong>invalid or has expired</strong>. Please request a new one.</span>
        </div>
        <a href="reset_password.php" class="btn btn-primary"><i class="fas fa-redo"></i> Request New Link</a>
        <?php endif; ?>

        <a href="login.php" class="btn btn-ghost" style="margin-top:10px;"><i class="fas fa-sign-in-alt"></i> Back to Sign In</a>


        <!-- ── STEP 3: Done ── -->
        <?php elseif ($step === 'done'): ?>
        <?php if ($success !== ''): ?>
            <?php if (strpos($success, 'reset successfully') !== false): ?>
                <div class="done-icon"><i class="fas fa-check-circle"></i></div>
                <h2>Password Updated!</h2>
                <p class="subtitle" style="margin-bottom:16px;">Your password has been changed successfully.</p>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Sign In Now</a>
            <?php else: ?>
                <div class="done-icon" style="color:#1565c0;"><i class="fas fa-envelope-open-text"></i></div>
                <h2>Check Your Email</h2>
                <p class="subtitle" style="margin-bottom:16px;">A reset link is on its way.</p>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <a href="login.php" class="btn btn-ghost"><i class="fas fa-sign-in-alt"></i> Back to Sign In</a>
                <a href="reset_password.php" class="btn btn-ghost" style="margin-top:8px;"><i class="fas fa-redo"></i> Resend Link</a>
            <?php endif; ?>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <a href="reset_password.php" class="btn btn-primary"><i class="fas fa-redo"></i> Try Again</a>
        <?php endif; ?>
        <?php endif; ?>

    </div>

    <div class="footer-links">
        Need help? Call <strong>0792546080</strong> or <a href="index.php">visit our shop</a>
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
            if (!bar || !label) return;
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