<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (optional, uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../login.php');
//     exit;
// }

// Define root path
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/audit_helper.php';
require_once ROOT_PATH . '/includes/config.php';

// Define upload path and public URL for remedy images
$projectRoot = dirname(ROOT_PATH);
$uploadRootDir = $projectRoot . '/systemuploads/';
$uploadDir = $uploadRootDir . 'products/';
$webUploadPath = '/systemuploads/products/';

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadRootDir, 0755, true);
    mkdir($uploadDir, 0755, true);
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Function to get table columns
function getTableColumns($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("DESCRIBE $tableName");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_flip($columns); // Return as associative array for easy checking
    } catch (Exception $e) {
        error_log("Error getting table columns: " . $e->getMessage());
        return [];
    }
}

function ensureSeoMarketingTable(PDO $pdo): void {
    $pdo->exec("
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
    ");

    // Backfill new columns for existing installs where table already existed.
    $columnsToEnsure = [
        'custom_sizes' => "ALTER TABLE remedy_seo_marketing ADD COLUMN custom_sizes TEXT NULL",
        'custom_sachets' => "ALTER TABLE remedy_seo_marketing ADD COLUMN custom_sachets TEXT NULL"
    ];

    foreach ($columnsToEnsure as $columnName => $alterSql) {
        $colStmt = $pdo->prepare("SHOW COLUMNS FROM remedy_seo_marketing LIKE ?");
        $colStmt->execute([$columnName]);
        if (!$colStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec($alterSql);
        }
    }
}

function ensureRemediesVariationColumns(PDO $pdo): void {
    $columnsToEnsure = [
        'custom_sizes' => "ALTER TABLE remedies ADD COLUMN custom_sizes TEXT NULL",
        'custom_sachets' => "ALTER TABLE remedies ADD COLUMN custom_sachets TEXT NULL"
    ];

    foreach ($columnsToEnsure as $columnName => $alterSql) {
        $colStmt = $pdo->prepare("SHOW COLUMNS FROM remedies LIKE ?");
        $colStmt->execute([$columnName]);
        if (!$colStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec($alterSql);
        }
    }

    // Backfill variation values from auxiliary table when available.
    try {
        $seoTableExistsStmt = $pdo->query("SHOW TABLES LIKE 'remedy_seo_marketing'");
        if (!(bool)$seoTableExistsStmt->fetchColumn()) {
            return;
        }

        $seoColumns = [];
        $seoColsStmt = $pdo->query("SHOW COLUMNS FROM remedy_seo_marketing");
        $seoColRows = $seoColsStmt ? $seoColsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($seoColRows as $seoCol) {
            $fieldName = strtolower(trim((string)($seoCol['Field'] ?? '')));
            if ($fieldName !== '') {
                $seoColumns[$fieldName] = true;
            }
        }

        if (!empty($seoColumns['custom_sizes'])) {
            $pdo->exec("
                UPDATE remedies r
                LEFT JOIN remedy_seo_marketing m ON m.remedy_id = r.id
                SET r.custom_sizes = COALESCE(NULLIF(r.custom_sizes, ''), m.custom_sizes)
            ");
        }

        if (!empty($seoColumns['custom_sachets'])) {
            $pdo->exec("
                UPDATE remedies r
                LEFT JOIN remedy_seo_marketing m ON m.remedy_id = r.id
                SET r.custom_sachets = COALESCE(NULLIF(r.custom_sachets, ''), m.custom_sachets)
            ");
        }
    } catch (Exception $e) {
        error_log('Variation backfill skipped: ' . $e->getMessage());
    }
}

// Get remedies table structure
ensureSeoMarketingTable($pdo);
ensureRemediesVariationColumns($pdo);
$remediesColumns = getTableColumns($pdo, 'remedies');

// Initialize variables
$errors = [];
$success = false;
$formData = [
    'sku' => '',
    'name' => '',
    'slug' => '',
    'category_id' => '',
    'supplier_id' => '',
    'description' => '',
    'ingredients' => '',
    'usage_instructions' => '',
    'seo_title' => '',
    'seo_meta_description' => '',
    'seo_keywords' => '',
    'focus_keyword' => '',
    'og_title' => '',
    'og_description' => '',
    'canonical_url' => '',
    'target_audience' => '',
    'value_proposition' => '',
    'customer_pain_points' => '',
    'cta_text' => '',
    'cta_link' => '',
    'faq_q1' => '',
    'faq_a1' => '',
    'faq_q2' => '',
    'faq_a2' => '',
    'custom_sizes' => '',
    'custom_sachets' => '',
    'unit_price' => '',
    'cost_price' => '',
    'discount_price' => '',
    'stock_quantity' => '',
    'reorder_level' => '10',
    'is_featured' => 0,
    'is_active' => 1
];
$customSizeRows = [['label' => '', 'price' => '']];
$customSachetRows = [['label' => '', 'price' => '']];

// Function to generate slug
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return rtrim($slug, '-');
}

// Function to generate SKU
function generateSKU($supplierId, $pdo) {
    if (!$supplierId) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT UPPER(LEFT(name, 3)) as code FROM suppliers WHERE id = ?");
        $stmt->execute([$supplierId]);
        $supplierCode = $stmt->fetchColumn();
        
        if (!$supplierCode) $supplierCode = 'SUP';
        
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM remedies WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $sequence = str_pad($stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);
        
        $yearMonth = date('ym');
        return $supplierCode . '-' . $yearMonth . '-' . $sequence;
    } catch (Exception $e) {
        error_log("Error generating SKU: " . $e->getMessage());
        return null;
    }
}

function trimInputRecursively($value) {
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = trimInputRecursively($item);
        }
        return $result;
    }

    return trim((string)$value);
}

