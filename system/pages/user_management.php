<?php
// user_management.php - User Management System

// Start output buffering
ob_start();



// ===== CONFIGURATION =====
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/role_permissions.php';
require_once __DIR__ . '/../includes/super_admin_bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

ensureReservedSuperAdminAccount($pdo);

// ===== SESSION MAPPING (NON-DESTRUCTIVE) =====
// Prefer native admin session keys; fallback to user_* keys when needed.
$adminRole = $_SESSION['admin_role'] ?? null;
$adminId = $_SESSION['admin_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

if (!$adminRole && $userRole) {
    $_SESSION['admin_role'] = $userRole;
    $adminRole = $userRole;
}
if (($adminId === null || $adminId === '') && $userId !== null) {
    $_SESSION['admin_id'] = $userId;
    $adminId = $userId;
}
if (empty($_SESSION['admin_name']) && $userName) {
    $_SESSION['admin_name'] = $userName;
}

$_SESSION['admin_logged_in'] = in_array(strtolower((string)($adminRole ?? '')), ['super_admin', 'admin', 'staff'], true) && !empty($adminId);

$adminRoleNormalized = strtolower((string)($adminRole ?? ''));
if (!in_array($adminRoleNormalized, ['admin', 'super_admin'], true)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Access denied: Admins only.'];
    header('Location: admin_dashboard.php?page=dashboard');
    exit;
}




function normalizeUserStatus($user) {
    if (!is_array($user)) {
        return $user;
    }
    
    $is_active = isset($user['is_active']) ? (int)$user['is_active'] : 0;
    $status = $user['status'] ?? '';
    $failed_attempts = isset($user['failed_login_attempts']) ? (int)$user['failed_login_attempts'] : 0;
    
    // Check for locked account first (highest priority)
    if ($failed_attempts >= 5) {
        $status = 'locked';
    }
    // If status is empty or not set, determine it
    elseif (empty($status) || $status == '') {
        if ($is_active == 1) {
            $status = 'active';
        } else {
            $status = 'inactive';
        }
    }
    // Map database status to display status
    else {
        $statusMapping = [
            'approved' => 'active',
            '' => 'inactive',
            null => 'inactive',
            '0' => 'inactive',
            '1' => 'active'
        ];
        
        if (isset($statusMapping[$status])) {
            $status = $statusMapping[$status];
        }
    }
    
    // Update the user array
    $user['status'] = $status;
    $user['is_active'] = $is_active;
    
    return $user;
}

function getUserDisplayStatus($user) {
    if (!is_array($user)) {
        return 'inactive';
    }
    
    $status = $user['status'] ?? '';
    $failed_attempts = isset($user['failed_login_attempts']) ? (int)$user['failed_login_attempts'] : 0;
    
    // Check for locked account first
    if ($failed_attempts >= 5) {
        return 'locked';
    }
    
    // If empty, check is_active
    if (empty($status)) {
        return (isset($user['is_active']) && $user['is_active'] == 1) ? 'active' : 'inactive';
    }
    
    // Map status
    $statusMapping = [
        'approved' => 'active',
        '' => 'inactive',
        null => 'inactive'
    ];
    
    return isset($statusMapping[$status]) ? $statusMapping[$status] : $status;
}

function detectUserAvatarColumn($conn) {
    static $avatarColumn = null;
    if ($avatarColumn !== null) {
        return $avatarColumn;
    }

    $avatarColumn = '';
    $candidates = ['avatar_url', 'profile_image', 'image_url'];
    foreach ($candidates as $candidate) {
        $safe = mysqli_real_escape_string($conn, $candidate);
        $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '{$safe}'");
        if ($check && mysqli_num_rows($check) > 0) {
            $avatarColumn = $candidate;
            break;
        }
    }

    return $avatarColumn;
}

function normalizeUserAvatarUrl($avatarPath) {
    $avatarPath = trim((string)$avatarPath);
    if ($avatarPath === '') {
        return '';
    }
    $avatarPath = str_replace('\\', '/', $avatarPath);
    if (strpos($avatarPath, 'systemuploads/users/') === 0) {
        return projectPathUrl('/' . $avatarPath);
    }
    if (strpos($avatarPath, '/') === 0) {
        return strpos($avatarPath, '/uploads/') === 0
            ? systemUrl(ltrim($avatarPath, '/'))
            : projectPathUrl($avatarPath);
    }
    if (strpos($avatarPath, 'uploads/') === 0) {
        return systemUrl($avatarPath);
    }
    return projectPathUrl('/' . $avatarPath);
}

function getUserActivityLevel($lastLogin) {
    if (!$lastLogin || $lastLogin == '0000-00-00 00:00:00' || $lastLogin == '0000-00-00') {
        return 'never';
    }
    
    try {
        $now = new DateTime();
        $loginTime = new DateTime($lastLogin);
        $interval = $now->diff($loginTime);
        $days = $interval->days;
        
        if ($days < 1) {
            return 'today';
        } elseif ($days <= 7) {
            return 'week';
        } elseif ($days <= 30) {
            return 'month';
        } else {
            return 'inactive';
        }
    } catch (Exception $e) {
        return 'never';
    }
}

function getLastLoginText($lastLogin) {
    if (!$lastLogin || $lastLogin == '0000-00-00 00:00:00' || $lastLogin == '0000-00-00') {
        return 'Never';
    }
    
    try {
        $now = new DateTime();
        $loginTime = new DateTime($lastLogin);
        $interval = $now->diff($loginTime);
        
        if ($interval->y > 0) {
            return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        } elseif ($interval->m > 0) {
            return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getRoleBadge($role) {
    $badges = [
        'super_admin' => '<span class="badge bg-dark">Super Admin</span>',
        'admin' => '<span class="badge bg-danger">Admin</span>',
        'staff' => '<span class="badge bg-primary">Staff</span>',
        'customer' => '<span class="badge bg-secondary">Customer</span>',
        'pending' => '<span class="badge bg-warning">Pending</span>'
    ];
    
    return $badges[$role] ?? '<span class="badge bg-light text-dark">' . ucfirst($role) . '</span>';
}

function getUserStatusBadge($status) {
    $badges = [
        'active' => '<span class="badge bg-success">Active</span>',
        'approved' => '<span class="badge bg-success">Approved</span>',
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'inactive' => '<span class="badge bg-secondary">Inactive</span>',
        'suspended' => '<span class="badge bg-danger">Suspended</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'locked' => '<span class="badge bg-danger">Locked</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light text-dark">' . ucfirst($status) . '</span>';
}

function getCurrentAdminRole() {
    static $resolvedRole = null;
    if ($resolvedRole !== null) {
        return $resolvedRole;
    }

    $sessionRole = strtolower((string)($_SESSION['admin_role'] ?? 'staff'));
    if ($sessionRole === 'super_admin') {
        $resolvedRole = 'super_admin';
        return $resolvedRole;
    }

    if ((int)($_SESSION['is_super_admin'] ?? 0) === 1) {
        $resolvedRole = 'super_admin';
        return $resolvedRole;
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId > 0) {
        $conn = getDBConnection();
        $result = mysqli_query($conn, "SELECT is_super_admin FROM users WHERE id = {$adminId} LIMIT 1");
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            if ((int)($row['is_super_admin'] ?? 0) === 1) {
                $resolvedRole = 'super_admin';
                return $resolvedRole;
            }
        }
    }

    $resolvedRole = $sessionRole;
    return $resolvedRole;
}

function canAssignRole($targetRole) {
    $currentRole = getCurrentAdminRole();
    $targetRole = strtolower(trim((string)$targetRole));

    if (!in_array($targetRole, ['super_admin', 'admin', 'staff', 'customer'], true)) {
        return false;
    }

    if ($currentRole === 'super_admin') {
        return in_array($targetRole, ['super_admin', 'admin', 'staff', 'customer'], true);
    }

    if ($currentRole === 'admin') {
        return in_array($targetRole, ['admin', 'staff', 'customer'], true);
    }

    return $currentRole === 'staff' && $targetRole === 'customer';
}

function normalizeStoredRole($role, $isSuperAdmin = null) {
    if ((int)$isSuperAdmin === 1) {
        return 'super_admin';
    }

    $normalizedRole = strtolower(trim((string)$role));
    if (in_array($normalizedRole, ['super_admin', 'admin', 'staff', 'customer'], true)) {
        return $normalizedRole;
    }

    return 'customer';
}

function canManageUser($targetUserRole) {
    $currentRole = getCurrentAdminRole();
    $targetRole = normalizeStoredRole($targetUserRole);

    if ($currentRole === 'super_admin') {
        return true;
    }

    if ($currentRole === 'admin') {
        return $targetRole !== 'super_admin';
    }

    return $currentRole === 'staff' && $targetRole === 'customer';
}

function getHiddenSuperAdminEmail(): string {
    return strtolower(trim(getReservedSuperAdminEmail()));
}

function isHiddenSuperAdminEmailValue($email): bool {
    return strtolower(trim((string)$email)) === getHiddenSuperAdminEmail();
}

function hiddenSuperAdminWhereClause($conn): string {
    $safe = mysqli_real_escape_string($conn, getHiddenSuperAdminEmail());
    return "LOWER(TRIM(COALESCE(email, ''))) <> '{$safe}'";
}

// ===== INITIALIZE VARIABLES =====
$usersData = [];
$pendingUsersData = [];
$stats = [];
$searchQuery = '';
$roleFilter = '';
$statusFilter = '';
$sortBy = 'created_at';
$sortOrder = 'desc';
$page = 1;
$perPage = 15;
$message = '';
$messageType = '';

// ===== GET FILTER PARAMETERS =====
$searchQuery = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';
$page = intval($_GET['p'] ?? 1);
$perPage = intval($_GET['per_page'] ?? 15);
$offset = ($page - 1) * $perPage;

// ===== LOAD USER STATISTICS =====
function getUserStatistics($conn) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'pending' => 0,
        'suspended' => 0,
        'locked' => 0,
        'by_role' => [],
        'recent_week' => 0,
        'recent_login' => 0
    ];
    $excludeClause = hiddenSuperAdminWhereClause($conn);
    
    // Total users count
    $totalQuery = "SELECT COUNT(*) as total FROM users WHERE {$excludeClause}";
    $result = mysqli_query($conn, $totalQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['total'] = $row['total'];
    }
    
    // Active users (is_active = 1 AND status NOT IN ('pending', 'suspended', 'locked'))
    $activeQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND is_active = 1 AND status NOT IN ('pending', 'suspended', 'locked')";
    $result = mysqli_query($conn, $activeQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['active'] = $row['count'];
    }
    
    // Inactive users (is_active = 0 AND status NOT IN ('suspended', 'locked', 'pending'))
    $inactiveQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND is_active = 0 AND status NOT IN ('suspended', 'locked', 'pending')";
    $result = mysqli_query($conn, $inactiveQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['inactive'] = $row['count'];
    }
    
    // Pending users (status = 'pending' OR (status = '' AND is_active = 0))
    $pendingQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND (status = 'pending' OR (status = '' AND is_active = 0))";
    $result = mysqli_query($conn, $pendingQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['pending'] = $row['count'];
    }
    
    // Suspended users
    $suspendedQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND status = 'suspended'";
    $result = mysqli_query($conn, $suspendedQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['suspended'] = $row['count'];
    }
    
    // Locked users (failed_login_attempts >= 5 OR status = 'locked')
    $lockedQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND (failed_login_attempts >= 5 OR status = 'locked')";
    $result = mysqli_query($conn, $lockedQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['locked'] = $row['count'];
    }
    
    // Users by role (normalize legacy empty/invalid roles to customer)
    $roleQuery = "SELECT
                    CASE
                        WHEN COALESCE(is_super_admin, 0) = 1 THEN 'super_admin'
                        WHEN LOWER(TRIM(COALESCE(role, ''))) IN ('super_admin', 'admin', 'staff', 'customer')
                            THEN LOWER(TRIM(role))
                        ELSE 'customer'
                    END AS role,
                    COUNT(*) as count
                  FROM users
                  WHERE {$excludeClause}
                  GROUP BY
                    CASE
                        WHEN COALESCE(is_super_admin, 0) = 1 THEN 'super_admin'
                        WHEN LOWER(TRIM(COALESCE(role, ''))) IN ('super_admin', 'admin', 'staff', 'customer')
                            THEN LOWER(TRIM(role))
                        ELSE 'customer'
                    END
                  ORDER BY role";
    $result = mysqli_query($conn, $roleQuery);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stats['by_role'][$row['role']] = $row['count'];
        }
    }
    
    // Recent registrations (last 7 days)
    $recentQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $result = mysqli_query($conn, $recentQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['recent_week'] = $row['count'];
    }
    
    // Users with recent login (last 30 days)
    $recentLoginQuery = "SELECT COUNT(*) as count FROM users WHERE {$excludeClause} AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = mysqli_query($conn, $recentLoginQuery);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['recent_login'] = $row['count'];
    }
    
    return $stats;
}

