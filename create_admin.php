<?php
require_once 'config/database.php';

// Ensure admin_users table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error creating table: " . $e->getMessage() . "</p>";
    exit;
}


// Generate password hash for 'Gyanhub01#'
$username = 'gyanhub';
$password = 'Gyanhub01#';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Password Hash Generator</h2>";
echo "<p><strong>Plain Password:</strong> " . $password . "</p>";
echo "<p><strong>Hashed Password:</strong> " . $hashed_password . "</p>";

try {
    // Delete existing user with username 'gyanhub'
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);

    // Insert new admin user with correct hash
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hashed_password, 'gyanhub@example.com']);

    echo "<p style='color: green;'><strong>Success!</strong> Admin user created/updated successfully.</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<p>Username: gyanhub</p>";
    echo "<p>Password: Gyanhub01#</p>";

    // Verify the password works
    $stmt = $pdo->prepare("SELECT username, password FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
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