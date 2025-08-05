<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$message = '';
$error = '';
$syllabus = null;

// Get syllabus ID from URL
$syllabus_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($syllabus_id <= 0) {
    header('Location: view-syllabi.php');
    exit;
}

// Fetch syllabus data
try {
    $stmt = $pdo->prepare("SELECT * FROM syllabi WHERE id = ?");
    $stmt->execute([$syllabus_id]);
    $syllabus = $stmt->fetch();
    
    if (!$syllabus) {
        header('Location: view-syllabi.php');
        exit;
    }
    
    // Get associated certificates count
    $cert_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE syllabus_id = ?");
    $cert_stmt->execute([$syllabus_id]);
    $certificate_count = $cert_stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Error loading syllabus: ' . $e->getMessage();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, handle associated certificates
        if ($certificate_count > 0) {
            if (isset($_POST['delete_certificates']) && $_POST['delete_certificates'] == 'yes') {
                // Delete associated certificates
                $stmt = $pdo->prepare("DELETE FROM certificates WHERE syllabus_id = ?");
                $stmt->execute([$syllabus_id]);
            } else {
                // Set syllabus_id to NULL for associated certificates
                $stmt = $pdo->prepare("UPDATE certificates SET syllabus_id = NULL WHERE syllabus_id = ?");
                $stmt->execute([$syllabus_id]);
            }
        }
        
        // Delete the syllabus record
        $stmt = $pdo->prepare("DELETE FROM syllabi WHERE id = ?");
        $stmt->execute([$syllabus_id]);
        
        // Delete the PDF file if it exists
        if (file_exists($syllabus['syllabus_pdf'])) {
            unlink($syllabus['syllabus_pdf']);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect with success message
        header('Location: view-syllabi.php?deleted=1');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = 'Error deleting syllabus: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Syllabus</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 0 auto;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 30px;
        }

        .delete-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .delete-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .delete-title {
            color: #dc3545;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .delete-subtitle {
            color: #666;
            font-size: 16px;
        }

        .syllabus-info {
            background: #000;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #222;
        }

        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-weight: normal;
        }

        .info-value {
            color: #fff;
            text-align: right;
            flex: 1;
            margin-left: 20px;
        }

        .warning-section {
            background: #2d1810;
            border: 1px solid #8b4513;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .warning-title {
            color: #ffa500;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-icon {
            font-size: 20px;
        }

        .warning-text {
            color: #ffb366;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .certificate-options {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }

        .option-group {
            margin-bottom: 15px;
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .radio-option {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .radio-option:hover {
            background: #222;
        }

        .radio-option input[type="radio"] {
            margin-right: 12px;
            margin-top: 4px;
        }

        .option-label {
            flex: 1;
        }

        .option-title {
            color: #fff;
            font-weight: normal;
            margin-bottom: 5px;
        }

        .option-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        .danger-zone {
            background: #1a0d0d;
            border: 1px solid #4d1a1a;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .confirm-section {
            margin-bottom: 25px;
        }

        .confirm-checkbox {
            display: flex;
            align-items: center;
            background: #111;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
        }

        .confirm-checkbox input[type="checkbox"] {
            margin-right: 12px;
        }

        .confirm-text {
            color: #fff;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-danger {
            background: #dc3545;
            color: #fff;
            border: 1px solid #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #c82333;
        }

        .btn-danger:disabled {
            background: #6c757d;
            border-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .delete-container {
                margin: 20px;
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
            
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-value {
                text-align: left;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Delete Syllabus</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="view-syllabi.php">&larr; Back to Syllabi</a>
            </div>
            
            <div class="delete-container">
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="delete-header">
                    <div class="delete-icon">🗑️</div>
                    <h2 class="delete-title">Delete Syllabus</h2>
                    <p class="delete-subtitle">This action cannot be undone</p>
                </div>
                
                <div class="syllabus-info">
                    <div class="info-row">
                        <span class="info-label">Syllabus Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($syllabus['syllabus_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($syllabus['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value"><?php echo $syllabus['updated_at'] ? date('F j, Y \a\t g:i A', strtotime($syllabus['updated_at'])) : 'Never'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Associated Certificates:</span>
                        <span class="info-value"><?php echo $certificate_count; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">PDF File:</span>
                        <span class="info-value"><?php echo basename($syllabus['syllabus_pdf']); ?></span>
                    </div>
                </div>
                
                <?php if ($certificate_count > 0): ?>
                    <div class="warning-section">
                        <div class="warning-title">
                            <span class="warning-icon">⚠️</span>
                            Certificate Impact Warning
                        </div>
                        <div class="warning-text">
                            This syllabus is associated with <strong><?php echo $certificate_count; ?></strong> certificate(s). 
                            Please choose how to handle these certificates:
                        </div>
                        
                        <div class="certificate-options">
                            <div class="option-group">
                                <div class="radio-option">
                                    <input type="radio" id="keep_certificates" name="delete_certificates" value="no" checked>
                                    <div class="option-label">
                                        <div class="option-title">Keep Certificates (Recommended)</div>
                                        <div class="option-description">
                                            Certificates will be preserved but will no longer be linked to this syllabus. 
                                            This is the safer option as it maintains certificate validity.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="radio-option">
                                    <input type="radio" id="delete_certificates" name="delete_certificates" value="yes">
                                    <div class="option-label">
                                        <div class="option-title">Delete All Associated Certificates</div>
                                        <div class="option-description">
                                            <strong style="color: #dc3545;">Warning:</strong> This will permanently delete all 
                                            <?php echo $certificate_count; ?> certificate(s) associated with this syllabus. 
                                            This action cannot be undone.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="danger-zone">
                    <div class="warning-title">
                        <span class="warning-icon">🚨</span>
                        Danger Zone
                    </div>
                    <div class="warning-text">
                        The following will be permanently deleted:
                        <ul style="margin: 10px 0; padding-left: 20px; color: #ffb366;">
                            <li>Syllabus record from database</li>
                            <li>PDF file from server storage</li>
                            <?php if ($certificate_count > 0): ?>
                                <li id="certificate-deletion-note" style="display: none;">All associated certificates</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <form method="POST" id="deleteForm">
                    <div class="confirm-section">
                        <div class="confirm-checkbox">
                            <input type="checkbox" id="confirm_delete" name="confirm_delete" required>
                            <label for="confirm_delete" class="confirm-text">
                                I understand that this action is permanent and cannot be undone
                            </label>
                        </div>
                    </div>
                    
                    <?php if ($certificate_count > 0): ?>
                        <input type="hidden" id="delete_certificates_hidden" name="delete_certificates" value="no">
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-danger" id="deleteButton" disabled>
                            Delete Syllabus Permanently
                        </button>
                        <a href="view-syllabi.php" class="btn btn-secondary">Cancel</a>
                        <a href="view_syllabus_detail.php?id=<?php echo $syllabus['id']; ?>" class="btn">View Details</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Handle confirmation checkbox
        document.getElementById('confirm_delete').addEventListener('change', function() {
            document.getElementById('deleteButton').disabled = !this.checked;
        });

        <?php if ($certificate_count > 0): ?>
        // Handle certificate deletion options
        const certificateRadios = document.querySelectorAll('input[name="delete_certificates"]');
        const hiddenInput = document.getElementById('delete_certificates_hidden');
        const certificateNote = document.getElementById('certificate-deletion-note');
        const deleteButton = document.getElementById('deleteButton');

        certificateRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                hiddenInput.value = this.value;
                
                if (this.value === 'yes') {
                    certificateNote.style.display = 'list-item';
                    deleteButton.textContent = 'Delete Syllabus & All Certificates';
                    deleteButton.style.background = '#a71e2a';
                } else {
                    certificateNote.style.display = 'none';
                    deleteButton.textContent = 'Delete Syllabus Permanently';
                    deleteButton.style.background = '#dc3545';
                }
            });
        });
        <?php endif; ?>

        // Form submission confirmation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const isDeleteCertificates = <?php echo $certificate_count > 0 ? 'document.getElementById("delete_certificates_hidden").value === "yes"' : 'false'; ?>;
            
            let confirmMessage = 'Are you sure you want to delete this syllabus permanently?';
            
            if (isDeleteCertificates) {
                confirmMessage = 'Are you sure you want to delete this syllabus AND all <?php echo $certificate_count; ?> associated certificates? This action cannot be undone!';
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>