// ===== LOAD USERS DATA =====
function loadUsersData($conn, $searchQuery, $roleFilter, $statusFilter, $sortBy, $sortOrder, $offset, $perPage) {
    $data = [
        'users' => [],
        'total' => 0,
        'pages' => 0
    ];
    
    // Build WHERE clause
    $whereClause = "WHERE " . hiddenSuperAdminWhereClause($conn);
    $params = [];
    $types = '';
    
    if (!empty($searchQuery)) {
        $whereClause .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= 'ssss';
    }
    
    if (!empty($roleFilter) && $roleFilter !== 'all') {
        $normalizedRoleFilter = strtolower(trim($roleFilter));

        if ($normalizedRoleFilter === 'customer') {
            // Treat legacy empty/invalid role values as customer, excluding super admins by flag.
            $whereClause .= " AND (
                COALESCE(is_super_admin, 0) = 0 AND (
                LOWER(TRIM(COALESCE(role, ''))) = 'customer'
                OR TRIM(COALESCE(role, '')) = ''
                OR LOWER(TRIM(COALESCE(role, ''))) NOT IN ('super_admin', 'admin', 'staff', 'customer')
                )
            )";
        } elseif ($normalizedRoleFilter === 'super_admin') {
            $whereClause .= " AND (
                COALESCE(is_super_admin, 0) = 1
                OR LOWER(TRIM(COALESCE(role, ''))) = 'super_admin'
            )";
        } else {
            $whereClause .= " AND COALESCE(is_super_admin, 0) = 0 AND LOWER(TRIM(COALESCE(role, ''))) = ?";
            $params[] = $normalizedRoleFilter;
            $types .= 's';
        }
    }
    
    // Handle status filter
    if (!empty($statusFilter) && $statusFilter !== 'all') {
        if ($statusFilter === 'active') {
            $whereClause .= " AND (is_active = 1 AND status NOT IN ('pending', 'suspended', 'locked'))";
        } elseif ($statusFilter === 'inactive') {
            $whereClause .= " AND (is_active = 0 AND status NOT IN ('suspended', 'locked', 'pending'))";
        } elseif ($statusFilter === 'pending') {
            $whereClause .= " AND (status = 'pending' OR (status = '' AND is_active = 0))";
        } elseif ($statusFilter === 'suspended') {
            $whereClause .= " AND status = 'suspended'";
        } elseif ($statusFilter === 'locked') {
            $whereClause .= " AND (failed_login_attempts >= 5 OR status = 'locked')";
        }
    }
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $countQuery);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $countQuery);
    }
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $data['total'] = $row['total'];
        $data['pages'] = ceil($row['total'] / $perPage);
    }
    
    $avatarColumn = detectUserAvatarColumn($conn);
    $avatarSelect = $avatarColumn !== ''
        ? ", {$avatarColumn} AS avatar_path"
        : ", '' AS avatar_path";

    // Get users
    $query = "SELECT 
                id, 
                username, 
                full_name as name, 
                email,
                CASE
                    WHEN COALESCE(is_super_admin, 0) = 1 THEN 'super_admin'
                    WHEN LOWER(TRIM(COALESCE(role, ''))) IN ('super_admin', 'admin', 'staff', 'customer')
                        THEN LOWER(TRIM(role))
                    ELSE 'customer'
                END AS role,
                is_super_admin,
                role AS role_raw,
                phone, 
                status, 
                is_active,
                last_login, 
                created_at,
                created_by,
                approved_by,
                approved_at,
                last_active,
                failed_login_attempts,
                lock_until,
                registration_ip
                $avatarSelect
              FROM users 
              $whereClause 
              ORDER BY $sortBy $sortOrder 
              LIMIT $offset, $perPage";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row = normalizeUserStatus($row);
            $row['avatar_url'] = normalizeUserAvatarUrl($row['avatar_path'] ?? '');
            $data['users'][] = $row;
        }
    }
    
    return $data;
}

// ===== LOAD PENDING USERS =====
function loadPendingUsers($conn) {
    $pendingUsers = [];
    $excludeClause = hiddenSuperAdminWhereClause($conn);

    // Keep query compatible with DBs where registration_user_agent does not exist
    $hasRegistrationUserAgent = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'registration_user_agent'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) {
        $hasRegistrationUserAgent = true;
    }

    $registrationAgentSelect = $hasRegistrationUserAgent
        ? "registration_user_agent"
        : "NULL AS registration_user_agent";

    $avatarColumn = detectUserAvatarColumn($conn);
    $avatarSelect = $avatarColumn !== ''
        ? ", {$avatarColumn} AS avatar_path"
        : ", '' AS avatar_path";

    $query = "SELECT 
                id, 
                username, 
                full_name as name, 
                email, 
                CASE
                    WHEN COALESCE(is_super_admin, 0) = 1 THEN 'super_admin'
                    WHEN LOWER(TRIM(COALESCE(role, ''))) IN ('super_admin', 'admin', 'staff', 'customer')
                        THEN LOWER(TRIM(role))
                    ELSE 'customer'
                END AS role,
                is_super_admin,
                role AS role_raw,
                phone, 
                status, 
                is_active,
                created_at,
                registration_ip,
                $registrationAgentSelect
                $avatarSelect
             FROM users 
             WHERE {$excludeClause}
             AND (status = 'pending' OR (status = '' AND is_active = 0))
             AND COALESCE(is_super_admin, 0) = 0
             AND LOWER(TRIM(COALESCE(role, ''))) NOT IN ('super_admin', 'admin', 'staff')
             ORDER BY created_at DESC";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row = normalizeUserStatus($row);
            $row['avatar_url'] = normalizeUserAvatarUrl($row['avatar_path'] ?? '');
            $pendingUsers[] = $row;
        }
    }
    
    return $pendingUsers;
}

// ===== HANDLE AJAX/GET ACTIONS EARLY =====
if (isset($_GET['ajax'])) {
    handleAjaxRequest();
    exit;
}

if (($_GET['action'] ?? '') === 'export_users') {
    exportUsers();
    exit;
}

// ===== PROCESS FORM SUBMISSIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_user':
            addUser();
            break;
            
        case 'edit_user':
            editUser();
            break;
            
        case 'delete_user':
            deleteUser();
            break;
            
        case 'approve_user':
            approveUser();
            break;
            
        case 'reject_user':
            rejectUser();
            break;
            
        case 'change_password':
            changePassword();
            break;
            
        case 'unlock_user':
            unlockUser();
            break;
            
        case 'bulk_action':
            bulkAction();
            break;
            
        case 'export_users':
            exportUsers();
            break;
            
        default:
            break;
    }
}

// ===== AJAX REQUEST HANDLER =====
function handleAjaxRequest() {
    $ajaxAction = $_GET['ajax'] ?? '';
    
    switch ($ajaxAction) {
        case 'get_user_details':
            getUserDetails();
            break;
            
        case 'check_username':
            checkUsername();
            break;
            
        case 'check_email':
            checkEmail();
            break;
    }
}

