<?php
require_once '../config/database.php';
require_once '../includes/session.php';
requireLogin();

$message = '';
$error = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $selected_certificates = $_POST['certificates'] ?? [];
    
    if (empty($selected_certificates)) {
        $error = 'Please select at least one certificate.';
    } elseif (empty($action)) {
        $error = 'Please select an action.';
    } else {
        // Validate certificate IDs
        $certificate_ids = array_filter(array_map('intval', $selected_certificates));
        
        if (empty($certificate_ids)) {
            $error = 'Invalid certificate selection.';
        } else {
            try {
                $pdo->beginTransaction();
                
                switch ($action) {
                    case 'activate':
                        $stmt = $pdo->prepare("UPDATE certificates SET status = 'active', updated_at = NOW() WHERE id IN (" . str_repeat('?,', count($certificate_ids) - 1) . "?)");
                        $stmt->execute($certificate_ids);
                        $affected = $stmt->rowCount();
                        $message = "Successfully activated $affected certificate(s).";
                        break;
                        
                    case 'revoke':
                        $stmt = $pdo->prepare("UPDATE certificates SET status = 'revoked', updated_at = NOW() WHERE id IN (" . str_repeat('?,', count($certificate_ids) - 1) . "?)");
                        $stmt->execute($certificate_ids);
                        $affected = $stmt->rowCount();
                        $message = "Successfully revoked $affected certificate(s).";
                        break;
                        
                    case 'delete':
                        // Get certificate files for deletion
                        $stmt = $pdo->prepare("SELECT certificate_image FROM certificates WHERE id IN (" . str_repeat('?,', count($certificate_ids) - 1) . "?)");
                        $stmt->execute($certificate_ids);
                        $files_to_delete = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // Delete from database
                        $stmt = $pdo->prepare("DELETE FROM certificates WHERE id IN (" . str_repeat('?,', count($certificate_ids) - 1) . "?)");
                        $stmt->execute($certificate_ids);
                        $affected = $stmt->rowCount();
                        
                        // Delete files
                        foreach ($files_to_delete as $file) {
                            if ($file && file_exists($file)) {
                                unlink($file);
                            }
                        }
                        
                        $message = "Successfully deleted $affected certificate(s) and associated files.";
                        break;
                        
                    case 'export':
                        // Export selected certificates to CSV
                        $stmt = $pdo->prepare("SELECT certificate_code, name, email, syllabus_name, issue_date, status, created_at FROM certificates WHERE id IN (" . str_repeat('?,', count($certificate_ids) - 1) . "?) ORDER BY created_at DESC");
                        $stmt->execute($certificate_ids);
                        $certificates = $stmt->fetchAll();
                        
                        if (!empty($certificates)) {
                            $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.csv';
                            
                            header('Content-Type: text/csv');
                            header('Content-Disposition: attachment; filename="' . $filename . '"');
                            
                            $output = fopen('php://output', 'w');
                            
                            // CSV headers
                            fputcsv($output, ['Certificate Code', 'Student Name', 'Email', 'Syllabus', 'Issue Date', 'Status', 'Created Date']);
                            
                            // CSV data
                            foreach ($certificates as $cert) {
                                fputcsv($output, [
                                    $cert['certificate_code'],
                                    $cert['name'],
                                    $cert['email'],
                                    $cert['syllabus_name'],
                                    $cert['issue_date'],
                                    $cert['status'],
                                    $cert['created_at']
                                ]);
                            }
                            
                            fclose($output);
                            exit();
                        } else {
                            $error = 'No certificates found for export.';
                        }
                        break;
                        
                    default:
                        $error = 'Invalid action selected.';
                        break;
                }
                
                $pdo->commit();
                
            } catch (PDOException $e) {
                $pdo->rollback();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Pagination settings
$records_per_page = 20;
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
    <title>Bulk Certificate Management</title>
    <link rel="stylesheet" href="../css/certificate.css">
    <style>
        .bulk-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #dee2e6;
        }
        .bulk-actions-form {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            color: #000;
        }
        .bulk-actions-form select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
            color: #000;
        }
        .select-all-container {
            margin-bottom: 15px;
            color: #000;
        }
        .select-all-container label {
            font-weight: bold;
            cursor: pointer;
                        color: #000;

        }
        .certificate-checkbox {
            margin-right: 10px;
            color: #000;
        }
        .selected-count {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-bulk {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .btn-activate {
            background: #28a745;
            color: white;
        }
        .btn-revoke {
            background: #ffc107;
            color: #212529;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-export {
            background: #17a2b8;
            color: white;
        }
        .btn-bulk:hover {
            opacity: 0.9;
            color: #000;
        }
        .btn-bulk:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .certificates-table {
            margin-top: 20px;
        }
        .certificates-table th:first-child,
        .certificates-table td:first-child {
            width: 50px;
            text-align: center;
        }
        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex: 1;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Bulk Certificate Management</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
        
        <div class="dashboard-content">
            <div class="back-link">
                <a href="../dashboard.php">&larr; Back to Dashboard</a> |
                <a href="view-certificate.php">View Certificates</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <?php
            try {
                $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked
                    FROM certificates";
                $stats_stmt = $pdo->query($stats_query);
                $stats = $stats_stmt->fetch();
            } catch (PDOException $e) {
                $stats = ['total' => 0, 'active' => 0, 'revoked' => 0];
            }
            ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Certificates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #28a745;"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active Certificates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #ffc107;"><?php echo $stats['revoked']; ?></div>
                    <div class="stat-label">Revoked Certificates</div>
                </div>
            </div>
            
            <!-- Search and Filter -->
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
                            <a href="bulk_certificate_actions.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($certificates)): ?>
                <form method="POST" id="bulkForm">
                    <!-- Bulk Actions Panel -->
                    <div class="bulk-actions">
                        <div class="select-all-container">
                            <label>
                                <input type="checkbox" id="selectAll"> Select All Certificates
                            </label>
                            <span id="selectedCount" class="selected-count" style="display: none;">0 selected</span>
                        </div>
                        
                        <div class="bulk-actions-form">
                            <label for="action"><strong>Action:</strong></label>
                            <select name="action" id="action" required>
                                <option value="">-- Select Action --</option>
                                <option value="activate">Activate Selected</option>
                                <option value="revoke">Revoke Selected</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                            </select>
                            
                            <div class="action-buttons">
                                <button type="submit" class="btn-bulk btn-primary" id="executeAction" disabled>
                                    Execute Action
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Certificates Table -->
                    <table class="certificates-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllHeader"></th>
                                <th>Certificate Code</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Syllabus</th>
                                <th>Issue Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $certificate): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="certificates[]" 
                                               value="<?php echo $certificate['id']; ?>" 
                                               class="certificate-checkbox">
                                    </td>
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
                                        <div class="certificate-actions">
                                            <a href="view_certificate_detail.php?id=<?php echo $certificate['id']; ?>" 
                                               class="btn btn-view btn-small">View</a>
                                            <a href="edit_certificate.php?id=<?php echo $certificate['id']; ?>" 
                                               class="btn btn-edit btn-small">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                
                <!-- Pagination -->
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
            <?php else: ?>
                <div class="no-records">
                    <h3>No certificates found</h3>
                    <p>No certificates match your search criteria.</p>
                    <a href="create_certificate.php" class="btn">Create New Certificate</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const selectAllHeader = document.getElementById('selectAllHeader');
            const certificateCheckboxes = document.querySelectorAll('.certificate-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            const executeButton = document.getElementById('executeAction');
            const actionSelect = document.getElementById('action');
            const bulkForm = document.getElementById('bulkForm');
            
            function updateSelectedCount() {
                const checkedBoxes = document.querySelectorAll('.certificate-checkbox:checked');
                const count = checkedBoxes.length;
                
                if (count > 0) {
                    selectedCount.textContent = count + ' selected';
                    selectedCount.style.display = 'inline';
                    executeButton.disabled = false;
                } else {
                    selectedCount.style.display = 'none';
                    executeButton.disabled = true;
                }
                
                // Update select all checkbox state
                if (count === certificateCheckboxes.length) {
                    selectAll.indeterminate = false;
                    selectAll.checked = true;
                    selectAllHeader.indeterminate = false;
                    selectAllHeader.checked = true;
                } else if (count > 0) {
                    selectAll.indeterminate = true;
                    selectAll.checked = false;
                    selectAllHeader.indeterminate = true;
                    selectAllHeader.checked = false;
                } else {
                    selectAll.indeterminate = false;
                    selectAll.checked = false;
                    selectAllHeader.indeterminate = false;
                    selectAllHeader.checked = false;
                }
            }
            
            // Select all functionality
            function toggleSelectAll(checked) {
                certificateCheckboxes.forEach(checkbox => {
                    checkbox.checked = checked;
                });
                updateSelectedCount();
            }
            
            selectAll.addEventListener('change', function() {
                toggleSelectAll(this.checked);
            });
            
            selectAllHeader.addEventListener('change', function() {
                selectAll.checked = this.checked;
                toggleSelectAll(this.checked);
            });
            
            // Individual checkbox change
            certificateCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            
            // Form submission with confirmation
            bulkForm.addEventListener('submit', function(e) {
                const action = actionSelect.value;
                const checkedBoxes = document.querySelectorAll('.certificate-checkbox:checked');
                const count = checkedBoxes.length;
                
                if (count === 0) {
                    e.preventDefault();
                    alert('Please select at least one certificate.');
                    return;
                }
                
                if (!action) {
                    e.preventDefault();
                    alert('Please select an action.');
                    return;
                }
                
                let confirmMessage = '';
                switch (action) {
                    case 'activate':
                        confirmMessage = `Are you sure you want to activate ${count} certificate(s)?`;
                        break;
                    case 'revoke':
                        confirmMessage = `Are you sure you want to revoke ${count} certificate(s)?`;
                        break;
                    case 'delete':
                        confirmMessage = `Are you sure you want to DELETE ${count} certificate(s)?\n\nThis action cannot be undone and will permanently remove the certificates and their files.`;
                        break;
                    case 'export':
                        // No confirmation needed for export
                        return;
                    default:
                        confirmMessage = `Are you sure you want to perform this action on ${count} certificate(s)?`;
                }
                
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });
            
            // Initialize count
            updateSelectedCount();
        });
    </script>
</body>
</html>