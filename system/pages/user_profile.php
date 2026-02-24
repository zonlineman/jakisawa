<?php
// ============================================================================
// NOTE: DO NOT call session_start() here!
// The parent file (admin_dashboard.php) already called session_start()
// ============================================================================



// Define paths (if not already defined)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Include required files BEFORE ANY OUTPUT
require_once BASE_PATH . '/includes/database.php'; 
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/role_permissions.php';
// Get user ID from session (parent already started session)
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);

// // Check if user is logged in
// if ($user_id === 0) {
//     echo '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> User not logged in. Session expired.</div>';
//     exit;
// }

$success_msg = '';
$error_msg = '';
$user = [];
$avatarColumn = null;

function normalizeAvatarWebPath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '/systemuploads/users/')) {
        return projectPathUrl($path);
    }
    if (str_starts_with($path, 'systemuploads/users/')) {
        return projectPathUrl('/' . $path);
    }
    if (str_starts_with($path, '/system/uploads/avatars/')) {
        return projectPathUrl($path);
    }
    if (str_starts_with($path, 'system/uploads/avatars/')) {
        return projectPathUrl('/' . $path);
    }
    if (str_starts_with($path, '/uploads/avatars/')) {
        return systemUrl(ltrim($path, '/'));
    }
    if (str_starts_with($path, 'uploads/avatars/')) {
        return systemUrl($path);
    }
    if (str_starts_with($path, '/')) {
        return projectPathUrl($path);
    }
    return projectPathUrl('/' . $path);
}

function avatarWebPathToFs(string $path): string
{
    $normalized = normalizeAvatarWebPath($path);
    if ($normalized === '' || preg_match('#^https?://#i', $normalized)) {
        return '';
    }
    $projectRoot = dirname(BASE_PATH);
    $base = (defined('PROJECT_BASE_URL') && PROJECT_BASE_URL !== '') ? PROJECT_BASE_URL : '';
    $relative = $normalized;
    if ($base !== '' && str_starts_with($relative, $base . '/')) {
        $relative = substr($relative, strlen($base));
    }
    if (str_starts_with($relative, '/systemuploads/users/')) {
        return $projectRoot . $relative;
    }
    if (str_starts_with($relative, '/system/uploads/avatars/')) {
        return $projectRoot . $relative;
    }
    if (str_starts_with($relative, '/uploads/avatars/')) {
        return $projectRoot . '/system' . $relative;
    }
    return '';
}

function detectAvatarColumn(PDO $pdo): ?string
{
    $preferred = ['avatar_url', 'profile_image', 'image_url'];
    foreach ($preferred as $col) {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$col]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $col;
        }
    }

    // Try to add avatar_url if no compatible column exists.
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER postal_code");
        return 'avatar_url';
    } catch (Throwable $e) {
        error_log('Avatar column add failed: ' . $e->getMessage());
    }

    return null;
}

$avatarColumn = detectAvatarColumn($pdo);

