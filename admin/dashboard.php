<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'Admin Dashboard - E-Swasthya Connect';

try {
    $pdo = getDBConnection();

    // Get system statistics
    $stats = [];

    // User statistics
    $stmt = $pdo->prepare("SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status");
    $stmt->execute();
    $user_stats = $stmt->fetchAll();

    // Problem statistics
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems GROUP BY status");
    $stmt->execute();
    $problem_stats = $stmt->fetchAll();

    // Recent activities
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as villager_name, avms.name as avms_name, doc.name as doctor_name
        FROM problems p 
        JOIN users u ON p.villager_id = u.id 
        LEFT JOIN users avms ON p.assigned_to = avms.id
        LEFT JOIN users doc ON p.escalated_to = doc.id
        ORDER BY p.updated_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_problems = $stmt->fetchAll();

    // Pending user approvals
    $stmt = $pdo->prepare("SELECT * FROM users WHERE status = 'pending' ORDER BY registered_at DESC");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();

    // System totals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems");
    $stmt->execute();
    $total_problems = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE status IN ('resolved', 'completed')");
    $stmt->execute();
    $resolved_problems = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE priority = 'urgent' AND status NOT IN ('resolved', 'completed')");
    $stmt->execute();
    $urgent_problems = $stmt->fetchColumn();

    // Process user stats
    $role_counts = ['villager' => 0, 'avms' => 0, 'doctor' => 0, 'admin' => 0];
    $status_counts = ['active' => 0, 'pending' => 0, 'inactive' => 0];

    foreach ($user_stats as $stat) {
        $role_counts[$stat['role']] += $stat['count'];
        $status_counts[$stat['status']] += $stat['count'];
    }

    // Process problem stats
    $prob_counts = [];
    foreach ($problem_stats as $stat) {
        $prob_counts[$stat['status']] = $stat['count'];
    }

} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $recent_problems = $pending_users = [];
    $total_users = $total_problems = $resolved_problems = $urgent_problems = 0;
    $role_counts = $status_counts = $prob_counts = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-1">
                            <i class="fas fa-user-shield text-primary"></i> 
                            System Administration
                        </h1>
                        <p class="text-muted mb-0">
                            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 
                            Monitor and manage the Village Health Connect system.
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="btn-group">
                            <a href="manage_users.php" class="btn btn-primary">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="system_reports.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Overview Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number"><?php echo $total_users; ?></div>
                <div class="stats-label">
                    <i class="fas fa-users"></i> Total Users
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number"><?php echo $total_problems; ?></div>
                <div class="stats-label">
                    <i class="fas fa-list-alt"></i> Total Problems
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-success"><?php echo $resolved_problems; ?></div>
                <div class="stats-label">
                    <i class="fas fa-check-circle"></i> Problems Resolved
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-number text-warning"><?php echo count($pending_users); ?></div>
                <div class="stats-label">
                    <i class="fas fa-user-clock"></i> Pending Approvals
                </div>
            </div>
        </div>
    </div>

    <!-- Urgent Issues Alert -->
    <?php if ($urgent_problems > 0): ?>
        <div class="alert alert-danger mb-4">
            <h5><i class="fas fa-exclamation-triangle"></i> System Alerts</h5>
            <p class="mb-2">There are <strong><?php echo $urgent_problems; ?></strong> urgent problem(s) that need immediate attention.</p>
            <button class="btn btn-danger btn-sm" onclick="viewUrgentProblems()">
                <i class="fas fa-eye"></i> Review Urgent Problems
            </button>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-bolt text-primary"></i> Quick Administrative Actions
                    </h5>
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <button class="btn btn-warning w-100" onclick="scrollToPendingApprovals()">
                                <i class="fas fa-user-clock"></i> Approvals (<?php echo count($pending_users); ?>)
                            </button>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <a href="manage_users.php" class="btn btn-primary w-100">
                                <i class="fas fa-users-cog"></i> Manage Users
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <a href="all_problems.php" class="btn btn-info w-100">
                                <i class="fas fa-list-alt"></i> All Problems
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <a href="system_reports.php" class="btn btn-success w-100">
                                <i class="fas fa-chart-line"></i> Reports
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <a href="settings.php" class="btn btn-secondary w-100">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                            <a href="backup.php" class="btn btn-dark w-100">
                                <i class="fas fa-database"></i> Backup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pending User Approvals -->
        <div class="col-lg-6">
            <div class="card" id="pending-approvals">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-clock text-warning"></i> 
                        Pending User Approvals
                        <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_users); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_users)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">No pending approvals</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_users as $user): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                        <small class="text-muted">
                                            <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                            | <?php echo htmlspecialchars($user['village'] ?? 'No village specified'); ?>
                                            | Registered: <?php echo formatDate($user['registered_at']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-5 text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-success btn-sm" 
                                                    onclick="approveUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="rejectUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> User Statistics</h5>
                </div>
                <div class="card-body">
                    <h6>By Role:</h6>
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-user text-primary"></i> Villagers:</span>
                                <span class="badge bg-primary"><?php echo $role_counts['villager']; ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-user-tie text-success"></i> ANMS:</span>
                                <span class="badge bg-success"><?php echo $role_counts['avms']; ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-user-md text-info"></i> Doctors:</span>
                                <span class="badge bg-info"><?php echo $role_counts['doctor']; ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-user-shield text-warning"></i> Admins:</span>
                                <span class="badge bg-warning text-dark"><?php echo $role_counts['admin']; ?></span>
                            </div>
                        </div>
                    </div>

                    <h6>By Status:</h6>
                    <div class="row">
                        <div class="col-4">
                            <div class="d-flex justify-content-between">
                                <span>Active:</span>
                                <span class="badge bg-success"><?php echo $status_counts['active']; ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="d-flex justify-content-between">
                                <span>Pending:</span>
                                <span class="badge bg-warning text-dark"><?php echo $status_counts['pending']; ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="d-flex justify-content-between">
                                <span>Inactive:</span>
                                <span class="badge bg-secondary"><?php echo $status_counts['inactive']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Problem Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Problem Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $status_labels = [
                            'pending' => ['Pending', 'warning'],
                            'assigned' => ['Assigned', 'info'],
                            'in_progress' => ['In Progress', 'primary'],
                            'resolved' => ['Resolved', 'success'],
                            'escalated' => ['Escalated', 'danger'],
                            'completed' => ['Completed', 'success']
                        ];

                        foreach ($status_labels as $status => $info):
                            $count = $prob_counts[$status] ?? 0;
                        ?>
                            <div class="col-6 mb-2">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo $info[0]; ?>:</span>
                                    <span class="badge bg-<?php echo $info[1]; ?><?php echo $info[1] === 'warning' ? ' text-dark' : ''; ?>">
                                        <?php echo $count; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent System Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_problems)): ?>
                        <p class="text-muted text-center">No recent activity</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Problem</th>
                                        <th>Villager</th>
                                        <th>ANMS</th>
                                        <th>Doctor</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_problems as $problem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($problem['title']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($problem['description'], 0, 50)) . '...'; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($problem['villager_name']); ?></td>
                                        <td><?php echo $problem['avms_name'] ? htmlspecialchars($problem['avms_name']) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                        <td><?php echo $problem['doctor_name'] ? htmlspecialchars($problem['doctor_name']) : '<span class="text-muted">-</span>'; ?></td>
                                        <td>
                                            <span class="badge priority-<?php echo $problem['priority']; ?>">
                                                <?php echo ucfirst($problem['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo $problem['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $problem['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($problem['updated_at']); ?></td>
                                        <td>
                                            <a href="view_problem.php?id=<?php echo $problem['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="all_problems.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> View All Problems
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Admin Dashboard Functions
function scrollToPendingApprovals() {
    const element = document.getElementById('pending-approvals');
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Add a highlight effect
        element.style.animation = 'pulse 2s ease-in-out';
        setTimeout(() => {
            element.style.animation = '';
        }, 2000);
    }
}

function viewUrgentProblems() {
    window.location.href = 'all_problems.php?priority=urgent';
}

function approveUser(userId, userName) {
    if (confirm('Approve user "' + userName + '" and grant them access to the system?')) {
        showToast('Processing approval...', 'info', 2000);
        window.location.href = 'approve_user.php?id=' + userId + '&action=approve';
    }
}

function rejectUser(userId, userName) {
    if (confirm('Reject user "' + userName + '"? This will permanently delete their account.')) {
        showToast('Processing rejection...', 'warning', 2000);
        window.location.href = 'approve_user.php?id=' + userId + '&action=reject';
    }
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

// Auto-refresh urgent cases count
setInterval(function() {
    if (document.visibilityState === 'visible') {
        fetch('dashboard.php?ajax=urgent_count')
            .then(response => response.json())
            .then(data => {
                if (data.urgent_count > 0) {
                    const urgentAlert = document.querySelector('.alert-danger');
                    if (urgentAlert) {
                        const countElement = urgentAlert.querySelector('strong');
                        if (countElement && countElement.textContent !== data.urgent_count.toString()) {
                            countElement.textContent = data.urgent_count;
                            showToast('Urgent cases count updated: ' + data.urgent_count, 'warning', 3000);
                        }
                    }
                }
            })
            .catch(error => console.log('Auto-refresh failed:', error));
    }
}, 60000); // Check every minute

// Add click handlers for problem cards
document.addEventListener('DOMContentLoaded', function() {
    const problemRows = document.querySelectorAll('tbody tr');
    problemRows.forEach(function(row) {
        row.addEventListener('click', function() {
            const viewLink = this.querySelector('a[href*="view_problem.php"]');
            if (viewLink) {
                window.location.href = viewLink.href;
            }
        });
        
        // Add hover effect
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.cursor = 'pointer';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Add pulse animation for urgent cases
    const urgentBadges = document.querySelectorAll('.badge.bg-danger');
    urgentBadges.forEach(function(badge) {
        if (badge.textContent.includes('URGENT')) {
            badge.style.animation = 'pulse 2s infinite';
        }
    });
});

// CSS for pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>