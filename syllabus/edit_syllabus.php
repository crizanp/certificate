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

// Fetch existing syllabus data
try {
    $stmt = $pdo->prepare("SELECT * FROM syllabi WHERE id = ?");
    $stmt->execute([$syllabus_id]);
    $syllabus = $stmt->fetch();
    
    if (!$syllabus) {
        header('Location: view-syllabi.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Error loading syllabus: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $syllabus_name = trim($_POST['syllabus_name']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($syllabus_name)) {
        $error = 'Syllabus name is required.';
    } else {
        $update_pdf = false;
        $new_filepath = $syllabus['syllabus_pdf']; // Keep existing file by default
        
        // Check if new PDF is uploaded
        if (isset($_FILES['syllabus_pdf']) && $_FILES['syllabus_pdf']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['syllabus_pdf'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            if ($file_ext !== 'pdf') {
                $error = 'Only PDF files are allowed.';
            } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                $error = 'File size must be less than 10MB.';
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = 'uploads/syllabi/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $filename = uniqid() . '_' . time() . '.pdf';
                $new_filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $new_filepath)) {
                    $update_pdf = true;
                } else {
                    $error = 'Failed to upload file.';
                }
            }
        }
        
        if (empty($error)) {
            try {
                // Update database
                $stmt = $pdo->prepare("UPDATE syllabi SET syllabus_name = ?, syllabus_pdf = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$syllabus_name, $new_filepath, $description, $syllabus_id]);
                
                // Delete old PDF file if new one was uploaded
                if ($update_pdf && file_exists($syllabus['syllabus_pdf']) && $syllabus['syllabus_pdf'] !== $new_filepath) {
                    unlink($syllabus['syllabus_pdf']);
                }
                
                $message = 'Syllabus updated successfully!';
                
                // Refresh syllabus data
                $stmt = $pdo->prepare("SELECT * FROM syllabi WHERE id = ?");
                $stmt->execute([$syllabus_id]);
                $syllabus = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
                // Delete uploaded file if database update fails
                if ($update_pdf && file_exists($new_filepath)) {
                    unlink($new_filepath);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Syllabus</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        /* Additional styling for textarea */
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 14px;
            background-color: #000;
            color: #fff;
            transition: border-color 0.3s;
            resize: vertical;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.4;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #fff;
        }

        .form-group textarea::placeholder {
            color: #666;
        }

        .current-file {
            background: #111;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 12px;
            margin-top: 8px;
            color: #ccc;
            font-size: 14px;
        }

        .current-file strong {
            color: #fff;
        }

        .file-info-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .pdf-icon {
            font-size: 20px;
            color: #666;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            color: #fff;
            font-weight: normal;
        }

        .file-size {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Edit Syllabus</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="view-syllabi.php">&larr; Back to Syllabi</a>
            </div>
            
            <div class="form-container">
                <?php if ($message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="syllabus_name">Syllabus Name *</label>
                        <input type="text" id="syllabus_name" name="syllabus_name" 
                               value="<?php echo htmlspecialchars($syllabus['syllabus_name']); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Enter syllabus description (optional)"><?php echo htmlspecialchars($syllabus['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="syllabus_pdf">Syllabus PDF</label>
                        
                        <!-- Current file info -->
                        <div class="current-file">
                            <strong>Current File:</strong>
                            <div class="file-info-section">
                                <div class="pdf-icon">📄</div>
                                <div class="file-details">
                                    <div class="file-name"><?php echo basename($syllabus['syllabus_pdf']); ?></div>
                                    <div class="file-size">
                                        <?php 
                                        if (file_exists($syllabus['syllabus_pdf'])) {
                                            echo number_format(filesize($syllabus['syllabus_pdf']) / 1024, 1) . ' KB';
                                        } else {
                                            echo 'File not found';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>" 
                                   target="_blank" class="btn btn-small">View Current</a>
                            </div>
                        </div>
                        
                        <!-- Upload new file -->
                        <div class="file-upload" style="margin-top: 15px;">
                            <input type="file" id="syllabus_pdf" name="syllabus_pdf" accept=".pdf">
                            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                Upload new PDF file to replace current one (Max size: 10MB) - Leave empty to keep current file
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Update Syllabus</button>
                        <a href="view-syllabi.php" class="btn btn-secondary">Cancel</a>
                        <a href="view_syllabus_detail.php?id=<?php echo $syllabus['id']; ?>" class="btn btn-view">View Details</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload preview
        document.getElementById('syllabus_pdf').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadDiv = e.target.parentNode;
            const existingInfo = uploadDiv.querySelector('.file-info');
            
            if (existingInfo) {
                existingInfo.remove();
            }
            
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                fileInfo.style.marginTop = '10px';
                fileInfo.style.padding = '10px';
                fileInfo.style.background = '#111';
                fileInfo.style.border = '1px solid #333';
                fileInfo.style.borderRadius = '4px';
                fileInfo.innerHTML = `
                    <strong style="color: #fff;">New file selected:</strong><br>
                    <span style="color: #ccc;">${file.name}</span><br>
                    <span style="color: #666; font-size: 12px;">Size: ${fileSize} MB</span>
                `;
                uploadDiv.appendChild(fileInfo);
            }
        });
    </script>
</body>
</html>