<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'All Problems - System Administration';

// Handle AJAX requests for statistics
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    try {
        $pdo = getDBConnection();
        
        // Check if problems table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'problems'");
        if ($tableCheck->rowCount() == 0) {
            throw new Exception('Database not set up. Please run the database setup first by importing sql/setup.sql in phpMyAdmin');
        }
        
        $stmt = $pdo->query(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`
            FROM problems"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ensure we return integers and consistent keys even if NULL
        $stats = [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'in_progress' => (int)($row['in_progress'] ?? 0),
            'escalated' => (int)($row['escalated'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
            'urgent' => (int)($row['urgent'] ?? 0),
            'high_priority' => (int)($row['high_priority'] ?? 0)
        ];

        // Prevent caching of the JSON response
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Handle problem management actions
$message = '';
$message_type = '';

// Check for flash messages from redirects
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

if ($_POST) {
    $action = $_POST['action'] ?? '';
    $problem_id = (int)($_POST['problem_id'] ?? 0);

    try {
        $pdo = getDBConnection();

        switch ($action) {
            case 'assign_avms':
                $avms_id = (int)($_POST['avms_id'] ?? 0);
                if ($avms_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE problems 
                        SET assigned_to = ?, status = 'in_progress', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$avms_id, $problem_id]);
                    $message = 'Problem assigned to ANMS officer successfully.';
                    $message_type = 'success';
                }
                break;

            case 'escalate_doctor':
                $doctor_id = (int)($_POST['doctor_id'] ?? 0);
                if ($doctor_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE problems 
                        SET escalated_to = ?, status = 'escalated', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$doctor_id, $problem_id]);
                    $message = 'Problem escalated to doctor successfully.';
                    $message_type = 'success';
                }
                break;

            case 'change_status':
                $new_status = $_POST['new_status'] ?? '';
                if (in_array($new_status, ['pending', 'in_progress', 'escalated', 'resolved'])) {
                    $stmt = $pdo->prepare("
                        UPDATE problems 
                        SET status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status, $problem_id]);
                    $message = 'Problem status updated successfully.';
                    $message_type = 'success';
                }
                break;

            case 'change_priority':
                $new_priority = $_POST['new_priority'] ?? '';
                if (in_array($new_priority, ['low', 'medium', 'high', 'urgent'])) {
                    $stmt = $pdo->prepare("
                        UPDATE problems 
                        SET priority = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_priority, $problem_id]);
                    $message = 'Problem priority updated successfully.';
                    $message_type = 'success';
                }
                break;
        }

        // Log admin action (if admin_logs table exists)
        if ($message_type === 'success') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                    VALUES (?, ?, 'problem', ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $action, $problem_id, $message]);
            } catch (Exception $e) {
                // Admin logs table doesn't exist, skip logging
                error_log("Admin logs table not found: " . $e->getMessage());
            }
            
            // Redirect to prevent form resubmission and ensure fresh data
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $message_type;
            header('Location: all_problems.php');
            exit;
        }

    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["1=1"];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "p.priority = ?";
    $params[] = $priority_filter;
}



if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ? OR v.name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get problems with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    $pdo = getDBConnection();

    // Count total problems
    $count_sql = "
        SELECT COUNT(*) 
        FROM problems p 
        LEFT JOIN users v ON p.villager_id = v.id 
        WHERE $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_problems = $count_stmt->fetchColumn();

    // Get problems with details
    $sql = "
        SELECT p.*, 
               v.name as villager_name, v.phone as villager_phone, v.village as villager_village,
               a.name as avms_name, a.phone as avms_phone,
               d.name as doctor_name, d.phone as doctor_phone,
               (SELECT COUNT(*) FROM medical_responses WHERE problem_id = p.id) as response_count
        FROM problems p 
        LEFT JOIN users v ON p.villager_id = v.id
        LEFT JOIN users a ON p.assigned_to = a.id
        LEFT JOIN users d ON p.escalated_to = d.id
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN p.priority = 'urgent' THEN 1
                WHEN p.priority = 'high' THEN 2
                WHEN p.priority = 'medium' THEN 3
                ELSE 4
            END,
            p.updated_at DESC 
        LIMIT $per_page OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $problems = $stmt->fetchAll();

    $total_pages = ceil($total_problems / $per_page);

    // Get available AVMS and doctors for assignments
    $avms_users = $pdo->query("SELECT id, name FROM users WHERE role = 'avms' AND (status = 'active' OR status IS NULL) ORDER BY name")->fetchAll();
    $doctors = $pdo->query("SELECT id, name FROM users WHERE role = 'doctor' AND (status = 'active' OR status IS NULL) ORDER BY name")->fetchAll();

} catch (Exception $e) {
    error_log("Error fetching problems: " . $e->getMessage());
    $problems = [];
    $total_problems = 0;
    $total_pages = 1;
    $avms_users = [];
    $doctors = [];
}

// Get summary statistics
$stats = [];
try {
    if (isset($pdo)) {
        // Check if problems table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'problems'");
        if ($tableCheck->rowCount() == 0) {
            throw new Exception('Database not set up. Please run the database setup first by importing sql/setup.sql in phpMyAdmin');
        }
        
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`
            FROM problems
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all values are integers and handle NULL
        $stats = [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'in_progress' => (int)($row['in_progress'] ?? 0),
            'escalated' => (int)($row['escalated'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
            'urgent' => (int)($row['urgent'] ?? 0),
            'high_priority' => (int)($row['high_priority'] ?? 0)
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'escalated' => 0, 'resolved' => 0, 'urgent' => 0, 'high_priority' => 0];
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
                            <i class="fas fa-clipboard-list me-2"></i>All Problems
                        </h1>
                        <p class="text-muted mb-0">
                            Monitor and manage all problems reported in the Village Health Connect system
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="reports.php" class="btn btn-success me-2">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                        <!-- Refresh and debug buttons removed; stats update automatically -->
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

    <!-- Database Setup Warning -->
    <?php if (empty($problems) && $total_problems == 0 && empty($search) && $status_filter === 'all' && $priority_filter === 'all'): ?>
        <div class="alert alert-warning">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Database Setup Required</h4>
            <p class="mb-2">It appears the database hasn't been set up yet. To get started:</p>
            <ol class="mb-3">
                <li>Use the automatic database setup below, or</li>
                <li>Open phpMyAdmin and import the <code>sql/setup.sql</code> file</li>
                <li>Refresh this page</li>
            </ol>
            <p class="mb-0">
                <a href="setup_database.php" class="btn btn-primary me-2">
                    <i class="fas fa-magic me-1"></i>Setup Database Now
                </a>
                <a href="../sql/setup.sql" class="btn btn-outline-primary me-2" target="_blank">
                    <i class="fas fa-download me-1"></i>Download Setup File
                </a>
                <a href="?debug=1" class="btn btn-outline-info">
                    <i class="fas fa-bug me-1"></i>Debug Info
                </a>
            </p>
        </div>
    <?php endif; ?>

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div class="alert alert-info">
            <strong>Debug Info:</strong><br>
            Total Problems: <?php echo $total_problems; ?><br>
            Problems Array Count: <?php echo count($problems); ?><br>
            Where Clause: <?php echo htmlspecialchars($where_clause); ?><br>
            Params: <?php echo htmlspecialchars(print_r($params, true)); ?><br>
            Page: <?php echo $page; ?>, Per Page: <?php echo $per_page; ?>, Offset: <?php echo $offset; ?><br>
            ANMS Users: <?php echo count($avms_users); ?>, Doctors: <?php echo count($doctors); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-2" data-stat="total">
            <div class="stats-card bg-primary text-white">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['total']; ?></div>
                    <small>Total Problems</small>
                </div>
            </div>
        </div>
            <div class="col-md-2" data-stat="pending">
            <div class="stats-card bg-warning text-dark">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['pending']; ?></div>
                    <small>Pending</small>
                </div>
            </div>
        </div>
            <div class="col-md-2" data-stat="in_progress">
            <div class="stats-card bg-info text-white">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['in_progress']; ?></div>
                    <small>In Progress</small>
                </div>
            </div>
        </div>
            <div class="col-md-2" data-stat="escalated">
            <div class="stats-card bg-danger text-white">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['escalated']; ?></div>
                    <small>Escalated</small>
                </div>
            </div>
        </div>
            <div class="col-md-2" data-stat="resolved">
            <div class="stats-card bg-success text-white">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['resolved']; ?></div>
                    <small>Resolved</small>
                </div>
            </div>
        </div>
            <div class="col-md-2" data-stat="urgent">
            <div class="stats-card bg-dark text-white">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['urgent']; ?></div>
                    <small>Urgent</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Filter & Search Problems</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end" id="searchForm">
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" onchange="submitForm()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority" onchange="submitForm()">
                                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Search by title, description, or villager name..."
                                   onkeyup="handleSearch()">
                        </div>
                        <div class="col-md-2">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                    <i class="fas fa-refresh me-1"></i>Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Problems List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($problems)): ?>
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                        <h4>No Problems Found</h4>
                        <p class="text-muted mb-4">
                            <?php if (!empty($search) || $status_filter !== 'all' || $priority_filter !== 'all' ): ?>
                                No problems match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                No problems have been reported yet.
                            <?php endif; ?>
                        </p>
                        <a href="all_problems.php" class="btn btn-primary">
                            <i class="fas fa-refresh me-1"></i>View All Problems
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($problems as $problem): ?>
                    <div class="problem-card mb-4 priority-<?php echo $problem['priority']; ?>">
                        <div class="card shadow">
                            <div class="card-header problem-header-<?php echo $problem['priority']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1 d-flex align-items-center">
                                            <span class="problem-number me-3">#<?php echo str_pad($problem['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                            <?php echo htmlspecialchars($problem['title']); ?>
                                            <?php if ($problem['priority'] === 'urgent'): ?>
                                                <span class="badge bg-danger ms-2 blink">URGENT</span>
                                            <?php elseif ($problem['priority'] === 'high'): ?>
                                                <span class="badge bg-warning text-dark ms-2">HIGH PRIORITY</span>
                                            <?php endif; ?>
                                        </h5>
                                        <div class="problem-meta">
                                            
                                            <span class="badge bg-<?php echo $problem['status'] === 'resolved' ? 'success' : ($problem['status'] === 'escalated' ? 'danger' : 'info'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                            </span>
                                            <small class="text-light ms-3">
                                                <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($problem['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="problem-actions">
                                            <?php if ($problem['response_count'] > 0): ?>
                                                <span class="badge bg-success p-2">
                                                    <i class="fas fa-comments me-1"></i><?php echo $problem['response_count']; ?> Response(s)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="problem-description mb-3">
                                            <h6><i class="fas fa-info-circle text-primary me-2"></i>Description:</h6>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($problem['description'], 0, 200))); ?>
                                            <?php if (strlen($problem['description']) > 200): ?>...<?php endif; ?>
                                            </p>
                                        </div>

                                        <div class="stakeholder-info">
                                            <div class="row">
                                                <div class="col-sm-4">
                                                    <h6><i class="fas fa-user text-info me-2"></i>Villager:</h6>
                                                    <p class="mb-1"><strong><?php echo htmlspecialchars($problem['villager_name']); ?></strong></p>
                                                    <?php if ($problem['villager_phone']): ?>
                                                        <p class="mb-1 small"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($problem['villager_phone']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($problem['villager_village']): ?>
                                                        <p class="mb-0 small text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($problem['villager_village']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-sm-4">
                                                    <h6><i class="fas fa-user-tie text-warning me-2"></i>ANMS Officer:</h6>
                                                    <?php if ($problem['avms_name']): ?>
                                                        <p class="mb-1"><strong><?php echo htmlspecialchars($problem['avms_name']); ?></strong></p>
                                                        <?php if ($problem['avms_phone']): ?>
                                                            <p class="mb-0 small"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($problem['avms_phone']); ?></p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <p class="text-muted">Not assigned</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-sm-4">
                                                    <h6><i class="fas fa-user-md text-success me-2"></i>Doctor:</h6>
                                                    <?php if ($problem['doctor_name']): ?>
                                                        <p class="mb-1"><strong><?php echo htmlspecialchars($problem['doctor_name']); ?></strong></p>
                                                        <?php if ($problem['doctor_phone']): ?>
                                                            <p class="mb-0 small"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($problem['doctor_phone']); ?></p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <p class="text-muted">Not escalated</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="admin-actions">
                                            <h6><i class="fas fa-cog text-primary me-2"></i>Admin Actions:</h6>

                                            <!-- Status Management -->
                                            <div class="mb-3">
                                                <label class="form-label small">Change Status:</label>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="problem_id" value="<?php echo $problem['id']; ?>">
                                                    <select name="new_status" class="form-select form-select-sm" 
                                                            onchange="changeProblemStatus(this, <?php echo $problem['id']; ?>)"
                                                            data-original-value="<?php echo $problem['status']; ?>">
                                                        <option value="pending" <?php echo $problem['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="in_progress" <?php echo $problem['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="escalated" <?php echo $problem['status'] === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                                                        <option value="resolved" <?php echo $problem['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </form>
                                            </div>

                                            <!-- Priority Management -->
                                            <div class="mb-3">
                                                <label class="form-label small">Change Priority:</label>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="change_priority">
                                                    <input type="hidden" name="problem_id" value="<?php echo $problem['id']; ?>">
                                                    <select name="new_priority" class="form-select form-select-sm" 
                                                            onchange="changeProblemPriority(this, <?php echo $problem['id']; ?>)"
                                                            data-original-value="<?php echo $problem['priority']; ?>">
                                                        <option value="low" <?php echo $problem['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                                        <option value="medium" <?php echo $problem['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                        <option value="high" <?php echo $problem['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                                        <option value="urgent" <?php echo $problem['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                                    </select>
                                                </form>
                                            </div>

                                            <!-- Assignment Actions -->
                                            <?php if (!$problem['assigned_to'] && !empty($avms_users)): ?>
                                                <div class="mb-2">
                                                    <button class="btn btn-warning btn-sm w-100" onclick="showAssignModal(<?php echo $problem['id']; ?>, 'avms')">
                                                        <i class="fas fa-user-plus me-1"></i>Assign ANMS
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($problem['status'] !== 'escalated' && !empty($doctors)): ?>
                                                <div class="mb-2">
                                                    <button class="btn btn-danger btn-sm w-100" onclick="showAssignModal(<?php echo $problem['id']; ?>, 'doctor')">
                                                        <i class="fas fa-arrow-up me-1"></i>Escalate to Doctor
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-grid gap-1">
                                                <a href="view_problem.php?id=<?php echo $problem['id']; ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                <?php if ($problem['villager_phone']): ?>
                                                    <a href="tel:<?php echo $problem['villager_phone']; ?>" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-phone me-1"></i>Call Villager
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>
                                        Created: <?php echo date('M j, Y g:i A', strtotime($problem['created_at'])); ?>
                                        | Updated: <?php echo date('M j, Y g:i A', strtotime($problem['updated_at'])); ?>
                                    </small>
                                    <small>
                                        Age: <?php echo floor((time() - strtotime($problem['created_at'])) / 86400); ?> days
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_problems); ?> of <?php echo $total_problems; ?> problems
                                </div>
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter);  ?>&search=<?php echo urlencode($search); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter);  ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter);  ?>&search=<?php echo urlencode($search); ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignModalTitle">Assign Problem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="problem_id" id="assignProblemId">
                    <input type="hidden" name="action" id="assignAction">

                    <div class="mb-3">
                        <label for="assignUser" class="form-label" id="assignUserLabel">Select User</label>
                        <select class="form-select" id="assignUser" name="user_id" required>
                            <option value="">Choose...</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="assignmentInfo">This will assign the problem to the selected user.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="assignSubmitBtn">Assign</button>
                </div>
            </form>
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

.stats-card {
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.problem-card {
    transition: all 0.3s ease;
}

.problem-card:hover {
    transform: translateY(-3px);
}

.problem-header-urgent {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.problem-header-high {
    background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
    color: white;
}

.problem-header-medium {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.problem-header-low {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.problem-number {
    background: rgba(255,255,255,0.2);
    color: inherit;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.problem-description, .stakeholder-info, .admin-actions {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.problem-description {
    border-left: 4px solid #17a2b8;
}

.stakeholder-info {
    border-left: 4px solid #28a745;
}

.admin-actions {
    border-left: 4px solid #dc3545;
}

.blink {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.7; }
}

.stats-card.updating {
    animation: pulse 1s infinite;
    border: 2px solid #007bff;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.refresh-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    color: #007bff;
    font-size: 0.8rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.refresh-indicator.show {
    opacity: 1;
}
</style>

<script>
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('All Problems page loaded successfully');
    
    // Check if there's a success message (indicating a recent action)
    const alertElement = document.querySelector('.alert-success');
    if (alertElement) {
        // If there's a success message, refresh statistics to ensure they're up to date
        console.log('Success message detected, refreshing statistics...');
        setTimeout(function() {
            try {
                refreshStatistics();
            } catch (e) {
                console.error('refreshStatistics not available on load:', e);
            }
        }, 500);
    } else {
        // Otherwise, do a normal refresh
        try {
            refreshStatistics();
        } catch (e) {
            console.error('refreshStatistics not available on load:', e);
        }
    }
});

// Search functionality
let searchTimeout;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchInput = document.getElementById('search');
        if (searchInput && (searchInput.value.length >= 2 || searchInput.value.length === 0)) {
            document.getElementById('searchForm').submit();
        }
    }, 1000);
}

function submitForm() {
    const form = document.getElementById('searchForm');
    if (form) {
        form.submit();
    }
}

function resetFilters() {
    const statusSelect = document.getElementById('status');
    const prioritySelect = document.getElementById('priority');
    const categorySelect = document.getElementById('category');
    const searchInput = document.getElementById('search');
    
    if (statusSelect) statusSelect.value = 'all';
    if (prioritySelect) prioritySelect.value = 'all';
    if (categorySelect) categorySelect.value = 'all';
    if (searchInput) searchInput.value = '';
    
    window.location.href = 'all_problems.php';
}

function showAssignModal(problemId, type) {
    const assignProblemId = document.getElementById('assignProblemId');
    const assignUser = document.getElementById('assignUser');
    const assignAction = document.getElementById('assignAction');
    const modalTitle = document.getElementById('assignModalTitle');
    const userLabel = document.getElementById('assignUserLabel');
    const assignmentInfo = document.getElementById('assignmentInfo');
    const submitBtn = document.getElementById('assignSubmitBtn');
    const modal = document.getElementById('assignModal');

    if (!assignProblemId || !assignUser || !assignAction || !modalTitle || !userLabel || !assignmentInfo || !submitBtn || !modal) {
        console.error('Required modal elements not found');
        return;
    }

    assignProblemId.value = problemId;

    // Clear existing options
    assignUser.innerHTML = '<option value="">Choose...</option>';

    if (type === 'avms') {
        assignAction.value = 'assign_avms';
        assignAction.name = 'action';
        assignUser.name = 'avms_id';
        modalTitle.textContent = 'Assign to ANMS Officer';
        userLabel.textContent = 'Select ANMS Officer';
        assignmentInfo.textContent = 'This will assign the problem to the selected ANMS officer and change status to "In Progress".';
        submitBtn.textContent = 'Assign to ANMS';
        submitBtn.className = 'btn btn-warning';

        // Add AVMS users
        <?php foreach ($avms_users as $avms): ?>
        assignUser.innerHTML += '<option value="<?php echo $avms['id']; ?>"><?php echo htmlspecialchars($avms['name']); ?></option>';
        <?php endforeach; ?>

    } else if (type === 'doctor') {
        assignAction.value = 'escalate_doctor';
        assignAction.name = 'action';
        assignUser.name = 'doctor_id';
        modalTitle.textContent = 'Escalate to Doctor';
        userLabel.textContent = 'Select Doctor';
        assignmentInfo.textContent = 'This will escalate the problem to the selected doctor and change status to "Escalated".';
        submitBtn.textContent = 'Escalate to Doctor';
        submitBtn.className = 'btn btn-danger';

        // Add doctors
        <?php foreach ($doctors as $doctor): ?>
        assignUser.innerHTML += '<option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>';
        <?php endforeach; ?>
    }

    if (typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    // Add visual feedback
    showToast('Opening assignment modal...', 'info', 2000);
}

// Problem management functions
function changeProblemStatus(selectElement, problemId) {
    const newStatus = selectElement.value;
    const form = selectElement.closest('form');
    
    if (confirm('Change problem status to "' + newStatus + '"?')) {
        showToast('Updating problem status...', 'info', 2000);
        if (form) {
            // Submit form - page will reload with updated statistics
            form.submit();
        }
    } else {
        // Reset to original value
        selectElement.value = selectElement.getAttribute('data-original-value') || 'pending';
    }
}

function changeProblemPriority(selectElement, problemId) {
    const newPriority = selectElement.value;
    const form = selectElement.closest('form');
    
    if (confirm('Change problem priority to "' + newPriority + '"?')) {
        showToast('Updating problem priority...', 'info', 2000);
        if (form) {
            // Submit form - page will reload with updated statistics
            form.submit();
        }
    } else {
        // Reset to original value
        selectElement.value = selectElement.getAttribute('data-original-value') || 'medium';
    }
}

// Function to refresh statistics via AJAX
function refreshStatistics() {
    const refreshBtn = document.getElementById('refreshStatsBtn');
    let originalText = null;
    if (refreshBtn) {
        originalText = refreshBtn.innerHTML;
        // Show loading state
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        refreshBtn.disabled = true;
    }

    // Add a cache-buster to avoid cached responses and keep the request simple (no special headers)
    fetch('all_problems.php?action=get_stats&_=' + Date.now(), {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Stats fetch response:', data);
        if (data.success) {
            updateStatisticsDisplay(data.stats);
            showToast('Statistics updated successfully', 'success', 2000);
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'danger', 3000);
        }
    })
    .catch(error => {
        console.error('Error refreshing statistics:', error);
        showToast('Error updating statistics: ' + error.message, 'danger', 3000);
    })
    .finally(() => {
        // Reset button state if present
        if (refreshBtn && originalText !== null) {
            refreshBtn.innerHTML = originalText;
            refreshBtn.disabled = false;
        }
    });
}

// Function to update the statistics display
function updateStatisticsDisplay(stats) {
    // Find all stat containers with data-stat attribute for deterministic updates
    const statContainers = document.querySelectorAll('[data-stat]');

    statContainers.forEach(container => {
        const key = container.getAttribute('data-stat');
        const value = stats[key];
        const displayEl = container.querySelector('.h3');

        if (displayEl && typeof value !== 'undefined') {
            // Add updating class for effect
            const card = container.querySelector('.stats-card');
            if (card) card.classList.add('updating');

            // Update text content
            displayEl.textContent = value;
            console.log('Updated stat', key, value);

            // Remove updating indicator shortly after
            setTimeout(() => {
                if (card) card.classList.remove('updating');
            }, 1000);
        } else {
            console.warn('Stat key not found or no display element:', key);
        }
    });
}

// Show toast notification
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 5000;
    
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(toast);
    
    // Auto remove
    setTimeout(function() {
        if (toast.parentNode) {
            toast.remove();
        }
    }, duration);
}

// Auto-refresh for urgent cases and statistics
setInterval(function() {
    if (document.visibilityState === 'visible') {
        const urgentCount = <?php echo $stats['urgent']; ?>;
        if (urgentCount > 0) {
            // Auto-refresh statistics every 2 minutes if there are urgent cases
            refreshStatistics();
        }
    }
}, 120000);

// Auto-refresh statistics every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        refreshStatistics();
    }
}, 300000);
</script>

<?php include '../includes/footer.php'; ?>