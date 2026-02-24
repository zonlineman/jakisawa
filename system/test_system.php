<!DOCTYPE html>
<?php require_once __DIR__ . '/includes/config.php'; ?>
<html>
<head>
    <title>Remedy System Diagnostic</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .test { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .pass { border-left: 5px solid #28a745; }
        .fail { border-left: 5px solid #dc3545; }
        .warn { border-left: 5px solid #ffc107; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/assets/css/responsive-hotfix.css', ENT_QUOTES); ?>">
</head>
<body>
    <h1>🔍 Remedy System Diagnostic</h1>
    <p>This page checks all requirements for the edit remedy system.</p>

<?php
// Test 1: PHP Version
echo '<div class="test ' . (version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'pass' : 'fail') . '">';
echo '<h2>Test 1: PHP Version</h2>';
echo '<p>Current: ' . PHP_VERSION . '</p>';
echo '<p>Required: 7.4.0 or higher</p>';
echo version_compare(PHP_VERSION, '7.4.0') >= 0 ? '✅ PASS' : '❌ FAIL';
echo '</div>';

// Test 2: Database File
$db_path = __DIR__ . '/includes/database.php';
echo '<div class="test ' . (file_exists($db_path) ? 'pass' : 'fail') . '">';
echo '<h2>Test 2: Database File</h2>';
echo '<p>Looking for: ' . $db_path . '</p>';
if (file_exists($db_path)) {
    echo '<p>✅ File exists</p>';
    require_once $db_path;
    echo '<p>✅ File loaded successfully</p>';
} else {
    echo '<p>❌ File NOT found</p>';
    echo '<p>Create this file with database connection function</p>';
}
echo '</div>';

// Test 3: Database Connection
echo '<div class="test ';
try {
    if (function_exists('getDBConnection')) {
        $conn = getDBConnection();
        if ($conn && !$conn->connect_error) {
            echo 'pass">';
            echo '<h2>Test 3: Database Connection</h2>';
            echo '<p>✅ Connected successfully</p>';
            echo '<p>Host: ' . $conn->host_info . '</p>';
        } else {
            echo 'fail">';
            echo '<h2>Test 3: Database Connection</h2>';
            echo '<p>❌ Connection failed</p>';
            if ($conn) {
                echo '<p>Error: ' . $conn->connect_error . '</p>';
            }
        }
    } else {
        echo 'fail">';
        echo '<h2>Test 3: Database Connection</h2>';
        echo '<p>❌ getDBConnection() function not found</p>';
    }
} catch (Exception $e) {
    echo 'fail">';
    echo '<h2>Test 3: Database Connection</h2>';
    echo '<p>❌ Error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Test 4: Remedies Table
echo '<div class="test ';
if (isset($conn) && $conn && !$conn->connect_error) {
    $result = $conn->query("SHOW TABLES LIKE 'remedies'");
    if ($result && $result->num_rows > 0) {
        echo 'pass">';
        echo '<h2>Test 4: Remedies Table</h2>';
        echo '<p>✅ Table exists</p>';
        
        // Count records
        $count = $conn->query("SELECT COUNT(*) as total FROM remedies")->fetch_assoc();
        echo '<p>Total remedies: ' . $count['total'] . '</p>';
    } else {
        echo 'fail">';
        echo '<h2>Test 4: Remedies Table</h2>';
        echo '<p>❌ Table does NOT exist</p>';
    }
} else {
    echo 'warn">';
    echo '<h2>Test 4: Remedies Table</h2>';
    echo '<p>⚠️ Cannot test - no database connection</p>';
}
echo '</div>';

// Test 5: Categories Table
echo '<div class="test ';
if (isset($conn) && $conn && !$conn->connect_error) {
    $result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($result && $result->num_rows > 0) {
        echo 'pass">';
        echo '<h2>Test 5: Categories Table</h2>';
        echo '<p>✅ Table exists</p>';
        
        // Count records
        $count = $conn->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1")->fetch_assoc();
        echo '<p>Active categories: ' . $count['total'] . '</p>';
        
        if ($count['total'] == 0) {
            echo '<p style="color: orange;">⚠️ WARNING: No active categories found!</p>';
            echo '<p>Add categories before editing remedies.</p>';
        }
    } else {
        echo 'fail">';
        echo '<h2>Test 5: Categories Table</h2>';
        echo '<p>❌ Table does NOT exist</p>';
    }
} else {
    echo 'warn">';
    echo '<h2>Test 5: Categories Table</h2>';
    echo '<p>⚠️ Cannot test - no database connection</p>';
}
echo '</div>';

// Test 6: Uploads Directory
$upload_dir = dirname(__DIR__) . '/systemuploads/products/';
echo '<div class="test ' . (is_dir($upload_dir) && is_writable($upload_dir) ? 'pass' : 'fail') . '">';
echo '<h2>Test 6: Uploads Directory</h2>';
echo '<p>Path: ' . $upload_dir . '</p>';
if (is_dir($upload_dir)) {
    echo '<p>✅ Directory exists</p>';
    if (is_writable($upload_dir)) {
        echo '<p>✅ Directory is writable</p>';
    } else {
        echo '<p>❌ Directory is NOT writable</p>';
        echo '<p>Run: chmod 777 ' . $upload_dir . '</p>';
    }
} else {
    echo '<p>❌ Directory does NOT exist</p>';
    echo '<p>Run: mkdir -p ' . $upload_dir . ' && chmod 777 ' . $upload_dir . '</p>';
}
echo '</div>';

// Test 7: API Files
$api_files = [
    'get_categories.php' => '/pages/actions/ajax/get_categories.php',
    'get_suppliers.php' => '/pages/actions/ajax/get_suppliers.php',
    'get_remedy.php' => '/pages/actions/ajax/get_remedy.php',
    'edit_remedy.php' => '/pages/actions/remedies/edit_remedy.php',
    'update_stock.php' => '/pages/actions/remedies/update_stock.php'
];

echo '<div class="test">';
echo '<h2>Test 7: API Files</h2>';
$all_exist = true;
foreach ($api_files as $name => $path) {
    $full_path = __DIR__ . $path;
    $exists = file_exists($full_path);
    if (!$exists) $all_exist = false;
    echo '<p>' . ($exists ? '✅' : '❌') . ' ' . $name . '</p>';
    if (!$exists) {
        echo '<p style="margin-left: 20px; color: #666;">Expected at: ' . $full_path . '</p>';
    }
}
echo '</div>';

// Test 8: Test API Endpoint
echo '<div class="test">';
echo '<h2>Test 8: Test API Endpoints</h2>';
echo '<button class="btn" onclick="testAPI()">Test get_categories.php</button>';
echo '<pre id="api-result" style="margin-top: 10px; display: none;"></pre>';
echo '</div>';

?>

<script>
function testAPI() {
    const result = document.getElementById('api-result');
    result.style.display = 'block';
    result.textContent = 'Testing...';
    
    fetch('<?php echo htmlspecialchars((defined('SYSTEM_BASE_URL') ? SYSTEM_BASE_URL : '/system') . '/pages/actions/ajax/get_categories.php', ENT_QUOTES); ?>')
        .then(response => {
            result.textContent = 'Status: ' + response.status + '\n';
            result.textContent += 'Content-Type: ' + response.headers.get('content-type') + '\n\n';
            return response.text();
        })
        .then(text => {
            result.textContent += 'Response:\n' + text;
            
            // Try to parse as JSON
            try {
                const json = JSON.parse(text);
                result.textContent += '\n\n✅ Valid JSON!\n';
                result.textContent += JSON.stringify(json, null, 2);
            } catch (e) {
                result.textContent += '\n\n❌ NOT valid JSON!\n';
                result.textContent += 'Error: ' + e.message;
            }
        })
        .catch(error => {
            result.textContent += '\n\n❌ Fetch Error:\n' + error.message;
        });
}
</script>

<hr>
<h2>📋 Summary</h2>
<p>If all tests pass, your system is ready. If any fail, fix those issues first.</p>

<h2>🔧 Next Steps</h2>
<ol>
    <li>Make sure all tests above pass</li>
    <li>Click "Test get_categories.php" button to verify API works</li>
    <li>If API returns HTML instead of JSON, there's a PHP error in that file</li>
    <li>Use the DIAGNOSTIC edit_remedy.php to see exact error when saving</li>
</ol>

</body>
</html>
