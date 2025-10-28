<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Database Backup - Administration';

// Handle backup actions
$message = '';
$message_type = '';
$backup_files = [];

if ($_POST) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_backup':
            try {
                $backup_result = createDatabaseBackup();
                if ($backup_result['success']) {
                    $message = 'Database backup created successfully: ' . $backup_result['filename'];
                    $message_type = 'success';
                } else {
                    $message = 'Backup failed: ' . $backup_result['message'];
                    $message_type = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Backup error: ' . $e->getMessage();
                $message_type = 'danger';
            }
            break;

        case 'delete_backup':
            $filename = $_POST['filename'] ?? '';
            if ($filename && file_exists('../backups/' . $filename)) {
                if (unlink('../backups/' . $filename)) {
                    $message = 'Backup file deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete backup file.';
                    $message_type = 'danger';
                }
            }
            break;
    }
}

// Function to create database backup
function createDatabaseBackup() {
    try {
        // Ensure backup directory exists
        $backup_dir = '../backups';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "vhc_backup_{$timestamp}.sql";
        $filepath = $backup_dir . '/' . $filename;

        // Get database connection info
        $pdo = getDBConnection();
        $dsn_parts = [];
        preg_match('/host=([^;]+)/', DB_HOST, $host_match);
        preg_match('/dbname=([^;]+)/', DB_HOST, $db_match);

        $host = $host_match[1] ?? 'localhost';
        $database = $db_match[1] ?? DB_NAME;

        // Create backup content
        $backup_content = "-- Village Health Connect Database Backup
";
        $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "
";
        $backup_content .= "-- Database: {$database}

";
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;

";

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $create_table = $stmt->fetch(PDO::FETCH_NUM);

            $backup_content .= "-- Table: {$table}
";
            $backup_content .= "DROP TABLE IF EXISTS `{$table}`;
";
            $backup_content .= $create_table[1] . ";

";

            // Get table data
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $backup_content .= "-- Data for table: {$table}
";

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $columns = array_keys($row);
                    $backup_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");
";
                }
                $backup_content .= "
";
            }
        }

        $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;
";

        // Write backup file
        if (file_put_contents($filepath, $backup_content)) {
            // Log backup creation
            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                VALUES (?, 'create_backup', 'system', 0, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], "Database backup created: {$filename}"]);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to write backup file'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Get existing backup files
$backup_dir = '../backups';
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . '/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath),
                'path' => $filepath
            ];
        }
    }
    // Sort by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get database statistics
