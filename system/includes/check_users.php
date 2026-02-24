<?php
// check_users.php
session_start();

require_once 'config.php'; // must create $conn (mysqli)

// Database configuration
// define('DB_HOST', '127.0.0.1');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'JAKISAWA_SHOP');

function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}


$conn = getDBConnection();


// test.php â€” One-time password hashing migration
// DELETE this file immediately after successful run

require_once 'config.php'; // must create $conn (mysqli)

if (!$conn) {
    die("Database connection failed.");
}

$sql = "SELECT id, full_name, password_hash FROM users";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$updated = 0;
$skipped = 0;

echo "<h3>Password Hash Migration</h3>";

while ($user = mysqli_fetch_assoc($result)) {

    $currentPassword = $user['password_hash'];

    // Skip already-hashed passwords
    if (preg_match('/^\$2y\$/', $currentPassword)) {
        echo "âœ” {$user['full_name']} â€” already hashed<br>";
        $skipped++;
        continue;
    }

    // Hash plain-text password
    $hashedPassword = password_hash($currentPassword, PASSWORD_DEFAULT);

    $updateSql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $user['id']);
    mysqli_stmt_execute($stmt);

    echo "ðŸ” {$user['username']} â€” password hashed<br>";
    $updated++;
}

echo "<hr>";
echo "<strong>Done.</strong><br>";
echo "Hashed: $updated<br>";
echo "Skipped: $skipped<br>";

// mysqli_close($conn);

echo "<h2>Checking Users Table</h2>";

// Check if users table exists
$checkTable = "SHOW TABLES LIKE 'users'";
$result = mysqli_query($conn, $checkTable);

if (mysqli_num_rows($result) == 0) {
    echo "<div style='color: red;'>âŒ Users table does NOT exist!</div>";
    echo "<p><a href='setup_users_table.php'>Run Setup Script</a></p>";
} else {
    echo "<div style='color: green;'>âœ… Users table exists.</div>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $structure = "DESCRIBE users";
    $result = mysqli_query($conn, $structure);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show all users
    echo "<h3>All Users in Database:</h3>";
    $usersQuery = "SELECT * FROM users";
    $result = mysqli_query($conn, $usersQuery);
    
    
// First, check if there are any users
if (mysqli_num_rows($result) < 2) {
    echo "<div style='color: orange;'>âš ï¸ Few users found in the table!</div>";
    
    // Create default admin user
    echo "<p>Creating default admin user...</p>";
    $createAdmin = "INSERT INTO users (username, full_name, email, password, role, is_active, created_at) 
                   VALUES ('admin', 'System Administrator', 'jakisawa@jakisawashop.co.ke', ?, 'admin', 1, NOW())";
    
    // Hash the password for security
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, $createAdmin);
    mysqli_stmt_bind_param($stmt, "s", $defaultPassword);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<div style='color: green;'>âœ… Default admin user created!</div>";
        echo "<p>Email: jakisawa@jakisawashop.co.ke</p>";
        echo "<p>Password: admin123</p>";
    } else {
        echo "<div style='color: red;'>âŒ Error creating admin: " . mysqli_error($conn) . "</div>";
    }
    
    // Check if jakisawa@jakisawashop.co.ke exists
    $checkJohn = "SELECT id FROM users WHERE email = 'jakisawa@jakisawashop.co.ke'";
    $johnResult = mysqli_query($conn, $checkJohn);
    
    if (mysqli_num_rows($johnResult) == 0) {
        echo "<p>Creating super admin (jakisawa@jakisawashop.co.ke)...</p>";
        
        // Create your super admin account with special flag
        $createSuperAdmin = "INSERT INTO users (
            username, 
            full_name, 
            email, 
            password, 
            role, 
            is_active,
            is_super_admin,  // Add this column if it doesn't exist
            cannot_delete,   // Add this column if it doesn't exist
            created_at
        ) VALUES (
            'johnarumansi', 
            'John Arumansi', 
            'jakisawa@jakisawashop.co.ke', 
            ?, 
            'super_admin',  // Special role
            1,
            1,              // is_super_admin flag
            1,              // cannot_delete flag
            NOW()
        )";
        
        // Hash your password
        $johnPassword = password_hash('#@Mshamba,2026', PASSWORD_DEFAULT);
        $stmt2 = mysqli_prepare($conn, $createSuperAdmin);
        mysqli_stmt_bind_param($stmt2, "s", $johnPassword);
        
        if (mysqli_stmt_execute($stmt2)) {
            echo "<div style='color: green;'>âœ… Super admin John created!</div>";
            echo "<p>Email: jakisawa@jakisawashop.co.ke</p>";
            echo "<p>Password: #@Mshamba,2026</p>";
            echo "<p><strong>âš ï¸ IMPORTANT:</strong> This account cannot be deleted and has full privileges.</p>";
        } else {
            // If columns don't exist, try without them
            echo "<div style='color: orange;'>âš ï¸ Trying alternative insert...</div>";
            
            $createSuperAdminAlt = "INSERT INTO users (
                username, 
                full_name, 
                email, 
                password, 
                role, 
                is_active,
                created_at
            ) VALUES (
                'johnarumansi', 
                'John Arumansi', 
                'jakisawa@jakisawashop.co.ke', 
                ?, 
                'admin',  // Use 'admin' role as fallback
                1,
                NOW()
            )";
            
            $stmt3 = mysqli_prepare($conn, $createSuperAdminAlt);
            mysqli_stmt_bind_param($stmt3, "s", $johnPassword);
            
            if (mysqli_stmt_execute($stmt3)) {
                echo "<div style='color: green;'>âœ… Super admin John created (alternative)!</div>";
                echo "<p>Email: jakisawa@jakisawashop.co.ke</p>";
                echo "<p>Password: #@Mshamba,2026</p>";
                
                // Add a special note to this user
                $userId = mysqli_insert_id($conn);
                $addNote = "UPDATE users SET 
                    notes = 'SUPER ADMIN - DO NOT DELETE - Created by system on " . date('Y-m-d H:i:s') . "'
                    WHERE id = $userId";
                mysqli_query($conn, $addNote);
            } else {
                echo "<div style='color: red;'>âŒ Error creating John: " . mysqli_error($conn) . "</div>";
            }
        }
    } else {
        echo "<div style='color: blue;'>â„¹ï¸ John's account already exists.</div>";
        
        // Ensure John has admin privileges
        $updateJohn = "UPDATE users SET 
            role = 'super_admin',
            is_active = 1,
            notes = CONCAT(IFNULL(notes, ''), ' | UPDATED TO SUPER ADMIN ON " . date('Y-m-d H:i:s') . "')
            WHERE email = 'jakisawa@jakisawashop.co.ke'";
        
        if (mysqli_query($conn, $updateJohn)) {
            echo "<div style='color: green;'>âœ… John's account updated to super admin!</div>";
        }
    }
}

