<?php
require_once 'config/database.php';
require_once 'includes/session.php';
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .syllabi-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .syllabi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .syllabus-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            transition: box-shadow 0.3s;
        }
        .syllabus-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .syllabus-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .syllabus-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
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
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
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
            font-weight: bold;
            color: #4CAF50;
        }
        .stat-label {
            color: #666;
            font-size: 12px;
        }
        .syllabus-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-view { background: #17a2b8; }
        .btn-edit { background: #ffc107; color: #212529; }
        .btn-delete { background: #dc3545; }
        .btn-download { background: #28a745; }
        .btn-view:hover { background: #138496; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        .btn-download:hover { background: #218838; }
        .pagination {
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            text-decoration: none;
            border: 1px solid #dee2e6;
            color: #4CAF50;
        }
        .pagination a:hover {
            background: #e9ecef;
        }
        .pagination .current {
            background: #4CAF50;
            color: white;
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
        .no-records {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .pdf-icon {
            color: #dc3545;
            font-size: 24px;
            margin-bottom: 10px;
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
                <a href="dashboard.php">&larr; Back to Dashboard</a>
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
                                <a href="view_syllabi.php" class="btn btn-secondary">Clear</a>
                                <a href="create_syllabus.php" class="btn">Add New Syllabus</a>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($syllabi)): ?>
                    <div class="no-records">
                        <h3>No syllabi found</h3>
                        <p>No syllabi match your search criteria.</p>
                        <a href="create_syllabus.php" class="btn">Create New Syllabus</a>
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
                                    <div class="pdf-icon" style="text-align: center;">📄</div>
                                    
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