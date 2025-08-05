<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$syllabus_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($syllabus_id <= 0) {
    header('Location: view-syllabi.php');
    exit;
}

try {
    // Get syllabus details with creator info
    $stmt = $pdo->prepare("
        SELECT s.*, au.username as created_by_name, au.email as created_by_email 
        FROM syllabi s 
        LEFT JOIN admin_users au ON s.created_by = au.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$syllabus_id]);
    $syllabus = $stmt->fetch();
    
    if (!$syllabus) {
        header('Location: view-syllabi.php');
        exit;
    }
    
    // Get certificates associated with this syllabus
    $cert_stmt = $pdo->prepare("
        SELECT c.*, au.username as created_by_name 
        FROM certificates c 
        LEFT JOIN admin_users au ON c.created_by = au.id 
        WHERE c.syllabus_id = ? 
        ORDER BY c.created_at DESC
    ");
    $cert_stmt->execute([$syllabus_id]);
    $certificates = $cert_stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error loading syllabus details: ' . $e->getMessage();
    $syllabus = null;
    $certificates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syllabus Details</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        .detail-container {
            background: #1a1a1a;
            border-radius: 8px;
            border: 1px solid #333;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .detail-header {
            background: #111;
            padding: 20px;
            border-bottom: 1px solid #333;
        }

        .detail-title {
            font-size: 24px;
            color: #fff;
            margin-bottom: 10px;
            font-weight: normal;
        }

        .detail-meta {
            color: #666;
            font-size: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .detail-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-body {
            padding: 20px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: normal;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }

        .detail-content {
            color: #ccc;
            line-height: 1.6;
        }

        .file-info-card {
            background: #000;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .file-icon {
            font-size: 32px;
            color: #666;
        }

        .file-details h4 {
            color: #fff;
            margin-bottom: 5px;
            font-weight: normal;
        }

        .file-meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #000;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            color: #fff;
            font-weight: normal;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .certificates-section {
            background: #1a1a1a;
            border-radius: 8px;
            border: 1px solid #333;
            overflow: hidden;
        }

        .certificates-header {
            background: #111;
            padding: 15px 20px;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .certificates-header h3 {
            color: #fff;
            margin: 0;
            font-weight: normal;
        }

        .certificates-list {
            padding: 15px;
        }

        .certificate-item {
            background: #000;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .certificate-info h4 {
            color: #fff;
            margin-bottom: 5px;
            font-weight: normal;
        }

        .certificate-meta {
            color: #666;
            font-size: 13px;
        }

        .certificate-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .no-certificates {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .no-certificates h4 {
            color: #fff;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .file-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .certificate-item {
                flex-direction: column;
                align-items: stretch;
            }
            
            .certificate-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Syllabus Details</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="view-syllabi.php">&larr; Back to Syllabi</a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($syllabus): ?>
                <div class="detail-container">
                    <div class="detail-header">
                        <div class="detail-title"><?php echo htmlspecialchars($syllabus['syllabus_name']); ?></div>
                        <div class="detail-meta">
                            <span>📅 Created: <?php echo date('F j, Y g:i A', strtotime($syllabus['created_at'])); ?></span>
                            <?php if ($syllabus['updated_at'] && $syllabus['updated_at'] !== $syllabus['created_at']): ?>
                                <span>✏️ Updated: <?php echo date('F j, Y g:i A', strtotime($syllabus['updated_at'])); ?></span>
                            <?php endif; ?>
                            <?php if ($syllabus['created_by_name']): ?>
                                <span>👤 By: <?php echo htmlspecialchars($syllabus['created_by_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-body">
                        <!-- Statistics -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo count($certificates); ?></div>
                                <div class="stat-label">Certificates</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">
                                    <?php 
                                    if (file_exists($syllabus['syllabus_pdf'])) {
                                        echo number_format(filesize($syllabus['syllabus_pdf']) / 1024, 1);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">File Size (KB)</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number">PDF</div>
                                <div class="stat-label">File Type</div>
                            </div>
                        </div>

                        <!-- Description -->
                        <?php if ($syllabus['description']): ?>
                            <div class="detail-section">
                                <h3>Description</h3>
                                <div class="detail-content">
                                    <?php echo nl2br(htmlspecialchars($syllabus['description'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- File Information -->
                        <div class="detail-section">
                            <h3>Syllabus File</h3>
                            <div class="file-info-card">
                                <div class="file-icon">📄</div>
                                <div class="file-details" style="flex: 1;">
                                    <h4><?php echo basename($syllabus['syllabus_pdf']); ?></h4>
                                    <div class="file-meta">
                                        <?php if (file_exists($syllabus['syllabus_pdf'])): ?>
                                            Size: <?php echo number_format(filesize($syllabus['syllabus_pdf']) / 1024, 1); ?> KB
                                        <?php else: ?>
                                            File not found
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-actions">
                                        <a href="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>" 
                                           target="_blank" class="btn btn-small">View PDF</a>
                                        <a href="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>" 
                                           download class="btn btn-small btn-download">Download</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="edit_syllabus.php?id=<?php echo $syllabus['id']; ?>" class="btn btn-edit">Edit Syllabus</a>
                            <a href="delete_syllabus.php?id=<?php echo $syllabus['id']; ?>" class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to delete this syllabus? This will also affect associated certificates.')">Delete Syllabus</a>
                            <a href="../certificate/create-certificate.php?syllabus_id=<?php echo $syllabus['id']; ?>" class="btn">Create Certificate</a>
                        </div>
                    </div>
                </div>

                <!-- Associated Certificates -->
                <div class="certificates-section">
                    <div class="certificates-header">
                        <h3>Associated Certificates (<?php echo count($certificates); ?>)</h3>
                        <a href="../certificate/create-certificate.php?syllabus_id=<?php echo $syllabus['id']; ?>" class="btn btn-small">Add Certificate</a>
                    </div>
                    
                    <div class="certificates-list">
                        <?php if (empty($certificates)): ?>
                            <div class="no-certificates">
                                <h4>No certificates found</h4>
                                <p>No certificates have been created for this syllabus yet.</p>
                                <a href="../certificate/create-certificate.php?syllabus_id=<?php echo $syllabus['id']; ?>" class="btn">Create First Certificate</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <div class="certificate-item">
                                    <div class="certificate-info">
                                        <h4><?php echo htmlspecialchars($cert['name']); ?></h4>
                                        <div class="certificate-meta">
                                            Course: <?php echo htmlspecialchars($cert['syllabus_name']); ?><br>
                                            Created: <?php echo date('M j, Y', strtotime($cert['created_at'])); ?>
                                            <?php if ($cert['created_by_name']): ?>
                                                by <?php echo htmlspecialchars($cert['created_by_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="certificate-actions">
                                        <a href="../certificate/view_certificate_detail.php?id=<?php echo $cert['id']; ?>" class="btn btn-small btn-view">View</a>
                                        <a href="../certificate/edit_certificate.php?id=<?php echo $cert['id']; ?>" class="btn btn-small btn-edit">Edit</a>
                                        <a href="../certificate/generate_certificate.php?id=<?php echo $cert['id']; ?>" class="btn btn-small" target="_blank">Generate PDF</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>