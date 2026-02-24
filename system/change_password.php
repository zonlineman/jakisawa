<?php
// File: change_password.php
session_start();
require_once 'includes/database.php';

// Check if user is logged in with temporary password
if (!isset($_SESSION['user_id']) || !isset($_SESSION['temp_password'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password)) {
        $error = "Please enter current password";
    } elseif (empty($new_password)) {
        $error = "Please enter new password";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
        
        // Get current password hash
        $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password_hash'");
        $has_password_hash = mysqli_num_rows($result) > 0;
        
        if ($has_password_hash) {
            $sql = "SELECT password_hash FROM users WHERE id = ?";
        } else {
            $sql = "SELECT password as password_hash FROM users WHERE id = ?";
        }
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        // Verify current password
        if (password_verify($current_password, $user['password_hash'])) {
            // Hash new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            if ($has_password_hash) {
                $update_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            } else {
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            }
            
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_password_hash, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                // Remove temporary password flag
                unset($_SESSION['temp_password']);
                $success = "Password changed successfully!";
                
                // Redirect after 2 seconds
                header("refresh:2;url=login.php?password_changed=1");
            } else {
                $error = "Failed to update password";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error = "Current password is incorrect";
        }
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - JAKISAWA SHOP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0"><i class="bi bi-shield-exclamation"></i> Change Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            You are using a temporary password. Please set a new permanent password.
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label>Current (Temporary) Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
