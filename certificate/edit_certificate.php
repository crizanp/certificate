<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$message = '';
$error = '';
$certificate = null;

// Get certificate ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: view-certificate.php');
    exit();
}

$certificate_id = (int)$_GET['id'];

// Get all syllabi for dropdown
$syllabi = [];
try {
    $stmt = $pdo->query("SELECT id, syllabus_name, syllabus_pdf FROM syllabi ORDER BY syllabus_name");
    $syllabi = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading syllabi: ' . $e->getMessage();
}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $certificate) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $syllabus_id = $_POST['syllabus_id'];
    $issue_date = $_POST['issue_date'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($name)) {
        $error = 'Name is required.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email is required.';
    } elseif (empty($syllabus_id)) {
        $error = 'Please select a syllabus.';
    } elseif (!in_array($status, ['active', 'revoked'])) {
        $error = 'Invalid status selected.';
    } else {
        // Get syllabus info
            $stmt = $pdo->prepare("SELECT syllabus_name, syllabus_pdf FROM syllabi WHERE id = ?");
            $stmt->execute([$syllabus_id]);
            $syllabus = $stmt->fetch();
            
            if (!$syllabus) {
                $error = 'Selected syllabus not found.';
            } else {
                $update_image = false;
                $filepath = $certificate['certificate_image']; // Keep existing image by default
                
                // Handle file upload if new file is provided
                if (isset($_FILES['certificate_image']) && $_FILES['certificate_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['certificate_image'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    // Validate file type
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                    if (!in_array($file_ext, $allowed_extensions)) {
                        $error = 'Only JPG, PNG, GIF, or PDF files are allowed for certificate image.';
                    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                        $error = 'File size must be less than 5MB.';
                    } else {
                        // Create upload directory if it doesn't exist
                        $upload_dir = 'uploads/certificates/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $filename = uniqid() . '_' . time() . '.' . $file_ext;
                        $new_filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $new_filepath)) {
                            $update_image = true;
                            $filepath = $new_filepath;
                        } else {
                            $error = 'Failed to upload certificate image.';
                        }
                    }
                }
                
                if (!$error) {
                    try {
                        // Update certificate in database
                        $stmt = $pdo->prepare("UPDATE certificates SET name = ?, email = ?, syllabus_id = ?, syllabus_name = ?, syllabus_pdf = ?, certificate_image = ?, issue_date = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([
                            $name, 
                            $email, 
                            $syllabus_id, 
                            $syllabus['syllabus_name'], 
                            $syllabus['syllabus_pdf'], 
                            $filepath, 
                            $issue_date, 
                            $status,
                            $certificate_id
                        ]);
                        
                        // Delete old image if new one was uploaded
                        if ($update_image && $certificate['certificate_image'] && file_exists($certificate['certificate_image'])) {
                            unlink($certificate['certificate_image']);
                        }
                        
                        $message = 'Certificate updated successfully!';
                        
                        // Reload certificate data
                        $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
                        $stmt->execute([$certificate_id]);
                        $certificate = $stmt->fetch();
                        
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                        // Delete new uploaded file if database update fails
                        if ($update_image && file_exists($filepath)) {
                            unlink($filepath);
                        }
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
    <title>Edit Certificate</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        .current-image {
            max-width: 200px;
            max-height: 150px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }
        .file-upload-section {
            margin-top: 15px;
        }
        .current-file-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Edit Certificate</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="./view-certificate.php">&larr; Back to Certificates</a> |
                <a href="./view_certificate_detail.php?id=<?php echo $certificate_id; ?>">View Details</a>
            </div>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!$certificate): ?>
                <div class="message error">Certificate not found.</div>
            <?php else: ?>
                <div class="form-container">
                    <?php if ($message): ?>
                        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name">Student Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($certificate['name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Student Email *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($certificate['email']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="syllabus_id">Select Syllabus *</label>
                            <select id="syllabus_id" name="syllabus_id" required>
                                <option value="">-- Select Syllabus --</option>
                                <?php foreach ($syllabi as $syllabus): ?>
                                    <option value="<?php echo $syllabus['id']; ?>" 
                                            data-pdf="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>"
                                            <?php echo ($certificate['syllabus_id'] == $syllabus['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($syllabus['syllabus_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="syllabus-info" class="syllabus-info">
                                <strong>Syllabus PDF:</strong> <span id="syllabus-pdf-name"></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="issue_date">Issue Date *</label>
                            <input type="date" id="issue_date" name="issue_date" 
                                   value="<?php echo $certificate['issue_date']; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo ($certificate['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="revoked" <?php echo ($certificate['status'] === 'revoked') ? 'selected' : ''; ?>>Revoked</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="certificate_image">Certificate Image</label>
                            
                            <div class="current-file-info">
                                <strong>Current Certificate:</strong>
                                <?php if ($certificate['certificate_image']): ?>
                                    <?php 
                                    $ext = strtolower(pathinfo($certificate['certificate_image'], PATHINFO_EXTENSION));
                                    $filename = basename($certificate['certificate_image']);
                                    ?>
                                    <div style="margin-top: 10px;">
                                        <p>File: <?php echo htmlspecialchars($filename); ?></p>
                                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                 alt="Current Certificate" class="current-image">
                                        <?php else: ?>
                                            <p><em>PDF file - <a href="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" target="_blank">View PDF</a></em></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <em>No certificate image</em>
                                <?php endif; ?>
                            </div>
                            
                            <div class="file-upload-section">
                                <div class="file-upload">
                                    <input type="file" id="certificate_image" name="certificate_image" 
                                           accept=".jpg,.jpeg,.png,.gif,.pdf">
                                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                        Upload new certificate image (JPG, PNG, GIF, or PDF - Max size: 5MB)<br>
                                        <em>Leave empty to keep current image</em>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">Update Certificate</button>
                            <a href="view_certificate_detail.php?id=<?php echo $certificate_id; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show syllabus info when selected
        document.getElementById('syllabus_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const syllabusInfo = document.getElementById('syllabus-info');
            const pdfName = document.getElementById('syllabus-pdf-name');
            
            if (selectedOption.value) {
                const pdfPath = selectedOption.getAttribute('data-pdf');
                const fileName = pdfPath ? pdfPath.split('/').pop() : 'N/A';
                pdfName.textContent = fileName;
                syllabusInfo.style.display = 'block';
            } else {
                syllabusInfo.style.display = 'none';
            }
        });

        // Trigger syllabus info display on page load
        document.getElementById('syllabus_id').dispatchEvent(new Event('change'));

        // File upload preview
        document.getElementById('certificate_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const uploadDiv = e.target.parentNode;
                const existingInfo = uploadDiv.querySelector('.file-info');
                if (existingInfo) {
                    existingInfo.remove();
                }
                
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                fileInfo.style.marginTop = '10px';
                fileInfo.innerHTML = `
                    <strong>New file selected:</strong> ${file.name}<br>
                    <strong>Size:</strong> ${fileSize} MB
                `;
                uploadDiv.appendChild(fileInfo);
            }
        });
    </script>
</body>
</html>