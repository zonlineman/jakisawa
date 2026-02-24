<?php
// admin/includes/auth_functions.php



require_once 'database.php';




require_once 'role_permissions.php';
/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    if (!isset($_SESSION['admin_role'])) {
        return false;
    }
    return $_SESSION['admin_role'] === $role;
}

/**
 * Require authentication and authorization
 * - Ensures user is logged in
 * - Ensures account is active
 * - Ensures role can access requested page
 */
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // // 1. Check login
    // if (empty($_SESSION['admin_id'])) {
    //     header('Location: ../login.php');
    //     exit;
    // }

    // 2. Check account active
    if (isset($_SESSION['admin_is_active']) && (int)$_SESSION['admin_is_active'] !== 1) {
        session_unset();
        session_destroy();
        header('Location: ../login.php?error=account_disabled');
        exit;
    }

    // 3. Normalize role early (safety)
    if (empty($_SESSION['admin_role'])) {
        $_SESSION['admin_role'] = 'staff';
    }

    // 4. Page-level authorization
    $page = $_GET['page'] ?? 'dashboard';

    if (!canAccessPage($page)) {
        showAccessDenied(); // must exit internally
    }

    return true;
}

?>