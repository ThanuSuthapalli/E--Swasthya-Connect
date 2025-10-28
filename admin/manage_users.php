<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Manage Users - System Administration';

// Handle user management actions
$message = '';
$message_type = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    try {
        $pdo = getDBConnection();

        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User activated successfully.';
                $message_type = 'success';
                break;

            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User deactivated successfully.';
                $message_type = 'success';
                break;

            case 'approve':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User approved and activated successfully.';
                $message_type = 'success';
                break;

            case 'reject':
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = 'User rejected successfully.';
                $message_type = 'success';
                break;

            case 'delete':
                // Soft delete - mark as deleted instead of removing from database
                $stmt = $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                if ($stmt->rowCount() > 0) {
                    $message = 'User deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Cannot delete admin users or user not found.';
                    $message_type = 'warning';
                }
                break;

            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                if (in_array($new_role, ['villager', 'avms', 'doctor'])) {
                    $stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$new_role, $user_id]);
                    if ($stmt->rowCount() > 0) {
                        $message = 'User role updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Cannot change admin role or user not found.';
                        $message_type = 'warning';
                    }
                }
                break;
        }

        // Log admin action (if admin_logs table exists)
        if ($message_type === 'success') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                    VALUES (?, ?, 'user', ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $action, $user_id, $message]);
            } catch (Exception $e) {
                // Admin logs table doesn't exist, skip logging
                error_log("Admin logs table not found: " . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// ENHANCED SEARCH AND FILTER PARAMETERS
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query with IMPROVED SEARCH
$where_conditions = ["u.status != 'deleted'"];
$params = [];

if ($role_filter !== 'all') {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $where_conditions[] = "(u.status = 'active')";
    } elseif ($status_filter === 'pending') {
        $where_conditions[] = "(u.status = 'pending' OR u.status IS NULL OR u.status = '')";
    } else {
        $where_conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }
}

// ENHANCED SEARCH - Multiple field search with better matching
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $where_conditions[] = "(
        LOWER(u.name) LIKE LOWER(?) OR 
        LOWER(u.email) LIKE LOWER(?) OR 
        u.phone LIKE ? OR 
        LOWER(u.village) LIKE LOWER(?) OR
        LOWER(u.role) LIKE LOWER(?) OR
        CAST(u.id AS CHAR) LIKE ?
    )";
    // Add search term for each field
    for ($i = 0; $i < 6; $i++) {
        $params[] = $search_term;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get users with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25; // Increased from 20
$offset = ($page - 1) * $per_page;

try {
    $pdo = getDBConnection();
    
    // Test database connection and table existence
    $test_query = "SHOW TABLES LIKE 'users'";
    $test_stmt = $pdo->query($test_query);
    $table_exists = $test_stmt->fetch();
    
    if (!$table_exists) {
        throw new Exception("Users table does not exist. Please run database setup first.");
    }

    // Count total users
    $count_sql = "SELECT COUNT(*) FROM users u WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();

    // Get users with pagination and additional info
    $sql = "
        SELECT u.*, 
               CASE 
                   WHEN u.status IS NULL OR u.status = '' THEN 'pending'
                   ELSE u.status 
               END as display_status,
               (SELECT COUNT(*) FROM problems WHERE villager_id = u.id) as problems_count,
               (SELECT COUNT(*) FROM medical_responses WHERE doctor_id = u.id) as responses_count,
               (SELECT COUNT(*) FROM problems WHERE assigned_to = u.id) as assigned_problems
        FROM users u 
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN u.status = 'pending' OR u.status IS NULL OR u.status = '' THEN 1
                WHEN u.status = 'active' THEN 2
                ELSE 3
            END,
            u.registered_at DESC 
        LIMIT $per_page OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $total_pages = ceil($total_users / $per_page);

} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $total_users = 0;
    $total_pages = 1;
    // Debug: Log the error
    error_log("Error fetching users: " . $e->getMessage());
    
    // If no users exist, show a helpful message
    if (strpos($e->getMessage(), 'Users table does not exist') !== false) {
        $message = 'Database setup required. Please run the database setup first.';
        $message_type = 'warning';
    } else {
        $message = 'Error loading users: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get summary statistics
$stats = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN role = 'villager' THEN 1 ELSE 0 END) as villagers,
                SUM(CASE WHEN role = 'avms' THEN 1 ELSE 0 END) as avms,
                SUM(CASE WHEN role = 'doctor' THEN 1 ELSE 0 END) as doctors,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'pending' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM users 
            WHERE status != 'deleted' OR status IS NULL
        ");
        $stats = $stmt->fetch();
    }
} catch (Exception $e) {
    $stats = ['total' => 0, 'villagers' => 0, 'avms' => 0, 'doctors' => 0, 'admins' => 0, 'active' => 0, 'inactive' => 0, 'pending' => 0, 'rejected' => 0];
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
                            <i class="fas fa-users me-2"></i>Manage Users
                        </h1>
                        <p class="text-muted mb-0">
                            View and manage all system users, their roles, and account status
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <?php if ($stats['pending'] > 0): ?>
                            <a href="approvals.php" class="btn btn-warning me-2">
                                <i class="fas fa-user-check me-1"></i>Pending Approvals (<?php echo $stats['pending']; ?>)
                            </a>
                        <?php endif; ?>
                        <a href="reports.php" class="btn btn-success me-2">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                        <a href="?debug=1" class="btn btn-info">
                            <i class="fas fa-bug me-1"></i>Debug
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div class="alert alert-info">
            <strong>Debug Info:</strong><br>
            Total Users: <?php echo $total_users; ?><br>
            Users Array Count: <?php echo count($users); ?><br>
            Where Clause: <?php echo htmlspecialchars($where_clause); ?><br>
            Params: <?php echo htmlspecialchars(print_r($params, true)); ?><br>
            Page: <?php echo $page; ?>, Per Page: <?php echo $per_page; ?>, Offset: <?php echo $offset; ?>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-card bg-primary text-white clickable-card" onclick="clearFilters()">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['total']; ?></div>
                    <small>Total Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-info text-white clickable-card" onclick="filterByRole('villager')">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['villagers']; ?></div>
                    <small>Villagers</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-success text-white clickable-card" onclick="filterByRole('doctor')">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['doctors']; ?></div>
                    <small>Doctors</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-warning text-dark clickable-card" onclick="filterByRole('avms')">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['avms']; ?></div>
                    <small>ANMS</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-secondary text-white clickable-card overlay-card" data-overlay-text="Admins" onclick="filterByRole('admin')">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['admins']; ?></div>
                    <small class="stats-card-label">Admins</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-danger text-white clickable-card overlay-card <?php echo $stats['pending'] > 0 ? 'blink-card' : ''; ?>" 
                 data-overlay-text="Pending" onclick="filterByStatus('pending')">
                <div class="text-center p-3">
                    <div class="h3 mb-0"><?php echo $stats['pending']; ?></div>
                    <small class="stats-card-label">Pending</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ENHANCED Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-search me-2"></i>Advanced Search & Filters
                        <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                            <span class="badge bg-warning text-dark ms-2">Filtered</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end" id="searchForm">
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" onchange="submitForm()">
                                <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="villager" <?php echo $role_filter === 'villager' ? 'selected' : ''; ?>>Villagers (<?php echo $stats['villagers']; ?>)</option>
                                <option value="avms" <?php echo $role_filter === 'avms' ? 'selected' : ''; ?>>ANMS (<?php echo $stats['avms']; ?>)</option>
                                <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctors (<?php echo $stats['doctors']; ?>)</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins (<?php echo $stats['admins']; ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" onchange="submitForm()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active (<?php echo $stats['active']; ?>)</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending (<?php echo $stats['pending']; ?>)</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive (<?php echo $stats['inactive']; ?>)</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected (<?php echo $stats['rejected']; ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search (Name, Email, Phone, Village, ID)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Type anything to search..."
                                       autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="form-text">Search across name, email, phone, village, or user ID</div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                    <i class="fas fa-refresh me-1"></i>Reset All
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Search Results Info -->
                    <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                        <div class="mt-3 p-2 bg-light rounded">
                            <strong>Search Results:</strong> 
                            Found <?php echo $total_users; ?> user(s) 
                            <?php if (!empty($search)): ?>
                                matching "<em><?php echo htmlspecialchars($search); ?></em>"
                            <?php endif; ?>
                            <?php if ($role_filter !== 'all'): ?>
                                with role "<em><?php echo ucfirst($role_filter); ?></em>"
                            <?php endif; ?>
                            <?php if ($status_filter !== 'all'): ?>
                                with status "<em><?php echo ucfirst($status_filter); ?></em>"
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="resetFilters()">
                                Clear Filters
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0 d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-users me-2"></i>System Users</span>
                        <span class="badge bg-light text-dark">
                            Showing <?php echo min($per_page, $total_users); ?> of <?php echo number_format($total_users); ?>
                            <?php if ($total_users !== $stats['total']): ?>
                                (Total: <?php echo number_format($stats['total']); ?>)
                            <?php endif; ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4>No Users Found</h4>
                            <p class="text-muted mb-4">
                                <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                                    No users match your current search criteria:<br>
                                    <?php if (!empty($search)): ?>
                                        Search: "<strong><?php echo htmlspecialchars($search); ?></strong>"<br>
                                    <?php endif; ?>
                                    <?php if ($role_filter !== 'all'): ?>
                                        Role: <strong><?php echo ucfirst($role_filter); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if ($status_filter !== 'all'): ?>
                                        Status: <strong><?php echo ucfirst($status_filter); ?></strong><br>
                                    <?php endif; ?>
                                    <br>Try adjusting your search criteria or clear all filters.
                                <?php else: ?>
                                    No users found in the system.
                                <?php endif; ?>
                            </p>
                            <div>
                                <button class="btn btn-primary me-2" onclick="resetFilters()">
                                    <i class="fas fa-refresh me-1"></i>Clear All Filters
                                </button>
                                <a href="manage_users.php" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-users me-1"></i>View All Users
                                </a>
                                <a href="create_test_user.php" class="btn btn-success">
                                    <i class="fas fa-user-plus me-1"></i>Create Test Users
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>User Details</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Contact</th>
                                        <th>Activity</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr class="user-row <?php echo $user['display_status'] === 'pending' ? 'pending-approval' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                                    <div class="text-muted small">ID: <?php echo $user['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'doctor' ? 'success' : 
                                                    ($user['role'] === 'avms' ? 'warning' : 
                                                    ($user['role'] === 'villager' ? 'info' : 'danger')); 
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['display_status'] === 'active' ? 'success' : 
                                                    ($user['display_status'] === 'pending' ? 'warning' : 
                                                    ($user['display_status'] === 'rejected' ? 'danger' : 'secondary')); 
                                            ?> <?php echo $user['display_status'] === 'pending' ? 'blink' : ''; ?>">
                                                <?php echo ucfirst($user['display_status']); ?>
                                            </span>
                                            <?php if ($user['display_status'] === 'pending'): ?>
                                                <div class="small text-warning mt-1">
                                                    <i class="fas fa-clock"></i> Needs Approval
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['phone']): ?>
                                                <div><i class="fas fa-phone text-success me-1"></i>
                                                    <a href="tel:<?php echo $user['phone']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($user['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($user['village']): ?>
                                                <div class="text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($user['village']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'villager'): ?>
                                                <div class="small">
                                                    <i class="fas fa-clipboard-list text-info me-1"></i>
                                                    <?php echo $user['problems_count']; ?> problem(s)
                                                </div>
                                            <?php elseif ($user['role'] === 'doctor'): ?>
                                                <div class="small">
                                                    <i class="fas fa-stethoscope text-success me-1"></i>
                                                    <?php echo $user['responses_count']; ?> response(s)
                                                </div>
                                            <?php elseif ($user['role'] === 'avms'): ?>
                                                <div class="small">
                                                    <i class="fas fa-tasks text-warning me-1"></i>
                                                    <?php echo $user['assigned_problems']; ?> assigned
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">System user</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-muted small">
                                                <?php echo date('M j, Y', strtotime($user['registered_at'])); ?>
                                                <br>
                                                <?php echo date('g:i A', strtotime($user['registered_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($user['display_status'] === 'pending'): ?>
                                                            <li>
                                                                <button class="dropdown-item text-success" 
                                                                        onclick="changeUserStatus(<?php echo $user['id']; ?>, 'approve')">
                                                                    <i class="fas fa-check text-success me-2"></i>Approve User
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item text-danger" 
                                                                        onclick="changeUserStatus(<?php echo $user['id']; ?>, 'reject')">
                                                                    <i class="fas fa-times text-danger me-2"></i>Reject User
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                        <?php endif; ?>

                                                        <?php if ($user['display_status'] === 'active'): ?>
                                                            <li>
                                                                <button class="dropdown-item" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'deactivate')">
                                                                    <i class="fas fa-pause text-warning me-2"></i>Deactivate
                                                                </button>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <button class="dropdown-item" onclick="changeUserStatus(<?php echo $user['id']; ?>, 'activate')">
                                                                    <i class="fas fa-play text-success me-2"></i>Activate
                                                                </button>
                                                            </li>
                                                        <?php endif; ?>

                                                        <li>
                                                            <button class="dropdown-item" onclick="showChangeRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')">
                                                                <i class="fas fa-exchange-alt text-info me-2"></i>Change Role
                                                            </button>
                                                        </li>

                                                        <?php if ($user['phone']): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="tel:<?php echo $user['phone']; ?>">
                                                                    <i class="fas fa-phone text-primary me-2"></i>Call User
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-trash me-2"></i>Delete User
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <i class="fas fa-shield-alt me-1"></i>Protected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Enhanced Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-muted small">
                                    Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_users); ?> 
                                    of <?php echo number_format($total_users); ?> users
                                    <?php if ($total_users !== $stats['total']): ?>
                                        (filtered from <?php echo number_format($stats['total']); ?> total)
                                    <?php endif; ?>
                                </div>
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="fas fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="fas fa-angle-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change User Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" id="roleUserId">

                    <div class="mb-3">
                        <label for="newRole" class="form-label">Select New Role</label>
                        <select class="form-select" id="newRole" name="new_role" required>
                            <option value="villager">Villager - Can report health problems</option>
                            <option value="avms">ANMS Officer - Can handle and assign problems</option>
                            <option value="doctor">Doctor - Can provide medical responses</option>
                        </select>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Changing a user's role will affect their access permissions and available features in the system.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Role</button>
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
    position: relative;
    overflow: hidden;
}

.stats-card::after {
    content: attr(data-overlay-text);
    position: absolute;
    bottom: 8px;
    right: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: inherit;
}

.stats-card.overlay-card::after {
    /* Keep overlay hidden by default to avoid duplicate labels when
       the card already contains visible text. Show it only on hover. */
    opacity: 0;
}

.stats-card.overlay-card:hover::after {
    opacity: 1;
}

.stats-card-label {
    font-weight: 600;
}

.stats-card:hover, .clickable-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.blink-card {
    animation: glow 2s infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
    to { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8), 0 0 30px rgba(220, 53, 69, 0.6); }
}

.blink {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.7; }
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.user-row {
    transition: all 0.3s ease;
}

.user-row:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
}