// Fetch user data
try {
    if ($avatarColumn !== null) {
        $stmt = $pdo->prepare("SELECT *, `$avatarColumn` AS _avatar_path FROM users WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT *, NULL AS _avatar_path FROM users WHERE id = ?");
    }
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error_msg = "User profile not found";
    }
} catch (PDOException $e) {
    $error_msg = "Could not load user data: " . $e->getMessage();
    error_log("User Profile Error: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        
        // Validate required fields
        if (empty($full_name) || empty($email)) {
            throw new Exception("Full name and email are required");
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        // Check if email already exists (excluding current user)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $user_id]);
        if ($checkStmt->fetch()) {
            throw new Exception("Email already registered by another user");
        }
        
        // Update user
        $updateStmt = $pdo->prepare("
            UPDATE users SET 
            full_name = ?, 
            phone = ?, 
            email = ?, 
            address = ?, 
            city = ?, 
            postal_code = ?,
            updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $full_name, $phone, $email, $address, $city, $postal_code, $user_id
        ]);
        if ($updateStmt->rowCount() === 0) {
            // Could be unchanged values OR invalid session user id; verify user still exists.
            $verifyStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $verifyStmt->execute([$user_id]);
            if (!$verifyStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Profile update target user was not found. Please sign in again.");
            }
        }
        
        // Update session
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['admin_name'] = $full_name;
        $_SESSION['admin_email'] = $email;
        
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $success_msg = "Profile updated successfully!";
        
    } catch (Exception $e) {
        $error_msg = "Update failed: " . $e->getMessage();
        error_log("Profile Update Error: " . $e->getMessage());
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Validate new password
        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }
        
        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $pdo->prepare("
            UPDATE users SET 
            password_hash = ?,
            password_changed_at = NOW(),
            updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([$new_password_hash, $user_id]);
        
        $success_msg = "Password changed successfully!";
        
    } catch (Exception $e) {
        $error_msg = "Password change failed: " . $e->getMessage();
        error_log("Password Change Error: " . $e->getMessage());
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar']) && isset($_FILES['avatar'])) {
    try {
        if ($avatarColumn === null) {
            throw new Exception("Profile image storage is not configured in the users table.");
        }

        $avatar = $_FILES['avatar'];
        
        // Check for errors
        if ($avatar['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error code: " . $avatar['error']);
        }
        
        // Validate file type using finfo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $avatar['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only JPG, PNG, GIF, and WebP images are allowed. Detected: $file_type");
        }
        
        // Validate file size (max 2MB)
        if ($avatar['size'] > 2 * 1024 * 1024) {
            throw new Exception("Image size must be less than 2MB");
        }
        
        // Create uploads directory if it doesn't exist
        $projectRoot = dirname(BASE_PATH);
        $upload_dir = $projectRoot . '/systemuploads/users/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate safe unique filename
        $file_extension = strtolower((string)pathinfo($avatar['name'], PATHINFO_EXTENSION));
        if ($file_extension === '') {
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            $file_extension = $mimeToExt[$file_type] ?? 'jpg';
        }
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($avatar['tmp_name'], $filepath)) {
            throw new Exception("Failed to save uploaded file");
        }
        
        // Remove old avatar file (local avatars only)
        $oldAvatar = (string)($user['_avatar_path'] ?? '');
        if ($oldAvatar !== '') {
            $oldPath = avatarWebPathToFs($oldAvatar);
            if ($oldPath !== '' && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Update database with relative path
        $avatar_url = '/systemuploads/users/' . $filename;
        $updateStmt = $pdo->prepare("UPDATE users SET `$avatarColumn` = ?, updated_at = NOW() WHERE id = ?");
        
        $updateStmt->execute([$avatar_url, $user_id]);
        if ($updateStmt->rowCount() === 0) {
            $verifyStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $verifyStmt->execute([$user_id]);
            if (!$verifyStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Avatar update target user was not found. Please sign in again.");
            }
        }
        
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $success_msg = "Profile picture updated successfully!";
        
    } catch (Exception $e) {
        $error_msg = "Avatar upload failed: " . $e->getMessage();
        error_log("Avatar Upload Error: " . $e->getMessage());
    }
}

?>

<style>
    .profile-page {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h2 {
        color: #2c3e50;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .content-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 30px;
        margin-bottom: 30px;
    }
    
    .profile-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .avatar-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
    }
    
    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .avatar-overlay {
        position: absolute;
        bottom: 0;
        right: 0;
        background: #4361ee;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        border: 3px solid white;
    }
    
    .avatar-overlay:hover {
        background: #3a0ca3;
        transform: scale(1.1);
    }
    
    .section-title {
        font-size: 1.2rem;
        color: #4361ee;
        margin-bottom: 20px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .form-label {
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
    }
    
    .required::after {
        content: " *";
        color: #dc3545;
    }
    
    .btn-custom {
        background: linear-gradient(135deg, #4361ee, #3a0ca3);
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-custom:hover {
        background: linear-gradient(135deg, #3a0ca3, #7209b7);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-admin {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }
    
    .badge-staff {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        color: #212529;
    }
    
    .alert-custom {
        border-radius: 10px;
        border: none;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    }
</style>

<div class="profile-page">
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="bi bi-person-circle me-2"></i>My Profile</h2>
        <p class="text-muted">Manage your account settings and preferences</p>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if ($success_msg): ?>
    <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($user)): ?>
    
    <div class="row">
        <!-- Left Column - Profile Info -->
        <div class="col-lg-4">
            <div class="content-card">
                <div class="profile-header">
                    <div class="avatar-container">
                        <?php $avatarPath = normalizeAvatarWebPath((string)($user['_avatar_path'] ?? '')); ?>
                        <?php if (!empty($avatarPath)): ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" 
                                 alt="Profile Picture" 
                                 class="avatar-img" id="profileAvatarImage"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name'] ?? 'User'); ?>&background=4361ee&color=fff&size=150'">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name'] ?? 'User'); ?>&background=4361ee&color=fff&size=150" 
                                 alt="Profile Picture" 
                                 class="avatar-img" id="profileAvatarImage">
                        <?php endif; ?>
                        
                        <div class="avatar-overlay" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="bi bi-camera"></i>
                        </div>
                    </div>
                    
                    <h3><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h3>
                    <span class="status-badge badge-<?php echo strtolower($user['role'] ?? 'staff'); ?>">
                        <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'staff')); ?>
                    </span>
                    
                    <p class="text-muted mt-3">
                        <i class="bi bi-envelope me-1"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    
                    <div class="mt-3 pt-3" style="border-top: 1px solid #e0e0e0;">
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i>
                            Member since: <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Forms -->
        <div class="col-lg-8">
            <!-- Personal Information Form -->
            <div class="content-card">
                <h5 class="section-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Personal Information
                </h5>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="update_profile" class="btn btn-custom">
                            <i class="bi bi-save me-2"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="content-card">
                <h5 class="section-title">
                    <i class="bi bi-key me-2"></i>
                    Change Password
                </h5>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                            <small class="text-muted">Enter your current password</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                            <small class="text-muted">Re-enter new password</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="change_password" class="btn btn-custom">
                            <i class="bi bi-key me-2"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Avatar Upload Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-camera me-2"></i>
                        Update Profile Picture
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Choose Image</label>
                            <input type="file" id="avatarInput" name="avatar" class="form-control" accept="image/*" required>
                            <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF, WebP</small>
                        </div>

                        <div class="mb-3 text-center">
                            <img id="avatarPreview" src="<?php echo !empty($avatarPath) ? htmlspecialchars($avatarPath) : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? 'User') . '&background=4361ee&color=fff&size=150'; ?>" alt="Avatar preview" class="avatar-img" style="width:120px;height:120px;">
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="update_avatar" value="1" class="btn btn-custom w-100">
                                <i class="bi bi-upload me-2"></i> Upload Picture
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Unable to load user profile. Please try logging in again.
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Validate password form before submit
    $('form').submit(function(e) {
        const newPassInput = $(this).find('input[name="new_password"]');
        const confirmPassInput = $(this).find('input[name="confirm_password"]');
        
        if (newPassInput.length > 0 && confirmPassInput.length > 0) {
            const newPass = newPassInput.val();
            const confirmPass = confirmPassInput.val();
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        }
    });

    // Avatar preview before upload
    $('#avatarInput').on('change', function() {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Invalid image format. Use JPG, PNG, GIF, or WebP.');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            $('#avatarPreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    });
});
</script>
