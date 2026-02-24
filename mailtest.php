<?php
// Temporary diagnostic - delete after use

echo "<h3>Searching for PHPMailer...</h3>";

// Check multiple possible locations
$paths = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/PHPMailer/src/PHPMailer.php',
    '/home/' . get_current_user() . '/public_html/PHPMailer/src/PHPMailer.php',
    '/home/' . get_current_user() . '/public_html/vendor/autoload.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "✅ FOUND: $path <br>";
    } else {
        echo "❌ Not here: $path <br>";
    }
}

echo "<br><h3>Your directory info:</h3>";
echo "Current file location: " . __FILE__ . "<br>";
echo "APP_ROOT (dirname(__DIR__)): " . dirname(__DIR__) . "<br>";
echo "__DIR__: " . __DIR__ . "<br>";
echo "Current user: " . get_current_user() . "<br>";
?>