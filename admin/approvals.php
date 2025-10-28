<?php
require_once '../includes/config.php';
requireRole('admin');

$page_title = 'User Approvals - System Administration';

// Handle approval/rejection actions
$message = '';
$message_type = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    $user_ids = $_POST['user_ids'] ?? [];

    if (!empty($user_ids) && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();

            $success_count = 0;
            foreach ($user_ids as $user_id) {
                $user_id = (int)$user_id;

                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id]);

                    // Log approval
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                        VALUES (?, 'approve_user', 'user', ?, 'User approved by admin', NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $user_id]);

                } elseif ($action === 'reject') {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id]);

                    // Log rejection
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, created_at) 
                        VALUES (?, 'reject_user', 'user', ?, 'User rejected by admin', NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $user_id]);
                }

                if ($stmt->rowCount() > 0) {
                    $success_count++;
                }
            }

            $pdo->commit();

            if ($success_count > 0) {
                $message = $success_count . ' user(s) ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully.';
                $message_type = 'success';
            } else {
                $message = 'No users were updated.';
                $message_type = 'warning';
            }

        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'Please select users and a valid action.';
        $message_type = 'warning';
    }
}

// FIXED: Get pending users - including NULL and empty status
$pending_users = [];
try {
    $pdo = getDBConnection();

    // Debug query to see all users
    $debug_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'pending' OR status IS NULL OR status = '' THEN 1 ELSE 0 END) as pending_users,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_users
        FROM users 
        WHERE role != 'admin'
    ");
    $debug_stats = $debug_stmt->fetch();

    // Get pending users with enhanced query
    $stmt = $pdo->query("
        SELECT u.*, 
               COALESCE(u.village, '') as location_info,
               CASE 
                   WHEN u.status IS NULL OR u.status = '' THEN 'pending'
                   ELSE u.status 
               END as display_status
        FROM users u
        WHERE (u.status = 'pending' OR u.status IS NULL OR u.status = '') AND u.role != 'admin'
        ORDER BY u.created_at DESC
    ");
    $pending_users = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error fetching pending users: " . $e->getMessage());
    $debug_stats = ['total_users' => 0, 'active_users' => 0, 'pending_users' => 0, 'rejected_users' => 0];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Debug Information -->
    <div class="alert alert-info alert-dismissible fade show mb-3">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <strong>System Status:</strong> 
        Total Users: <?php echo $debug_stats['total_users']; ?> | 
        Active: <?php echo $debug_stats['active_users']; ?> | 
        Pending: <?php echo $debug_stats['pending_users']; ?> | 
        Rejected: <?php echo $debug_stats['rejected_users']; ?>
        <br><strong>Found <?php echo count($pending_users); ?> user(s) awaiting approval.</strong>
    </div>

    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-2 text-primary">
                            <i class="fas fa-user-check me-2"></i>User Approvals
                            <?php if (count($pending_users) > 0): ?>
                                <span class="badge bg-warning text-dark blink"><?php echo count($pending_users); ?> PENDING</span>
                            <?php endif; ?>
                        </h1>
                        <p class="text-muted mb-0">
                            Review and approve new user registrations for the Village Health Connect system
                        </p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                        <a href="manage_users.php" class="btn btn-success">
                            <i class="fas fa-users me-1"></i>Manage All Users
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

    <!-- Approval Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-summary-card bg-warning text-dark">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-3x me-3"></i>
                    <div>
                        <div class="h2 mb-0"><?php echo count($pending_users); ?></div>
                        <small><strong>Pending Approvals</strong></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-success text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-check fa-3x me-3"></i>
                    <div>
                        <div class="h2 mb-0"><?php echo $debug_stats['active_users']; ?></div>
                        <small><strong>Approved Users</strong></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-danger text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-times fa-3x me-3"></i>
                    <div>
                        <div class="h2 mb-0"><?php echo $debug_stats['rejected_users']; ?></div>
                        <small><strong>Rejected Users</strong></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-summary-card bg-info text-white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users fa-3x me-3"></i>
                    <div>
                        <div class="h2 mb-0"><?php echo $debug_stats['total_users']; ?></div>
                        <small><strong>Total Users</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Detection Help -->
    <?php if (count($pending_users) === 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>How User Registration Works</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-plus text-primary me-2"></i>For New User Registration:</h6>
                            <ul class="list-unstyled">
                                <li>✅ Users register through the registration form</li>
                                <li>✅ New accounts get status = NULL (pending)</li>
                                <li>✅ They appear here for admin approval</li>
                                <li>✅ Admin can approve or reject them</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-search text-success me-2"></i>What We Look For:</h6>
                            <ul class="list-unstyled">
                                <li>📋 status = 'pending'</li>
                                <li>📋 status IS NULL</li>
                                <li>📋 status = '' (empty)</li>
                                <li>📋 role != 'admin'</li>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> If no pending users appear here, it means:
                        <ul class="mb-0 mt-2">
                            <li>No new registrations have occurred</li>
                            <li>All users have already been approved/rejected</li>
                            <li>Check the "Manage Users" page to see all user statuses</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Users -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header <?php echo count($pending_users) > 0 ? 'bg-warning text-dark' : 'bg-success text-white'; ?>">
                    <h5 class="mb-0 d-flex align-items-center justify-content-between">
                        <span>
                            <i class="fas fa-users me-2"></i>Pending User Approvals
                            <?php if (count($pending_users) > 0): ?>
                                <span class="badge <?php echo count($pending_users) > 0 ? 'bg-danger' : 'bg-light text-dark'; ?>">
                                    <?php echo count($pending_users); ?> WAITING
                                </span>
                            <?php endif; ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_users)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
                            <h3 class="text-success">All Caught Up!</h3>
                            <h5 class="mb-4">No Pending User Approvals</h5>
                            <p class="text-muted mb-4 lead">
                                Great job! All users have been processed. New user registrations will appear here automatically for approval.
                            </p>

                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-lightbulb me-2"></i>What happens next?</h6>
                                        <ul class="text-start mb-0">
                                            <li><strong>New registrations:</strong> Will appear here instantly</li>
                                            <li><strong>Auto-refresh:</strong> This page updates automatically</li>
                                            <li><strong>Notifications:</strong> You'll get alerts for new pending users</li>
                                            <li><strong>All users:</strong> Use "Manage Users" to see everyone</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <a href="manage_users.php" class="btn btn-primary btn-lg me-3">
                                    <i class="fas fa-users me-2"></i>Manage All Users (<?php echo $debug_stats['total_users']; ?>)
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-tachometer-alt me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong><?php echo count($pending_users); ?> new user(s) waiting for approval!</strong> 
                            These users have registered and need your approval to access the Village Health Connect system.
                        </div>

                        <form method="POST" action="">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <button type="button" id="selectAll" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-check-square me-1"></i>Select All
                                    </button>
                                    <button type="button" id="selectNone" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-square me-1"></i>Select None
                                    </button>
                                    <span class="text-muted ms-3" id="selectionCount">0 selected</span>
                                </div>
                                <div>
                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-lg" onclick="return confirm('Approve selected users and grant them access?')">
                                        <i class="fas fa-check me-2"></i>Approve Selected
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg" onclick="return confirm('Reject selected users? This action cannot be undone.')">
                                        <i class="fas fa-times me-2"></i>Reject Selected
                                    </button>
                                </div>
                            </div>

                            <div class="row">
                                <?php foreach ($pending_users as $user): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="user-approval-card">
                                        <div class="card h-100 border-warning">
                                            <div class="card-header bg-warning text-dark">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="form-check">
                                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                                               class="form-check-input user-checkbox" id="user_<?php echo $user['id']; ?>">
                                                        <label class="form-check-label fw-bold" for="user_<?php echo $user['id']; ?>">
                                                            Select User
                                                        </label>
                                                    </div>
                                                    <span class="badge bg-<?php 
                                                        echo $user['role'] === 'doctor' ? 'success' : 
                                                            ($user['role'] === 'avms' ? 'primary' : 'info'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center mb-3">
                                                    <div class="user-avatar-large mb-2">
                                                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                                    </div>
                                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                                                    <span class="badge bg-warning text-dark">Awaiting Approval</span>
                                                </div>

                                                <div class="user-details">
                                                    <?php if ($user['phone']): ?>
                                                        <div class="detail-item">
                                                            <i class="fas fa-phone text-success me-2"></i>
                                                            <strong>Phone:</strong> 
                                                            <a href="tel:<?php echo $user['phone']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($user['phone']); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($user['village']): ?>
                                                        <div class="detail-item">
                                                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                            <strong>Village:</strong> <?php echo htmlspecialchars($user['village']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="detail-item">
                                                        <i class="fas fa-calendar text-info me-2"></i>
                                                        <strong>Registered:</strong> 
                                                        <?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?>
                                                    </div>

                                                    <div class="detail-item">
                                                        <i class="fas fa-user-tag text-secondary me-2"></i>
                                                        <strong>User ID:</strong> <?php echo $user['id']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-success btn-sm w-100" 
                                                                onclick="approveSingleUser(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button type="button" class="btn btn-danger btn-sm w-100" 
                                                                onclick="rejectSingleUser(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="mt-2 d-grid">
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Full Details
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
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

.stats-summary-card {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.stats-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.user-approval-card {
    transition: all 0.3s ease;
}

.user-approval-card:hover {
    transform: translateY(-5px);
}

.user-approval-card .card {
    transition: all 0.3s ease;
}

.user-approval-card .card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.user-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.detail-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    padding: 5px;
    background: #f8f9fa;
    border-radius: 5px;
    font-size: 0.9rem;
}

.blink {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.7; }
}

@media (max-width: 768px) {
    .user-approval-card {
        margin-bottom: 20px;
    }

    .stats-summary-card {
        margin-bottom: 10px;
    }

    .user-avatar-large {
        width: 60px;
        height: 60px;
        font-size: 1.2rem;
    }
}
</style>

<script>
// Auto-refresh to catch new registrations
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000); // Refresh every minute

// Checkbox functionality
document.addEventListener('DOMContentLoaded', function() {
    const masterCheckbox = document.getElementById('masterCheckbox');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectAllBtn = document.getElementById('selectAll');
    const selectNoneBtn = document.getElementById('selectNone');
    const selectionCount = document.getElementById('selectionCount');

    function updateSelectionCount() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        selectionCount.textContent = checkedBoxes.length + ' selected';
    }

    // Select all button
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectionCount();
        });
    }

    // Select none button
    if (selectNoneBtn) {
        selectNoneBtn.addEventListener('click', function() {
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectionCount();
        });
    }

    // Individual checkbox change
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionCount);
    });

    // Initial count
    updateSelectionCount();
});

