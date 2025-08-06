<?php
require_once 'config/database.php';

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
    
    // Create certificates table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        syllabus_id INT,
        syllabus_name VARCHAR(255),
        syllabus_pdf VARCHAR(500),
        certificate_image VARCHAR(500),
        issue_date DATE,
        certificate_code VARCHAR(100) UNIQUE,
        status VARCHAR(20) DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Add missing columns to existing certificates table if they don't exist
    $columns_to_add = [
        'email' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'syllabus_name' => 'VARCHAR(255)',
        'syllabus_pdf' => 'VARCHAR(500)',
        'issue_date' => 'DATE',
        'certificate_code' => 'VARCHAR(100)',
        'status' => 'VARCHAR(20) DEFAULT "active"'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE certificates ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            // Column might already exist, continue
        }
    }
    
    // Rename participant_name to name if it exists
    try {
        $pdo->exec("ALTER TABLE certificates CHANGE participant_name name VARCHAR(255) NOT NULL");
    } catch (PDOException $e) {
        // Column might not exist or already renamed, continue
    }
    
} catch (PDOException $e) {
    // Continue silently if tables already exist
}

// Get statistics
$total_certificates = 0;
$total_syllabi = 0;
$syllabus_stats = [];

try {
    // Get total active certificates
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE status = 'active'");
    $count_stmt->execute();
    $total_certificates = $count_stmt->fetchColumn();
    
    // Get total syllabi
    $syllabus_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM syllabi");
    $syllabus_count_stmt->execute();
    $total_syllabi = $syllabus_count_stmt->fetchColumn();
    
    // Get syllabus statistics
    $stats_stmt = $pdo->prepare("SELECT s.syllabus_name, COUNT(c.id) as certificate_count 
                                FROM syllabi s 
                                LEFT JOIN certificates c ON s.id = c.syllabus_id AND c.status = 'active'
                                GROUP BY s.id, s.syllabus_name 
                                ORDER BY certificate_count DESC");
    $stats_stmt->execute();
    $syllabus_stats = $stats_stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error loading statistics: ' . $e->getMessage();
}

// Search functionality with flexible matching
$search_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$search_email = isset($_GET['email']) ? trim($_GET['email']) : '';
$certificates = [];
$search_performed = false;

if (!empty($search_name) && !empty($search_email)) {
    $search_performed = true;
    
    try {
        // Flexible search with SOUNDEX for name matching and relaxed email matching
        $query = "SELECT * FROM certificates 
                  WHERE status = 'active' 
                  AND (
                      REPLACE(REPLACE(LOWER(name), ' ', ''), '.', '') LIKE REPLACE(REPLACE(LOWER(?), ' ', ''), '.', '')
                      OR SOUNDEX(name) = SOUNDEX(?)
                      OR LOWER(name) LIKE LOWER(?)
                  )
                  AND (
                      REPLACE(REPLACE(LOWER(email), ' ', ''), '.', '') = REPLACE(REPLACE(LOWER(?), ' ', ''), '.', '')
                      OR LOWER(email) LIKE LOWER(?)
                  )
                  ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($query);
        
        // Parameters for flexible matching
        $name_clean = "%{$search_name}%";
        $email_exact = $search_email;
        $email_like = "%{$search_email}%";
        
        $stmt->execute([
            $search_name,  // For exact name matching (spaces/dots removed)
            $search_name,  // For SOUNDEX matching
            $name_clean,   // For LIKE matching
            $search_email, // For exact email matching (spaces/dots removed)
            $email_like    // For LIKE email matching
        ]);
        
        $certificates = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = 'Error searching certificates: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #000;
            min-height: 100vh;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .search-card {
            background: #111;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(255,255,255,0.05);
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-items: stretch;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #ccc;
        }

        .form-group input {
            padding: 12px 16px;
            border: 2px solid #333;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            background: #222;
            color: #fff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-btn {
            padding: 12px 24px;
            background: #333;
            color: white;
            border: 1px solid #555;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .search-btn:hover {
            background: #555;
        }

        .stats-section {
            background: #111;
            border-radius: 12px;
            border: 1px solid #333;
            padding: 20px;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            background: #222;
            border-radius: 8px;
            border: 1px solid #333;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #ccc;
            font-size: 0.9rem;
        }

        .syllabus-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .syllabus-item {
            background: #222;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .syllabus-name {
            color: #fff;
            font-weight: 500;
        }

        .syllabus-count {
            background: #333;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .results-section {
            background: #111;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(255,255,255,0.05);
            border: 1px solid #333;
            overflow: hidden;
        }

        .results-header {
            background: #222;
            color: white;
            padding: 20px 30px;
            border-bottom: 1px solid #333;
        }

        .results-header h2 {
            font-size: 1.5rem;
        }

        .no-results {
            text-align: center;
            padding: 60px 30px;
            color: #ccc;
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: #fff;
        }

       

        .certificate-card {
            border: 1px solid #333;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            background: #222;
        }

        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(255,255,255,0.1);
        }

        .certificate-header {
            background: #111;
            padding: 20px;
            border-bottom: 1px solid #333;
        }

        .certificate-code {
            font-size: 1.2rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
        }

        .certificate-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .certificate-body {
            padding: 20px;
        }

        .certificate-info {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 500;
            color: #ccc;
            margin-bottom: 5px;
        }

        .info-value {
            color: #fff;
            font-size: 1.05rem;
        }

        .certificate-image {
            text-align: center;
            margin-top: 20px;
        }

        .certificate-image img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .view-certificate-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.3s;
            border: 1px solid #555;
        }

        .view-certificate-btn:hover {
            background: #555;
        }

        .hidden-link {
            color: inherit;
            text-decoration: none;
            cursor: text;
        }

        .hidden-link:hover {
            color: inherit;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .certificate-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.6rem;
            }
            
            .search-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Certificate Verification Port<a href="dashboard.php" class="hidden-link">a</a>l</h1>
        </div>

        <div class="search-card">
            <form method="GET" action="" class="search-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" 
                           placeholder="Enter your full name"
                           value="<?php echo htmlspecialchars($search_name); ?>"
                           required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email address"
                           value="<?php echo htmlspecialchars($search_email); ?>"
                           required>
                </div>
                <button type="submit" class="search-btn">🔍 Search Certificates</button>
            </form>
        </div>

        <!-- <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($total_certificates); ?></div>
                    <div class="stat-label">Total Certificates</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($total_syllabi); ?></div>
                    <div class="stat-label">Total Syllabi</div>
                </div>
            </div>
            
            <?php if (!empty($syllabus_stats)): ?>
                <div class="syllabus-list">
                    <?php foreach ($syllabus_stats as $stat): ?>
                        <div class="syllabus-item">
                            <span class="syllabus-name"><?php echo htmlspecialchars($stat['syllabus_name']); ?></span>
                            <span class="syllabus-count"><?php echo $stat['certificate_count']; ?> certificates</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div> -->

        <?php if ($search_performed): ?>
            <div class="results-section">
                

                <?php if (empty($certificates)): ?>
                    <div class="no-results">
                        <h3>No certificates found</h3>
                        
                    </div>
                <?php else: ?>
                    <div class="certificate-grid">
                        <?php foreach ($certificates as $certificate): ?>
                            <div class="certificate-card">
                                <div class="certificate-header">
                                    <div class="certificate-code"><?php echo htmlspecialchars($certificate['certificate_code']); ?></div>
                                    <span class="certificate-status status-<?php echo $certificate['status']; ?>">
                                        <?php echo htmlspecialchars($certificate['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="certificate-body">
                                    <div class="certificate-info">
                                        <div class="info-label">Student Name:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($certificate['name']); ?></div>
                                    </div>
                                    
                                    <div class="certificate-info">
                                        <div class="info-label">Email:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($certificate['email']); ?></div>
                                    </div>
                                    
                                    <div class="certificate-info">
                                        <div class="info-label">Course/Syllabus:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($certificate['syllabus_name']); ?></div>
                                    </div>
                                    
                                    <div class="certificate-info">
                                        <div class="info-label">Issue Date:</div>
                                        <div class="info-value"><?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></div>
                                    </div>

                                    <?php if ($certificate['certificate_image']): ?>
                                        <div class="certificate-image">
                                            <?php 
                                            $ext = strtolower(pathinfo($certificate['certificate_image'], PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                            ?>
                                                <img src="certificate/<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                     alt="Certificate Preview" style="max-height: 200px;">
                                                <br>
                                                <a href="certificate/<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                   target="_blank" class="view-certificate-btn">📄 View Full Certificate</a>
                                                <a href="certificate/<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                   download="<?php echo htmlspecialchars($certificate['certificate_code'] . '_certificate.' . $ext); ?>" 
                                                   class="view-certificate-btn">💾 Download Certificate</a>
                                                   
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                   target="_blank" class="view-certificate-btn">📄 Download Certificate PDF</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>