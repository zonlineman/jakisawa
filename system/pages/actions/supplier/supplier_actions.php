<?php
// actions/supplier_actions.php - Handles all supplier CRUD operations

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
define('ROOT_PATH', dirname(__DIR__, 3));
require_once ROOT_PATH . '/includes/database.php';
require_once ROOT_PATH . '/includes/audit_helper.php';

$conn = getDBConnection();
$current_user_id = $_SESSION['admin_id'] ?? 0;
$current_user_role = strtolower((string) ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'staff'));
$is_admin_user = in_array($current_user_role, ['admin', 'super_admin'], true);

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ?page=suppliers');
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_supplier':
        addSupplier($conn, $current_user_id);
        break;
        
    case 'edit_supplier':
        editSupplier($conn, $current_user_id);
        break;
        
    case 'delete_supplier':
        deleteSupplier($conn, $current_user_id, $is_admin_user);
        break;
        
    case 'toggle_supplier_status':
        toggleSupplierStatus($conn, $current_user_id);
        break;
        
    default:
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid action'];
        redirectToSuppliers();
}

function addSupplier($conn, $current_user_id) {
    $name = $conn->real_escape_string(trim($_POST['name']));
    $contact_person = $conn->real_escape_string(trim($_POST['contact_person'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Supplier name is required'];
        redirectToSuppliers();
        return;
    }
    
    // Check if supplier name already exists
    $check_query = "SELECT id FROM suppliers WHERE name = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'A supplier with this name already exists'];
        $check_stmt->close();
        redirectToSuppliers();
        return;
    }
    $check_stmt->close();
    
    // Insert supplier
    $query = "INSERT INTO suppliers (name, contact_person, email, phone, address, is_active, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssi", $name, $contact_person, $email, $phone, $address, $is_active);
    
    if ($stmt->execute()) {
        $supplier_id = $stmt->insert_id;
        
        // Log audit
        logAudit($conn, 'supplier_created', 'suppliers', $supplier_id, null, null, $current_user_id);
        
        $_SESSION['message'] = ['type' => 'success', 'text' => "Supplier '{$name}' added successfully"];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to add supplier: ' . $conn->error];
    }
    
    $stmt->close();
    redirectToSuppliers();
}

function editSupplier($conn, $current_user_id) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string(trim($_POST['name']));
    $contact_person = $conn->real_escape_string(trim($_POST['contact_person'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $phone = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Supplier name is required'];
        redirectToSuppliers();
        return;
    }
    
    if ($id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid supplier ID'];
        redirectToSuppliers();
        return;
    }
    
    // Check if supplier exists
    $check_query = "SELECT id FROM suppliers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Supplier not found'];
        $check_stmt->close();
        redirectToSuppliers();
        return;
    }
    $check_stmt->close();
    
    // Check if new name conflicts with another supplier
    $conflict_query = "SELECT id FROM suppliers WHERE name = ? AND id != ?";
    $conflict_stmt = $conn->prepare($conflict_query);
    $conflict_stmt->bind_param("si", $name, $id);
    $conflict_stmt->execute();
    $conflict_result = $conflict_stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Another supplier with this name already exists'];
        $conflict_stmt->close();
        redirectToSuppliers();
        return;
    }
    $conflict_stmt->close();
    
    // Get old values for audit
    $old_query = "SELECT * FROM suppliers WHERE id = ?";
    $old_stmt = $conn->prepare($old_query);
    $old_stmt->bind_param("i", $id);
    $old_stmt->execute();
    $old_result = $old_stmt->get_result();
    $old_values = $old_result->fetch_assoc();
    $old_stmt->close();
    
    // Update supplier
    $query = "UPDATE suppliers SET 
             name = ?, 
             contact_person = ?, 
             email = ?, 
             phone = ?, 
             address = ?, 
             is_active = ? 
             WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssii", $name, $contact_person, $email, $phone, $address, $is_active, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Get new values for audit
            $new_query = "SELECT * FROM suppliers WHERE id = ?";
            $new_stmt = $conn->prepare($new_query);
            $new_stmt->bind_param("i", $id);
            $new_stmt->execute();
            $new_result = $new_stmt->get_result();
            $new_values = $new_result->fetch_assoc();
            $new_stmt->close();
            
            // Log audit
            logAudit($conn, 'supplier_updated', 'suppliers', $id, $old_values, $new_values, $current_user_id);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => "Supplier '{$name}' updated successfully"];
        } else {
            $_SESSION['message'] = ['type' => 'info', 'text' => 'No changes were made'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update supplier: ' . $conn->error];
    }
    
    $stmt->close();
    redirectToSuppliers();
}

