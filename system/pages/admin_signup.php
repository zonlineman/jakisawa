<?php
// admin_signup.php - FIXED VERSION WITH PASSWORD HASHING
session_start();

require_once __DIR__ . '/../includes/config.php';

// Security configuration
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);
define('PASSWORD_COST', 12); // Higher = more secure but slower

function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}

// Security helper functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9\-\+\(\)\s]{10,15}$/', $phone);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_COST]);
}

function generateUsername($email) {
    $username = strtolower(explode('@', $email)[0]);
    $username = preg_replace('/[^a-z0-9]/', '', $username);
    
    // Check if username exists and append numbers if needed
    $conn = getDBConnection();
    $originalUsername = $username;
    $counter = 1;
    
    while (true) {
        $checkQuery = "SELECT id FROM users WHERE username = ?";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "s", $username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        
        if (mysqli_stmt_num_rows($checkStmt) === 0) {
            mysqli_stmt_close($checkStmt);
            mysqli_close($conn);
            return $username;
        }
        
        $username = $originalUsername . $counter;
        $counter++;
        mysqli_stmt_close($checkStmt);
        
        if ($counter > 100) {
            // Fallback: use timestamp
            $username = $originalUsername . time();
            break;
        }
    }
    
    mysqli_close($conn);
    return $username;
}

