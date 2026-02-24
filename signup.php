<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Full name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id, email_verified, role FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $existingRole = normalizeUserRole($existingUser['role'] ?? '');
            $knownRoles = ['customer', 'admin', 'staff', 'super_admin'];
            if ($existingRole === '' || !in_array($existingRole, $knownRoles, true)) {
                $roleStmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id = ? LIMIT 1");
                $roleStmt->execute([(int)$existingUser['id']]);
            }
            if ((int)($existingUser['email_verified'] ?? 0) === 1) {
                $success = 'This email is already registered and verified. Please sign in.';
                header('Refresh: 2; url=login.php?email=' . urlencode($email));
            } else {
                $token = generateVerificationToken();
                $verifyStmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ? LIMIT 1");
                $verifyStmt->execute([$token, (int)$existingUser['id']]);

                $mailSent = sendCustomerVerificationEmail($email, $fullName !== '' ? $fullName : 'Customer', $token);
                if ($mailSent) {
                    $success = 'Your account exists but email is not verified. A fresh verification link has been sent. Verify before signing in.';
                } else {
                    $error = 'Your account exists but email is not verified. We could not send the verification email right now.';
                }
            }
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $token = generateVerificationToken();
            $insertStmt = $pdo->prepare("
                INSERT INTO users (full_name, email, phone, password_hash, role, status, email_verified, verification_token, created_at)
                VALUES (?, ?, ?, ?, 'customer', 'active', 0, ?, NOW())
            ");

            $insertStmt->execute([$fullName, $email, $phone, $hash, $token]);

            unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_role']);
            unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['phone'], $_SESSION['role']);

            $mailSent = sendCustomerVerificationEmail($email, $fullName, $token);
            if ($mailSent) {
                $success = 'Account created. Verification link sent to your email. Verify your email before signing in.';
            } else {
                $error = 'Account created, but verification email could not be sent. Please contact support.';
            }
        }
    }
}
$prefillEmail = trim($_GET['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Sign Up</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
        .wrap { max-width:460px; margin:40px auto; background:#fff; border:1px solid #ddd; border-radius:8px; padding:24px; }
        h1 { margin-top:0; font-size:24px; }
        label { display:block; margin:10px 0 6px; }
        input { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; }
        button { margin-top:16px; width:100%; padding:11px; border:0; border-radius:6px; background:#2e7d32; color:#fff; cursor:pointer; }
        .err { background:#fdecea; color:#b42318; border:1px solid #f5c2c7; padding:10px; border-radius:6px; margin-bottom:10px; }
        .ok { background:#ecfdf3; color:#067647; border:1px solid #abefc6; padding:10px; border-radius:6px; margin-bottom:10px; }
        .links { margin-top:14px; font-size:14px; text-align:center; }
        a { color:#2e7d32; text-decoration:none; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Create Customer Account</h1>
        <?php if ($error !== ''): ?>
            <div class="err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="ok"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name" type="text" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($prefillEmail); ?>" required>
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="text">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required>
            <button type="submit">Sign Up</button>
        </form>
        <div class="links">
            <a href="login.php">Already have an account? Sign in</a> | <a href="index.php">Back to shop</a>
        </div>
    </div>
</body>
</html>