.pending-approval {
    background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
    border-left: 4px solid #ffc107;
}

.pending-approval:hover {
    background: linear-gradient(135deg, #ffeaa7 0%, #f8f9fa 100%);
}

.dropdown-item {
    transition: all 0.3s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

#search {
    transition: all 0.3s ease;
}

#search:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #007bff;
}

.form-text {
    font-size: 0.8rem;
    color: #6c757d;
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 10px;
    }

    .table-responsive {
        font-size: 0.875rem;
    }

    .user-avatar {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
}
</style>

<script>
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced search functionality
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 2 || this.value.length === 0) {
                    // Auto-submit form after user stops typing
                    document.getElementById('searchForm').submit();
                }
            }, 1000); // Wait 1 second after user stops typing
        });
    }
});

// Filter functions
function filterByRole(role) {
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.value = role;
        document.getElementById('searchForm').submit();
    }
}

function filterByStatus(status) {
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.value = status;
        document.getElementById('searchForm').submit();
    }
}

function clearFilters() {
    window.location.href = 'manage_users.php';
}

function resetFilters() {
    const roleSelect = document.getElementById('role');
    const statusSelect = document.getElementById('status');
    const searchInput = document.getElementById('search');
    
    if (roleSelect) roleSelect.value = 'all';
    if (statusSelect) statusSelect.value = 'all';
    if (searchInput) searchInput.value = '';
    
    document.getElementById('searchForm').submit();
}

