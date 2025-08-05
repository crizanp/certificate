<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$message = '';
$error = '';
$certificate = null;

// Get certificate ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: view-certificates.php');
    exit();
}

$certificate_id = (int)$_GET['id'];

// Load certificate data
try {
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
    $stmt->execute([$certificate_id]);
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        $error = 'Certificate not found.';
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete']) && $certificate) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete certificate from database
        $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
        $stmt->execute([$certificate_id]);
        
        // Delete certificate image file if it exists
        if ($certificate['certificate_image'] && file_exists($certificate['certificate_image'])) {
            unlink($certificate['certificate_image']);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect with success message
        header('Location: view-certificates.php?deleted=1');
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollback();
        $error = 'Error deleting certificate: ' . $e->getMessage();
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel'])) {
    header('Location: view-certificates.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Certificate</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        .delete-confirmation {
            max-width: 600px;
            margin: 0 auto;
            background: #333;
            color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        .warning-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .certificate-info {
            background: #444;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
            border-left: 4px solid #dc3545;
        }
        .certificate-info h3 {
            color: white;
            margin-top: 0;
        }
        .info-row {
            margin-bottom: 10px;
            color: white;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
            color: #ccc;
        }
        .delete-buttons {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            border: none;
            font-size: 16px;
            transition: transform 0.3s;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        .warning-text {
            color: #dc3545;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }
        .consequences {
            background: #2d2d2d;
            border: 1px solid #555;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .consequences h4 {
            color: #ffc107;
            margin-top: 0;
        }
        .consequences ul {
            color: #ccc;
            margin: 10px 0;
        }
        .consequences p {
            color: #ccc;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #28a745;
            color: white;
        }
        .status-inactive {
            background: #6c757d;
            color: white;
        }
        .status-revoked {
            background: #dc3545;
            color: white;
        }
        .back-link a {
            color: #8fa5ff;
            text-decoration: none;
        }
        .back-link a:hover {
            color: #a8b7ff;
        }
        .message.error {
            background: #721c24;
            color: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Delete Certificate</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="view-certificates.php">&larr; Back to Certificates</a>
                <?php if ($certificate): ?>
                    | <a href="view_certificate_detail.php?id=<?php echo $certificate_id; ?>">View Details</a>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!$certificate): ?>
                <div class="message error">Certificate not found.</div>
            <?php else: ?>
                <div class="delete-confirmation">
                    <div class="warning-icon">⚠️</div>
                    <h2>Delete Certificate</h2>
                    <div class="warning-text">Are you sure you want to delete this certificate?</div>
                    
                    <div class="certificate-info">
                        <h3>Certificate Details</h3>
                        <div class="info-row">
                            <span class="info-label">Certificate Code:</span>
                            <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Student Name:</span>
                            <?php echo htmlspecialchars($certificate['name']); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <?php echo htmlspecialchars($certificate['email']); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Syllabus:</span>
                            <?php echo htmlspecialchars($certificate['syllabus_name']); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Issue Date:</span>
                            <?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="status-badge status-<?php echo $certificate['status']; ?>">
                                <?php echo htmlspecialchars($certificate['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="consequences">
                        <h4>Warning: This action cannot be undone!</h4>
                        <p>Deleting this certificate will:</p>
                        <ul>
                            <li>Permanently remove the certificate record from the database</li>
                            <li>Delete the associated certificate image/PDF file</li>
                            <li>Make the certificate code invalid for verification</li>
                            <li>Remove all certificate history and metadata</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <div class="delete-buttons">
                            <button type="submit" name="confirm_delete" value="1" class="btn btn-danger"
                                    onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                                Yes, Delete Certificate
                            </button>
                            <button type="submit" name="cancel" value="1" class="btn btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Add additional confirmation for delete button
        document.querySelector('button[name="confirm_delete"]').addEventListener('click', function(e) {
            const confirmed = confirm('Are you absolutely sure you want to delete this certificate?\n\nThis action is PERMANENT and cannot be undone!\n\nThe certificate file will be permanently deleted from the server.');
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
            
            // Second confirmation
            const doubleConfirm = confirm('FINAL CONFIRMATION:\n\nYou are about to permanently delete certificate:\n' + 
                '<?php echo addslashes($certificate['certificate_code'] ?? ''); ?>\n\n' +
                'Student: <?php echo addslashes($certificate['name'] ?? ''); ?>\n\n' +
                'Click OK to proceed with deletion or Cancel to abort.');
            
            if (!doubleConfirm) {
                e.preventDefault();
                return false;
            }
    </script>
</body>
</html>