$db_stats = [];
try {
    $pdo = getDBConnection();

    // Get database size
    $stmt = $pdo->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $db_stats['size_mb'] = $stmt->fetchColumn() ?: 0;

    // Get table count
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $db_stats['table_count'] = $stmt->fetchColumn() ?: 0;

    // Get total records across main tables
    $main_tables = ['users', 'problems', 'medical_responses', 'notifications'];
    $total_records = 0;

    foreach ($main_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $count = $stmt->fetchColumn();
            $db_stats[$table . '_count'] = $count;
            $total_records += $count;
        } catch (Exception $e) {
            $db_stats[$table . '_count'] = 0;
        }
    }

    $db_stats['total_records'] = $total_records;

} catch (Exception $e) {
    $db_stats = [
        'size_mb' => 0,
        'table_count' => 0,
        'total_records' => 0,
        'users_count' => 0,
        'problems_count' => 0,
        'medical_responses_count' => 0,
        'notifications_count' => 0
    ];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary">
                            <i class="fas fa-database me-2"></i>Database Backup
                        </h1>
                        <p class="text-muted mb-0">
                            Create, manage, and restore database backups for the Village Health Connect system
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="settings.php" class="btn btn-secondary">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'times-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Backup Actions -->
        <div class="col-lg-4 mb-4">
            <!-- Create New Backup -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Create New Backup</h5>
                </div>
                <div class="card-body">
                    <div class="backup-info mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Backup Information:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Includes all tables and data</li>
                                <li>Backup files are stored securely</li>
                                <li>Process may take a few minutes</li>
                                <li>System remains operational during backup</li>
                            </ul>
                        </div>
                    </div>

                    <form method="POST" action="" onsubmit="return confirmBackup()">
                        <input type="hidden" name="action" value="create_backup">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="backupBtn">
                                <i class="fas fa-database me-2"></i>Create Database Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Database Statistics -->
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Database Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="db-stats">
                        <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-hdd text-primary me-2"></i>Database Size:</span>
                            <strong><?php echo $db_stats['size_mb']; ?> MB</strong>
                        </div>

                        <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-table text-success me-2"></i>Tables:</span>
                            <strong><?php echo $db_stats['table_count']; ?></strong>
                        </div>

                        <div class="stat-item d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fas fa-list text-warning me-2"></i>Total Records:</span>
                            <strong><?php echo number_format($db_stats['total_records']); ?></strong>
                        </div>

                        <hr class="my-3">

                        <div class="stat-item d-flex justify-content-between align-items-center mb-1">
                            <small><i class="fas fa-users text-muted me-1"></i>Users:</small>
                            <small><strong><?php echo number_format($db_stats['users_count']); ?></strong></small>
                        </div>

                        <div class="stat-item d-flex justify-content-between align-items-center mb-1">
                            <small><i class="fas fa-clipboard-list text-muted me-1"></i>Problems:</small>
                            <small><strong><?php echo number_format($db_stats['problems_count']); ?></strong></small>
                        </div>

                        <div class="stat-item d-flex justify-content-between align-items-center mb-1">
                            <small><i class="fas fa-stethoscope text-muted me-1"></i>Medical Responses:</small>
                            <small><strong><?php echo number_format($db_stats['medical_responses_count']); ?></strong></small>
                        </div>

                        <div class="stat-item d-flex justify-content-between align-items-center">
                            <small><i class="fas fa-bell text-muted me-1"></i>Notifications:</small>
                            <small><strong><?php echo number_format($db_stats['notifications_count']); ?></strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Files -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0 d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-archive me-2"></i>Backup Files</span>
                        <span class="badge bg-light text-dark"><?php echo count($backup_files); ?> Files</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($backup_files)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-archive fa-4x text-muted mb-4"></i>
                            <h4>No Backup Files Found</h4>
                            <p class="text-muted mb-4">
                                No database backups have been created yet. Click "Create Database Backup" to create your first backup.
                            </p>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="action" value="create_backup">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database me-1"></i>Create First Backup
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Backup File</th>
                                        <th class="text-end">File Size</th>
                                        <th class="text-end">Created Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $backup): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-archive text-success me-2"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($backup['name']); ?></strong>
                                                    <div class="text-muted small">SQL Database Backup</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-info">
                                                <?php echo formatBytes($backup['size']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div><?php echo date('M j, Y', $backup['date']); ?></div>
                                            <div class="text-muted small"><?php echo date('g:i A', $backup['date']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="download_backup.php?file=<?php echo urlencode($backup['name']); ?>" 
                                                   class="btn btn-outline-primary" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button class="btn btn-outline-info" 
                                                        onclick="viewBackupInfo('<?php echo htmlspecialchars($backup['name']); ?>')" 
                                                        title="View Info">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="deleteBackup('<?php echo htmlspecialchars($backup['name']); ?>')" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Backup Management Actions -->
                        <div class="backup-actions mt-4 p-3 bg-light rounded">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><i class="fas fa-tools me-2"></i>Backup Management</h6>
                                    <small class="text-muted">
                                        Regular backups ensure your data is safe. Consider automating daily backups for production systems.
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-outline-warning btn-sm" onclick="cleanOldBackups()">
                                            <i class="fas fa-broom me-1"></i>Clean Old Backups
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="scheduleBackup()">
                                            <i class="fas fa-clock me-1"></i>Schedule Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backup Info Modal -->
<div class="modal fade" id="backupInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Backup Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="backupInfoContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.page-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid #e9ecef;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.stat-item {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    margin-bottom: 8px;
}

.backup-actions {
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.backup-actions:hover {
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table th {
    border-top: none;
    font-weight: 600;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

@media (max-width: 768px) {
    .backup-actions .col-md-4 {
        text-align: left !important;
        margin-top: 15px;
    }

    .btn-group {
        width: 100%;
    }

    .btn-group .btn {
        flex: 1;
    }
}
</style>

<script>
function confirmBackup() {
    const btn = document.getElementById('backupBtn');
    const originalText = btn.innerHTML;

    if (confirm('Create a new database backup? This may take a few minutes.')) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Backup...';
        btn.disabled = true;
        return true;
    }
    return false;
}

function deleteBackup(filename) {
    if (confirm(`Are you sure you want to delete the backup file "${filename}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="filename" value="${filename}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewBackupInfo(filename) {
    const content = `
        <div class="backup-details">
            <h6><i class="fas fa-file-archive me-2"></i>Backup File Details</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Filename:</strong></td>
                    <td>${filename}</td>
                </tr>
                <tr>
                    <td><strong>Type:</strong></td>
                    <td>MySQL Database Backup (SQL)</td>
                </tr>
                <tr>
                    <td><strong>Contents:</strong></td>
                    <td>All tables, data, and structure</td>
                </tr>
                <tr>
                    <td><strong>Compression:</strong></td>
                    <td>Uncompressed SQL file</td>
                </tr>
            </table>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Restore Instructions:</strong>
                <ol class="mb-0 mt-2">
                    <li>Download the backup file</li>
                    <li>Access your database management system</li>
                    <li>Create a new database or select existing one</li>
                    <li>Import the SQL file to restore all data</li>
                </ol>
            </div>
        </div>
    `;

    document.getElementById('backupInfoContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('backupInfoModal')).show();
}

function cleanOldBackups() {
    if (confirm('Remove backup files older than 30 days?')) {
        alert('Old backup cleanup functionality would be implemented here.');
    }
}

function scheduleBackup() {
    alert('Automated backup scheduling would be configured here.');
}

<?php
// PHP function to format bytes
function formatBytes($size, $precision = 2) {
    if ($size === 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = log($size, 1024);
    $index = floor($base);

    return round(pow(1024, $base - $index), $precision) . ' ' . $units[$index];
}
?>
</script>

<?php include '../includes/footer.php'; ?>