<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$certificate = null;
$error = '';

// Get certificate ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid certificate ID.';
} else {
    $certificate_id = (int)$_GET['id'];
    
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Details</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        
        .certificate-detail {
            max-width: 800px;
            color: #000;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            width: 200px;
            color: #333;
        }
        .detail-value {
            flex: 1;
            color: #666;
        }
        .certificate-image-container {
            text-align: center;
            margin: 20px 0;
        }
        .certificate-image-large {
            max-width: 100%;
            max-height: 500px;
            border: 2px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-revoked {
            background-color: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;

        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .download-link {
            color: #007bff;
            text-decoration: none;
        }
        .download-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Certificate Details</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="./view-certificate.php">&larr; Back to Certificates</a> |
                <a href="./bulk_certificate_actions.php">Bulk Actions</a>
            </div>
            
            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                <div class="message success">Certificate has been successfully deleted.</div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($certificate): ?>
                <div class="certificate-detail">
                    <h2>Certificate Information</h2>
                    
                    <div class="detail-row">
                        <div class="detail-label">Certificate Code:</div>
                        <div class="detail-value"><strong><?php echo htmlspecialchars($certificate['certificate_code']); ?></strong></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Student Name:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($certificate['name']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($certificate['email']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Syllabus:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($certificate['syllabus_name']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Syllabus PDF:</div>
                        <div class="detail-value">
                            <?php if ($certificate['syllabus_pdf']): ?>
                                <a href="<?php echo htmlspecialchars($certificate['syllabus_pdf']); ?>" target="_blank" class="download-link">
                                    View Syllabus PDF
                                </a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Issue Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $certificate['status']; ?>">
                                <?php echo htmlspecialchars($certificate['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Created:</div>
                        <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($certificate['created_at'])); ?></div>
                    </div>
                    
                    <?php if ($certificate['updated_at']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated:</div>
                        <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($certificate['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($certificate['certificate_image']): ?>
                    <div class="certificate-image-container">
                        <h3>Certificate Image</h3>
                        <?php 
                        $ext = strtolower(pathinfo($certificate['certificate_image'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                        ?>
                            <img src="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                 alt="Certificate" class="certificate-image-large">
                        <?php else: ?>
                            <div style="padding: 40px; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px;">
                                <p><strong>PDF Certificate</strong></p>
                                <a href="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                   target="_blank" class="btn btn-primary">View PDF</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="edit_certificate.php?id=<?php echo $certificate['id']; ?>" class="btn btn-warning">
                            Edit Certificate
                        </a>
                        <a href="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                           target="_blank" class="btn btn-primary">
                            Download Certificate
                        </a>
                        <a href="delete_certificate.php?id=<?php echo $certificate['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to delete this certificate? This action cannot be undone.')">
                            Delete Certificate
                        </a>
                        <a href="view-certificate.php" class="btn btn-secondary">
                            Back to List
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>