// Handle form submission
$error = '';
$success = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $formData['name'] = sanitizeInput($_POST['name'] ?? '');
    $formData['email'] = sanitizeInput($_POST['email'] ?? '');
    $formData['phone'] = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    // Name validation
    if (empty($formData['name'])) {
        $errors[] = "Full name is required.";
    } elseif (strlen($formData['name']) < 2) {
        $errors[] = "Name must be at least 2 characters.";
    } elseif (strlen($formData['name']) > 100) {
        $errors[] = "Name cannot exceed 100 characters.";
    }
    
    // Email validation
    if (empty($formData['email'])) {
        $errors[] = "Email address is required.";
    } elseif (!validateEmail($formData['email'])) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Phone validation (optional)
    if (!empty($formData['phone']) && !validatePhone($formData['phone'])) {
        $errors[] = "Please enter a valid phone number.";
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // If no validation errors, proceed with database operations
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Start transaction for data integrity
        mysqli_begin_transaction($conn);
        
        try {
            // Check if email already exists
            $checkQuery = "SELECT id FROM users WHERE email = ?";
            $checkStmt = mysqli_prepare($conn, $checkQuery);
            mysqli_stmt_bind_param($checkStmt, "s", $formData['email']);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                throw new Exception("An account with this email already exists.");
            }
            
            // Generate unique username
            $username = generateUsername($formData['email']);
            
            // Hash the password securely
            $hashed_password = hashPassword($password);
            
            // Insert new user with pending status
            $insertQuery = "INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active, created_by) 
                           VALUES (?, ?, ?, ?, ?, 'pending', 0, 0)";
            $insertStmt = mysqli_prepare($conn, $insertQuery);
            mysqli_stmt_bind_param($insertStmt, "sssss", 
                $username, 
                $formData['email'], 
                $hashed_password, 
                $formData['name'], 
                $formData['phone']
            );
            
            if (!mysqli_stmt_execute($insertStmt)) {
                throw new Exception("Registration failed. Please try again.");
            }
            
            // Get the new user ID
            $newUserId = mysqli_insert_id($conn);
            
           // Get the new user ID
$newUserId = mysqli_insert_id($conn);

// Update the user record with registration IP (optional: add last login time)
$updateQuery = "UPDATE users SET 
                registration_ip = ?,
                last_login = NOW()
                WHERE id = ?";
$updateStmt = mysqli_prepare($conn, $updateQuery);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
mysqli_stmt_bind_param($updateStmt, "si", $ip, $newUserId);
mysqli_stmt_execute($updateStmt);

// Commit transaction
mysqli_commit($conn);
            $success = "Registration successful! Your account is pending approval by an administrator.";
            
            // Send notification email (in production)
            // sendApprovalEmail($formData['email'], $formData['name']);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        } finally {
            // Clean up statements
            if (isset($checkStmt)) mysqli_stmt_close($checkStmt);
            if (isset($insertStmt)) mysqli_stmt_close($insertStmt);
            if (isset($logStmt)) mysqli_stmt_close($logStmt);
            mysqli_close($conn);
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Signup - JAKISAWA SHOP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-dark: #1b5e20;
            --success: #4caf50;
            --danger: #f44336;
            --warning: #ff9800;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 40px;
            animation: slideUp 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h2 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        
        .form-control.error {
            border-color: var(--danger);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            margin-top: 15px;
        }
        
        .btn-secondary:hover {
            background: #f1f8e9;
        }
        
        .error-message {
            background: #ffebee;
            color: var(--danger);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--danger);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .success-message {
            background: #e8f5e9;
            color: var(--primary-dark);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success);
        }
        
        .success-message h4 {
            margin-bottom: 10px;
            color: var(--primary-dark);
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 8px;
            padding-left: 5px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 3px 0;
        }
        
        .requirement i {
            font-size: 0.8rem;
        }
        
        .requirement.valid {
            color: var(--success);
        }
        
        .requirement.invalid {
            color: #757575;
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: var(--danger); }
        .strength-medium { background: var(--warning); }
        .strength-strong { background: var(--success); }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 0.9rem;
            color: #1976d2;
        }
        
        .info-box ul {
            margin: 10px 0 0 20px;
        }
        
        .info-box li {
            margin: 5px 0;
        }
        
        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        @media (max-width: 576px) {
            .card {
                padding: 25px;
            }
            
            h2 {
                font-size: 1.4rem;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-user-shield"></i> Admin Account Registration</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle fa-2x" style="float: left; margin-right: 15px;"></i>
                    <div>
                        <h4><i class="fas fa-check-circle"></i> Registration Successful!</h4>
                        <?php echo $success; ?>
                        <p style="margin-top: 10px; font-size: 0.9rem;">
                            <strong>What happens next?</strong>
                            <ul>
                                <li>An administrator will review your application</li>
                                <li>You'll receive an email notification when approved</li>
                                <li>You can then login with your credentials</li>
                            </ul>
                        </p>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                            <i class="fas fa-info-circle"></i> For immediate access, contact an existing administrator.
                        </p>
                    </div>
                </div>
                
                <a href="admin_login.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> Your account requires administrator approval before you can access the system.
                </div>
                
                <form method="POST" id="registrationForm">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               placeholder="Enter your full name" 
                               value="<?php echo htmlspecialchars($formData['name']); ?>"
                               required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your email address" 
                               value="<?php echo htmlspecialchars($formData['email']); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number (Optional)</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               placeholder="Enter your phone number"
                               value="<?php echo htmlspecialchars($formData['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="show-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <div class="password-requirements">
                            <div class="requirement invalid" id="req-length">
                                <i class="fas fa-circle"></i> At least <?php echo MIN_PASSWORD_LENGTH; ?> characters
                            </div>
                            <div class="requirement invalid" id="req-uppercase">
                                <i class="fas fa-circle"></i> One uppercase letter
                            </div>
                            <div class="requirement invalid" id="req-lowercase">
                                <i class="fas fa-circle"></i> One lowercase letter
                            </div>
                            <div class="requirement invalid" id="req-number">
                                <i class="fas fa-circle"></i> One number
                            </div>
                        </div>
                        
                        <div class="password-strength">
                            <div class="strength-bar" id="strength-bar"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm your password" required>
                            <button type="button" class="show-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement invalid" id="req-match">
                                <i class="fas fa-circle"></i> Passwords match
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Register Account
                    </button>
                    
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= <?php echo MIN_PASSWORD_LENGTH; ?>) strength++;
            
            // Upper case check
            if (/[A-Z]/.test(password)) strength++;
            
            // Lower case check
            if (/[a-z]/.test(password)) strength++;
            
            // Number check
            if (/[0-9]/.test(password)) strength++;
            
            // Update requirements
            document.getElementById('req-length').className = 
                password.length >= <?php echo MIN_PASSWORD_LENGTH; ?> ? 
                'requirement valid' : 'requirement invalid';
            
            document.getElementById('req-uppercase').className = 
                /[A-Z]/.test(password) ? 'requirement valid' : 'requirement invalid';
            
            document.getElementById('req-lowercase').className = 
                /[a-z]/.test(password) ? 'requirement valid' : 'requirement invalid';
            
            document.getElementById('req-number').className = 
                /[0-9]/.test(password) ? 'requirement valid' : 'requirement invalid';
            
            // Update strength bar
            const strengthBar = document.getElementById('strength-bar');
            let width = 0;
            let className = 'strength-weak';
            
            if (strength === 0) {
                width = 0;
            } else if (strength === 1) {
                width = 25;
                className = 'strength-weak';
            } else if (strength === 2) {
                width = 50;
                className = 'strength-weak';
            } else if (strength === 3) {
                width = 75;
                className = 'strength-medium';
            } else if (strength === 4) {
                width = 100;
                className = 'strength-strong';
            }
            
            strengthBar.style.width = width + '%';
            strengthBar.className = 'strength-bar ' + className;
            
            return strength;
        }
        
        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('req-match');
            
            if (confirm.length === 0) {
                matchElement.className = 'requirement invalid';
                return false;
            }
            
            if (password === confirm) {
                matchElement.className = 'requirement valid';
                return true;
            } else {
                matchElement.className = 'requirement invalid';
                return false;
            }
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    checkPasswordMatch();
                    updateSubmitButton();
                });
            }
            
            if (confirmField) {
                confirmField.addEventListener('input', function() {
                    checkPasswordMatch();
                    updateSubmitButton();
                });
            }
            
            // Update submit button state
            function updateSubmitButton() {
                const strength = checkPasswordStrength(passwordField.value);
                const match = checkPasswordMatch();
                
                if (strength >= 3 && match) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Register Account';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Complete Password Requirements';
                }
            }
            
            // Initial check
            updateSubmitButton();
            
            // Form submission
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = passwordField.value;
                
                // Basic validation
                if (!name || !email || !password) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                return true;
            });
        });
        
        // Validate email on blur
        document.getElementById('email')?.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = 'var(--danger)';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
        
        // Phone number formatting
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length > 3 && value.length <= 6) {
                value = value.replace(/(\d{3})(\d{1,3})/, '$1-$2');
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{1,4})/, '$1-$2-$3');
            }
            
            this.value = value;
        });
    </script>
</body>
</html>
