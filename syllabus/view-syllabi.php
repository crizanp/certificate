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
} catch (PDOException $e) {
    // Continue silently if tables already exist
}

requireLogin();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with search
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE syllabus_name LIKE ? OR description LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM syllabi $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Get syllabi with pagination
    $query = "SELECT s.*, au.username as created_by_name FROM syllabi s 
              LEFT JOIN admin_users au ON s.created_by = au.id 
              $where_clause ORDER BY s.created_at DESC LIMIT $records_per_page OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $syllabi = $stmt->fetchAll();

    // Get certificate counts for each syllabus
    $syllabus_stats = [];
    if (!empty($syllabi)) {
        $syllabus_ids = array_column($syllabi, 'id');
        $placeholders = str_repeat('?,', count($syllabus_ids) - 1) . '?';
        $stats_query = "SELECT syllabus_id, COUNT(*) as certificate_count FROM certificates WHERE syllabus_id IN ($placeholders) GROUP BY syllabus_id";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute($syllabus_ids);
        $stats = $stats_stmt->fetchAll();
        
        foreach ($stats as $stat) {
            $syllabus_stats[$stat['syllabus_id']] = $stat['certificate_count'];
        }
    }
} catch (PDOException $e) {
    $error = 'Error loading syllabi: ' . $e->getMessage();
    $syllabi = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Syllabi</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        /* Additional styles for syllabi grid view */
        .syllabi-container {
            background: #1a1a1a;
            border-radius: 8px;
            border: 1px solid #333;
            overflow: hidden;
        }

        .syllabi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .syllabus-card {
            border: 1px solid #333;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s, border-color 0.3s;
            background: #000;
        }

        .syllabus-card:hover {
            box-shadow: 0 4px 15px rgba(255,255,255,0.1);
            border-color: #555;
        }

        .syllabus-header {
            background: #111;
            padding: 15px;
            border-bottom: 1px solid #333;
        }

        .syllabus-title {
            font-size: 18px;
            font-weight: normal;
            color: #fff;
            margin-bottom: 5px;
        }

        .syllabus-meta {
            font-size: 12px;
            color: #666;
        }

        .syllabus-body {
            padding: 15px;
        }

        .syllabus-description {
            color: #ccc;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 14px;
        }

        .syllabus-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 20px;
            font-weight: normal;
            color: #fff;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
        }

        .syllabus-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-download { 
            background: #333; 
            color: #fff;
            border: 1px solid #555;
        }
        .btn-download:hover { 
            background: #555; 
        }

        .pdf-icon {
            color: #666;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .syllabi-grid {
                grid-template-columns: 1fr;
                padding: 15px;
                gap: 15px;
            }
            
            .syllabus-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .syllabus-actions .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .syllabus-card {
                margin: 0 -5px;
            }
            
            .syllabus-header,
            .syllabus-body {
                padding: 12px;
            }
            
            .syllabus-stats {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>View Syllabi</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="../dashboard.php">&larr; Back to Dashboard</a>
            </div>
            
            <div class="syllabi-container">
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">Search Syllabi</label>
                                <input type="text" id="search" name="search" 
                                       placeholder="Search by syllabus name or description"
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="btn">Search</button>
                                <a href="view-syllabi.php" class="btn btn-secondary">Clear</a>
                                <a href="create-syllabus.php" class="btn">Add New Syllabus</a>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($syllabi)): ?>
                    <div class="no-records">
                        <h3>No syllabi found</h3>
                        <p>No syllabi match your search criteria.</p>
                        <a href="create-syllabus.php" class="btn">Create New Syllabus</a>
                    </div>
                <?php else: ?>
                    <div class="syllabi-grid">
                        <?php foreach ($syllabi as $syllabus): ?>
                            <div class="syllabus-card">
                                <div class="syllabus-header">
                                    <div class="syllabus-title"><?php echo htmlspecialchars($syllabus['syllabus_name']); ?></div>
                                    <div class="syllabus-meta">
                                        Created: <?php echo date('M j, Y', strtotime($syllabus['created_at'])); ?>
                                        <?php if ($syllabus['created_by_name']): ?>
                                            by <?php echo htmlspecialchars($syllabus['created_by_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="syllabus-body">
                                    <div class="pdf-icon">📄</div>
                                    
                                    <?php if ($syllabus['description']): ?>
                                        <div class="syllabus-description">
                                            <?php echo nl2br(htmlspecialchars(substr($syllabus['description'], 0, 150))); ?>
                                            <?php if (strlen($syllabus['description']) > 150): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="syllabus-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo isset($syllabus_stats[$syllabus['id']]) ? $syllabus_stats[$syllabus['id']] : 0; ?></div>
                                            <div class="stat-label">Certificates</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo file_exists($syllabus['syllabus_pdf']) ? number_format(filesize($syllabus['syllabus_pdf']) / 1024, 1) : 'N/A'; ?></div>
                                            <div class="stat-label">KB</div>
                                        </div>
                                    </div>
                                    
                                    <div class="syllabus-actions">
                                        <a href="<?php echo htmlspecialchars($syllabus['syllabus_pdf']); ?>" 
                                           target="_blank" class="btn btn-download btn-small">Download PDF</a>
                                        <a href="view_syllabus_detail.php?id=<?php echo $syllabus['id']; ?>" 
                                           class="btn btn-view btn-small">View Details</a>
                                        <a href="edit_syllabus.php?id=<?php echo $syllabus['id']; ?>" 
                                           class="btn btn-edit btn-small">Edit</a>
                                        <a href="delete_syllabus.php?id=<?php echo $syllabus['id']; ?>" 
                                           class="btn btn-delete btn-small"
                                           onclick="return confirm('Are you sure you want to delete this syllabus? This will also affect associated certificates.')">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>