function deleteSupplier($conn, $current_user_id, $is_admin_user) {
    if (!$is_admin_user) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Only administrators can delete suppliers.'];
        redirectToSuppliers();
        return;
    }

    $id = intval($_POST['id']);
    
    if ($id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid supplier ID'];
        redirectToSuppliers();
        return;
    }
    
    // Check if supplier exists
    $check_query = "SELECT name FROM suppliers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Supplier not found'];
        $check_stmt->close();
        redirectToSuppliers();
        return;
    }
    
    $supplier_name = $check_result->fetch_assoc()['name'];
    $check_stmt->close();
    
    // Check if supplier has products
    $products_query = "SELECT COUNT(*) as product_count FROM remedies WHERE supplier_id = ?";
    $products_stmt = $conn->prepare($products_query);
    $products_stmt->bind_param("i", $id);
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    $product_count = $products_result->fetch_assoc()['product_count'];
    $products_stmt->close();
    
    if ($product_count > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => "Cannot delete supplier '{$supplier_name}'. It has {$product_count} product(s) assigned. Please reassign or remove the products first."];
        redirectToSuppliers();
        return;
    }
    
    // Delete supplier
    $query = "DELETE FROM suppliers WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Log audit
        logAudit($conn, 'supplier_deleted', 'suppliers', $id, ['name' => $supplier_name], null, $current_user_id);
        
        $_SESSION['message'] = ['type' => 'success', 'text' => "Supplier '{$supplier_name}' deleted successfully"];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete supplier: ' . $conn->error];
    }
    
    $stmt->close();
    redirectToSuppliers();
}

function toggleSupplierStatus($conn, $current_user_id) {
    $id = intval($_POST['id']);
    
    if ($id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid supplier ID'];
        redirectToSuppliers();
        return;
    }
    
    // Check if supplier exists and get current status
    $check_query = "SELECT name, is_active FROM suppliers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Supplier not found'];
        $check_stmt->close();
        redirectToSuppliers();
        return;
    }
    
    $supplier_data = $check_result->fetch_assoc();
    $supplier_name = $supplier_data['name'];
    $current_status = $supplier_data['is_active'];
    $check_stmt->close();
    
    // Toggle status
    $new_status = $current_status ? 0 : 1;
    $query = "UPDATE suppliers SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $new_status, $id);
    
    if ($stmt->execute()) {
        // Log audit
        $old_values = ['is_active' => $current_status];
        $new_values = ['is_active' => $new_status];
        logAudit($conn, 'supplier_status_toggle', 'suppliers', $id, $old_values, $new_values, $current_user_id);
        
        $status_text = $new_status ? 'activated' : 'deactivated';
        $_SESSION['message'] = ['type' => 'success', 'text' => "Supplier '{$supplier_name}' {$status_text} successfully"];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update supplier status: ' . $conn->error];
    }
    
    $stmt->close();
    redirectToSuppliers();
}

function logAudit($conn, $action, $table, $record_id, $old_values, $new_values, $user_id) {
    auditLogMysqli(
        $conn,
        (string)$action,
        (string)$table,
        $record_id,
        $old_values,
        $new_values,
        $user_id,
        $_SERVER['REMOTE_ADDR'] ?? null
    );
}

function redirectToSuppliers() {
    $fallback = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/admin_dashboard.php?page=suppliers';
    $target = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== ''
        ? $_SERVER['HTTP_REFERER']
        : $fallback;
    header('Location: ' . $target);
    exit();
}
?>
