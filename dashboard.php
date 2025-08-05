<?php
require_once 'config/database.php';
require_once 'includes/session.php';
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