function formatVariationPriceForStorage($priceRaw) {
    $price = (float)$priceRaw;
    $formatted = number_format($price, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function buildVariationRowsFromSeparateInputs(array $labels, array $prices) {
    $rows = [];
    $count = max(count($labels), count($prices));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $price = trim((string)($prices[$i] ?? ''));
        if ($label === '' && $price === '') {
            continue;
        }
        $rows[] = ['label' => $label, 'price' => $price];
    }

    if (empty($rows)) {
        $rows[] = ['label' => '', 'price' => ''];
    }

    return $rows;
}

function normalizeVariationPairs(array $labels, array $prices, $fieldLabel, array &$errors, $errorKey) {
    $normalized = [];
    $count = max(count($labels), count($prices));

    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $priceRaw = trim((string)($prices[$i] ?? ''));

        if ($label === '' && $priceRaw === '') {
            continue;
        }

        if ($label === '') {
            $errors[$errorKey] = sprintf('%s row %d is missing a label.', $fieldLabel, $i + 1);
            return '';
        }
        if ($priceRaw === '') {
            $errors[$errorKey] = sprintf('%s row %d is missing a price.', $fieldLabel, $i + 1);
            return '';
        }

        $priceRaw = str_ireplace(['ksh', 'kes'], '', $priceRaw);
        $priceRaw = str_replace(',', '', $priceRaw);

        if (!is_numeric($priceRaw)) {
            $errors[$errorKey] = sprintf('%s row %d has an invalid numeric price.', $fieldLabel, $i + 1);
            return '';
        }

        $price = (float)$priceRaw;
        if ($price <= 0) {
            $errors[$errorKey] = sprintf('%s row %d price must be greater than 0.', $fieldLabel, $i + 1);
            return '';
        }

        $normalized[] = $label . '|' . formatVariationPriceForStorage($priceRaw);
    }

    return implode("\n", $normalized);
}

function parseVariationLineWithPrice($line) {
    $line = trim((string)$line);
    if ($line === '') {
        return ['skip' => true];
    }

    $label = '';
    $priceRaw = '';

    if (strpos($line, '|') !== false) {
        $parts = explode('|', $line, 2);
        $label = trim((string)$parts[0]);
        $priceRaw = trim((string)$parts[1]);
    } elseif (preg_match('/^(.*?)\s*[:=-]\s*(?:ksh|kes)?\s*([0-9]+(?:\.[0-9]{1,2})?)\s*(?:ksh|kes)?$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $priceRaw = trim((string)$matches[2]);
    } elseif (preg_match('/^(.*\S)\s+(?:ksh|kes)\s*([0-9]+(?:\.[0-9]{1,2})?)$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $priceRaw = trim((string)$matches[2]);
    } elseif (preg_match('/^(.*\S)\s+([0-9]+(?:\.[0-9]{1,2})?)\s*(?:ksh|kes)?$/i', $line, $matches)) {
        $label = trim((string)$matches[1]);
        $priceRaw = trim((string)$matches[2]);
    } else {
        return ['error' => 'Use "Label|Price" (or "Label Price", e.g. "1kg 200ksh")'];
    }

    $priceRaw = str_ireplace(['ksh', 'kes'], '', $priceRaw);
    $priceRaw = str_replace(',', '', $priceRaw);

    if ($label === '') {
        return ['error' => 'Variation label is required'];
    }
    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        return ['error' => 'Variation price must be numeric'];
    }

    $price = (float)$priceRaw;
    if ($price <= 0) {
        return ['error' => 'Variation price must be greater than 0'];
    }

    return [
        'label' => $label,
        'price' => number_format($price, 2, '.', '')
    ];
}

function normalizeVariationText($rawText, $fieldLabel, array &$errors, $errorKey) {
    $rawText = trim((string)$rawText);
    if ($rawText === '') {
        return '';
    }

    $normalized = [];
    $lines = preg_split('/\r\n|\r|\n/', $rawText);

    foreach ($lines as $lineNumber => $line) {
        $parsed = parseVariationLineWithPrice($line);
        if (!empty($parsed['skip'])) {
            continue;
        }
        if (!empty($parsed['error'])) {
            $errors[$errorKey] = sprintf(
                '%s line %d is invalid: %s.',
                $fieldLabel,
                $lineNumber + 1,
                $parsed['error']
            );
            return $rawText;
        }

        $normalized[] = $parsed['label'] . '|' . $parsed['price'];
    }

    return implode("\n", $normalized);
}

function getVariationRowsFromNormalizedText($rawText) {
    $rows = [];
    $rawText = trim((string)$rawText);
    if ($rawText !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $rawText);
        foreach ($lines as $line) {
            $parsed = parseVariationLineWithPrice($line);
            if (!empty($parsed['skip']) || !empty($parsed['error'])) {
                continue;
            }
            $rows[] = [
                'label' => (string)$parsed['label'],
                'price' => formatVariationPriceForStorage($parsed['price'])
            ];
        }
    }

    if (empty($rows)) {
        $rows[] = ['label' => '', 'price' => ''];
    }

    return $rows;
}

