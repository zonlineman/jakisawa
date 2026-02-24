<?php
// login.php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once 'includes/database.php';
require_once 'includes/super_admin_bootstrap.php';

$customerEntryRelative = file_exists(__DIR__ . '/../index.php') ? '../index.php' : '../index.php';

// Ensure reserved super admin exists and has enforced credentials.
ensureReservedSuperAdminAccount($pdo);

// Check if already logged in with a valid admin/staff/super_admin session
if (isset($_SESSION['admin_id'])) {
    $existingRole = $_SESSION['admin_role'] ?? '';
    if (in_array($existingRole, ['super_admin', 'admin', 'staff'], true)) {
        header('Location: admin_dashboard.php');
        exit();
    }

    // Stale/invalid admin session state can cause redirect loops
    unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_logged_in'], $_SESSION['admin_is_active']);
}

$error = '';
$success = '';
$showSignupOption = false;
$showForgotOption = false;
$showVerifyReminder = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            // Get user from database
            $stmt = $pdo->prepare("
                SELECT *
                FROM users 
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userRole = strtolower(trim((string)($user['role'] ?? '')));
                $isSuperAdmin = ($userRole === 'super_admin') || ((int)($user['is_super_admin'] ?? 0) === 1);
                if ($isSuperAdmin) {
                    $userRole = 'super_admin';
                }

                // Check if account is active
                $isActive = true;
                if (array_key_exists('is_active', $user)) {
                    $isActive = ((int)$user['is_active'] === 1);
                } elseif (array_key_exists('status', $user)) {
                    $isActive = (strtolower((string)$user['status']) === 'active');
                }

                if (!$isActive) {
                    $error = 'Your account is deactivated. Please contact administrator.';
                }
                elseif (
                    array_key_exists('email_verified', $user)
                    && (int)$user['email_verified'] !== 1
                    && !isReservedSuperAdminEmail((string)($user['email'] ?? ''))
                ) {
                    $error = 'Your email is not verified yet. Please verify your email before signing in.';
                    $showVerifyReminder = true;
                }
                // Verify password
                elseif (password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID after successful authentication
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_name'] = $user['full_name'];
                    $_SESSION['admin_role'] = $userRole;
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_is_active'] = 1;
                    $_SESSION['last_activity'] = time();

                    // Mirror to legacy keys used in some pages
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $userRole;
                    $_SESSION['role'] = $userRole;
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("
                        UPDATE users 
                        SET last_login = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$user['id']]);
                    
                    // Redirect based on role
                    if (in_array($userRole, ['super_admin', 'admin', 'staff'], true)) {
                        header('Location: admin_dashboard.php');
                    } else {
                        $cartItemCount = 0;
                        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                            foreach ($_SESSION['cart'] as $item) {
                                $cartItemCount += (int)($item['quantity'] ?? 0);
                            }
                        }
                        header('Location: ' . $customerEntryRelative . ($cartItemCount > 0 ? '?section=order' : ''));
                    }
                    exit();
                } else {
                    $error = 'Invalid email or password';
                    $showForgotOption = true;
                }
            } else {
                $error = 'No account found with that email.';
                $showSignupOption = true;
                $showForgotOption = true;
            }
        } catch (PDOException $e) {
            // Log error but show generic message to user
            error_log('Login error: ' . $e->getMessage());
            $error = 'System error. Please try again later. Code: ' . $e->getCode();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JAKISAWA SHOP</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ff0054;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header .logo {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-group {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .toggle-password {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            position: absolute;
            right: 45px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, var(--secondary) 0%, #7209b7 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-danger {
            background: #fee;
            color: #c00;
            border-left: 4px solid #f00;
        }
        
        .alert-success {
            background: #e8f8ef;
            color: #0a0;
            border-left: 4px solid #0a0;
        }
        
        .role-badges {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        @media (max-width: 576px) {
            .login-header {
                padding: 20px;
            }
            
            .login-body {
                padding: 20px;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h1>JAKISAWA SHOP</h1>
                <p>Administration System</p>
                
                <div class="role-badges">
                    <span class="role-badge">Admin Access</span>
                    <span class="role-badge">Staff Portal</span>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if ($showSignupOption): ?>
                <div class="alert alert-info">
                    <i class="fas fa-user-plus me-2"></i>
                    Email not registered.
                    <a href="signup.php?email=<?php echo urlencode((string)($_POST['email'] ?? '')); ?>" class="alert-link">Create an account</a>.
                </div>
                <?php endif; ?>

                <?php if ($showForgotOption): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-key me-2"></i>
                    Forgot your password?
                    <a href="forgot_password.php?email=<?php echo urlencode((string)($_POST['email'] ?? '')); ?>" class="alert-link">Send reset link to your email</a>.
                </div>
                <?php endif; ?>

                <?php if ($showVerifyReminder): ?>
                <div class="alert alert-info">
                    <i class="fas fa-envelope-open-text me-2"></i>
                    Check your inbox and click the verification link before signing in.
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Enter your email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required
                                   autocomplete="email">
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="form-control" 
                                   placeholder="Enter your password"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </form>
                
                <div class="login-footer">
                    <p class="mb-2">
                        <a href="forgot_password.php">
                            <i class="fas fa-key me-1"></i> Forgot Password?
                        </a>
                    </p>
                    <p class="mb-0 text-muted">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Use your registered email and password
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
        
        // Form submission loading state
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Signing in...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        // Auto-focus on email field
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.focus();
        }
    });
    </script>
</body>
</html>