// Create necessary columns if they don't exist
echo "<p>Checking database structure...</p>";

$columnsToAdd = [
    "is_super_admin" => "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin TINYINT(1) DEFAULT 0",
    "cannot_delete" => "ALTER TABLE users ADD COLUMN IF NOT EXISTS cannot_delete TINYINT(1) DEFAULT 0",
    "notes" => "ALTER TABLE users ADD COLUMN IF NOT EXISTS notes TEXT NULL"
];

foreach ($columnsToAdd as $column => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "<div style='color: green;'>âœ… Column '$column' checked/created</div>";
    } else {
        echo "<div style='color: orange;'>âš ï¸ Could not add column '$column': " . mysqli_error($conn) . "</div>";
    }
}

// Set John as super admin and protected
echo "<p>Setting up John's account privileges...</p>";
$protectJohn = "UPDATE users SET 
    role = 'super_admin',
    is_super_admin = 1,
    cannot_delete = 1,
   
WHERE email = 'jakisawa@jakisawashop.co.ke'";

if (mysqli_query($conn, $protectJohn)) {
    $affected = mysqli_affected_rows($conn);
    if ($affected > 0) {
        echo "<div style='color: green;'>âœ… John's account is now protected as super admin!</div>";
    } else {
        echo "<div style='color: blue;'>â„¹ï¸ John's account not found or already set up.</div>";
    }
}

// Create a trigger to prevent deletion of John's account (if you have permission)
echo "<p>Creating protection trigger...</p>";
$createTrigger = "
CREATE TRIGGER IF NOT EXISTS prevent_delete_super_admin
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    IF OLD.email = 'jakisawa@jakisawashop.co.ke' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete super admin account: jakisawa@jakisawashop.co.ke';
    END IF;
END;
";

// Try to create trigger (might fail without proper permissions)
if (mysqli_multi_query($conn, $createTrigger)) {
    echo "<div style='color: green;'>âœ… Protection trigger created!</div>";
} else {
    echo "<div style='color: orange;'>âš ï¸ Could not create trigger (might need admin privileges): " . mysqli_error($conn) . "</div>";
}

// Summary
echo "<hr><h3>Summary of Created Accounts:</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Email</th><th>Password</th><th>Role</th><th>Protected</th></tr>";
echo "<tr><td>jakisawa@jakisawashop.co.ke</td><td>admin123</td><td>Admin</td><td>No</td></tr>";
echo "<tr><td>jakisawa@jakisawashop.co.ke</td><td>#@Mshamba,2026</td><td>Super Admin</td><td>âœ… YES</td></tr>";
echo "</table>";

echo "<div style='background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid #c62828;'>";
echo "<strong>âš ï¸ SECURITY NOTES:</strong>";
echo "<ul>";
echo "<li>Change default passwords immediately after first login</li>";
echo "<li>John's account (jakisawa@jakisawashop.co.ke) cannot be deleted</li>";
echo "<li>John has full system privileges</li>";
echo "<li>Consider enabling 2-factor authentication for admin accounts</li>";
echo "</ul>";
echo "</div>";

    // } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Password</th><th>Role</th><th>Active</th><th>Created</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['password']) . " <small>(" . strlen($row['password']) . " chars)</small></td>";
            echo "<td>" . $row['role'] . "</td>";
            echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
//}

mysqli_close($conn);

echo "<hr>";
echo "<h3>Debug Login Issue:</h3>";
echo "<p>1. Check if jakisawa@jakisawashop.co.ke exists</p>";
echo "<p>2. Check if password is 'admin123' (plain text)</p>";
echo "<p>3. Check if is_active = 1</p>";
echo "<p>4. Check if role is 'admin' or 'staff'</p>";

echo "<hr>";
echo "<a href='admin_login.php'>Go to Login Page</a>";
?>
