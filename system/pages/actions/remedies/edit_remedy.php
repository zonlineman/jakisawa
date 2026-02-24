<?php
/**
 * EDIT REMEDY - COMPLETE PRODUCTION VERSION
 * Updates all fields including image handling
 * KEEPS ORIGINAL SKU when editing
 */

// Prevent any output before JSON
ob_start();

// Suppress errors
error_reporting(0);
ini_set('display_errors', '0');

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Clear any previous output
if (ob_get_length()) ob_clean();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$role = strtolower((string)($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$isAdmin = in_array($role, ['admin', 'super_admin'], true);

function formatEditVariationPrice($priceRaw) {
    $value = str_ireplace(['ksh', 'kes'], '', (string)$priceRaw);
    $value = str_replace(',', '', $value);
    $value = trim($value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    $numeric = (float)$value;
    if ($numeric <= 0) {
        return null;
    }

    $formatted = number_format($numeric, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function normalizeEditVariationPairs(array $labels, array $prices, $fieldLabel) {
    $lines = [];
    $count = max(count($labels), count($prices));

    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $priceRaw = trim((string)($prices[$i] ?? ''));

        if ($label === '' && $priceRaw === '') {
            continue;
        }

        if ($label === '' || $priceRaw === '') {
            throw new Exception($fieldLabel . ' row ' . ($i + 1) . ' requires both label and price.');
        }

        $price = formatEditVariationPrice($priceRaw);
        if ($price === null) {
            throw new Exception($fieldLabel . ' row ' . ($i + 1) . ' has an invalid price.');
        }

        $lines[] = $label . '|' . $price;
    }

    return implode("\n", $lines);
}

function generateEditSlug($value) {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim((string)$slug, '-');
}

try {
    // Verify POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get remedy ID
    $remedy_id = filter_input(INPUT_POST, 'remedy_id', FILTER_VALIDATE_INT);
    
    if (!$remedy_id || $remedy_id <= 0) {
        throw new Exception('Invalid remedy ID');
    }
    
    // Load database
    $db_path = __DIR__ . '/../../../includes/database.php';
    if (!file_exists($db_path)) {
        throw new Exception('Database configuration not found');
    }
    
    require_once $db_path;
    
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // FIRST, get the existing remedy data including current SKU
    $get_query = "SELECT sku, slug, is_active FROM remedies WHERE id = ?";
    $get_stmt = $conn->prepare($get_query);
    $get_stmt->bind_param('i', $remedy_id);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    $existing_remedy = $get_result->fetch_assoc();
    $get_stmt->close();
    
    if (!$existing_remedy) {
        throw new Exception('Remedy not found');
    }
    
    // Use the existing SKU - DO NOT generate a new one
    $sku = $existing_remedy['sku'];
    $current_is_active = (int)($existing_remedy['is_active'] ?? 0);
    
    // Get and validate form data (SKU is NOT taken from POST - we keep the original)
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $ingredients = trim($_POST['ingredients'] ?? '');
    $usage_instructions = trim($_POST['usage_instructions'] ?? '');
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_meta_description = trim($_POST['seo_meta_description'] ?? '');
    $seo_keywords = trim($_POST['seo_keywords'] ?? '');
    $focus_keyword = trim($_POST['focus_keyword'] ?? '');
    $og_title = trim($_POST['og_title'] ?? '');
    $og_description = trim($_POST['og_description'] ?? '');
    $canonical_url = trim($_POST['canonical_url'] ?? '');
    $target_audience = trim($_POST['target_audience'] ?? '');
    $value_proposition = trim($_POST['value_proposition'] ?? '');
    $customer_pain_points = trim($_POST['customer_pain_points'] ?? '');
    $cta_text = trim($_POST['cta_text'] ?? '');
    $cta_link = trim($_POST['cta_link'] ?? '');
    $faq_q1 = trim($_POST['faq_q1'] ?? '');
    $faq_a1 = trim($_POST['faq_a1'] ?? '');
    $faq_q2 = trim($_POST['faq_q2'] ?? '');
    $faq_a2 = trim($_POST['faq_a2'] ?? '');
    $custom_sizes = trim($_POST['custom_sizes'] ?? '');
    $custom_sachets = trim($_POST['custom_sachets'] ?? '');
    if (isset($_POST['size_label']) || isset($_POST['size_price'])) {
        $sizeLabels = isset($_POST['size_label']) && is_array($_POST['size_label']) ? $_POST['size_label'] : [];
        $sizePrices = isset($_POST['size_price']) && is_array($_POST['size_price']) ? $_POST['size_price'] : [];
        $custom_sizes = normalizeEditVariationPairs($sizeLabels, $sizePrices, 'Custom sizes');
    }
    if (isset($_POST['sachet_label']) || isset($_POST['sachet_price'])) {
        $sachetLabels = isset($_POST['sachet_label']) && is_array($_POST['sachet_label']) ? $_POST['sachet_label'] : [];
        $sachetPrices = isset($_POST['sachet_price']) && is_array($_POST['sachet_price']) ? $_POST['sachet_price'] : [];
        $custom_sachets = normalizeEditVariationPairs($sachetLabels, $sachetPrices, 'Custom sachets');
    }
    $unit_price = filter_input(INPUT_POST, 'unit_price', FILTER_VALIDATE_FLOAT);
    $cost_price = filter_input(INPUT_POST, 'cost_price', FILTER_VALIDATE_FLOAT);
    $discount_price = filter_input(INPUT_POST, 'discount_price', FILTER_VALIDATE_FLOAT);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if (!$isAdmin) {
        $is_active = $current_is_active;
    }
    
    // Validate required fields
    if (empty($name)) {
        throw new Exception('Name is required');
    }

    if ($slug === '') {
        $slug = generateEditSlug($name);
    }
    if ($slug === '') {
        $slug = trim((string)($existing_remedy['slug'] ?? ''));
    }
    
    if (!$category_id || $category_id <= 0) {
        throw new Exception('Category is required');
    }
    
    if ($unit_price === false || $unit_price < 0) {
        throw new Exception('Valid unit price is required');
    }
    
    if ($stock_quantity === false || $stock_quantity < 0) {
        throw new Exception('Valid stock quantity is required');
    }
    
    // Validate reorder level
    if ($reorder_level !== false && $reorder_level > 100000) {
        throw new Exception('Reorder level exceeds maximum allowed');
    }
    
    // Validate price
    if ($unit_price > 1000000) {
        throw new Exception('Unit price exceeds maximum allowed');
    }

    if ($slug !== '') {
        $slugCheckStmt = $conn->prepare("SELECT id FROM remedies WHERE slug = ? AND id <> ? LIMIT 1");
        if ($slugCheckStmt) {
            $slugCheckStmt->bind_param('si', $slug, $remedy_id);
            $slugCheckStmt->execute();
            $slugResult = $slugCheckStmt->get_result();
            if ($slugResult && $slugResult->num_rows > 0) {
                $slugCheckStmt->close();
                throw new Exception('Slug already exists');
            }
            $slugCheckStmt->close();
        }
    }

    // Basic SEO field limits
    if (strlen($seo_title) > 255 || strlen($og_title) > 255 || strlen($focus_keyword) > 255) {
        throw new Exception('SEO title, OG title, and focus keyword must be 255 characters or less');
    }
    
    // Set defaults for optional numeric fields
    if ($supplier_id === false || $supplier_id <= 0) {
        $supplier_id = null;
    }
    
    if ($cost_price === false || $cost_price < 0) {
        $cost_price = null;
    }
    
    if ($discount_price === false || $discount_price < 0) {
        $discount_price = null;
    }
    
    if ($reorder_level === false || $reorder_level < 0) {
        $reorder_level = 10;
    }

    // Ensure variation columns exist on remedies table.
    $sizesColRes = $conn->query("SHOW COLUMNS FROM remedies LIKE 'custom_sizes'");
    $sachetsColRes = $conn->query("SHOW COLUMNS FROM remedies LIKE 'custom_sachets'");
    $hasRemedyCustomSizes = $sizesColRes && $sizesColRes->num_rows > 0;
    $hasRemedyCustomSachets = $sachetsColRes && $sachetsColRes->num_rows > 0;

    if (!$hasRemedyCustomSizes) {
        $conn->query("ALTER TABLE remedies ADD COLUMN custom_sizes TEXT NULL");
    }
    if (!$hasRemedyCustomSachets) {
        $conn->query("ALTER TABLE remedies ADD COLUMN custom_sachets TEXT NULL");
    }

    $project_root = realpath(__DIR__ . '/../../../../');
    if (!$project_root) {
        throw new Exception('Project root not found');
    }

    $resolve_image_file = static function ($stored_path) use ($project_root) {
        $stored_path = trim((string)$stored_path);
        if ($stored_path === '' || preg_match('#^https?://#i', $stored_path)) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $stored_path), '/');
        $candidates = [
            $project_root . '/' . $relative,
            $project_root . '/system/' . $relative
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    };
    
    // Handle image upload
    $image_path = null;
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
    
    // First, get current image path if needed
    if ($remove_image || (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK)) {
        $old_query = "SELECT image_url FROM remedies WHERE id = ?";
        $old_stmt = $conn->prepare($old_query);
        $old_stmt->bind_param('i', $remedy_id);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        $old_image = $old_result->fetch_assoc();
        $old_stmt->close();
    }
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // New image uploaded
        $upload_dir = $project_root . '/systemuploads/products/';
        
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_extensions));
        }
        
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Maximum 5MB allowed.');
        }
        
        $new_filename = 'remedy_' . $remedy_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (@move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = '/systemuploads/products/' . $new_filename;
            
            // Delete old image if exists
            if (!empty($old_image['image_url'])) {
                $old_file = $resolve_image_file($old_image['image_url']);
                if ($old_file && file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
        } else {
            throw new Exception('Failed to upload image');
        }
    } elseif ($remove_image) {
        // Remove existing image
        if (!empty($old_image['image_url'])) {
            $old_file = $resolve_image_file($old_image['image_url']);
            if ($old_file && file_exists($old_file)) {
                @unlink($old_file);
            }
        }
        
        $image_path = '';
    }
    
    // Build UPDATE query - USING EXISTING SKU, not generating new one
    if ($image_path !== null) {
        // Update WITH image change, keeping original SKU
        $query = "UPDATE remedies SET 
            sku = ?,  -- Keep the original SKU
            name = ?,
            slug = ?,
            description = ?,
            category_id = ?,
            supplier_id = ?,
            ingredients = ?,
            usage_instructions = ?,
            unit_price = ?,
            cost_price = ?,
            discount_price = ?,
            stock_quantity = ?,
            reorder_level = ?,
            is_featured = ?,
            is_active = ?,
            image_url = ?,
            updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param(
            'ssssissssddiiissi',
            $sku,              // Original SKU - NOT changed
            $name,
            $slug,
            $description,
            $category_id,
            $supplier_id,
            $ingredients,
            $usage_instructions,
            $unit_price,
            $cost_price,
            $discount_price,
            $stock_quantity,
            $reorder_level,
            $is_featured,
            $is_active,
            $image_path,
            $remedy_id
        );
    } else {
        // Update WITHOUT image change, keeping original SKU
        $query = "UPDATE remedies SET 
            sku = ?,  -- Keep the original SKU
            name = ?,
            slug = ?,
            description = ?,
            category_id = ?,
            supplier_id = ?,
            ingredients = ?,
            usage_instructions = ?,
            unit_price = ?,
            cost_price = ?,
            discount_price = ?,
            stock_quantity = ?,
            reorder_level = ?,
            is_featured = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param(
            'ssssissssddiiiii',
            $sku,              // Original SKU - NOT changed
            $name,
            $slug,
            $description,
            $category_id,
            $supplier_id,
            $ingredients,
            $usage_instructions,
            $unit_price,
            $cost_price,
            $discount_price,
            $stock_quantity,
            $reorder_level,
            $is_featured,
            $is_active,
            $remedy_id
        );
    }
    
    // Execute query
    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    
    $stmt->close();

    // Persist variation definitions directly on remedies table.
    $variationUpdateStmt = $conn->prepare("
        UPDATE remedies
        SET custom_sizes = ?, custom_sachets = ?, updated_at = NOW()
        WHERE id = ?
    ");
    if ($variationUpdateStmt) {
        $variationUpdateStmt->bind_param(
            'ssi',
            $custom_sizes,
            $custom_sachets,
            $remedy_id
        );
        if (!$variationUpdateStmt->execute()) {
            throw new Exception('Variation update failed: ' . $variationUpdateStmt->error);
        }
        $variationUpdateStmt->close();
    }

    // Ensure SEO/marketing table exists
    $createSeoTableSql = "
        CREATE TABLE IF NOT EXISTS remedy_seo_marketing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            remedy_id INT NOT NULL UNIQUE,
            seo_title VARCHAR(255) NULL,
            seo_meta_description TEXT NULL,
            seo_keywords TEXT NULL,
            focus_keyword VARCHAR(255) NULL,
            og_title VARCHAR(255) NULL,
            og_description TEXT NULL,
            canonical_url VARCHAR(255) NULL,
            target_audience VARCHAR(255) NULL,
            value_proposition TEXT NULL,
            customer_pain_points TEXT NULL,
            cta_text VARCHAR(255) NULL,
            cta_link VARCHAR(255) NULL,
            faq_q1 VARCHAR(255) NULL,
            faq_a1 TEXT NULL,
            faq_q2 VARCHAR(255) NULL,
            faq_a2 TEXT NULL,
            custom_sizes TEXT NULL,
            custom_sachets TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_remedy_id (remedy_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($createSeoTableSql);

    // Backfill new columns when table existed before this feature.
    $sizeColRes = $conn->query("SHOW COLUMNS FROM remedy_seo_marketing LIKE 'custom_sizes'");
    $sachetColRes = $conn->query("SHOW COLUMNS FROM remedy_seo_marketing LIKE 'custom_sachets'");
    $customSizesExists = $sizeColRes && $sizeColRes->num_rows > 0;
    $customSachetsExists = $sachetColRes && $sachetColRes->num_rows > 0;

    if (!$customSizesExists) {
        $conn->query("ALTER TABLE remedy_seo_marketing ADD COLUMN custom_sizes TEXT NULL");
    }
    if (!$customSachetsExists) {
        $conn->query("ALTER TABLE remedy_seo_marketing ADD COLUMN custom_sachets TEXT NULL");
    }

    // Save SEO + marketing values
    $seoUpsertSql = "
        INSERT INTO remedy_seo_marketing (
            remedy_id, seo_title, seo_meta_description, seo_keywords, focus_keyword,
            og_title, og_description, canonical_url, target_audience, value_proposition,
            customer_pain_points, cta_text, cta_link, faq_q1, faq_a1, faq_q2, faq_a2,
            custom_sizes, custom_sachets
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
        ON DUPLICATE KEY UPDATE
            seo_title = VALUES(seo_title),
            seo_meta_description = VALUES(seo_meta_description),
            seo_keywords = VALUES(seo_keywords),
            focus_keyword = VALUES(focus_keyword),
            og_title = VALUES(og_title),
            og_description = VALUES(og_description),
            canonical_url = VALUES(canonical_url),
            target_audience = VALUES(target_audience),
            value_proposition = VALUES(value_proposition),
            customer_pain_points = VALUES(customer_pain_points),
            cta_text = VALUES(cta_text),
            cta_link = VALUES(cta_link),
            faq_q1 = VALUES(faq_q1),
            faq_a1 = VALUES(faq_a1),
            faq_q2 = VALUES(faq_q2),
            faq_a2 = VALUES(faq_a2),
            custom_sizes = VALUES(custom_sizes),
            custom_sachets = VALUES(custom_sachets)
    ";

    $seoStmt = $conn->prepare($seoUpsertSql);
    if ($seoStmt) {
        $seoStmt->bind_param(
            'issssssssssssssssss',
            $remedy_id,
            $seo_title,
            $seo_meta_description,
            $seo_keywords,
            $focus_keyword,
            $og_title,
            $og_description,
            $canonical_url,
            $target_audience,
            $value_proposition,
            $customer_pain_points,
            $cta_text,
            $cta_link,
            $faq_q1,
            $faq_a1,
            $faq_q2,
            $faq_a2,
            $custom_sizes,
            $custom_sachets
        );
        $seoStmt->execute();
        $seoStmt->close();
    }

    $conn->close();
    
    // Clear buffer and send success
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Remedy updated successfully',
        'sku' => $sku,  // Return the original SKU
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    // Clear buffer
    if (ob_get_length()) ob_end_clean();
    
    // Log error for debugging
    error_log('Edit Remedy Error: ' . $e->getMessage());
    
    // Send error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
