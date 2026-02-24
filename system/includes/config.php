<?php
// config.php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // Change to 'production' when live
}

// Only allow direct access to config.php itself
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    die('Direct access not permitted');
}

// Now this file can be safely included anywhere
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/paths.php';

// includes/config.php
define('SITENAME', 'JAKISAWA SHOP');
define('SITE_NAME', 'JAKISAWA SHOP');
define('CURRENCY', 'KES');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('SUPPORT_EMAIL', 'support@jakisawashop.co.ke');
define('APP_NAME', 'JAKISAWA SHOP');
define('SMTP_HOST', 'mail.jakisawashop.co.ke');
define('SMTP_USER', 'support@jakisawashop.co.ke');
define('SMTP_PASS', '#@Support,2026');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

// SMS configuration
// Set SMS_PROVIDER to 'africastalking' to enable SMS sending.
define('SMS_PROVIDER', 'none');
define('SMS_AT_USERNAME', '');
define('SMS_AT_API_KEY', '');
define('SMS_AT_SENDER_ID', '');
define('SMS_AT_ENDPOINT', 'https://api.africastalking.com/version1/messaging');

// // admin/includes/database.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// /**
//  * Get MySQLi database connection with error handling
//  */
// function getDBConnection() {
//     static $conn = null;
    
//     if ($conn === null) {
//         // Try to connect
//         $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
//         if (!$conn) {
//             // Don't die(), just log and return false
//             error_log("Database connection failed: " . mysqli_connect_error());
//             return false;
//         }
        
//         // Set charset
//         if (!mysqli_set_charset($conn, 'utf8mb4')) {
//             error_log("Error setting charset: " . mysqli_error($conn));
//         }
//     }
    
//     return $conn;
// }

// /**
//  * Safe query execution with error handling
//  */
// function safeQuery($sql) {
//     $conn = getDBConnection();
//     if (!$conn) {
//         error_log("No database connection for query: $sql");
//         return false;
//     }
    
//     $result = mysqli_query($conn, $sql);
//     if (!$result) {
//         error_log("Query failed: " . mysqli_error($conn) . " | SQL: $sql");
//         return false;
//     }
    
//     return $result;
// }


?>