// Fetch categories and suppliers
try {
    $categories = $pdo->query("SELECT id, name, color FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
    $suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $suppliers = [];
    error_log("Error fetching categories/suppliers: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $rawPost = trimInputRecursively($_POST);
    foreach ($formData as $fieldName => $defaultValue) {
        if (array_key_exists($fieldName, $rawPost) && !is_array($rawPost[$fieldName])) {
            $formData[$fieldName] = (string)$rawPost[$fieldName];
        }
    }

    $sizeLabels = isset($rawPost['size_label']) && is_array($rawPost['size_label']) ? $rawPost['size_label'] : [];
    $sizePrices = isset($rawPost['size_price']) && is_array($rawPost['size_price']) ? $rawPost['size_price'] : [];
    $sachetLabels = isset($rawPost['sachet_label']) && is_array($rawPost['sachet_label']) ? $rawPost['sachet_label'] : [];
    $sachetPrices = isset($rawPost['sachet_price']) && is_array($rawPost['sachet_price']) ? $rawPost['sachet_price'] : [];

    $customSizeRows = buildVariationRowsFromSeparateInputs($sizeLabels, $sizePrices);
    $customSachetRows = buildVariationRowsFromSeparateInputs($sachetLabels, $sachetPrices);

    $hasSizeRows = !empty($sizeLabels) || !empty($sizePrices);
    $hasSachetRows = !empty($sachetLabels) || !empty($sachetPrices);

    if ($hasSizeRows) {
        $formData['custom_sizes'] = normalizeVariationPairs(
            $sizeLabels,
            $sizePrices,
            'Custom remedy sizes',
            $errors,
            'custom_sizes'
        );
    } else {
        $formData['custom_sizes'] = normalizeVariationText(
            $formData['custom_sizes'] ?? '',
            'Custom remedy sizes',
            $errors,
            'custom_sizes'
        );
        $customSizeRows = getVariationRowsFromNormalizedText($formData['custom_sizes']);
    }

    if ($hasSachetRows) {
        $formData['custom_sachets'] = normalizeVariationPairs(
            $sachetLabels,
            $sachetPrices,
            'Custom sachet options',
            $errors,
            'custom_sachets'
        );
    } else {
        $formData['custom_sachets'] = normalizeVariationText(
            $formData['custom_sachets'] ?? '',
            'Custom sachet options',
            $errors,
            'custom_sachets'
        );
        $customSachetRows = getVariationRowsFromNormalizedText($formData['custom_sachets']);
    }
    
    // Validate required fields
    $required = ['name', 'category_id', 'unit_price'];
    foreach ($required as $field) {
        if (empty($formData[$field])) {
            $errors[$field] = "This field is required";
        }
    }
    
    // Generate SKU if not provided but supplier is selected
    if (empty($formData['sku']) && !empty($formData['supplier_id'])) {
        $formData['sku'] = generateSKU($formData['supplier_id'], $pdo);
        if (!$formData['sku']) {
            $errors['sku'] = "Could not generate SKU. Please enter manually.";
        }
    } elseif (empty($formData['sku']) && empty($formData['supplier_id'])) {
        $errors['sku'] = "SKU cannot be auto-generated without a supplier. Please enter SKU manually.";
    }
    
    // Validate SKU uniqueness if provided
    if (!empty($formData['sku'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM remedies WHERE sku = ?");
            $stmt->execute([$formData['sku']]);
            if ($stmt->fetchColumn() > 0) {
                $errors['sku'] = "SKU already exists";
            }
        } catch (Exception $e) {
            $errors['sku'] = "Error checking SKU uniqueness";
        }
    }
    
    // Validate numeric fields
    $numericFields = ['unit_price', 'cost_price', 'discount_price', 'stock_quantity', 'reorder_level'];
    foreach ($numericFields as $field) {
        if (!empty($formData[$field]) && !is_numeric($formData[$field])) {
            $errors[$field] = "Must be a valid number";
        }
    }
    
    // Auto-generate slug if empty
    if (empty($formData['slug']) && !empty($formData['name'])) {
        $formData['slug'] = generateSlug($formData['name']);
    }
    
    // Validate slug uniqueness
    if (!empty($formData['slug'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM remedies WHERE slug = ?");
            $stmt->execute([$formData['slug']]);
            if ($stmt->fetchColumn() > 0) {
                $errors['slug'] = "Slug already exists";
            }
        } catch (Exception $e) {
            $errors['slug'] = "Error checking slug uniqueness";
        }
    }
    
    // Handle file upload
    $image_url = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileName = $_FILES['image']['name'];
        $fileTmpName = $_FILES['image']['tmp_name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];
        
        // Check file size
        if ($fileSize > $maxSize) {
            $errors['image'] = "Image size exceeds 5MB limit.";
        } else {
            // Get actual MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $fileMimeType = finfo_file($finfo, $fileTmpName);
            finfo_close($finfo);
            
            // Check if file type is allowed
            if (!array_key_exists($fileMimeType, $allowedTypes)) {
                $errors['image'] = "Invalid image type. Only JPG, PNG, GIF, and WebP are allowed.";
            } else {
                // Get file extension
                $fileExtension = $allowedTypes[$fileMimeType];
                
                // Generate unique filename
                $newFileName = uniqid('remedy_', true) . '.' . $fileExtension;
                
                // Destination path
                $destination = $uploadDir . $newFileName;
                
                // Check if upload directory is writable
                if (!is_writable($uploadDir)) {
                    $errors['image'] = "Upload directory is not writable. Please check permissions.";
                } elseif (move_uploaded_file($fileTmpName, $destination)) {
                    // Store relative web path
                    $image_url = $webUploadPath . $newFileName;
                } else {
                    $errors['image'] = "Failed to upload image. Error code: " . $_FILES['image']['error'];
                }
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorCode = $_FILES['image']['error'];
        $errors['image'] = $uploadErrors[$errorCode] ?? 'Unknown upload error';
    }
    
    // Convert checkbox values
    $formData['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $formData['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Build SQL query based on actual table structure
            $columns = [];
            $placeholders = [];
            $values = [];
            
            // Check each field against actual table columns
            $possibleFields = [
                'sku' => !empty($formData['sku']) ? $formData['sku'] : null,
                'name' => !empty($formData['name']) ? $formData['name'] : null,
                'slug' => !empty($formData['slug']) ? $formData['slug'] : null,
                'category_id' => !empty($formData['category_id']) ? $formData['category_id'] : null,
                'supplier_id' => !empty($formData['supplier_id']) ? $formData['supplier_id'] : null,
                'description' => isset($remediesColumns['description']) ? (!empty($formData['description']) ? $formData['description'] : null) : null,
                'image_url' => $image_url ?: null,
                'ingredients' => isset($remediesColumns['ingredients']) ? (!empty($formData['ingredients']) ? $formData['ingredients'] : null) : null,
                'usage_instructions' => isset($remediesColumns['usage_instructions']) ? (!empty($formData['usage_instructions']) ? $formData['usage_instructions'] : null) : null,
                'custom_sizes' => isset($remediesColumns['custom_sizes']) ? (!empty($formData['custom_sizes']) ? $formData['custom_sizes'] : null) : null,
                'custom_sachets' => isset($remediesColumns['custom_sachets']) ? (!empty($formData['custom_sachets']) ? $formData['custom_sachets'] : null) : null,
                'seo_title' => isset($remediesColumns['seo_title']) ? (!empty($formData['seo_title']) ? $formData['seo_title'] : null) : null,
                'seo_meta_description' => isset($remediesColumns['seo_meta_description']) ? (!empty($formData['seo_meta_description']) ? $formData['seo_meta_description'] : null) : null,
                'seo_keywords' => isset($remediesColumns['seo_keywords']) ? (!empty($formData['seo_keywords']) ? $formData['seo_keywords'] : null) : null,
                'focus_keyword' => isset($remediesColumns['focus_keyword']) ? (!empty($formData['focus_keyword']) ? $formData['focus_keyword'] : null) : null,
                'og_title' => isset($remediesColumns['og_title']) ? (!empty($formData['og_title']) ? $formData['og_title'] : null) : null,
                'og_description' => isset($remediesColumns['og_description']) ? (!empty($formData['og_description']) ? $formData['og_description'] : null) : null,
                'canonical_url' => isset($remediesColumns['canonical_url']) ? (!empty($formData['canonical_url']) ? $formData['canonical_url'] : null) : null,
                'target_audience' => isset($remediesColumns['target_audience']) ? (!empty($formData['target_audience']) ? $formData['target_audience'] : null) : null,
                'value_proposition' => isset($remediesColumns['value_proposition']) ? (!empty($formData['value_proposition']) ? $formData['value_proposition'] : null) : null,
                'customer_pain_points' => isset($remediesColumns['customer_pain_points']) ? (!empty($formData['customer_pain_points']) ? $formData['customer_pain_points'] : null) : null,
                'cta_text' => isset($remediesColumns['cta_text']) ? (!empty($formData['cta_text']) ? $formData['cta_text'] : null) : null,
                'cta_link' => isset($remediesColumns['cta_link']) ? (!empty($formData['cta_link']) ? $formData['cta_link'] : null) : null,
                'unit_price' => !empty($formData['unit_price']) ? $formData['unit_price'] : 0,
                'cost_price' => isset($remediesColumns['cost_price']) ? (!empty($formData['cost_price']) ? $formData['cost_price'] : null) : null,
                'discount_price' => isset($remediesColumns['discount_price']) ? (!empty($formData['discount_price']) ? $formData['discount_price'] : null) : null,
                'stock_quantity' => isset($remediesColumns['stock_quantity']) ? (!empty($formData['stock_quantity']) ? $formData['stock_quantity'] : 0) : 0,
                'reorder_level' => isset($remediesColumns['reorder_level']) ? (!empty($formData['reorder_level']) ? $formData['reorder_level'] : 10) : 10,
                'is_featured' => $formData['is_featured'],
                'is_active' => $formData['is_active']
            ];
            
            foreach ($possibleFields as $column => $value) {
                if (isset($remediesColumns[$column])) {
                    $columns[] = $column;
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }
            
            if (empty($columns)) {
                throw new Exception("No valid columns found for insertion");
            }
            
            $sql = "INSERT INTO remedies (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            $remedyId = $pdo->lastInsertId();

            // Persist SEO + marketing fields in auxiliary table (works even if remedies table lacks these columns)
            $seoStmt = $pdo->prepare("
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
            ");
            $seoStmt->execute([
                $remedyId,
                $formData['seo_title'] ?: null,
                $formData['seo_meta_description'] ?: null,
                $formData['seo_keywords'] ?: null,
                $formData['focus_keyword'] ?: null,
                $formData['og_title'] ?: null,
                $formData['og_description'] ?: null,
                $formData['canonical_url'] ?: null,
                $formData['target_audience'] ?: null,
                $formData['value_proposition'] ?: null,
                $formData['customer_pain_points'] ?: null,
                $formData['cta_text'] ?: null,
                $formData['cta_link'] ?: null,
                $formData['faq_q1'] ?: null,
                $formData['faq_a1'] ?: null,
                $formData['faq_q2'] ?: null,
                $formData['faq_a2'] ?: null,
                $formData['custom_sizes'] ?: null,
                $formData['custom_sachets'] ?: null
            ]);
            
            // Log the action (if audit_log table exists)
            try {
                $userId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
                $userId = $userId > 0 ? $userId : null;
                $description = "Created remedy: {$formData['name']} (SKU: {$formData['sku']})";
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                auditLogPdo($pdo, 'remedy_created', 'remedies', $remedyId, null, $description, $userId, $ipAddress);
            } catch (Exception $e) {
                // If audit_log doesn't exist, just continue
                error_log("Note: Audit log not saved - " . $e->getMessage());
            }
            
            $pdo->commit();
            
            // Redirect to remedies list. This page can be included after dashboard HTML has started.
            $_SESSION['success_message'] = "Remedy added successfully!";
            $redirectUrl = (defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/admin_dashboard.php?page=remedies&success=1';
            if (!headers_sent()) {
                header('Location: ' . $redirectUrl, true, 303);
                exit;
            }

            echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url='
                . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8')
                . '"></noscript>';
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['database'] = "Error saving remedy: " . $e->getMessage();
            error_log("Add remedy error: " . $e->getMessage());
        }
    }
}

if (empty($customSizeRows)) {
    $customSizeRows = getVariationRowsFromNormalizedText($formData['custom_sizes']);
}
if (empty($customSachetRows)) {
    $customSachetRows = getVariationRowsFromNormalizedText($formData['custom_sachets']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Remedy - JAKISAWA Herbal System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary-color: #6c757d;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container-fluid.py-4 {
            max-width: 1400px;
            margin: 0 auto;
        }

        .col-lg-10.mx-auto {
            width: 100%;
            max-width: 1180px;
        }

        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-header .btn {
            white-space: nowrap;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
        }

        .input-group > .form-control {
            min-width: 0;
        }

        .input-group-text {
            min-width: 48px;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #0d3c11);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        #remedyTabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.25rem;
            margin-bottom: 1rem !important;
        }

        #remedyTabs .nav-item {
            flex: 1 1 0;
            margin: 0;
        }

        #remedyTabs .nav-link {
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            text-align: center;
            border: 1px solid transparent;
            border-radius: 10px;
            color: var(--secondary-color);
            font-weight: 500;
            padding: 0.7rem 0.85rem;
            line-height: 1.2;
            transition: all 0.2s ease;
        }

        #remedyTabs .nav-link i {
            margin-right: 0 !important;
            flex: 0 0 auto;
        }

        #remedyTabs .nav-link:hover {
            color: var(--primary-color);
            background: #f8faf8;
        }
        
        #remedyTabs .nav-link.active {
            color: var(--primary-color);
            border-color: rgba(46, 125, 50, 0.25);
            background: rgba(46, 125, 50, 0.08);
        }

        .tab-content {
            padding-top: 0.5rem;
        }

        .tab-pane .row {
            row-gap: 0.25rem;
        }

        .tab-pane .mb-3 {
            margin-bottom: 1rem !important;
        }
        
        .badge-category {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 10px;
            display: none;
            margin-top: 10px;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: var(--secondary-color);
            margin-top: 0.25rem;
        }

        .variation-rows {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .variation-row {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem;
            background: #fff;
        }

        .variation-row .form-label.small {
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .variation-row .btn {
            white-space: nowrap;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .sticky-actions .d-flex {
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .form-check.form-switch {
            min-height: 76px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-check.form-switch .form-check-label {
            font-weight: 600;
            margin-left: 0.15rem;
        }

        .form-check.form-switch .form-check-input {
            margin-top: 0.2rem;
        }
        
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 992px) {
            .container-fluid.py-4 {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            .top-bar {
                padding: 1rem;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .top-bar .btn {
                width: 100%;
            }

            #remedyTabs {
                flex-wrap: wrap;
            }

            #remedyTabs .nav-item {
                flex: 1 1 calc(50% - 0.5rem);
            }

            #remedyTabs .nav-link {
                justify-content: flex-start;
                text-align: left;
                font-size: 0.92rem;
            }

            .sticky-actions {
                position: static;
                border-radius: 0 0 12px 12px;
            }

            .sticky-actions .d-flex > div {
                flex: 1 1 100%;
            }

            .sticky-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .container-fluid.py-4 {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            .card-body {
                padding: 1rem;
            }

            .form-control,
            .form-select,
            .btn {
                font-size: 0.95rem;
            }

            .preview-image {
                max-width: 100%;
            }

            .alert-notification {
                top: 0.5rem;
                right: 0.5rem;
                min-width: 0;
                width: calc(100vw - 1rem);
            }

            #remedyTabs {
                gap: 0.4rem;
            }

            #remedyTabs .nav-item {
                flex: 1 1 calc(50% - 0.4rem);
            }

            #remedyTabs .nav-link {
                justify-content: center;
                text-align: center;
                font-size: 0.86rem;
                padding: 0.55rem 0.5rem;
                white-space: normal;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Database Error -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> <?php echo htmlspecialchars($errors['database']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- General Errors -->
                <?php if (!empty($errors) && !isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $field => $error): 
                                if ($field !== 'database'): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endif;
                            endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="top-bar">
                    <h1 class="page-title">
                        <i class="fas fa-plus-circle"></i>
                        Add New Remedy
                    </h1>
                    <div class="btn-toolbar">
                        <a href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/admin_dashboard.php?page=inventory', ENT_QUOTES); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Inventory
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4" id="remedyTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                    <i class="fas fa-info-circle me-2"></i>Basic Info
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                                    <i class="fas fa-list-alt me-2"></i>Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pricing-tab" data-bs-toggle="tab" data-bs-target="#pricing" type="button" role="tab">
                                    <i class="fas fa-tag me-2"></i>Pricing & Stock
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button" role="tab">
                                    <i class="fas fa-chart-line me-2"></i>SEO & Marketing
                                </button>
                            </li>
                        </ul>
                        
                        <form id="addRemedyForm" method="POST" enctype="multipart/form-data" novalidate>
                            <div class="tab-content" id="remedyTabsContent">
                                <!-- Basic Info Tab -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="sku" class="form-label">SKU Code</label>
                                            <input type="text" class="form-control <?php echo isset($errors['sku']) ? 'is-invalid' : ''; ?>" 
                                                   id="sku" name="sku" value="<?php echo htmlspecialchars($formData['sku']); ?>" 
                                                   placeholder="Auto-generated when supplier is selected">
                                            <?php if (isset($errors['sku'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['sku']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">
                                                Format: SUP-YYMM-001 (Auto-generated when supplier is selected)
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label required">Remedy Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                                   id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" 
                                                   placeholder="e.g., Ginger Tea" required>
                                            <?php if (isset($errors['name'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="slug" class="form-label">URL Slug</label>
                                            <input type="text" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>" 
                                                   id="slug" name="slug" value="<?php echo htmlspecialchars($formData['slug']); ?>" 
                                                   placeholder="Auto-generated from name">
                                            <?php if (isset($errors['slug'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['slug']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">Leave blank to auto-generate</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label required">Category</label>
                                            <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" 
                                                    id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" 
                                                            <?php echo ($formData['category_id'] == $category['id']) ? 'selected' : ''; ?>
                                                            data-color="<?php echo $category['color']; ?>">
                                                        <span class="badge-category" style="background-color: <?php echo $category['color']; ?>"></span>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['category_id'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['category_id']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="supplier_id" class="form-label">Supplier</label>
                                            <select class="form-select <?php echo isset($errors['supplier_id']) ? 'is-invalid' : ''; ?>" 
                                                    id="supplier_id" name="supplier_id">
                                                <option value="">Select Supplier</option>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?php echo $supplier['id']; ?>" 
                                                            <?php echo ($formData['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['supplier_id'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['supplier_id']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">Required for SKU auto-generation</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="image" class="form-label">Product Image</label>
                                            <input type="file" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" 
                                                   id="image" name="image" accept="image/*">
                                            <?php if (isset($errors['image'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['image']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">Max 5MB. JPG, PNG, GIF, WebP allowed</div>
                                            <div class="mt-2">
                                                <img id="imagePreview" class="preview-image" alt="Image preview">
                                                <button type="button" id="removeImage" class="btn btn-sm btn-outline-danger d-none mt-2">
                                                    <i class="fas fa-times me-1"></i>Remove Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($remediesColumns['description'])): ?>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description"
                                                  rows="3" placeholder="Enter remedy description"><?php
                                            echo htmlspecialchars($formData['description']);
                                        ?></textarea>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Details Tab -->
                                <div class="tab-pane fade" id="details" role="tabpanel">
                                    <?php if (isset($remediesColumns['ingredients'])): ?>
                                    <div class="mb-3">
                                        <label for="ingredients" class="form-label">Ingredients</label>
                                        <textarea class="form-control" id="ingredients" name="ingredients" 
                                                  rows="4" placeholder="List the ingredients (one per line)"><?php echo htmlspecialchars($formData['ingredients']); ?></textarea>
                                        <div class="help-text">List each ingredient on a new line</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($remediesColumns['usage_instructions'])): ?>
                                    <div class="mb-3">
                                        <label for="usage_instructions" class="form-label">Usage Instructions</label>
                                        <textarea class="form-control" id="usage_instructions" name="usage_instructions" 
                                                  rows="4" placeholder="How to use this remedy"><?php echo htmlspecialchars($formData['usage_instructions']); ?></textarea>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Pricing & Stock Tab -->
                                <div class="tab-pane fade" id="pricing" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="unit_price" class="form-label required">Unit Price (KSh)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">KSh</span>
                                                <input type="number" class="form-control <?php echo isset($errors['unit_price']) ? 'is-invalid' : ''; ?>" 
                                                       id="unit_price" name="unit_price" 
                                                       value="<?php echo htmlspecialchars($formData['unit_price']); ?>" 
                                                       min="0" step="0.01" required>
                                            </div>
                                            <?php if (isset($errors['unit_price'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['unit_price']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isset($remediesColumns['cost_price'])): ?>
                                        <div class="col-md-4 mb-3">
                                            <label for="cost_price" class="form-label">Cost Price (KSh)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">KSh</span>
                                                <input type="number" class="form-control <?php echo isset($errors['cost_price']) ? 'is-invalid' : ''; ?>" 
                                                       id="cost_price" name="cost_price" 
                                                       value="<?php echo htmlspecialchars($formData['cost_price']); ?>" 
                                                       min="0" step="0.01">
                                            </div>
                                            <?php if (isset($errors['cost_price'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['cost_price']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($remediesColumns['discount_price'])): ?>
                                        <div class="col-md-4 mb-3">
                                            <label for="discount_price" class="form-label">Discount Price (KSh)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">KSh</span>
                                                <input type="number" class="form-control <?php echo isset($errors['discount_price']) ? 'is-invalid' : ''; ?>" 
                                                       id="discount_price" name="discount_price" 
                                                       value="<?php echo htmlspecialchars($formData['discount_price']); ?>" 
                                                       min="0" step="0.01">
                                            </div>
                                            <?php if (isset($errors['discount_price'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['discount_price']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Custom Remedy Sizes</label>
                                            <input type="hidden" id="custom_sizes" name="custom_sizes" value="<?php echo htmlspecialchars($formData['custom_sizes']); ?>">
                                            <div id="sizeRows" class="variation-rows">
                                                <?php foreach ($customSizeRows as $sizeRow): ?>
                                                    <div class="variation-row row g-2 align-items-end">
                                                        <div class="col-md-6">
                                                            <label class="form-label small text-muted">Size Label</label>
                                                            <input type="text"
                                                                   class="form-control variation-label <?php echo isset($errors['custom_sizes']) ? 'is-invalid' : ''; ?>"
                                                                   name="size_label[]"
                                                                   placeholder="e.g. 1kg"
                                                                   value="<?php echo htmlspecialchars((string)($sizeRow['label'] ?? '')); ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small text-muted">Price (KSh)</label>
                                                            <input type="number"
                                                                   class="form-control variation-price <?php echo isset($errors['custom_sizes']) ? 'is-invalid' : ''; ?>"
                                                                   name="size_price[]"
                                                                   min="0"
                                                                   step="0.01"
                                                                   placeholder="e.g. 200"
                                                                   value="<?php echo htmlspecialchars((string)($sizeRow['price'] ?? '')); ?>">
                                                        </div>
                                                        <div class="col-md-2 d-grid">
                                                            <button type="button" class="btn btn-outline-danger remove-variation-row">Remove</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-variation-row" data-kind="size" data-target="sizeRows">Add Size Option</button>
                                            <div class="help-text">Each row is separate. It is saved as <code>Size|Price</code> (example: <code>1kg|200</code>).</div>
                                            <?php if (isset($errors['custom_sizes'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['custom_sizes']); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Custom Sachet Options</label>
                                            <input type="hidden" id="custom_sachets" name="custom_sachets" value="<?php echo htmlspecialchars($formData['custom_sachets']); ?>">
                                            <div id="sachetRows" class="variation-rows">
                                                <?php foreach ($customSachetRows as $sachetRow): ?>
                                                    <div class="variation-row row g-2 align-items-end">
                                                        <div class="col-md-6">
                                                            <label class="form-label small text-muted">Option Label</label>
                                                            <input type="text"
                                                                   class="form-control variation-label <?php echo isset($errors['custom_sachets']) ? 'is-invalid' : ''; ?>"
                                                                   name="sachet_label[]"
                                                                   placeholder="e.g. 250g"
                                                                   value="<?php echo htmlspecialchars((string)($sachetRow['label'] ?? '')); ?>">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small text-muted">Price (KSh)</label>
                                                            <input type="number"
                                                                   class="form-control variation-price <?php echo isset($errors['custom_sachets']) ? 'is-invalid' : ''; ?>"
                                                                   name="sachet_price[]"
                                                                   min="0"
                                                                   step="0.01"
                                                                   placeholder="e.g. 50"
                                                                   value="<?php echo htmlspecialchars((string)($sachetRow['price'] ?? '')); ?>">
                                                        </div>
                                                        <div class="col-md-2 d-grid">
                                                            <button type="button" class="btn btn-outline-danger remove-variation-row">Remove</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-variation-row" data-kind="sachet" data-target="sachetRows">Add Sachet Option</button>
                                            <div class="help-text">Each row is separate. It is saved as <code>Label|Price</code> for customer-side pricing.</div>
                                            <?php if (isset($errors['custom_sachets'])): ?>
                                                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['custom_sachets']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <?php if (isset($remediesColumns['stock_quantity'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                            <input type="number" class="form-control <?php echo isset($errors['stock_quantity']) ? 'is-invalid' : ''; ?>" 
                                                   id="stock_quantity" name="stock_quantity" 
                                                   value="<?php echo htmlspecialchars($formData['stock_quantity']); ?>" 
                                                   min="0" step="0.001">
                                            <?php if (isset($errors['stock_quantity'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['stock_quantity']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">Use decimal values if needed (e.g., 0.5 for half unit)</div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($remediesColumns['reorder_level'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <label for="reorder_level" class="form-label">Reorder Level</label>
                                            <input type="number" class="form-control <?php echo isset($errors['reorder_level']) ? 'is-invalid' : ''; ?>" 
                                                   id="reorder_level" name="reorder_level" 
                                                   value="<?php echo htmlspecialchars($formData['reorder_level']); ?>" 
                                                   min="0" step="0.001">
                                            <?php if (isset($errors['reorder_level'])): ?>
                                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['reorder_level']); ?></div>
                                            <?php endif; ?>
                                            <div class="help-text">Alert when stock falls below this level</div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <?php if (isset($remediesColumns['is_featured'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="is_featured" name="is_featured" value="1" 
                                                       <?php echo $formData['is_featured'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_featured">
                                                    <strong>Featured Product</strong>
                                                </label>
                                                <div class="help-text">Show this remedy as featured on the website</div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($remediesColumns['is_active'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       id="is_active" name="is_active" value="1" 
                                                       <?php echo $formData['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">
                                                    <strong>Active Status</strong>
                                                </label>
                                                <div class="help-text">Make this remedy available for sale</div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- SEO & Marketing Tab -->
                                <div class="tab-pane fade" id="seo" role="tabpanel">
                                    <div class="alert alert-info">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Fill these fields to improve search visibility and increase conversion from visitors to buyers.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="seo_title" class="form-label">SEO Title</label>
                                            <input type="text" class="form-control" id="seo_title" name="seo_title"
                                                   maxlength="255" placeholder="e.g., Organic Ginger Tea for Digestion | JAKISAWA"
                                                   value="<?php echo htmlspecialchars($formData['seo_title']); ?>">
                                            <div class="help-text">Recommended: 50-60 characters</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="focus_keyword" class="form-label">Focus Keyword</label>
                                            <input type="text" class="form-control" id="focus_keyword" name="focus_keyword"
                                                   maxlength="255" placeholder="e.g., herbal tea for bloating"
                                                   value="<?php echo htmlspecialchars($formData['focus_keyword']); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="seo_meta_description" class="form-label">Meta Description</label>
                                        <textarea class="form-control" id="seo_meta_description" name="seo_meta_description"
                                                  rows="3" maxlength="320"
                                                  placeholder="Brief persuasive summary for search results"><?php echo htmlspecialchars($formData['seo_meta_description']); ?></textarea>
                                        <div class="help-text">Recommended: 140-160 characters</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="seo_keywords" class="form-label">SEO Keywords</label>
                                        <input type="text" class="form-control" id="seo_keywords" name="seo_keywords"
                                               placeholder="Comma-separated keywords"
                                               value="<?php echo htmlspecialchars($formData['seo_keywords']); ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="og_title" class="form-label">Social Share Title (OG Title)</label>
                                            <input type="text" class="form-control" id="og_title" name="og_title"
                                                   maxlength="255" value="<?php echo htmlspecialchars($formData['og_title']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="canonical_url" class="form-label">Canonical URL</label>
                                            <input type="url" class="form-control" id="canonical_url" name="canonical_url"
                                                   placeholder="https://example.com/remedy/slug"
                                                   value="<?php echo htmlspecialchars($formData['canonical_url']); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="og_description" class="form-label">Social Share Description (OG Description)</label>
                                        <textarea class="form-control" id="og_description" name="og_description"
                                                  rows="2" maxlength="320"><?php echo htmlspecialchars($formData['og_description']); ?></textarea>
                                    </div>

                                    <hr>
                                    <h6 class="mb-3">Customer Conversion Content</h6>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="target_audience" class="form-label">Target Audience</label>
                                            <input type="text" class="form-control" id="target_audience" name="target_audience"
                                                   placeholder="e.g., Adults with digestion issues"
                                                   value="<?php echo htmlspecialchars($formData['target_audience']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cta_text" class="form-label">Call-to-Action Text</label>
                                            <input type="text" class="form-control" id="cta_text" name="cta_text"
                                                   placeholder="e.g., Order now and feel relief"
                                                   value="<?php echo htmlspecialchars($formData['cta_text']); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cta_link" class="form-label">CTA Link</label>
                                        <input type="text" class="form-control" id="cta_link" name="cta_link"
                                               placeholder="/index.php#order-section"
                                               value="<?php echo htmlspecialchars($formData['cta_link']); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="value_proposition" class="form-label">Value Proposition</label>
                                        <textarea class="form-control" id="value_proposition" name="value_proposition"
                                                  rows="3" placeholder="Why this remedy is better than alternatives"><?php echo htmlspecialchars($formData['value_proposition']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="customer_pain_points" class="form-label">Customer Pain Points</label>
                                        <textarea class="form-control" id="customer_pain_points" name="customer_pain_points"
                                                  rows="3" placeholder="Problems this remedy solves"><?php echo htmlspecialchars($formData['customer_pain_points']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="faq_q1" class="form-label">FAQ 1 - Question</label>
                                            <input type="text" class="form-control" id="faq_q1" name="faq_q1"
                                                   value="<?php echo htmlspecialchars($formData['faq_q1']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="faq_a1" class="form-label">FAQ 1 - Answer</label>
                                            <textarea class="form-control" id="faq_a1" name="faq_a1" rows="2"><?php echo htmlspecialchars($formData['faq_a1']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="faq_q2" class="form-label">FAQ 2 - Question</label>
                                            <input type="text" class="form-control" id="faq_q2" name="faq_q2"
                                                   value="<?php echo htmlspecialchars($formData['faq_q2']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="faq_a2" class="form-label">FAQ 2 - Answer</label>
                                            <textarea class="form-control" id="faq_a2" name="faq_a2" rows="2"><?php echo htmlspecialchars($formData['faq_a2']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="sticky-actions">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </button>
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check-circle me-2"></i>Add Remedy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            function formatPriceForStorage(priceValue) {
                var numeric = parseFloat(priceValue);
                if (isNaN(numeric)) {
                    return '';
                }
                return String(parseFloat(numeric.toFixed(2)));
            }

            function buildVariationRow(kind, labelValue, priceValue) {
                var labelName = kind === 'size' ? 'size_label[]' : 'sachet_label[]';
                var priceName = kind === 'size' ? 'size_price[]' : 'sachet_price[]';
                var labelPlaceholder = kind === 'size' ? 'e.g. 1kg' : 'e.g. 250g';
                var pricePlaceholder = kind === 'size' ? 'e.g. 200' : 'e.g. 50';
                var labelCaption = kind === 'size' ? 'Size Label' : 'Option Label';

                return $(
                    '<div class="variation-row row g-2 align-items-end">' +
                        '<div class="col-md-6">' +
                            '<label class="form-label small text-muted">' + labelCaption + '</label>' +
                            '<input type="text" class="form-control variation-label" name="' + labelName + '" placeholder="' + labelPlaceholder + '">' +
                        '</div>' +
                        '<div class="col-md-4">' +
                            '<label class="form-label small text-muted">Price (KSh)</label>' +
                            '<input type="number" class="form-control variation-price" name="' + priceName + '" min="0" step="0.01" placeholder="' + pricePlaceholder + '">' +
                        '</div>' +
                        '<div class="col-md-2 d-grid">' +
                            '<button type="button" class="btn btn-outline-danger remove-variation-row">Remove</button>' +
                        '</div>' +
                    '</div>'
                ).find('.variation-label').val(labelValue || '').end()
                 .find('.variation-price').val(priceValue || '').end();
            }

            function ensureMinimumVariationRows() {
                if ($('#sizeRows .variation-row').length === 0) {
                    $('#sizeRows').append(buildVariationRow('size', '', ''));
                }
                if ($('#sachetRows .variation-row').length === 0) {
                    $('#sachetRows').append(buildVariationRow('sachet', '', ''));
                }
            }

            function serializeVariationRows(containerSelector) {
                var lines = [];
                $(containerSelector).find('.variation-row').each(function() {
                    var label = $(this).find('.variation-label').val().trim();
                    var priceRaw = $(this).find('.variation-price').val().trim();
                    if (!label || !priceRaw) {
                        return;
                    }
                    lines.push(label + '|' + formatPriceForStorage(priceRaw));
                });
                return lines.join("\n");
            }

            function syncVariationHiddenFields() {
                $('#custom_sizes').val(serializeVariationRows('#sizeRows'));
                $('#custom_sachets').val(serializeVariationRows('#sachetRows'));
            }

            function validateVariationRows(containerSelector) {
                var sectionValid = true;
                $(containerSelector).find('.variation-row').each(function() {
                    var labelInput = $(this).find('.variation-label');
                    var priceInput = $(this).find('.variation-price');
                    var label = labelInput.val().trim();
                    var priceRaw = priceInput.val().trim();

                    if (label === '' && priceRaw === '') {
                        return;
                    }

                    if (label === '') {
                        labelInput.addClass('is-invalid');
                        sectionValid = false;
                    }
                    if (priceRaw === '') {
                        priceInput.addClass('is-invalid');
                        sectionValid = false;
                        return;
                    }

                    var numeric = parseFloat(priceRaw);
                    if (isNaN(numeric) || numeric <= 0) {
                        priceInput.addClass('is-invalid');
                        sectionValid = false;
                    }
                });

                return sectionValid;
            }

            // Auto-generate slug from name
            $('#name').on('blur', function() {
                if ($('#slug').val() === '') {
                    var name = $(this).val();
                    var slug = name.toLowerCase()
                        .replace(/[^\w\s-]/g, '')
                        .replace(/[\s_-]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    $('#slug').val(slug);
                }
            });

            // Image preview
            $('#image').on('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview').attr('src', e.target.result).show();
                        $('#removeImage').removeClass('d-none');
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            // Remove image
            $('#removeImage').on('click', function() {
                $('#image').val('');
                $('#imagePreview').hide().attr('src', '');
                $(this).addClass('d-none');
            });

            $('.add-variation-row').on('click', function() {
                var kind = $(this).data('kind') === 'sachet' ? 'sachet' : 'size';
                var targetId = $(this).data('target');
                $('#' + targetId).append(buildVariationRow(kind, '', ''));
                syncVariationHiddenFields();
            });

            $(document).on('click', '.remove-variation-row', function() {
                var container = $(this).closest('.variation-rows');
                $(this).closest('.variation-row').remove();
                if (container.find('.variation-row').length === 0) {
                    var kind = container.attr('id') === 'sachetRows' ? 'sachet' : 'size';
                    container.append(buildVariationRow(kind, '', ''));
                }
                syncVariationHiddenFields();
            });

            $(document).on('input change', '.variation-label, .variation-price', function() {
                syncVariationHiddenFields();
            });

            // Form validation
            $('#addRemedyForm').on('submit', function(e) {
                var isValid = true;
                var firstErrorField = null;

                syncVariationHiddenFields();

                // Clear previous invalid states
                $(this).find('.is-invalid').removeClass('is-invalid');

                // Check required fields
                $(this).find('[required]').each(function() {
                    if (!$(this).val().trim()) {
                        isValid = false;
                        $(this).addClass('is-invalid');

                        if (!firstErrorField) {
                            firstErrorField = $(this);
                        }
                    }
                });

                if (!validateVariationRows('#sizeRows')) {
                    isValid = false;
                    if (!firstErrorField) {
                        firstErrorField = $('#sizeRows .is-invalid').first();
                    }
                }
                if (!validateVariationRows('#sachetRows')) {
                    isValid = false;
                    if (!firstErrorField) {
                        firstErrorField = $('#sachetRows .is-invalid').first();
                    }
                }

                // Special validation for SKU/supplier
                var sku = $('#sku').val().trim();
                var supplierId = $('#supplier_id').val();

                if (!sku && !supplierId) {
                    isValid = false;
                    $('#sku').addClass('is-invalid');
                    $('#supplier_id').addClass('is-invalid');
                    showNotification('Please enter SKU or select a supplier for auto-generation', 'error');
                }

                // Validate numeric fields
                $('input[type="number"]').each(function() {
                    var value = $(this).val();
                    var min = $(this).attr('min');

                    if (value !== '' && min !== undefined && parseFloat(value) < parseFloat(min)) {
                        isValid = false;
                        $(this).addClass('is-invalid');
                        if (!firstErrorField) {
                            firstErrorField = $(this);
                        }
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    showNotification('Please fix the errors in the form', 'error');

                    if (firstErrorField) {
                        var tabPane = firstErrorField.closest('.tab-pane');
                        var tabId = tabPane.attr('id');
                        $('.nav-link[data-bs-target="#' + tabId + '"]').tab('show');
                        firstErrorField.focus();
                    }
                }
            });

            // Remove invalid class when user starts typing
            $(document).on('input change', 'input, select, textarea', function() {
                if ($(this).hasClass('is-invalid')) {
                    $(this).removeClass('is-invalid');
                }
            });

            // Auto-generate SKU when supplier is selected
            $('#supplier_id').on('change', function() {
                if ($(this).val() && !$('#sku').val().trim()) {
                    $('#sku').attr('placeholder', 'SKU will be auto-generated on save');
                }
            });

            function showNotification(message, type) {
                var alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
                var icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';

                $('.alert-notification').remove();

                var alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade show alert-notification" role="alert">' +
                    '<i class="fas ' + icon + ' me-2"></i>' + message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>');

                $('body').append(alert);

                setTimeout(function() {
                    alert.alert('close');
                }, 5000);
            }

            ensureMinimumVariationRows();
            syncVariationHiddenFields();
        });
    </script>
</body>
</html>
