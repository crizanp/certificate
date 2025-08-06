<?php
require_once '../config/database.php';
require_once '../includes/session.php';

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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Continue silently if tables already exist
}

requireLogin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $syllabus_name = trim($_POST['syllabus_name']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($syllabus_name)) {
        $error = 'Syllabus name is required.';
    } elseif (!isset($_FILES['syllabus_pdf']) || $_FILES['syllabus_pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a PDF file.';
    } else {
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
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    // Insert into database
                    $stmt = $pdo->prepare("INSERT INTO syllabi (syllabus_name, syllabus_pdf, description, created_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$syllabus_name, $filepath, $description, $_SESSION['admin_id']]);
                    
                    $message = 'Syllabus created successfully!';
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
                $error = 'Failed to upload file.';
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
    <title>Create Syllabus</title>
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Create Syllabus</h1>
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
                        <label for="syllabus_name">Syllabus Name *</label>
                        <input type="text" id="syllabus_name" name="syllabus_name" 
                               value="<?php echo isset($_POST['syllabus_name']) ? htmlspecialchars($_POST['syllabus_name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Enter syllabus description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="syllabus_pdf">Syllabus PDF *</label>
                        <div class="file-upload">
                            <input type="file" id="syllabus_pdf" name="syllabus_pdf" accept=".pdf" required>
                            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                                Upload PDF file (Max size: 10MB)
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Create Syllabus</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload preview
        document.getElementById('syllabus_pdf').addEventListener('change', function(e) {
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