// ===== ADD USER FUNCTION =====
function addUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    
    // Only admin can add users
    if (!isAdmin()) {
        $message = 'Access denied. Only administrators can add users.';
        $messageType = 'error';
        return;
    }
    
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = strtolower((string)($_POST['role'] ?? 'customer'));
    $role = mysqli_real_escape_string($conn, $role);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $status = 'active';
    $currentUserId = $_SESSION['admin_id'] ?? 0;
    $currentUsername = $_SESSION['admin_name'] ?? 'System';
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($username) || empty($password)) {
        $message = 'All required fields must be filled';
        $messageType = 'error';
        return;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address';
        $messageType = 'error';
        return;
    }

    if (isHiddenSuperAdminEmailValue($email)) {
        $message = 'This email is reserved and cannot be registered manually.';
        $messageType = 'error';
        return;
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long';
        $messageType = 'error';
        return;
    }

    if (!canAssignRole($role)) {
        $message = 'Access denied. You cannot assign this role.';
        $messageType = 'error';
        return;
    }
    
    // Check if user already exists
    $checkQuery = "SELECT id FROM users WHERE email = ? OR username = ?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
    mysqli_stmt_execute($stmt);
    $checkResult = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $message = 'User with this email or username already exists.';
        $messageType = 'error';
        mysqli_stmt_close($stmt);
        return;
    }
    mysqli_stmt_close($stmt);
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $query = "INSERT INTO users (
                full_name, 
                email, 
                username, 
                password_hash, 
                role, 
                phone, 
                status, 
                is_active,
                created_by,
                created_at,
                registration_ip
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    mysqli_stmt_bind_param($stmt, 'sssssssiis', 
        $name, $email, $username, $hashedPassword, $role, 
        $phone, $status, 1, $currentUserId, $ip);
    
    if (mysqli_stmt_execute($stmt)) {
        $userId = mysqli_insert_id($conn);
        $message = 'User added successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity('add_user', "Added new user: $name ($email) as $role");
        
    } else {
        $message = 'Failed to add user: ' . mysqli_error($conn);
        $messageType = 'error';
    }
    mysqli_stmt_close($stmt);
}

// ===== EDIT USER FUNCTION =====
function editUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    $currentUserId = $_SESSION['admin_id'] ?? 0;
    $currentUserRole = $_SESSION['admin_role'] ?? 'staff';
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $role = strtolower((string)($_POST['role'] ?? 'customer'));
    $role = mysqli_real_escape_string($conn, $role);
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');
    
    // Get user current data for permission check
    $userQuery = "SELECT role, is_super_admin, id, email FROM users WHERE id = $userId";
    $userResult = mysqli_query($conn, $userQuery);
    
    if (!$userResult || mysqli_num_rows($userResult) === 0) {
        $message = 'User not found';
        $messageType = 'error';
        return;
    }
    
    $userData = mysqli_fetch_assoc($userResult);
    if (isHiddenSuperAdminEmailValue($userData['email'] ?? '')) {
        $message = 'Access denied. This account is protected.';
        $messageType = 'error';
        return;
    }
    $existingRole = normalizeStoredRole($userData['role'] ?? '', $userData['is_super_admin'] ?? 0);
    
    // Check permissions
    if (!canManageUser($existingRole)) {
        $message = 'Access denied. You cannot edit this user.';
        $messageType = 'error';
        return;
    }

    if (!canAssignRole($role)) {
        $message = 'Access denied. You cannot assign this role.';
        $messageType = 'error';
        return;
    }
    
    // Prevent self-role change (admin can't demote themselves)
    if ($userId == $currentUserId && $role != $existingRole) {
        $message = 'You cannot change your own role.';
        $messageType = 'error';
        return;
    }
    
    // Check if email/username already exists (excluding current user)
    $checkQuery = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, 'ssi', $email, $username, $userId);
    mysqli_stmt_execute($stmt);
    $checkResult = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $message = 'User with this email or username already exists.';
        $messageType = 'error';
        mysqli_stmt_close($stmt);
        return;
    }
    mysqli_stmt_close($stmt);
    
    // Determine is_active based on status
    $is_active = ($status === 'active' || $status === 'approved') ? 1 : 0;
    
    // Update user
    $query = "UPDATE users SET 
              full_name = ?, 
              email = ?, 
              username = ?, 
              role = ?, 
              phone = ?, 
              status = ?,
              is_active = ?,
              updated_at = NOW() 
              WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssii', 
        $name, $email, $username, $role, $phone, $status, $is_active, $userId);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = 'User updated successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity('edit_user', "Updated user ID: $userId");
        
    } else {
        $message = 'Failed to update user: ' . mysqli_error($conn);
        $messageType = 'error';
    }
    mysqli_stmt_close($stmt);
}

// ===== DELETE USER FUNCTION =====
function deleteUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    $currentUserId = $_SESSION['admin_id'] ?? 0;
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    // Prevent self-deletion
    if ($userId == $currentUserId) {
        $message = 'You cannot delete your own account.';
        $messageType = 'error';
        return;
    }
    
    // Get user data for permission check
    $userQuery = "SELECT role, is_super_admin, email FROM users WHERE id = $userId";
    $userResult = mysqli_query($conn, $userQuery);
    
    if (!$userResult || mysqli_num_rows($userResult) === 0) {
        $message = 'User not found';
        $messageType = 'error';
        return;
    }
    
    $userData = mysqli_fetch_assoc($userResult);
    if (isHiddenSuperAdminEmailValue($userData['email'] ?? '')) {
        $message = 'Access denied. This account is protected.';
        $messageType = 'error';
        return;
    }
    $targetRole = normalizeStoredRole($userData['role'] ?? '', $userData['is_super_admin'] ?? 0);
    
    // Check permissions
    if (!canManageUser($targetRole)) {
        $message = 'Access denied. You cannot delete this user.';
        $messageType = 'error';
        return;
    }
    
    // Delete user
    $query = "DELETE FROM users WHERE id = $userId";
    
    if (mysqli_query($conn, $query)) {
        $message = 'User deleted successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity('delete_user', "Deleted user ID: $userId");
        
    } else {
        $message = 'Failed to delete user: ' . mysqli_error($conn);
        $messageType = 'error';
    }
}

// ===== APPROVE USER FUNCTION =====
function approveUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    
    // Only admin can approve users
    if (!isAdmin()) {
        $message = 'Access denied. Only administrators can approve users.';
        $messageType = 'error';
        return;
    }
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    $role = strtolower((string)($_POST['role'] ?? 'customer'));
    $role = mysqli_real_escape_string($conn, $role);
    $currentUserId = $_SESSION['admin_id'] ?? 0;

    if (!canAssignRole($role)) {
        $message = 'Access denied. You cannot assign this role.';
        $messageType = 'error';
        return;
    }
    
    // Update user
    $query = "UPDATE users SET 
              role = ?, 
              status = 'active', 
              is_active = 1,
              approved_by = ?,
              approved_at = NOW(),
              updated_at = NOW() 
              WHERE id = ? AND (status = 'pending' OR status = '')";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sii', $role, $currentUserId, $userId);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_affected_rows($conn) > 0) {
            $message = 'User approved successfully';
            $messageType = 'success';
            
            // Log activity
            logActivity('approve_user', "Approved user ID: $userId as $role");
            
        } else {
            $message = 'User not found or already approved';
            $messageType = 'warning';
        }
    } else {
        $message = 'Failed to approve user: ' . mysqli_error($conn);
        $messageType = 'error';
    }
    mysqli_stmt_close($stmt);
}

// ===== REJECT USER FUNCTION =====
function rejectUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    
    // Only admin can reject users
    if (!isAdmin()) {
        $message = 'Access denied. Only administrators can reject users.';
        $messageType = 'error';
        return;
    }
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    // Delete pending user
    $query = "DELETE FROM users WHERE id = $userId AND (status = 'pending' OR status = '')";
    
    if (mysqli_query($conn, $query)) {
        if (mysqli_affected_rows($conn) > 0) {
            $message = 'User registration rejected';
            $messageType = 'success';
            
            // Log activity
            logActivity('reject_user', "Rejected user ID: $userId");
            
        } else {
            $message = 'User not found or not pending';
            $messageType = 'warning';
        }
    } else {
        $message = 'Failed to reject user: ' . mysqli_error($conn);
        $messageType = 'error';
    }
}

// ===== CHANGE PASSWORD FUNCTION =====
function changePassword() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    $currentUserId = $_SESSION['admin_id'] ?? 0;
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    // Validate passwords
    if (empty($newPassword) || empty($confirmPassword)) {
        $message = 'Both password fields are required';
        $messageType = 'error';
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match';
        $messageType = 'error';
        return;
    }
    
    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long';
        $messageType = 'error';
        return;
    }
    
    // Only allow if admin or if changing own password
    if (!isAdmin() && $userId != $currentUserId) {
        $message = 'Access denied. You can only change your own password.';
        $messageType = 'error';
        return;
    }

    // Admins cannot change passwords for protected roles they cannot manage.
    if ($userId !== $currentUserId) {
        $targetResult = mysqli_query($conn, "SELECT role, is_super_admin, email FROM users WHERE id = $userId LIMIT 1");
        if (!$targetResult || mysqli_num_rows($targetResult) === 0) {
            $message = 'User not found.';
            $messageType = 'error';
            return;
        }

        $targetUser = mysqli_fetch_assoc($targetResult);
        if (isHiddenSuperAdminEmailValue($targetUser['email'] ?? '')) {
            $message = 'Access denied. This account is protected.';
            $messageType = 'error';
            return;
        }
        $targetRole = normalizeStoredRole($targetUser['role'] ?? '', $targetUser['is_super_admin'] ?? 0);
        if (!canManageUser($targetRole)) {
            $message = 'Access denied. You cannot change this user password.';
            $messageType = 'error';
            return;
        }
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $query = "UPDATE users SET password_hash = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'si', $hashedPassword, $userId);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = 'Password changed successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity('change_password', "Changed password for user ID: $userId");
        
    } else {
        $message = 'Failed to change password: ' . mysqli_error($conn);
        $messageType = 'error';
    }
    mysqli_stmt_close($stmt);
}

