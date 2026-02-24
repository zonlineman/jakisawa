<?php
// File: signup.php - Public Registration for ALL roles
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/includes/config.php';

// Database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Check if registration is open for all roles
$registration_open = true; // Set to false to close registration

$errors = [];
$success = '';
$form_data = [
    'full_name' => '',
    'email' => trim($_GET['email'] ?? ''),
    'phone' => '',
    'role' => 'customer'
];

function sendSystemVerificationEmail($toEmail, $toName, $token) {
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '' || $token === '') {
        return false;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/system/signup.php'));
    $projectBase = preg_replace('#/system$#', '', $scriptDir);
    $verifyEntryPath = file_exists(dirname(__DIR__) . '/verify-email.php')
        ? '/verify-email.php'
        : '/verify-email.php';
    $verifyUrl = $scheme . '://' . $host . $projectBase . $verifyEntryPath . '?email='
        . urlencode($toEmail) . '&token=' . urlencode($token);

    $subject = 'Verify your email - JAKISAWA SHOP';
    $message = "Hello {$toName},\n\n"
        . "Please verify your email by opening this link:\n{$verifyUrl}\n\n"
        . "If you did not create this account, please ignore this message.\n";
    $headers = "From: support@jakisawashop.co.ke\r\n"
        . "Reply-To: support@jakisawashop.co.ke\r\n";

    return @mail($toEmail, $subject, $message, $headers);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['role'] = $_POST['role'] ?? 'customer';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($form_data['full_name'])) {
        $errors[] = "Full name is required";
    }
    
    if (empty($form_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Validate role
    $allowed_roles = ['customer', 'staff', 'admin'];
    if (!in_array($form_data['role'], $allowed_roles)) {
        $errors[] = "Invalid role selected";
    }
    
    // Check if email exists
    if (empty($errors)) {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $form_data['email']);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $errors[] = "Email already registered";
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // Create account if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // For security, staff/admin accounts need approval
        $status = 'active';
        if ($form_data['role'] === 'staff' || $form_data['role'] === 'admin') {
            $status = 'pending'; // Needs admin approval
        }
        
        $registeredEmail = $form_data['email'];
        try {
            $verification_token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $verification_token = sha1(uniqid('verify_', true));
        }
        $insert_sql = "INSERT INTO users (full_name, email, phone, password_hash, role, status, email_verified, verification_token, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "sssssss", 
            $form_data['full_name'],
            $form_data['email'],
            $form_data['phone'],
            $password_hash,
            $form_data['role'],
            $status,
            $verification_token
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $mailSent = sendSystemVerificationEmail($form_data['email'], $form_data['full_name'], $verification_token);
            
            if ($status === 'pending') {
                $success = "Registration submitted! Verify your email first, then wait for admin approval.";
            } else {
                $success = "Registration successful. Verify your email before login.";
            }

            if (!$mailSent) {
                $success .= " Verification email could not be sent automatically. Contact support.";
            }
            
            // Reset form data
            $form_data = [
                'full_name' => '',
                'email' => '',
                'phone' => '',
                'role' => 'customer'
            ];

            header("refresh:2;url=login.php?email=" . urlencode($registeredEmail));
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - JAKISAWA SHOP</title>
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
        .signup-container {
            max-width: 500px;
            width: 100%;
            margin: auto;
        }
        .signup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .signup-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .signup-body {
            padding: 25px;
        }
        .role-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .badge-customer { background: #6c757d; color: white; }
        .badge-staff { background: #0d6efd; color: white; }
        .badge-admin { background: #dc3545; color: white; }
        .btn-signup {
            background: #1b5e20;
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-signup:hover {
            background: #155a1a;
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <h3><i class="bi bi-person-plus"></i> Create Account</h3>
                <p class="mb-0">Join JAKISAWA SHOP</p>
            </div>
            
            <div class="signup-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$registration_open): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Registration is currently closed. Please contact administrator.
                    </div>
                <?php else: ?>
                    <form method="POST" id="signupForm">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                       placeholder="arumansi@gmail.com">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                       placeholder="Optional">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Account Type *</label>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="form-check card border h-100">
                                            <input class="form-check-input" type="radio" name="role" 
                                                   value="customer" id="roleCustomer" 
                                                   <?php echo $form_data['role'] === 'customer' ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-center p-3" for="roleCustomer">
                                                <i class="bi bi-person fs-1 text-secondary"></i>
                                                <div class="mt-2"><strong>Customer</strong></div>
                                                <small class="text-muted">Browse & purchase products</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check card border h-100">
                                            <input class="form-check-input" type="radio" name="role" 
                                                   value="staff" id="roleStaff"
                                                   <?php echo $form_data['role'] === 'staff' ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-center p-3" for="roleStaff">
                                                <i class="bi bi-person-badge fs-1 text-primary"></i>
                                                <div class="mt-2"><strong>Staff</strong></div>
                                                <small class="text-muted">Manage orders & inventory</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check card border h-100">
                                            <input class="form-check-input" type="radio" name="role" 
                                                   value="admin" id="roleAdmin"
                                                   <?php echo $form_data['role'] === 'admin' ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-center p-3" for="roleAdmin">
                                                <i class="bi bi-shield-check fs-1 text-danger"></i>
                                                <div class="mt-2"><strong>Admin</strong></div>
                                                <small class="text-muted">Full system access</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" 
                                           class="form-control" required minlength="8"
                                           placeholder="Minimum 8 characters">
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="togglePassword('password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           class="form-control" required
                                           placeholder="Re-enter password">
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="small mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a> and 
                                    <a href="#" class="text-decoration-none">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Staff and Admin accounts require approval from system administrator.
                            Customer accounts are activated immediately.
                        </div>
                        
                        <button type="submit" class="btn btn-signup text-white w-100 mb-3">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </button>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none fw-bold">Sign In</a>
                            </p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = event.currentTarget.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // Password strength indicator
        const password = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrength');
        const confirmPass = document.getElementById('confirm_password');
        const matchText = document.getElementById('passwordMatch');
        
        password.addEventListener('input', function() {
            const pass = this.value;
            let strength = 0;
            
            if (pass.length >= 8) strength += 20;
            if (/[A-Z]/.test(pass)) strength += 20;
            if (/[a-z]/.test(pass)) strength += 20;
            if (/[0-9]/.test(pass)) strength += 20;
            if (/[^A-Za-z0-9]/.test(pass)) strength += 20;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Set color based on strength
            if (strength <= 40) {
                strengthBar.className = 'progress-bar bg-danger';
            } else if (strength <= 80) {
                strengthBar.className = 'progress-bar bg-warning';
            } else {
                strengthBar.className = 'progress-bar bg-success';
            }
        });
        
        // Password confirmation check
        confirmPass.addEventListener('input', function() {
            if (this.value === password.value) {
                matchText.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Passwords match</span>';
            } else if (this.value) {
                matchText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Passwords do not match</span>';
            } else {
                matchText.innerHTML = '';
            }
        });
        
        // Form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            if (!document.getElementById('terms').checked) {
                e.preventDefault();
                alert('Please agree to the Terms & Conditions');
                return false;
            }
            return true;
        });
        
        // Auto-focus on first field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="full_name"]').focus();
        });
    </script>
</body>
</html>
