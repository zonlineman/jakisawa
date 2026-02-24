<?php

require_once 'config.php';
function getDBConnection() {
    static $conn = null;
    if ($conn === null || !@mysqli_ping($conn)) {
        if ($conn instanceof mysqli) {
            @mysqli_close($conn);
        }
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            error_log("Database connection failed: " . mysqli_connect_error());
            http_response_code(500);
            die("Service temporarily unavailable. Please try again later."); // Or include error page
        }
        mysqli_set_charset($conn, 'utf8mb4');
    }
    return $conn;
}

// Helper function for prepared statements
function executeQuery($sql, $params = []) {
    $conn = getDBConnection();
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        die("SQL Error: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) $types .= 'i';
            elseif (is_float($param)) $types .= 'd';
            else $types .= 's';
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    return $stmt;
}

// Check if table exists
function tableExists($table) {
    $conn = getDBConnection();
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Optional: Set timezone
    $pdo->exec("SET time_zone = '+03:00'");
     
} catch (PDOException $e) {
    // Log the error instead of displaying it directly
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly message
    die("Database connection failed. Please try again later.");
}




?> 