// ===== UNLOCK USER FUNCTION =====
function unlockUser() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $userId = intval($_POST['user_id'] ?? 0);
    
    // Only admin can unlock users
    if (!isAdmin()) {
        $message = 'Access denied. Only administrators can unlock accounts.';
        $messageType = 'error';
        return;
    }
    
    if ($userId === 0) {
        $message = 'Invalid user ID';
        $messageType = 'error';
        return;
    }
    
    // Check if user exists and is locked
    $checkQuery = "SELECT id, role, is_super_admin, email, status, failed_login_attempts FROM users WHERE id = $userId";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if (mysqli_num_rows($checkResult) === 0) {
        $message = 'User not found.';
        $messageType = 'error';
        return;
    }
    
    $userData = mysqli_fetch_assoc($checkResult);
    if (isHiddenSuperAdminEmailValue($userData['email'] ?? '')) {
        $message = 'Access denied. This account is protected.';
        $messageType = 'error';
        return;
    }

    $targetRole = normalizeStoredRole($userData['role'] ?? '', $userData['is_super_admin'] ?? 0);
    if (!canManageUser($targetRole)) {
        $message = 'Access denied. You cannot unlock this user.';
        $messageType = 'error';
        return;
    }
    
    if ((int)($userData['failed_login_attempts'] ?? 0) < 5 && ($userData['status'] ?? '') !== 'locked') {
        $message = 'User account is not locked.';
        $messageType = 'warning';
        return;
    }
    
    // Unlock account
    $query = "UPDATE users SET 
              failed_login_attempts = 0, 
              lock_until = NULL, 
              status = 'active',
              is_active = 1,
              updated_at = NOW() 
              WHERE id = $userId";
    
    if (mysqli_query($conn, $query)) {
        $message = 'User account unlocked successfully';
        $messageType = 'success';
        
        // Log activity
        logActivity('unlock_user', "Unlocked user ID: $userId");
        
    } else {
        $message = 'Failed to unlock account: ' . mysqli_error($conn);
        $messageType = 'error';
    }
}

// ===== BULK ACTION FUNCTION =====
function bulkAction() {
    global $message, $messageType;
    
    $conn = getDBConnection();
    $action = $_POST['bulk_action'] ?? '';
    $userIds = $_POST['user_ids'] ?? [];
    
    if (empty($userIds) || !is_array($userIds)) {
        $message = 'No users selected';
        $messageType = 'error';
        return;
    }
    
    $currentUserId = (int)($_SESSION['admin_id'] ?? 0);
    $sanitizedIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function($id) {
        return $id > 0;
    })));

    // Remove current user from the list
    $sanitizedIds = array_values(array_filter($sanitizedIds, static function($id) use ($currentUserId) {
        return $id !== $currentUserId;
    }));
    
    if (empty($sanitizedIds)) {
        $message = 'No valid users selected for bulk action';
        $messageType = 'error';
        return;
    }
    
    $idsString = implode(',', $sanitizedIds);
    $rolesByUserId = [];
    $skippedIds = [];
    $roleLookupResult = mysqli_query($conn, "SELECT id, role, is_super_admin, email FROM users WHERE id IN ($idsString)");
    if ($roleLookupResult) {
        while ($roleRow = mysqli_fetch_assoc($roleLookupResult)) {
            if (isHiddenSuperAdminEmailValue($roleRow['email'] ?? '')) {
                $skippedIds[] = (int)$roleRow['id'];
                continue;
            }
            $rolesByUserId[(int)$roleRow['id']] = normalizeStoredRole($roleRow['role'] ?? '', $roleRow['is_super_admin'] ?? 0);
        }
    }

    $allowedIds = [];
    foreach ($sanitizedIds as $candidateId) {
        $targetRole = $rolesByUserId[$candidateId] ?? '';
        if (canManageUser($targetRole)) {
            $allowedIds[] = $candidateId;
        } else {
            $skippedIds[] = $candidateId;
        }
    }

    if (empty($allowedIds)) {
        $message = 'Access denied. Selected users include protected roles.';
        $messageType = 'error';
        return;
    }

    $sanitizedIds = $allowedIds;
    $idsString = implode(',', $sanitizedIds);
    $affectedRows = 0;
    $skippedCount = count($skippedIds);
    
    switch ($action) {
        case 'activate':
            if (!isAdmin()) {
                $message = 'Access denied. Only administrators can activate users.';
                $messageType = 'error';
                return;
            }
            
            $query = "UPDATE users SET status = 'active', is_active = 1, updated_at = NOW() WHERE id IN ($idsString)";
            if (mysqli_query($conn, $query)) {
                $affectedRows = mysqli_affected_rows($conn);
                $message = "$affectedRows user(s) activated successfully";
                $messageType = 'success';
                logActivity('bulk_activate', "Activated users: $idsString");
            }
            break;
            
        case 'deactivate':
            if (!isAdmin()) {
                $message = 'Access denied. Only administrators can deactivate users.';
                $messageType = 'error';
                return;
            }
            
            $query = "UPDATE users SET status = 'inactive', is_active = 0, updated_at = NOW() WHERE id IN ($idsString)";
            if (mysqli_query($conn, $query)) {
                $affectedRows = mysqli_affected_rows($conn);
                $message = "$affectedRows user(s) deactivated successfully";
                $messageType = 'success';
                logActivity('bulk_deactivate', "Deactivated users: $idsString");
            }
            break;
            
        case 'delete':
            if (!isAdmin()) {
                $message = 'Access denied. Only administrators can delete users.';
                $messageType = 'error';
                return;
            }
            
            $query = "DELETE FROM users WHERE id IN ($idsString)";
            if (mysqli_query($conn, $query)) {
                $affectedRows = mysqli_affected_rows($conn);
                $message = "$affectedRows user(s) deleted successfully";
                $messageType = 'success';
                logActivity('bulk_delete', "Deleted users: $idsString");
            }
            break;
            
        case 'unlock':
            if (!isAdmin()) {
                $message = 'Access denied. Only administrators can unlock users.';
                $messageType = 'error';
                return;
            }
            
            $query = "UPDATE users SET failed_login_attempts = 0, lock_until = NULL, status = 'active', updated_at = NOW() WHERE id IN ($idsString)";
            if (mysqli_query($conn, $query)) {
                $affectedRows = mysqli_affected_rows($conn);
                $message = "$affectedRows user(s) unlocked successfully";
                $messageType = 'success';
                logActivity('bulk_unlock', "Unlocked users: $idsString");
            }
            break;
            
        default:
            $message = 'Invalid bulk action';
            $messageType = 'error';
            return;
    }
    
    if ($messageType === 'success' && $skippedCount > 0) {
        $message .= " {$skippedCount} protected user(s) were skipped.";
    }

    if ($affectedRows === 0 && !$message) {
        $message = 'No users were affected by this action';
        $messageType = 'warning';
    }
}

