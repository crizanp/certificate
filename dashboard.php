<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Ensure required tables exist
try {
    // Create syllabi table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS syllabi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        syllabus_name VARCHAR(255) NOT NULL,
        syllabus_pdf VARCHAR(500) NOT NULL,
        description TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
    )");
    
    // Create certificates table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        syllabus_id INT,
        syllabus_name VARCHAR(255),
        syllabus_pdf VARCHAR(500),
        certificate_image VARCHAR(500),
        issue_date DATE,
        certificate_code VARCHAR(100) UNIQUE,
        status VARCHAR(20) DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (syllabus_id) REFERENCES syllabi(id),
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
    )");
    
    // Add missing columns to existing certificates table if they don't exist
    $columns_to_add = [
        'email' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'syllabus_name' => 'VARCHAR(255)',
        'syllabus_pdf' => 'VARCHAR(500)',
        'issue_date' => 'DATE',
        'certificate_code' => 'VARCHAR(100)',
        'status' => 'VARCHAR(20) DEFAULT "active"'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE certificates ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            // Column might already exist, continue
        }
    }
    
    // Rename participant_name to name if it exists
    try {
        $pdo->exec("ALTER TABLE certificates CHANGE participant_name name VARCHAR(255) NOT NULL");
    } catch (PDOException $e) {
        // Column might not exist or already renamed, continue
    }
    
} catch (PDOException $e) {
    // Continue silently if tables already exist or there are foreign key issues
}

// Require login to access this page
requireLogin();
// Get admin info
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">

   
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
       
        <div class="dashboard-content">
            <h3>Welcome, <?php echo htmlspecialchars($admin['username']); ?>!</h3>
           
           

            <?php
            // Get statistics
            try {
                $syllabi_count = $pdo->query("SELECT COUNT(*) FROM syllabi")->fetchColumn();
                $certificates_count = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
            } catch (PDOException $e) {
                $syllabi_count = 0;
                $certificates_count = 0;
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $syllabi_count; ?></div>
                    <div>Total Syllabi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $certificates_count; ?></div>
                    <div>Total Certificates</div>
                </div>
            </div>
           
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Certificate Management</h3>
                    <ul class="feature-links">
                        <li><a href="certificate/create-certificate.php">Create Certificate</a></li>
                        <li><a href="certificate/view-certificate.php">View Certificates</a></li>
                        <li><a href="certificate/bulk_certificate_actions.php">Manage Certificates</a></li>
                    </ul>
                </div>

                <div class="dashboard-card">
                    <h3>Syllabus Management</h3>
                    <ul class="feature-links">
                        <li><a href="syllabus/create-syllabus.php">Create Syllabus</a></li>
                        <li><a href="syllabus/view-syllabi.php">View Syllabi</a></li>
                        <!-- <li><a href="syllabus/manage-syllabi.php">Manage Syllabi</a></li> -->
                    </ul>
                </div>

                <!-- <div class="dashboard-card">
                    <h3>User Management</h3>
                    <ul class="feature-links">
                        <li><a href="manage_users.php">Manage Users</a></li>
                        <li><a href="user_reports.php">User Reports</a></li>
                        <li><a href="user_analytics.php">User Analytics</a></li>
                    </ul>
                </div>

                <div class="dashboard-card">
                    <h3>System Settings</h3>
                    <ul class="feature-links">
                        <li><a href="system_settings.php">General Settings</a></li>
                        <li><a href="backup_restore.php">Backup & Restore</a></li>
                        <li><a href="system_logs.php">System Logs</a></li>
                    </ul>
                </div> -->
            </div>
        </div>
    </div>
</body>
</html>