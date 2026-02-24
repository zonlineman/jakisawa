<?php
// File: C:\xampp\htdocs\JAKISAWA\system\pages\actions\remedies\add_remedy.php
// PURPOSE: Handle adding new remedies WITH IMAGE UPLOAD

// 1. Start session
session_start();

// 2. Include database and functions
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

$systemBase = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system');
$redirectRemedies = $systemBase . '/admin_dashboard.php?page=remedies';
$redirectAddRemedy = $systemBase . '/admin_dashboard.php?page=add_remedy';

// 3. Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectRemedies);
    exit();
}

// 4. Connect to database
$conn = getDBConnection();

// 5. Collect form data
$remedy = [
    'sku' => trim($_POST['sku'] ?? ''),
    'name' => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'category_id' => intval($_POST['category_id'] ?? 0),
    'supplier_id' => !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL,
    'ingredients' => trim($_POST['ingredients'] ?? ''),
    'usage_instructions' => trim($_POST['usage_instructions'] ?? ''),
    'unit_price' => floatval($_POST['unit_price'] ?? 0),
    'cost_price' => !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : NULL,
    'discount_price' => !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : NULL,
    'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
    'reorder_level' => intval($_POST['reorder_level'] ?? 10),
    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    'is_active' => 1 // New remedies are active by default
];

// 6. ========== HANDLE IMAGE UPLOAD ==========
$image_path = NULL;

// Check if image was uploaded
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $image = $_FILES['product_image'];
    
    // Allowed image types
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB maximum
    
    // Validate file type
    if (!in_array($image['type'], $allowed_types)) {
        $_SESSION['errors'][] = 'Invalid image type. Allowed: JPG, PNG, GIF, WebP';
        header('Location: ' . $redirectAddRemedy);
        exit();
    }
    
    // Validate file size
    if ($image['size'] > $max_size) {
        $_SESSION['errors'][] = 'Image too large. Maximum size: 5MB';
        header('Location: ' . $redirectAddRemedy);
        exit();
    }
    
    // Create unique filename
    $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
    $filename = 'remedy_' . uniqid() . '_' . time() . '.' . $file_extension;
    
    // Set upload directory
    $project_root = realpath(__DIR__ . '/../../../../');
    $upload_dir = $project_root . '/systemuploads/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Move uploaded file
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($image['tmp_name'], $destination)) {
        // Success! Store relative path for database
        $image_path = '/systemuploads/products/' . $filename;
        
        // Optional: Create thumbnail
        createThumbnail($destination, $upload_dir . 'thumb_' . $filename, 200, 200);
        
    } else {
        $_SESSION['errors'][] = 'Failed to upload image';
        header('Location: ' . $redirectAddRemedy);
        exit();
    }
}

// 7. Add image path to remedy data
$remedy['image_url'] = $image_path;

// 8. Create slug from name (for URLs)
$remedy['slug'] = createSlug($remedy['name']);

// 9. Validate required fields
$errors = [];
if (empty($remedy['sku'])) $errors[] = 'SKU is required';
if (empty($remedy['name'])) $errors[] = 'Name is required';
if (empty($remedy['category_id'])) $errors[] = 'Category is required';
if ($remedy['unit_price'] <= 0) $errors[] = 'Unit price must be greater than 0';

// 10. Check if SKU already exists
$check_sql = "SELECT id FROM remedies WHERE sku = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $remedy['sku']);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $errors[] = 'SKU already exists. Please use a different SKU.';
}

// 11. If errors, go back to form
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: ' . $redirectAddRemedy);
    exit();
}

// 12. INSERT INTO DATABASE
try {
    $sql = "INSERT INTO remedies (
        sku, name, slug, description, category_id, supplier_id,
        ingredients, usage_instructions, unit_price, cost_price,
        discount_price, stock_quantity, reorder_level, is_featured,
        is_active, image_url, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    // Bind all parameters
    $stmt->bind_param(
        "ssssissssddiiiss",
        $remedy['sku'],
        $remedy['name'],
        $remedy['slug'],
        $remedy['description'],
        $remedy['category_id'],
        $remedy['supplier_id'],
        $remedy['ingredients'],
        $remedy['usage_instructions'],
        $remedy['unit_price'],
        $remedy['cost_price'],
        $remedy['discount_price'],
        $remedy['stock_quantity'],
        $remedy['reorder_level'],
        $remedy['is_featured'],
        $remedy['is_active'],
        $remedy['image_url']
    );
    
    // 13. Execute the INSERT
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        
        // SUCCESS!
        $_SESSION['success'] = 'Remedy added successfully!';
        
        // Log the action
        error_log("New remedy added: ID=$new_id, SKU={$remedy['sku']}, Name={$remedy['name']}");
        
        // Redirect to remedies page
        header('Location: ' . $redirectRemedies);
        
    } else {
        $_SESSION['errors'][] = 'Database error: ' . $stmt->error;
        header('Location: ' . $redirectAddRemedy);
    }
    
} catch (Exception $e) {
    $_SESSION['errors'][] = 'Error: ' . $e->getMessage();
    header('Location: ' . $redirectAddRemedy);
}

exit();

// ========== HELPER FUNCTIONS ==========

/**
 * Create a URL-friendly slug from text
 */
function createSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

/**
 * Create a thumbnail image
 */
function createThumbnail($source_path, $dest_path, $width, $height) {
    // Get image info
    $image_info = getimagesize($source_path);
    if (!$image_info) return false;
    
    list($orig_width, $orig_height, $type) = $image_info;
    
    // Create image from source based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    // Create thumbnail canvas
    $thumbnail = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    // Resize image
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
    
    // Save thumbnail based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $dest_path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $dest_path);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $dest_path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumbnail, $dest_path, 85);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return true;
}
?>