// ===== EXPORT USERS FUNCTION =====
function exportUsers() {
    global $searchQuery, $roleFilter, $statusFilter;
    
    // This function handles CSV export
    // Only admin can export users
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Only administrators can export users.']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Build WHERE clause
    $whereClause = "WHERE " . hiddenSuperAdminWhereClause($conn);

    // If specific IDs were submitted (Export Selected), honor them first
    $postedIds = $_POST['user_ids'] ?? [];
    if (is_array($postedIds) && !empty($postedIds)) {
        $safeIds = array_filter(array_map('intval', $postedIds), function($id) {
            return $id > 0;
        });
        if (!empty($safeIds)) {
            $whereClause .= " AND id IN (" . implode(',', $safeIds) . ")";
        }
    }
    
    if (!empty($searchQuery)) {
        $whereClause .= " AND (full_name LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%' OR username LIKE '%$searchQuery%')";
    }
    
    if (!empty($roleFilter) && $roleFilter !== 'all') {
        $normalizedRoleFilter = strtolower(trim($roleFilter));
        if ($normalizedRoleFilter === 'customer') {
            $whereClause .= " AND (
                COALESCE(is_super_admin, 0) = 0 AND (
                LOWER(TRIM(COALESCE(role, ''))) = 'customer'
                OR TRIM(COALESCE(role, '')) = ''
                OR LOWER(TRIM(COALESCE(role, ''))) NOT IN ('super_admin', 'admin', 'staff', 'customer')
                )
            )";
        } elseif ($normalizedRoleFilter === 'super_admin') {
            $whereClause .= " AND (
                COALESCE(is_super_admin, 0) = 1
                OR LOWER(TRIM(COALESCE(role, ''))) = 'super_admin'
            )";
        } else {
            $safeRoleFilter = mysqli_real_escape_string($conn, $normalizedRoleFilter);
            $whereClause .= " AND COALESCE(is_super_admin, 0) = 0 AND LOWER(TRIM(COALESCE(role, ''))) = '$safeRoleFilter'";
        }
    }
    
    if (!empty($statusFilter) && $statusFilter !== 'all') {
        if ($statusFilter === 'active') {
            $whereClause .= " AND (is_active = 1 OR status = 'active')";
        } elseif ($statusFilter === 'inactive') {
            $whereClause .= " AND (is_active = 0 AND status != 'suspended')";
        } elseif ($statusFilter === 'pending') {
            $whereClause .= " AND (status = 'pending' OR (is_active = 0 AND status = ''))";
        } elseif ($statusFilter === 'suspended') {
            $whereClause .= " AND status = 'suspended'";
        }
    }
    
    // Get users
    $query = "SELECT
                id,
                username,
                full_name,
                email,
                CASE
                    WHEN COALESCE(is_super_admin, 0) = 1 THEN 'super_admin'
                    WHEN LOWER(TRIM(COALESCE(role, ''))) IN ('super_admin', 'admin', 'staff', 'customer')
                        THEN LOWER(TRIM(role))
                    ELSE 'customer'
                END AS role,
                is_super_admin,
                phone,
                status,
                is_active,
                last_login,
                created_at,
                created_by,
                approved_by,
                approved_at,
                registration_ip
              FROM users $whereClause ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'ID', 'Username', 'Full Name', 'Email', 'Role', 'Phone', 
        'Status', 'Active', 'Last Login', 'Created At', 'Created By',
        'Approved By', 'Approved At', 'Registration IP'
    ]);
    
    // Add data
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['id'],
                $row['username'],
                $row['full_name'],
                $row['email'],
                $row['role'],
                $row['phone'] ?? '',
                $row['status'] ?? '',
                $row['is_active'],
                $row['last_login'] ?? '',
                $row['created_at'],
                $row['created_by'] ?? '',
                $row['approved_by'] ?? '',
                $row['approved_at'] ?? '',
                $row['registration_ip'] ?? ''
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// ===== GET USER DETAILS (AJAX) =====
function getUserDetails() {
    $conn = getDBConnection();
    $userId = intval($_GET['id'] ?? 0);
    
    if ($userId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        if (isHiddenSuperAdminEmailValue($row['email'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            mysqli_stmt_close($stmt);
            exit;
        }
        $row = normalizeUserStatus($row);
        $row['role'] = normalizeStoredRole($row['role'] ?? '', $row['is_super_admin'] ?? 0);
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// ===== LOAD DATA FROM DATABASE =====
$conn = getDBConnection();

// Load user statistics
$stats = getUserStatistics($conn);

// Load users data
$usersData = loadUsersData($conn, $searchQuery, $roleFilter, $statusFilter, $sortBy, $sortOrder, $offset, $perPage);

// Load pending users (only for admins)
$pendingUsersData = [];
if (isAdmin()) {
    $pendingUsersData = loadPendingUsers($conn);
}

// ===== ROLE OPTIONS =====
$allRoleOptions = [
    'super_admin' => 'Super Admin',
    'admin' => 'Administrator',
    'staff' => 'Staff Member',
    'customer' => 'Customer'
];

$roleFilterOptions = $allRoleOptions;

$roleOptions = array_filter(
    $allRoleOptions,
    static function($roleKey) {
        return canAssignRole($roleKey);
    },
    ARRAY_FILTER_USE_KEY
);

// ===== STATUS OPTIONS =====
$statusOptions = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'pending' => 'Pending',
    'suspended' => 'Suspended',
    'locked' => 'Locked'
];

// ===== ACTIVITY LEVELS =====
$activityLevels = [
    'today' => ['label' => 'Today', 'color' => 'success'],
    'week' => ['label' => 'This Week', 'color' => 'primary'],
    'month' => ['label' => 'This Month', 'color' => 'info'],
    'inactive' => ['label' => 'Inactive', 'color' => 'secondary'],
    'never' => ['label' => 'Never Logged In', 'color' => 'danger']
];

// HTML starts here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME ?? 'Admin Panel'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        /* User Management Specific Styles */
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: #212529;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.2;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1rem;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .js-zoom-avatar {
            cursor: zoom-in;
        }

        .user-avatar-lightbox {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.82);
            z-index: 5000;
            padding: 24px;
        }

        .user-avatar-lightbox.is-open {
            display: flex;
        }

        .user-avatar-lightbox img {
            max-width: min(92vw, 980px);
            max-height: 88vh;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 14px 38px rgba(0, 0, 0, 0.45);
            background: #fff;
        }

        .user-avatar-lightbox-close {
            position: absolute;
            top: 14px;
            right: 16px;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            background: #fff;
            color: #111;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }
        
        .user-avatar-sm {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        
        .user-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
        }
        
        .user-table-row {
            transition: background-color 0.2s ease;
        }
        
        .user-table-row:hover {
            background-color: #f8f9fa;
        }
        
        .user-status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #6c757d; }
        .status-pending { background-color: #ffc107; }
        .status-locked { background-color: #dc3545; }
        .status-suspended { background-color: #6610f2; }
        
        .user-activity-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .activity-today { background-color: #d4edda; color: #155724; }
        .activity-week { background-color: #d1ecf1; color: #0c5460; }
        .activity-month { background-color: #d6d8d9; color: #383d41; }
        .activity-inactive { background-color: #f8d7da; color: #721c24; }
        .activity-never { background-color: #f8d7da; color: #721c24; }
        
        .bulk-actions-bar {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .user-details-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .user-info-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-info-item:last-child {
            border-bottom: none;
        }
        
        .user-info-label {
            font-weight: 600;
            color: #666;
            min-width: 150px;
        }
        
        .user-info-value {
            color: #333;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .quick-action-btn {
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .quick-action-btn:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .stats-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stats-card-icon.total { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .stats-card-icon.active { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .stats-card-icon.pending { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stats-card-icon.locked { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .role-distribution {
            margin-top: 20px;
        }
        
        .role-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .role-item:last-child {
            border-bottom: none;
        }
        
        .role-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .role-super_admin { background: #6f42c1; }
        .role-admin { background: #dc3545; }
        .role-staff { background: #007bff; }
        .role-customer { background: #6c757d; }
        
        .export-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            min-width: 100px;
            text-align: center;
        }
        
        .filter-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .filter-tag {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pagination-enhanced {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid #dee2e6;
        }

        .quick-filter-form .form-select,
        .quick-filter-form .btn {
            min-height: 32px;
            font-size: 0.85rem;
        }

        .bulk-actions-bar .form-select,
        .bulk-actions-bar .btn {
            min-height: 32px;
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 14px;
            }

            .page-title {
                font-size: 1.2rem;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pagination-enhanced {
                flex-direction: column;
                gap: 15px;
            }
            
            .export-options {
                flex-direction: column;
            }
            
            .export-btn {
                width: 100%;
            }

            #usersTable thead {
                display: none;
            }

            #usersTable,
            #usersTable tbody,
            #usersTable tr,
            #usersTable td {
                display: block;
                width: 100%;
            }

            #usersTable tr {
                border: 1px solid #e9ecef;
                border-radius: 10px;
                padding: 10px;
                margin-bottom: 12px;
                background: #fff;
            }

            #usersTable td {
                border: 0;
                padding: 6px 4px;
            }

            #usersTable td::before {
                content: attr(data-label);
                font-size: 0.75rem;
                color: #6c757d;
                display: block;
                margin-bottom: 2px;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container-fluid py-3">
        <!-- Page Header -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-users-cog text-primary"></i>
                User Management
            </h1>
            <div class="btn-toolbar">
                <?php if (isAdmin()): ?>
                <button class="btn btn-primary" onclick="showAddUserModal()">
                    <i class="fas fa-user-plus me-2"></i> Add New User
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notification Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p class="text-muted mb-0">Total Users</p>
                    <small class="text-success">
                        <i class="fas fa-arrow-up me-1"></i>
                        <?php echo $stats['recent_week']; ?> new this week
                    </small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3><?php echo number_format($stats['active']); ?></h3>
                    <p class="text-muted mb-0">Active Users</p>
                    <small class="text-info">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <?php echo $stats['recent_login']; ?> recent login
                    </small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-icon pending">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3><?php echo number_format($stats['pending']); ?></h3>
                    <p class="text-muted mb-0">Pending Approval</p>
                    <small class="text-warning">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Needs attention
                    </small>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-card-icon locked">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h3><?php echo number_format($stats['locked']); ?></h3>
                    <p class="text-muted mb-0">Locked Accounts</p>
                    <small class="text-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Requires unlock
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Pending Users Section (Admin Only) -->
        <?php if (isAdmin() && !empty($pendingUsersData)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>
                    Pending User Approvals
                    <span class="badge bg-white text-warning ms-2"><?php echo count($pendingUsersData); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact Info</th>
                                <th>Registration Date</th>
                                <th>Registration IP</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingUsersData as $user): ?>
                            <?php 
                            $user = normalizeUserStatus($user);
                            $displayStatus = getUserDisplayStatus($user);
                            ?>
                            <tr>
                                <td data-label="Select">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar user-avatar-sm">
                                            <?php if (!empty($user['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User avatar" class="js-zoom-avatar" data-full="<?php echo htmlspecialchars($user['avatar_url']); ?>">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['name'] ?? $user['username'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            <div>
                                                <?php echo getRoleBadge($user['role']); ?>
                                                <?php echo getUserStatusBadge($displayStatus); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="User Profile">
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <?php if ($user['phone']): ?>
                                    <div><small><?php echo htmlspecialchars($user['phone']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td data-label="Role & Status">
                                    <small><?php echo htmlspecialchars($user['registration_ip'] ?? 'N/A'); ?></small>
                                </td>
                                <td data-label="Contact Information">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-success" onclick="showApproveUserModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name'] ?? $user['username']); ?>')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name'] ?? $user['username']); ?>')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulkActionsBar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span id="selectedCount">0</span> users selected
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" id="bulkActionSelect" style="width: 150px;">
                        <option value="">Choose action...</option>
                        <?php if (isAdmin()): ?>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        <option value="unlock">Unlock Selected</option>
                        <option value="delete">Delete Selected</option>
                        <?php endif; ?>
                        <option value="export">Export Selected</option>
                    </select>
                    
                    <button class="btn btn-sm btn-primary" id="bulkApplyBtn" onclick="applyBulkAction()" disabled>
                        <i class="fas fa-play"></i> Apply
                    </button>
                    
                    <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" id="bulkActionHint">Select users and choose an action to continue.</small>
        </div>
        
        <!-- User Management Section -->
        <div class="card">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            All Users
                            <span class="badge bg-primary ms-2"><?php echo $usersData['total']; ?></span>
                        </h5>
                        <small class="text-muted">
                            Showing <?php echo count($usersData['users']); ?> of <?php echo $usersData['total']; ?> users
                            <?php if ($usersData['pages'] > 1): ?>
                            | Page <?php echo $page; ?> of <?php echo $usersData['pages']; ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <!-- Filters and Actions -->
                    <div class="d-flex gap-2 align-items-center mt-2 mt-md-0">
                        <!-- Search -->
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   placeholder="Search users..." 
                                   id="searchInput"
                                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                                   onkeypress="if(event.key === 'Enter') performSearch()">
                            <button class="btn btn-outline-primary" type="button" onclick="performSearch()" title="Search users">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Export Options -->
                        <?php if (isAdmin()): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="exportUsers('csv')">
                                    <i class="fas fa-file-csv me-2"></i> CSV Format
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportUsers('excel')">
                                    <i class="fas fa-file-excel me-2"></i> Excel Format
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportUsers('pdf')">
                                    <i class="fas fa-file-pdf me-2"></i> PDF Format
                                </a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Advanced Filters Toggle -->
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                            <i class="fas fa-filter me-1"></i> Filters
                        </button>
                    </div>
                </div>

                <form method="GET" class="row g-2 mt-2 quick-filter-form">
                    <input type="hidden" name="page" value="user_management">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                    <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
                    <div class="col-6 col-md-3">
                        <select name="role" class="form-select form-select-sm">
                            <option value="all">All Roles</option>
                            <?php foreach ($roleFilterOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $roleFilter === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All Status</option>
                            <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-check me-1"></i> Apply
                        </button>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="?page=user_management" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </form>
                
                <!-- Active Filters -->
                <?php if ($searchQuery || ($roleFilter && $roleFilter !== 'all') || ($statusFilter && $statusFilter !== 'all')): ?>
                <div class="filter-tags mt-3">
                    <?php if ($searchQuery): ?>
                    <div class="filter-tag">
                        <span>Search: "<?php echo htmlspecialchars($searchQuery); ?>"</span>
                        <a href="?page=user_management&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>"
                           class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($roleFilter && $roleFilter !== 'all'): ?>
                    <div class="filter-tag">
                        <span>Role: <?php echo htmlspecialchars($roleFilterOptions[$roleFilter] ?? ucwords(str_replace('_', ' ', $roleFilter))); ?></span>
                        <a href="?page=user_management&search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>"
                           class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($statusFilter && $statusFilter !== 'all'): ?>
                    <div class="filter-tag">
                        <span>Status: <?php echo ucfirst($statusFilter); ?></span>
                        <a href="?page=user_management&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>"
                           class="text-decoration-none">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Advanced Filters -->
                <div class="collapse mt-3" id="advancedFilters">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page" value="user_management">
                        
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control form-control-sm">
                                <option value="all">All Roles</option>
                                <?php foreach ($roleFilterOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $roleFilter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-control-sm">
                                <option value="all">All Status</option>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-control form-control-sm">
                                <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Registration Date</option>
                                <option value="last_login" <?php echo $sortBy === 'last_login' ? 'selected' : ''; ?>>Last Login</option>
                                <option value="full_name" <?php echo $sortBy === 'full_name' ? 'selected' : ''; ?>>Name</option>
                                <option value="role" <?php echo $sortBy === 'role' ? 'selected' : ''; ?>>Role</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Order</label>
                            <select name="order" class="form-control form-control-sm">
                                <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>Descending</option>
                                <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <a href="?page=user_management" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i> Clear All
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Role Distribution -->
                <?php if (!empty($stats['by_role'])): ?>
                <div class="role-distribution mb-4">
                    <h6 class="mb-3">Users by Role</h6>
                    <div class="row">
                        <?php foreach ($stats['by_role'] as $role => $count): ?>
                        <div class="col-md-4 mb-2">
                            <div class="role-item">
                                <div>
                                    <span class="role-color role-<?php echo $role; ?>"></span>
                                    <span><?php echo htmlspecialchars($allRoleOptions[$role] ?? ucwords(str_replace('_', ' ', $role))); ?></span>
                                </div>
                                <div>
                                    <span class="fw-bold"><?php echo $count; ?></span>
                                    <small class="text-muted">
                                        (<?php echo $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0; ?>%)
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Users Table -->
                <?php if (!empty($usersData['users'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th width="50">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onchange="toggleAllSelection(this)">
                                    </div>
                                </th>
                                <th>User Profile</th>
                                <th>Role & Status</th>
                                <th>Contact Information</th>
                                <th>Activity</th>
                                <th>Registration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersData['users'] as $user): ?>
                            <?php 
                            $canEdit = canManageUser($user['role']);
                            $canDelete = isAdmin() && canManageUser($user['role']) && $user['id'] != ($_SESSION['admin_id'] ?? 0);
                            $canChangePassword = $user['id'] == ($_SESSION['admin_id'] ?? 0) || (isAdmin() && canManageUser($user['role']));
                            $activityLevel = getUserActivityLevel($user['last_login'] ?? '');
                            $displayStatus = getUserDisplayStatus($user);
                            ?>
                            <tr class="user-table-row" data-user-id="<?php echo $user['id']; ?>">
                                <td data-label="Activity">
                                    <div class="form-check">
                                        <input class="form-check-input user-checkbox" type="checkbox" value="<?php echo $user['id']; ?>">
                                    </div>
                                </td>
                                <td data-label="Registration">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar">
                                            <?php if (!empty($user['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User avatar" class="js-zoom-avatar" data-full="<?php echo htmlspecialchars($user['avatar_url']); ?>">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['name'] ?? $user['username'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            <?php if ($user['id'] == ($_SESSION['admin_id'] ?? 0)): ?>
                                            <div><span class="badge bg-info">You</span></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <div class="mb-2">
                                        <?php echo getRoleBadge($user['role']); ?>
                                        <?php echo getUserStatusBadge($displayStatus); ?>
                                    </div>
                                    <div>
                                        <span class="user-status-indicator status-<?php echo $displayStatus; ?>"></span>
                                        <small class="text-muted"><?php echo ucfirst($displayStatus); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo $user['email']; ?>
                                        </a>
                                    </div>
                                    <?php if ($user['phone']): ?>
                                    <div>
                                        <a href="tel:<?php echo $user['phone']; ?>" class="text-decoration-none">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo $user['phone']; ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <span class="user-activity-badge activity-<?php echo $activityLevel; ?>">
                                            <?php echo ucfirst($activityLevel); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="text-muted">
                                            Last: <?php echo getLastLoginText($user['last_login'] ?? ''); ?>
                                        </small>
                                    </div>
                                    <?php if ($displayStatus === 'locked'): ?>
                                    <div class="mt-1">
                                        <small class="text-danger">
                                            <i class="fas fa-lock me-1"></i> Account locked
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <small class="text-muted">Registered:</small>
                                        <div><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                    <?php if ($user['approved_at'] && $user['approved_at'] != '0000-00-00 00:00:00'): ?>
                                    <div>
                                        <small class="text-muted">Approved:</small>
                                        <div><?php echo date('M d, Y', strtotime($user['approved_at'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <!-- View Details -->
                                        <button class="btn btn-sm btn-info" onclick="viewUserDetails(<?php echo $user['id']; ?>)" 
                                                title="View Details" data-bs-toggle="tooltip">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Edit User -->
                                        <?php if ($canEdit): ?>
                                        <button class="btn btn-sm btn-warning" onclick="showEditUserModal(
                                            <?php echo $user['id']; ?>,
                                            '<?php echo addslashes($user['name'] ?? ''); ?>',
                                            '<?php echo addslashes($user['email']); ?>',
                                            '<?php echo addslashes($user['username']); ?>',
                                            '<?php echo addslashes($user['role']); ?>',
                                            '<?php echo addslashes($user['phone'] ?? ''); ?>',
                                            '<?php echo addslashes($displayStatus); ?>'
                                        )" title="Edit User" data-bs-toggle="tooltip">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Change Password -->
                                        <?php if ($canChangePassword): ?>
                                        <button class="btn btn-sm btn-secondary" onclick="showChangePasswordModal(
                                            <?php echo $user['id']; ?>,
                                            '<?php echo addslashes($user['name'] ?? $user['username']); ?>'
                                        )" title="Change Password" data-bs-toggle="tooltip">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Unlock Account (Admin only) -->
                                        <?php if (isAdmin() && $displayStatus === 'locked'): ?>
                                        <button class="btn btn-sm btn-success" onclick="unlockUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name'] ?? $user['username']); ?>')"
                                                title="Unlock Account" data-bs-toggle="tooltip">
                                            <i class="fas fa-unlock"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Delete User -->
                                        <?php if ($canDelete): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(
                                            <?php echo $user['id']; ?>,
                                            '<?php echo addslashes($user['name'] ?? $user['username']); ?>'
                                        )" title="Delete User" data-bs-toggle="tooltip">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4>No Users Found</h4>
                    <p class="text-muted">No users match your current filters.</p>
                    <?php if ($searchQuery || $roleFilter || $statusFilter): ?>
                    <a href="?page=user_management" class="btn btn-primary">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-success ms-2" onclick="showAddUserModal()">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if (isset($usersData['pages']) && $usersData['pages'] > 1): ?>
                <div class="pagination-enhanced">
                    <div class="pagination-info">
                        <small class="text-muted">
                            Showing <?php echo count($usersData['users']); ?> of <?php echo $usersData['total']; ?> users
                        </small>
                    </div>
                    
                    <div class="pagination-controls">
                        <!-- First Page -->
                        <?php if ($page > 1): ?>
                        <a href="?page=user_management&p=1&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>&per_page=<?php echo $perPage; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                        <a href="?page=user_management&p=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>&per_page=<?php echo $perPage; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($usersData['pages'], $page + 2);
                        
                        if ($startPage > 1): ?>
                        <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=user_management&p=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>&per_page=<?php echo $perPage; ?>"
                           class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $usersData['pages']): ?>
                        <span class="btn btn-sm btn-outline-secondary disabled">...</span>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <?php if ($page < $usersData['pages']): ?>
                        <a href="?page=user_management&p=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>&per_page=<?php echo $perPage; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Last Page -->
                        <?php if ($page < $usersData['pages']): ?>
                        <a href="?page=user_management&p=<?php echo $usersData['pages']; ?>&search=<?php echo urlencode($searchQuery); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=<?php echo urlencode($sortBy); ?>&order=<?php echo urlencode($sortOrder); ?>&per_page=<?php echo $perPage; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Items Per Page -->
                    <div class="pagination-per-page">
                        <small class="text-muted me-2">Show:</small>
                        <select class="form-select form-select-sm" onchange="changePerPage(this.value)" style="width: auto;">
                            <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                            <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- User details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-control" required>
                                    <?php foreach ($roleOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" id="editUserName" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" id="editUserEmail" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="editUserUsername" class="form-control" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select name="role" id="editUserRole" class="form-control" required>
                                    <?php foreach ($roleOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="editUserStatus" class="form-control">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="editUserPhone" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="user_id" id="passwordUserId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <div class="form-control bg-light" id="passwordUserName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Approve User Modal -->
    <div class="modal fade" id="approveUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="approveUserForm">
                    <input type="hidden" name="action" value="approve_user">
                    <input type="hidden" name="user_id" id="approveUserId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <div class="form-control bg-light" id="approveUserName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign Role *</label>
                            <select name="role" class="form-control" required>
                                <?php foreach ($roleOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Action Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="bulkActionTitle">Bulk Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bulkActionForm">
                    <input type="hidden" name="action" value="bulk_action">
                    <input type="hidden" name="bulk_action" id="bulkActionType">
                    <div id="bulkUserIds"></div>
                    
                    <div class="modal-body" id="bulkActionContent">
                        <!-- Confirmation message will be loaded here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="userAvatarLightbox" class="user-avatar-lightbox" onclick="closeUserAvatarLightbox(event)">
        <button type="button" class="user-avatar-lightbox-close" aria-label="Close" onclick="closeUserAvatarLightbox(event)">&times;</button>
        <img id="userAvatarLightboxImg" src="" alt="User photo preview">
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        // User Management JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            if (window.bootstrap && window.bootstrap.Tooltip) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    window.bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
                });
            }
            
            // Setup search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }
            
            // Setup checkbox selection
            setupCheckboxSelection();

            const bulkActionSelect = document.getElementById('bulkActionSelect');
            if (bulkActionSelect) {
                bulkActionSelect.addEventListener('change', updateBulkActionState);
            }
            
            // Initialize DataTable
            try {
                $('#usersTable').DataTable({
                    "pageLength": <?php echo $perPage; ?>,
                    "lengthMenu": [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
                    "order": [],
                    "searching": false,
                    "info": false,
                    "paging": false
                });
            } catch (e) {
                console.log('DataTable initialization failed:', e);
            }
        });
        
        // Search functionality
        function performSearch() {
            const searchQuery = document.getElementById('searchInput').value;
            const currentUrl = new URL(window.location.href);
            
            currentUrl.searchParams.set('search', searchQuery);
            currentUrl.searchParams.set('p', 1); // Reset to first page
            
            window.location.href = currentUrl.toString();
        }

        function clearSearch() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('search');
            currentUrl.searchParams.set('p', 1);
            window.location.href = currentUrl.toString();
        }
        
        // Checkbox selection
        function setupCheckboxSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkActionsBar);
            });
        }
        
        function toggleAllSelection(checkbox) {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActionsBar();
        }
        
        function updateBulkActionsBar() {
            const selected = document.querySelectorAll('.user-checkbox:checked');
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = selected.length;
            
            if (selected.length > 0) {
                bulkActionsBar.style.display = 'block';
            } else {
                bulkActionsBar.style.display = 'none';
            }
            updateBulkActionState();
        }

        function updateBulkActionState() {
            const selected = document.querySelectorAll('.user-checkbox:checked').length;
            const action = document.getElementById('bulkActionSelect')?.value || '';
            const applyBtn = document.getElementById('bulkApplyBtn');
            const hint = document.getElementById('bulkActionHint');

            if (!applyBtn || !hint) {
                return;
            }

            const canApply = selected > 0 && action !== '';
            applyBtn.disabled = !canApply;

            if (selected === 0) {
                hint.textContent = 'Select users and choose an action to continue.';
            } else if (action === '') {
                hint.textContent = selected + ' selected. Choose a bulk action.';
            } else {
                hint.textContent = selected + ' selected. Ready to apply "' + action + '".';
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const selectAll = document.getElementById('selectAllCheckbox');
            
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
            if (selectAll) {
                selectAll.checked = false;
            }
            const actionSelect = document.getElementById('bulkActionSelect');
            if (actionSelect) {
                actionSelect.value = '';
            }
            
            updateBulkActionsBar();
        }
        
        function applyBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const selected = document.querySelectorAll('.user-checkbox:checked');
            
            if (!action) {
                showAlert('Please select an action first.', 'warning');
                return;
            }
            
            if (selected.length === 0) {
                showAlert('Please select at least one user.', 'warning');
                return;
            }
            
            const userIds = Array.from(selected).map(cb => cb.value);
            
            // For export, handle differently
            if (action === 'export') {
                exportSelectedUsers(userIds);
                return;
            }
            
            // Show confirmation modal for other actions
            showBulkActionConfirmation(action, userIds);
        }
        
        function showBulkActionConfirmation(action, userIds) {
            const modal = new bootstrap.Modal(document.getElementById('bulkActionModal'));
            const title = document.getElementById('bulkActionTitle');
            const content = document.getElementById('bulkActionContent');
            const actionType = document.getElementById('bulkActionType');
            const userIdsContainer = document.getElementById('bulkUserIds');
            
            // Clear previous content
            userIdsContainer.innerHTML = '';
            
            // Add user IDs as hidden inputs
            userIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = id;
                userIdsContainer.appendChild(input);
            });
            
            // Set action type
            actionType.value = action;
            
            // Set title and content based on action
            let actionText = '';
            let message = '';
            
            switch(action) {
                case 'activate':
                    actionText = 'Activate Users';
                    message = `Are you sure you want to activate ${userIds.length} selected user(s)?`;
                    break;
                case 'deactivate':
                    actionText = 'Deactivate Users';
                    message = `Are you sure you want to deactivate ${userIds.length} selected user(s)?`;
                    break;
                case 'unlock':
                    actionText = 'Unlock Users';
                    message = `Are you sure you want to unlock ${userIds.length} selected user(s)?`;
                    break;
                case 'delete':
                    actionText = 'Delete Users';
                    message = `<div class="alert alert-danger">
                                  <i class="fas fa-exclamation-triangle me-2"></i>
                                  <strong>Warning:</strong> This action cannot be undone.<br><br>
                                  Are you sure you want to permanently delete ${userIds.length} selected user(s)?
                               </div>`;
                    break;
            }
            
            title.textContent = actionText;
            content.innerHTML = `<p>${message}</p>`;
            
            modal.show();
        }
        
        function exportSelectedUsers(userIds) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.pathname + '?page=user_management';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_users';
            form.appendChild(actionInput);
            
            // Add selected user IDs
            userIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            // Add current filters
            const searchInput = document.createElement('input');
            searchInput.type = 'hidden';
            searchInput.name = 'search';
            searchInput.value = '<?php echo $searchQuery; ?>';
            form.appendChild(searchInput);
            
            const roleInput = document.createElement('input');
            roleInput.type = 'hidden';
            roleInput.name = 'role';
            roleInput.value = '<?php echo $roleFilter; ?>';
            form.appendChild(roleInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = '<?php echo $statusFilter; ?>';
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function openUserAvatarLightbox(imageUrl) {
            if (!imageUrl) return;
            const lightbox = document.getElementById('userAvatarLightbox');
            const image = document.getElementById('userAvatarLightboxImg');
            if (!lightbox || !image) return;
            image.src = imageUrl;
            lightbox.classList.add('is-open');
        }

        function closeUserAvatarLightbox(event) {
            const lightbox = document.getElementById('userAvatarLightbox');
            const image = document.getElementById('userAvatarLightboxImg');
            if (!lightbox || !image) return;
            if (event && event.target && event.target !== lightbox && !event.target.classList.contains('user-avatar-lightbox-close')) {
                return;
            }
            lightbox.classList.remove('is-open');
            image.src = '';
        }

        document.addEventListener('click', function(event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            if (!target.classList.contains('js-zoom-avatar')) return;
            event.preventDefault();
            const full = (target.getAttribute('data-full') || target.getAttribute('src') || '').trim();
            if (full) {
                openUserAvatarLightbox(full);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeUserAvatarLightbox();
            }
        });

        // Modal Functions
        function showAddUserModal() {
            const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
            modal.show();
        }
        
        function showEditUserModal(userId, name, email, username, role, phone, status) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserName').value = name || '';
            document.getElementById('editUserEmail').value = email;
            document.getElementById('editUserUsername').value = username;
            document.getElementById('editUserRole').value = role;
            document.getElementById('editUserPhone').value = phone || '';
            document.getElementById('editUserStatus').value = status || 'active';
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
        
        function showChangePasswordModal(userId, userName) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordUserName').textContent = userName;
            
            // Clear password fields
            const form = document.getElementById('changePasswordForm');
            form.querySelector('input[name="new_password"]').value = '';
            form.querySelector('input[name="confirm_password"]').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
            modal.show();
        }
        
        function showApproveUserModal(userId, userName) {
            document.getElementById('approveUserId').value = userId;
            document.getElementById('approveUserName').textContent = userName;
            
            const modal = new bootstrap.Modal(document.getElementById('approveUserModal'));
            modal.show();
        }
        
        // User Details via AJAX
        function viewUserDetails(userId) {
            // Show loading state
            const content = document.getElementById('userDetailsContent');
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading user details...</p>
                </div>
            `;
            
            // Fetch user details via AJAX
            fetch(`?page=user_management&ajax=get_user_details&id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.user) {
                        displayUserDetails(data.user);
                    } else {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Failed to load user details: ${data.message || 'Unknown error'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading user details. Please try again.
                        </div>
                    `;
                });
            
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            modal.show();
        }
        
        function displayUserDetails(user) {
            const content = document.getElementById('userDetailsContent');
            const lastLogin = user.last_login && user.last_login !== '0000-00-00 00:00:00' 
                ? new Date(user.last_login).toLocaleString() 
                : 'Never';
            const created = new Date(user.created_at).toLocaleString();
            const approved = user.approved_at && user.approved_at !== '0000-00-00 00:00:00'
                ? new Date(user.approved_at).toLocaleString()
                : 'Not approved';
            
            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            const projectBase = <?php echo json_encode((defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '')); ?>;
            const systemBase = <?php echo json_encode((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system')); ?>;

            function withProjectBase(path) {
                const value = String(path || '');
                if (!value.startsWith('/')) return value;
                if (!projectBase) return value;
                if (value === projectBase || value.startsWith(projectBase + '/')) return value;
                return projectBase + value;
            }

            function normalizeAvatarUrl(path) {
                const value = (path || '').trim();
                if (!value) return '';
                if (value.startsWith('systemuploads/users/')) return withProjectBase('/' + value);
                if (value.startsWith('/uploads/')) return systemBase + value;
                if (value.startsWith('uploads/')) return systemBase + '/' + value;
                if (value.startsWith('/')) return withProjectBase(value);
                return withProjectBase('/' + value);
            }

            const avatarSource = normalizeAvatarUrl(user.avatar_url || user.profile_image || user.image_url || '');
            const avatarHtml = avatarSource
                ? `<img src="${escapeHtml(avatarSource)}" alt="User avatar" class="js-zoom-avatar" data-full="${escapeHtml(avatarSource)}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
                : `${escapeHtml((user.name || user.username || '').charAt(0).toUpperCase())}`;
            
            content.innerHTML = `
                <div class="user-details">
                    <!-- User Header -->
                    <div class="text-center mb-4">
                        <div class="user-avatar d-inline-flex" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            ${avatarHtml}
                        </div>
                        <h4 class="mt-3">${escapeHtml(user.name || user.username || '')}</h4>
                        <p class="text-muted">@${escapeHtml(user.username || '')}</p>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            ${getRoleBadgeHtml(user.role)}
                            ${getUserStatusBadgeHtml(user.status || 'inactive')}
                        </div>
                    </div>
                    
                    <!-- User Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="user-info-item">
                                <div class="user-info-label">Email Address</div>
                                <div class="user-info-value">
                                    <a href="mailto:${escapeHtml(user.email || '')}">${escapeHtml(user.email || '')}</a>
                                </div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Phone Number</div>
                                <div class="user-info-value">
                                    ${user.phone ? `<a href="tel:${escapeHtml(user.phone)}">${escapeHtml(user.phone)}</a>` : 'Not provided'}
                                </div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Account Status</div>
                                <div class="user-info-value">
                                    <span class="user-status-indicator status-${user.status || 'inactive'}"></span>
                                    ${capitalizeFirst(user.status || 'inactive')}
                                    ${user.is_active == 1 ? '(Active)' : '(Inactive)'}
                                </div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Last Login</div>
                                <div class="user-info-value">${lastLogin}</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="user-info-item">
                                <div class="user-info-label">Registration Date</div>
                                <div class="user-info-value">${created}</div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Created By</div>
                                <div class="user-info-value">${escapeHtml(user.created_by || 'System')}</div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Approval Date</div>
                                <div class="user-info-value">${approved}</div>
                            </div>
                            
                            <div class="user-info-item">
                                <div class="user-info-label">Approved By</div>
                                <div class="user-info-value">${escapeHtml(user.approved_by || 'Not approved')}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Information -->
                    <div class="mt-4">
                        <h6><i class="fas fa-shield-alt me-2"></i>Security Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="user-info-item">
                                    <div class="user-info-label">Failed Login Attempts</div>
                                    <div class="user-info-value">
                                        ${user.failed_login_attempts || 0}
                                        ${user.failed_login_attempts >= 5 ? '<span class="badge bg-danger ms-2">Locked</span>' : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="user-info-item">
                                    <div class="user-info-label">Account Locked Until</div>
                                    <div class="user-info-value">
                                        ${user.lock_until && user.lock_until !== '0000-00-00 00:00:00'
                                            ? new Date(user.lock_until).toLocaleString()
                                            : 'Not locked'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Registration Information -->
                    <div class="mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Registration Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="user-info-item">
                                    <div class="user-info-label">Registration IP</div>
                                    <div class="user-info-value">${escapeHtml(user.registration_ip || 'Not available')}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="user-info-item">
                                    <div class="user-info-label">Last Active</div>
                                    <div class="user-info-value">
                                        ${user.last_active && user.last_active !== '0000-00-00 00:00:00'
                                            ? new Date(user.last_active).toLocaleString()
                                            : 'Never'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-4">
                        <h6><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                        <div class="quick-actions-grid">
                            ${canManageTargetRole(user.role) ? `
                            <div class="quick-action-btn" onclick="showEditUserModal(
                                ${user.id},
                                '${escapeHtml(user.name || '').replace(/'/g, "\\'")}',
                                '${escapeHtml(user.email || '').replace(/'/g, "\\'")}',
                                '${escapeHtml(user.username || '').replace(/'/g, "\\'")}',
                                '${escapeHtml(user.role || '').replace(/'/g, "\\'")}',
                                '${escapeHtml(user.phone || '').replace(/'/g, "\\'")}',
                                '${escapeHtml(user.status || '').replace(/'/g, "\\'")}'
                            )">
                                <i class="fas fa-edit text-warning"></i>
                                <div class="mt-1">Edit</div>
                            </div>
                            ` : ''}
                            
                            ${(user.id === getCurrentUserId() || (isAdminLikeRole(getCurrentUserRole()) && canManageTargetRole(user.role))) ? `
                            <div class="quick-action-btn" onclick="showChangePasswordModal(
                                ${user.id},
                                '${escapeHtml(user.name || user.username || '').replace(/'/g, "\\'")}'
                            )">
                                <i class="fas fa-key text-info"></i>
                                <div class="mt-1">Change Password</div>
                            </div>
                            ` : ''}
                            
                            ${user.failed_login_attempts >= 5 && isAdminLikeRole(getCurrentUserRole()) && canManageTargetRole(user.role) ? `
                            <div class="quick-action-btn" onclick="unlockUser(${user.id}, '${escapeHtml(user.name || user.username || '').replace(/'/g, "\\'")}')">
                                <i class="fas fa-unlock text-success"></i>
                                <div class="mt-1">Unlock</div>
                            </div>
                            ` : ''}
                            
                            ${isAdminLikeRole(getCurrentUserRole()) && canManageTargetRole(user.role) && user.id !== getCurrentUserId() ? `
                            <div class="quick-action-btn" onclick="deleteUser(${user.id}, '${escapeHtml(user.name || user.username || '').replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash text-danger"></i>
                                <div class="mt-1">Delete</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        
        // User Action Functions
        function deleteUser(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"?\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname + '?page=user_management';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectUser(userId, userName) {
            if (confirm(`Are you sure you want to reject "${userName}"'s registration request?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname + '?page=user_management';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reject_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function unlockUser(userId, userName) {
            if (confirm(`Are you sure you want to unlock "${userName}"'s account?\n\nThis will reset their failed login attempts to 0.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.pathname + '?page=user_management';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'unlock_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Export Functions
        function exportUsers(format) {
            const search = '<?php echo urlencode($searchQuery); ?>';
            const role = '<?php echo urlencode($roleFilter); ?>';
            const status = '<?php echo urlencode($statusFilter); ?>';
            
            // Show loading
            const exportBtn = event.target.closest('a');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Exporting...';
            
            // Create export URL
            const exportUrl = `?page=user_management&action=export_users&search=${search}&role=${role}&status=${status}&format=${format}`;
            
            // Trigger download
            window.location.href = exportUrl;
            
            // Restore button after a delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
            }, 1000);
        }
        
        // Utility Functions
        function changePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('p', 1); // Reset to first page
            window.location.href = url.toString();
        }
        
        function capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
        
        function getCurrentUserRole() {
            return '<?php echo strtolower((string)($_SESSION['admin_role'] ?? 'staff')); ?>';
        }

        function isAdminLikeRole(role) {
            const normalizedRole = (role || '').toLowerCase();
            return normalizedRole === 'super_admin' || normalizedRole === 'admin';
        }

        function canManageTargetRole(targetRole) {
            const currentRole = getCurrentUserRole();
            const normalizedTargetRole = (targetRole || '').toLowerCase();
            if (currentRole === 'super_admin') {
                return true;
            }
            if (currentRole === 'admin') {
                return normalizedTargetRole !== 'super_admin';
            }
            return currentRole === 'staff' && normalizedTargetRole === 'customer';
        }
        
        function getCurrentUserId() {
            return <?php echo $_SESSION['admin_id'] ?? 0; ?>;
        }
        
        function getRoleBadgeHtml(role) {
            const badges = {
                'super_admin': '<span class="badge bg-dark">Super Admin</span>',
                'admin': '<span class="badge bg-danger">Admin</span>',
                'staff': '<span class="badge bg-primary">Staff</span>',
                'customer': '<span class="badge bg-secondary">Customer</span>',
                'pending': '<span class="badge bg-warning">Pending</span>'
            };
            return badges[role] || `<span class="badge bg-light text-dark">${role}</span>`;
        }
        
        function getUserStatusBadgeHtml(status) {
            const badges = {
                'active': '<span class="badge bg-success">Active</span>',
                'inactive': '<span class="badge bg-secondary">Inactive</span>',
                'pending': '<span class="badge bg-warning">Pending</span>',
                'suspended': '<span class="badge bg-danger">Suspended</span>',
                'locked': '<span class="badge bg-danger">Locked</span>'
            };
            return badges[status] || `<span class="badge bg-light text-dark">${status}</span>`;
        }
        
        function showAlert(message, type = 'success') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.maxWidth = '400px';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Password confirmation validation
            const changePasswordForm = document.getElementById('changePasswordForm');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        showAlert('Passwords do not match. Please try again.', 'danger');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        showAlert('Password must be at least 6 characters long.', 'danger');
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Add user form validation
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    const password = this.querySelector('input[name="password"]').value;
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        showAlert('Password must be at least 6 characters long.', 'danger');
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>

<?php 
ob_end_flush();
?>
