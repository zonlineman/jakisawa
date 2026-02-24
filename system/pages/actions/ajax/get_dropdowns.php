<?php
// C:\xampp\htdocs\JAKISAWA\system\pages\actions\ajax\get_dropdowns.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

session_start();
header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // ========== GET CATEGORIES ==========
    $categories = [];
    $cat_query = "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name";
    
    if ($cat_result = $conn->query($cat_query)) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
        $cat_result->free();
    } else {
        throw new Exception("Category query failed: " . $conn->error);
    }
    
    // ========== GET SUPPLIERS ==========
    // CORRECT: Your table has 'name' column, not 'company_name'
    $suppliers = [];
    $sup_query = "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name";
    
    if ($sup_result = $conn->query($sup_query)) {
        while ($row = $sup_result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        $sup_result->free();
    } else {
        throw new Exception("Supplier query failed: " . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'suppliers' => $suppliers,
        'debug' => [
            'categories_count' => count($categories),
            'suppliers_count' => count($suppliers)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
?>