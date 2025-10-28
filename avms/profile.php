<?php
require_once '../includes/config.php';
requireRole('avms');

$page_title = 'My Profile - Village Health Connect';
$error = '';
$success = '';

// Get user information
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        setMessage('error', 'User not found.');
        redirect(SITE_URL . '/avms/dashboard.php');
    }
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    setMessage('error', 'System error occurred.');
    redirect(SITE_URL . '/avms/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $village = sanitizeInput($_POST['village'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($phone) || empty($village)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Update basic info
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, village = ? WHERE id = ?");
            $result = $stmt->execute([$name, $phone, $village, $_SESSION['user_id']]);

            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Please enter your current password to change it.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                }
            }

            if (empty($error) && $result) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_village'] = $village;
                $success = 'Profile updated successfully!';

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            } elseif (empty($error)) {
                $error = 'Failed to update profile. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'System error occurred while updating profile.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-1">
                            <i class="fas fa-user-tie text-primary"></i> ANMS Profile
                        </h1>
                        <p class="text-muted mb-0">Manage your ANMS officer profile and account settings</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="form-container">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <small class="text-muted">Email cannot be changed for security reasons</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                <div class="form-text">Primary contact number for villagers and emergencies</div>
                                <div class="invalid-feedback">Please enter your contact phone number.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="village" class="form-label">Service Area <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="village" name="village" 
                                       value="<?php echo htmlspecialchars($user['village']); ?>" required>
                                <div class="form-text">Village or area you serve as ANMS officer</div>
                                <div class="invalid-feedback">Please enter your service area.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Account Type</label>
                                <input type="text" class="form-control" value="ANMS Officer" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Account Status</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['status']); ?>" readonly>
                                    <span class="input-group-text">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <i class="fas fa-check-circle text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-clock text-warning"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="registered" class="form-label">ANMS Officer Since</label>
                                <input type="text" class="form-control" value="<?php echo formatDate($user['registered_at']); ?>" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_login" class="form-label">Last Login</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">
                        <i class="fas fa-lock text-warning"></i> Change Password (Optional)
                    </h5>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="text-muted">Required only if changing password</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Profile Information Sidebar -->
        <div class="col-lg-4">
            <!-- AVMS Guidelines -->
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-info-circle text-primary"></i> ANMS Profile Guidelines
                    </h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Keep your contact phone updated for villager access</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Service area helps route problems to you</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Use a professional name for villager trust</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i>
                            <small>Strong password protects sensitive data</small>
                        </li>
                        <li>
                            <i class="fas fa-check text-success"></i>
                            <small>Regular profile updates improve service</small>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Performance Statistics -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line text-success"></i> Your Performance
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Get AVMS statistics
                        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM problems WHERE assigned_to = ? GROUP BY status");
                        $stmt->execute([$_SESSION['user_id']]);
                        $avms_stats = $stmt->fetchAll();

                        $total_assigned = 0;
                        $resolved_count = 0;
                        $escalated_count = 0;

                        foreach ($avms_stats as $stat) {
                            $total_assigned += $stat['count'];
                            if ($stat['status'] === 'resolved') {
                                $resolved_count = $stat['count'];
                            }
                            if ($stat['status'] === 'escalated') {
                                $escalated_count = $stat['count'];
                            }
                        }

                        $resolution_rate = $total_assigned > 0 ? round(($resolved_count / $total_assigned) * 100, 1) : 0;
                    } catch (Exception $e) {
                        $avms_stats = [];
                        $total_assigned = $resolved_count = $escalated_count = 0;
                        $resolution_rate = 0;
                    }
                    ?>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Total Cases Handled:</span>
                            <span class="badge bg-primary"><?php echo $total_assigned; ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Problems Resolved:</span>
                            <span class="badge bg-success"><?php echo $resolved_count; ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Cases Escalated:</span>
                            <span class="badge bg-info"><?php echo $escalated_count; ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Resolution Rate:</span>
                            <span class="badge bg-<?php echo $resolution_rate >= 80 ? 'success' : ($resolution_rate >= 60 ? 'warning' : 'danger'); ?>">
                                <?php echo $resolution_rate; ?>%
                            </span>
                        </div>
                    </div>

                    <?php foreach ($avms_stats as $stat): ?>
                        <?php if (!in_array($stat['status'], ['resolved', 'escalated'])): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo ucfirst(str_replace('_', ' ', $stat['status'])); ?>:</span>
                                    <span class="badge status-<?php echo $stat['status']; ?>"><?php echo $stat['count']; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> ANMS Officer since: <?php echo date('M Y', strtotime($user['registered_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Service Area Information -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-map-marker-alt text-info"></i> Service Area
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Current Area:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($user['village']); ?></span>
                    </div>

                    <?php
                    try {
                        // Get area statistics
                        $stmt = $pdo->prepare("
                            SELECT COUNT(DISTINCT p.villager_id) as unique_villagers,
                                   COUNT(p.id) as total_problems
                            FROM problems p
                            JOIN users u ON p.villager_id = u.id
                            WHERE p.assigned_to = ?
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $area_stats = $stmt->fetch();
                    } catch (Exception $e) {
                        $area_stats = ['unique_villagers' => 0, 'total_problems' => 0];
                    }
                    ?>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <small>Villagers Helped:</small>
                            <small class="badge bg-info"><?php echo $area_stats['unique_villagers']; ?></small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small>Total Interventions:</small>
                            <small class="badge bg-primary"><?php echo $area_stats['total_problems']; ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt text-warning"></i> Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                        <a href="my_assignments.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-folder"></i> My Active Cases
                        </a>
                        <a href="manage_problems.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-list"></i> Manage All Problems
                        </a>
                        <a href="reports.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-chart-bar"></i> Generate Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                // Check password confirmation
                const newPassword = document.getElementById('new_password');
                const confirmPassword = document.getElementById('confirm_password');

                if (newPassword.value && newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity("");
                }

                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Real-time password confirmation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = this;

    if (newPassword.value && newPassword.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity("Passwords don't match");
    } else {
        confirmPassword.setCustomValidity("");
    }
});

// Phone number formatting
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 10) value = value.slice(0, 10);
    this.value = value;
});
</script>

<?php include '../includes/footer.php'; ?>