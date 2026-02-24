<?php
// File: logout.php
session_start();

// Clear all session variables
$_SESSION = [];
unset($_SESSION['admin_logged_in'], $_SESSION['admin_is_active']);

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit();
?>
