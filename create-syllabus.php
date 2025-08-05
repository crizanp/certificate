<?php
require_once 'config/database.php';
require_once 'includes/session.php';
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        .file-upload:hover {
            border-color: #4CAF50;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            margin-bottom: 20px;
        }
        .back-link a {
            color: #4CAF50;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
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
                <a href="dashboard.php">&larr; Back to Dashboard</a>
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
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
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