// Individual user actions with enhanced feedback
function approveSingleUser(userId) {
    if (confirm('Approve this user and grant them access to the Village Health Connect system?')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';

        submitSingleAction('approve', userId, function(success) {
            if (success) {
                btn.innerHTML = '<i class="fas fa-check"></i> Approved!';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-info');

                // Add success animation to card
                const card = btn.closest('.user-approval-card');
                card.style.transition = 'all 0.5s ease';
                card.style.backgroundColor = '#d4edda';
                card.style.transform = 'scale(0.95)';

                setTimeout(() => {
                    card.style.opacity = '0';
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                }, 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }
}

function rejectSingleUser(userId) {
    if (confirm('Reject this user? This will deny them access to the Village Health Connect system.')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';

        submitSingleAction('reject', userId, function(success) {
            if (success) {
                btn.innerHTML = '<i class="fas fa-times"></i> Rejected';
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-secondary');

                // Add rejection animation to card
                const card = btn.closest('.user-approval-card');
                card.style.transition = 'all 0.5s ease';
                card.style.backgroundColor = '#f8d7da';
                card.style.transform = 'scale(0.95)';

                setTimeout(() => {
                    card.style.opacity = '0';
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                }, 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }
}

function submitSingleAction(action, userId, callback) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="user_ids[]" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();

    // Call callback with success (we'll reload anyway)
    if (callback) callback(true);
}

function viewUserDetails(userId) {
    // This could open a modal or redirect to user details page
    window.location.href = `view_user.php?id=${userId}`;
}

// Show notification for pending users
<?php if (count($pending_users) > 0): ?>
document.title = "(<?php echo count($pending_users); ?>) User Approvals - Village Health Connect";

// Show desktop notification if supported
if ("Notification" in window) {
    if (Notification.permission === "granted") {
        new Notification("Village Health Connect - Approval Needed", {
            body: "<?php echo count($pending_users); ?> user(s) waiting for approval",
            icon: "/favicon.ico"
        });
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(function (permission) {
            if (permission === "granted") {
                new Notification("Village Health Connect - Approval Needed", {
                    body: "<?php echo count($pending_users); ?> user(s) waiting for approval",
                    icon: "/favicon.ico"
                });
            }
        });
    }
}
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>