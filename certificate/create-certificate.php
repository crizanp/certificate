<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$message = '';
$error = '';

// Get all syllabi for dropdown
$syllabi = [];
try {
    $stmt = $pdo->query("SELECT id, syllabus_name, syllabus_pdf FROM syllabi ORDER BY syllabus_name");
    $syllabi = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading syllabi: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $syllabus_id = $_POST['syllabus_id'];
    $issue_date = $_POST['issue_date'];
    
    // Validate input
    if (empty($name)) {
        $error = 'Name is required.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email is required.';
    } elseif (empty($syllabus_id)) {
        $error = 'Please select a syllabus.';
    } elseif (!isset($_FILES['certificate_image']) || $_FILES['certificate_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a certificate image.';
    } else {
        // Check if email already has a certificate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE email = ?");
        $stmt->execute([$email]);
        $existing_count = $stmt->fetchColumn();
        
        if ($existing_count > 0) {
            $error = 'A certificate has already been issued for this email address.';
        } else {
        // Get syllabus info
        $stmt = $pdo->prepare("SELECT syllabus_name, syllabus_pdf FROM syllabi WHERE id = ?");
        $stmt->execute([$syllabus_id]);
        $syllabus = $stmt->fetch();
        
        if (!$syllabus) {
            $error = 'Selected syllabus not found.';
        } else {
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
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    try {
                        // Generate certificate code
                        $certificate_code = 'CERT-' . strtoupper(uniqid());
                        
                        // Insert into database
                        $stmt = $pdo->prepare("INSERT INTO certificates (name, email, syllabus_id, syllabus_name, syllabus_pdf, certificate_image, issue_date, certificate_code, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $name, 
                            $email, 
                            $syllabus_id, 
                            $syllabus['syllabus_name'], 
                            $syllabus['syllabus_pdf'], 
                            $filepath, 
                            $issue_date, 
                            $certificate_code, 
                            $_SESSION['admin_id']
                        ]);
                        
                        $message = 'Certificate created successfully! Certificate Code: ' . $certificate_code;
                        // Clear form data
                        $_POST = array();
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                        // Delete uploaded file if database insert fails
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                    }
                } else {
                    $error = 'Failed to upload certificate image.';
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
    <title>Create Certificate</title>
    <link rel="stylesheet" href="../css/certificate.css">
   
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Create Certificate</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="../dashboard.php">&larr; Back to Dashboard</a>
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
                        <label for="name">Student Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Student Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="syllabus_id">Select Syllabus *</label>
                        <select id="syllabus_id" name="syllabus_id" required>
                            <option value="">-- Select Syllabus --</option>
                            <?php foreach ($syllabi as $syllabus): ?>
                                <option value="<?php echo $syllabus['id']; ?>" 
                                        data-pdf="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>"
                                        <?php echo (isset($_POST['syllabus_id']) && $_POST['syllabus_id'] == $syllabus['id']) ? 'selected' : ''; ?>>
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
                               value="<?php echo isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d'); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="certificate_image">Certificate Image *</label>
                        <div class="file-upload">
                            <input type="file" id="certificate_image" name="certificate_image" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                Upload certificate image (JPG, PNG, GIF, or PDF - Max size: 5MB)
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Create Certificate</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
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
                    <strong>Selected file:</strong> ${file.name}<br>
                    <strong>Size:</strong> ${fileSize} MB
                `;
                uploadDiv.appendChild(fileInfo);
            }
        });
    </script>
</body>
</html>