function clearSearch() {
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.value = '';
        document.getElementById('searchForm').submit();
    }
}

function submitForm() {
    const form = document.getElementById('searchForm');
    if (form) {
        form.submit();
    }
}

// User management functions
function changeUserStatus(userId, action) {
    let confirmMessage = '';
    switch(action) {
        case 'approve':
            confirmMessage = 'Approve this user and grant them access to the system?';
            break;
        case 'reject':
            confirmMessage = 'Reject this user? They will be denied access to the system.';
            break;
        case 'activate':
            confirmMessage = 'Activate this user account?';
            break;
        case 'deactivate':
            confirmMessage = 'Deactivate this user account?';
            break;
        default:
            confirmMessage = 'Are you sure you want to perform this action?';
    }

    if (confirm(confirmMessage)) {
        submitAction(action, userId);
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        submitAction('delete', userId);
    }
}

function submitAction(action, userId) {
    showToast('Processing user action...', 'info', 2000);
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    form.innerHTML = '<input type="hidden" name="action" value="' + action + '">' +
                     '<input type="hidden" name="user_id" value="' + userId + '">';
    document.body.appendChild(form);
    form.submit();
}

function showChangeRoleModal(userId, currentRole) {
    const roleUserId = document.getElementById('roleUserId');
    const newRole = document.getElementById('newRole');
    const modal = document.getElementById('changeRoleModal');
    
    if (roleUserId) roleUserId.value = userId;
    if (newRole) newRole.value = currentRole;
    
    if (modal && typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    showToast('Opening role change modal...', 'info', 2000);
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }

    // Escape to clear search
    if (e.key === 'Escape' && document.activeElement === document.getElementById('search')) {
        clearSearch();
    }
});

// Auto-focus search on page load if there are parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('search');
    if (searchInput && (urlParams.get('search') || urlParams.get('role') !== 'all' || urlParams.get('status') !== 'all')) {
        searchInput.focus();
    }
    
    // Initialize any other components that need setup
    console.log('Manage Users page loaded successfully');
});

// Highlight search terms in results
<?php if (!empty($search)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const searchTerm = <?php echo json_encode($search); ?>;
    const regex = new RegExp(`(${searchTerm})`, 'gi');

    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.innerHTML = row.innerHTML.replace(regex, '<mark>$1</mark>');
    });
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>