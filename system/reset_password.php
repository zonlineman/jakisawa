<?php
// File: reset_password.php
session_start();
require_once 'includes/database.php';

$message = '';
$error = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid reset link";
} else {
    $conn = getDBConnection();
    
    // Check if token exists and is not expired
    $sql = "SELECT id, email FROM users WHERE reset_token = ? 
            AND reset_expires > NOW() LIMIT 1";
    $stmt = executeQuery($sql, [$token]);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        $valid_token = true;
        $user_id = $user['id'];
        
        // Process password reset
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($password)) {
                $error = "Please enter new password";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } else {
                // Hash new password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $update_sql = "UPDATE users SET 
                              password_hash = ?, 
                              reset_token = NULL, 
                              reset_expires = NULL,
                              updated_at = NOW()
                              WHERE id = ?";
                
                $update_stmt = executeQuery($update_sql, [$password_hash, $user_id]);
                
                if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                    $message = "Password has been reset successfully!<br>
                               You can now login with your new password.";
                    $valid_token = false; // Token is now used
                } else {
                    $error = "Failed to reset password";
                }
            }
        }
    } else {
        $error = "Invalid or expired reset link";
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JAKISAWA SHOP</title>
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
        .reset-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            margin: auto;
        }
        .reset-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .reset-body {
            padding: 25px;
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container">
        <div class="reset-box">
            <div class="reset-header">
                <h3><i class="bi bi-key-fill"></i> Set New Password</h3>
                <p class="mb-0">Create your new password</p>
            </div>
            
            <div class="reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <hr>
                        <a href="forgot_password.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Request New Link
                        </a>
                    </div>
                <?php elseif ($message): ?>
                    <div class="alert alert-success">
                        <?php echo $message; ?>
                        <hr>
                        <a href="login.php" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
                        </a>
                    </div>
                <?php elseif ($valid_token): ?>
                    <form method="POST" id="resetForm">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" id="password" 
                                   class="form-control" required minlength="8"
                                   placeholder="Enter new password">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password"
                                   class="form-control" required
                                   placeholder="Re-enter new password">
                            <div id="passwordMatch" class="small mt-1"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mb-3">
                            <i class="bi bi-check-circle me-2"></i>Reset Password
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation check
        const password = document.getElementById('password');
        const confirmPass = document.getElementById('confirm_password');
        const matchText = document.getElementById('passwordMatch');
        
        if (password && confirmPass) {
            confirmPass.addEventListener('input', function() {
                if (this.value === password.value) {
                    matchText.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Passwords match</span>';
                } else if (this.value) {
                    matchText.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Passwords do not match</span>';
                } else {
                    matchText.innerHTML = '';
                }
            });
        }
        
        // Form validation
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const pass = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (pass.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters');
                    return false;
                }
                
                if (pass !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match');
                    return false;
                }
                
                return true;
            });
        }
    </script>
</body>
</html>
