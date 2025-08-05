<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR syllabus_name LIKE ? OR certificate_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM certificates $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Get certificates with pagination
    $query = "SELECT * FROM certificates $where_clause ORDER BY created_at DESC LIMIT $records_per_page OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading certificates: ' . $e->getMessage();
    $certificates = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Certificates</title>
    <link rel="stylesheet" href="../css/certificate.css">
    
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>View Certificates</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="../dashboard.php">&larr; Back to Dashboard</a>
            </div>
            
            <div class="certificates-container">
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" 
                                       placeholder="Search by name, email, syllabus, or certificate code"
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($filter_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="revoked" <?php echo ($filter_status === 'revoked') ? 'selected' : ''; ?>>Revoked</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="btn">Filter</button>
                                <a href="view-certificate.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (empty($certificates)): ?>
                    <div class="no-records">
                        <h3>No certificates found</h3>
                        <p>No certificates match your search criteria.</p>
                        <a href="create_certificate.php" class="btn">Create New Certificate</a>
                    </div>
                <?php else: ?>
                    <table class="certificates-table">
                        <thead>
                            <tr>
                                <th>Certificate Code</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Syllabus</th>
                                <th>Issue Date</th>
                                <th>Status</th>
                                <th>Certificate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $certificate): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($certificate['certificate_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($certificate['name']); ?></td>
                                    <td><?php echo htmlspecialchars($certificate['email']); ?></td>
                                    <td><?php echo htmlspecialchars($certificate['syllabus_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($certificate['issue_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $certificate['status']; ?>">
                                            <?php echo htmlspecialchars($certificate['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($certificate['certificate_image']): ?>
                                            <?php 
                                            $ext = strtolower(pathinfo($certificate['certificate_image'], PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($certificate['certificate_image']); ?>" 
                                                     alt="Certificate" class="certificate-image">
                                            <?php else: ?>
                                                <span>PDF File</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="certificate-actions">
                                            <a href="view_certificate_detail.php?id=<?php echo $certificate['id']; ?>" 
                                               class="btn btn-view btn-small">View</a>
                                            <a href="edit_certificate.php?id=<?php echo $certificate['id']; ?>" 
                                               class="btn btn-edit btn-small">Edit</a>
                                            <a href="delete_certificate.php?id=<?php echo $certificate['id']; ?>" 
                                               class="btn btn-delete btn-small"
                                               onclick="return confirm('Are you sure you want to delete this certificate?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>