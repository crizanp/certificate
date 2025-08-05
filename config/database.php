<?php
// Alternative Database configuration
define('DB_HOST', 'localhost'); // Use IP instead of localhost
define('DB_PORT', '3308');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'admin_system');

// Create connection with port specification
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch(PDOException $e) {
    // More detailed error information
    die("Connection failed: " . $e->getMessage() . "<br>Error Code: " . $e->getCode());
}
?>