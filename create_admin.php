<?php
require_once 'config/database.php';

// Generate password hash for 'admin123'
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Password Hash Generator</h2>";
echo "<p><strong>Plain Password:</strong> " . $password . "</p>";
echo "<p><strong>Hashed Password:</strong> " . $hashed_password . "</p>";

try {
    // Delete existing admin user
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
    $stmt->execute(['admin']);
    
    // Insert new admin user with correct hash
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute(['admin', $hashed_password, 'admin@example.com']);
    
    echo "<p style='color: green;'><strong>Success!</strong> Admin user created/updated successfully.</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<p>Username: admin</p>";
    echo "<p>Password: admin123</p>";
    
    // Verify the password works
    $stmt = $pdo->prepare("SELECT username, password FROM admin_users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();
    
    if ($user && password_verify('admin123', $user['password'])) {
        echo "<p style='color: green;'>✅ Password verification successful!</p>";
    } else {
        echo "<p style='color: red;'>❌ Password verification failed!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='login.php'>Go to Login Page</a>";
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
p { margin: 10px 0; }
</style>